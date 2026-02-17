<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$user_id = (int)($_SESSION['user_id'] ?? 0);

/** Helpers */
function clamp01($x){ return max(0, min(1, $x)); }
function money($n){
  $sym = "$"; // USD
  $n = (float)$n;
  $sign = $n < 0 ? "-" : "";
  return $sign . $sym . number_format(abs($n), 2);
}

/** =========================
 *  KPI stats (closed trades only)
 *  ========================= */
$stmt = $conn->prepare("
  SELECT
    COUNT(*) AS total,
    SUM(COALESCE(r_multiple,0)) AS net_r,
    AVG(COALESCE(r_multiple,0)) AS avg_r,
    SUM(CASE WHEN r_multiple > 0 THEN 1 ELSE 0 END) AS wins,
    SUM(CASE WHEN r_multiple > 0 THEN r_multiple ELSE 0 END) AS gross_win_r,
    SUM(CASE WHEN r_multiple < 0 THEN ABS(r_multiple) ELSE 0 END) AS gross_loss_r
  FROM trades
  WHERE user_id = ?
    AND r_multiple IS NOT NULL
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$total = (int)($stats['total'] ?? 0);
$net_r = (float)($stats['net_r'] ?? 0);
$avg_r = (float)($stats['avg_r'] ?? 0);
$wins  = (int)($stats['wins'] ?? 0);

$win_rate   = $total > 0 ? ($wins / $total) * 100 : 0;
$expectancy = $total > 0 ? ($net_r / $total) : 0;

$gross_win_r   = (float)($stats['gross_win_r'] ?? 0);
$gross_loss_r  = (float)($stats['gross_loss_r'] ?? 0);
$profit_factor = $gross_loss_r > 0 ? ($gross_win_r / $gross_loss_r) : 0;

/** =========================
 *  Unreviewed trades count
 *  ========================= */
$unrevStmt = $conn->prepare("SELECT COUNT(*) AS c FROM trades WHERE user_id = ? AND (is_reviewed = 0 OR is_reviewed IS NULL)");
$unrevStmt->bind_param("i", $user_id);
$unrevStmt->execute();
$unrev = (int)($unrevStmt->get_result()->fetch_assoc()['c'] ?? 0);

/** =========================
 *  Rule adherence avg (if exists)
 *  ========================= */
$avg_rules_score = null;
try {
  $rs = $conn->prepare("
    SELECT AVG(rules_score) AS avg_score
    FROM trade_reviews tr
    INNER JOIN trades t ON t.id = tr.trade_id
    WHERE t.user_id = ?
  ");
  $rs->bind_param("i", $user_id);
  $rs->execute();
  $avg_rules_score = $rs->get_result()->fetch_assoc()['avg_score'];
  $avg_rules_score = $avg_rules_score === null ? null : (float)$avg_rules_score;
} catch (Throwable $e) {
  $avg_rules_score = null;
}

/** =========================
 *  Month range for line chart (Daily Net R)
 *  ========================= */
$year = (int)date("Y");
$month = (int)date("n");
$monthStart = sprintf("%04d-%02d-01 00:00:00", $year, $month);
$nextMonth  = ($month === 12)
  ? sprintf("%04d-01-01 00:00:00", $year + 1)
  : sprintf("%04d-%02d-01 00:00:00", $year, $month + 1);

$dailyStmt = $conn->prepare("
  SELECT DATE(entry_time) AS d,
         SUM(COALESCE(r_multiple,0)) AS net_r
  FROM trades
  WHERE user_id = ?
    AND entry_time >= ? AND entry_time < ?
    AND r_multiple IS NOT NULL
  GROUP BY DATE(entry_time)
  ORDER BY d ASC
");
$dailyStmt->bind_param("iss", $user_id, $monthStart, $nextMonth);
$dailyStmt->execute();
$dailyRows = $dailyStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$lineLabels = [];
$lineValues = [];
foreach ($dailyRows as $r) {
  $lineLabels[] = $r['d'];
  $lineValues[] = (float)$r['net_r'];
}

/** =========================
 *  Top rule breaks
 *  ========================= */
$topBreaks = [];
try {
  $tb = $conn->prepare("
    SELECT r.name AS rule_name, COUNT(*) AS breaks
    FROM trade_rule_checks c
    INNER JOIN trade_reviews rv ON rv.id = c.review_id
    INNER JOIN trades t ON t.id = rv.trade_id
    INNER JOIN trade_rules r ON r.id = c.rule_id
    WHERE t.user_id = ?
      AND c.status = 'broken'
    GROUP BY r.id
    ORDER BY breaks DESC
    LIMIT 6
  ");
  $tb->bind_param("i", $user_id);
  $tb->execute();
  $topBreaks = $tb->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
  $topBreaks = [];
}

/** =========================
 *  Discipline Score
 *  ========================= */
$ruleAdh01 = ($avg_rules_score === null) ? null : clamp01($avg_rules_score / 100.0);
$pf01  = ($profit_factor <= 0) ? 0 : clamp01($profit_factor / 2.0);
$exp01 = clamp01(($expectancy + 1.0) / 2.0);
$cons01 = ($total <= 0) ? 0 : clamp01(min(1, $total / 30));

if ($ruleAdh01 === null) {
  $discipline = (int)round(($pf01*0.45 + $exp01*0.35 + $cons01*0.20) * 100);
} else {
  $discipline = (int)round(($pf01*0.25 + $exp01*0.25 + $ruleAdh01*0.35 + $cons01*0.15) * 100);
}

$radarLabels = ["Discipline","Consistency","Expectancy","Risk control","Edge"];
$radarValues = [
  $discipline,
  (int)round($cons01 * 100),
  (int)round($exp01 * 100),
  ($ruleAdh01 === null) ? 0 : (int)round($ruleAdh01 * 100),
  (int)round($pf01 * 100)
];

/** =========================
 *  Monthly Calendar (AUTO FALLBACK)
 *  - tries pnl_amount
 *  - if column missing, falls back to risk_amount * r_multiple
 *  - days clickable → log.php?date=YYYY-MM-DD
 *  - dynamic calendar rows (4–6)
 *  ========================= */
$ym = preg_replace('/[^0-9\-]/', '', ($_GET['ym'] ?? date('Y-m')));
if (!preg_match('/^\d{4}\-\d{2}$/', $ym)) $ym = date('Y-m');

$monthStartDT = new DateTimeImmutable($ym . "-01");
$monthEndDT   = $monthStartDT->modify("+1 month");

$monthStartStr = $monthStartDT->format("Y-m-d 00:00:00");
$monthEndStr   = $monthEndDT->format("Y-m-d 00:00:00");

// Calendar grid starts on Sunday
$firstDow = (int)$monthStartDT->format("w"); // 0=Sun..6=Sat
$gridStartDT = $monthStartDT->modify("-{$firstDow} days");

// Dynamic grid until end-of-month is included and we end on Saturday
$gridDays = [];
$cursor = $gridStartDT;
while ($cursor < $monthEndDT || (int)$cursor->format("w") !== 6) {
  $gridDays[] = $cursor;
  $cursor = $cursor->modify("+1 day");
}

// Pull daily totals (try pnl_amount, fallback to risk*r_multiple)
$calRows = [];
try {
  // Preferred: pnl_amount exists
  $calStmt = $conn->prepare("
    SELECT
      DATE(entry_time) AS d,
      COUNT(*) AS trades,
      SUM(COALESCE(pnl_amount,0)) AS pnl
    FROM trades
    WHERE user_id = ?
      AND entry_time >= ? AND entry_time < ?
    GROUP BY DATE(entry_time)
  ");
  $calStmt->bind_param("iss", $user_id, $monthStartStr, $monthEndStr);
  $calStmt->execute();
  $calRows = $calStmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
  // Fallback: estimate PnL from risk_amount * r_multiple (closed trades only)
  $calStmt = $conn->prepare("
    SELECT
      DATE(entry_time) AS d,
      COUNT(*) AS trades,
      SUM(COALESCE(risk_amount,0) * COALESCE(r_multiple,0)) AS pnl
    FROM trades
    WHERE user_id = ?
      AND entry_time >= ? AND entry_time < ?
      AND r_multiple IS NOT NULL
    GROUP BY DATE(entry_time)
  ");
  $calStmt->bind_param("iss", $user_id, $monthStartStr, $monthEndStr);
  $calStmt->execute();
  $calRows = $calStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$dayMap = []; // date => ['trades'=>int,'pnl'=>float]
foreach ($calRows as $r) {
  $dayMap[$r['d']] = ['trades'=>(int)$r['trades'], 'pnl'=>(float)$r['pnl']];
}

// Monthly stats
$monthTrades = 0;
$monthPnl = 0.0;
$activeDays = 0;
foreach ($dayMap as $v) {
  $monthTrades += $v['trades'];
  $monthPnl += $v['pnl'];
  if ($v['trades'] > 0) $activeDays++;
}

$prevYm = $monthStartDT->modify("-1 month")->format("Y-m");
$nextYm = $monthStartDT->modify("+1 month")->format("Y-m");

/** =========================
 *  APP SHELL
 *  ========================= */
$pageTitle = "Dashboard • NXLOG Analytics";
$current   = "dashboard";
require_once __DIR__ . "/partials/app_header.php";
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* ===== Monthly Calendar UI ===== */
.monthly-card{ padding:16px; }
.monthly-head{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.monthly-left{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.monthly-nav{ display:flex; align-items:center; gap:8px; }
.monthly-nav a{
  border:1px solid var(--border);
  background:var(--pill);
  padding:8px 10px;
  border-radius:12px;
  font-weight:900;
}
.monthly-title{ font-weight:900; }
.monthly-stats{
  display:flex; gap:10px; flex-wrap:wrap; align-items:center;
  color:var(--muted); font-size:12px; font-weight:800;
}
.monthly-stats b{ color:var(--text); }

.monthly-grid{ display:grid; grid-template-columns: repeat(7, minmax(0,1fr)); gap:10px; }
.monthly-dow{ font-size:12px; font-weight:900; color:var(--muted); padding:0 2px; }

.daylink{ display:block; text-decoration:none; color:inherit; }
.daycell{
  min-height:86px;
  border:1px solid var(--border);
  border-radius:14px;
  background:var(--card);
  padding:10px;
  position:relative;
  transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
}
.daycell:hover{ transform: translateY(-1px); box-shadow: var(--shadow); border-color: rgba(109,94,252,.35); }
.daycell.muted{ opacity:.45; }
.daynum{
  position:absolute; top:8px; left:10px;
  font-size:12px; color:var(--muted); font-weight:900;
}
.daymeta{ margin-top:22px; display:flex; flex-direction:column; gap:6px; }
.daypill{
  display:inline-flex; align-items:center; gap:6px;
  border:1px solid var(--border);
  background:var(--pill);
  padding:6px 8px;
  border-radius:12px;
  font-weight:900;
  width:max-content;
  font-size:12px;
}
.daypill.good{ color:#16a34a; }
.daypill.bad{ color:#ef4444; }
</style>

<div style="height:14px"></div>

<!-- KPIs -->
<div class="grid grid-4">
  <div class="card">
    <h3>Net R</h3>
    <div class="kpi">
      <div>
        <div class="value"><?= number_format($net_r, 2) ?>R</div>
        <div class="sub">Closed trades only</div>
      </div>
      <div class="badge <?= $net_r >= 0 ? 'good' : 'bad' ?>">
        <?= $net_r >= 0 ? "▲" : "▼" ?> <?= number_format(abs($net_r), 2) ?>R
      </div>
    </div>
  </div>

  <div class="card">
    <h3>Expectancy</h3>
    <div class="kpi">
      <div>
        <div class="value"><?= number_format($expectancy, 2) ?>R</div>
        <div class="sub">Net R ÷ Trades</div>
      </div>
      <div class="badge"><?= $total ?> trades</div>
    </div>
  </div>

  <div class="card">
    <h3>Profit Factor</h3>
    <div class="kpi">
      <div>
        <div class="value"><?= $profit_factor > 0 ? number_format($profit_factor, 2) : "-" ?></div>
        <div class="sub">Gross Win R ÷ Gross Loss R</div>
      </div>
      <div style="width:74px;height:74px">
        <canvas id="pfDonut"></canvas>
      </div>
    </div>
  </div>

  <div class="card">
    <h3>Win rate</h3>
    <div class="kpi">
      <div>
        <div class="value"><?= number_format($win_rate, 1) ?>%</div>
        <div class="sub">Wins: <?= $wins ?> / <?= $total ?></div>
      </div>
      <div class="badge"><?= number_format($avg_r, 2) ?>R avg</div>
    </div>
  </div>
</div>

<!-- Execution & Consistency -->
<div class="section-title">Execution & Consistency</div>

<div class="grid grid-3">
  <div class="card">
    <h3>Win % vs Loss %</h3>
    <div style="height:220px">
      <canvas id="wlDonut"></canvas>
    </div>
    <div class="sub" style="margin-top:8px;color:var(--muted);font-size:12px">
      Uses closed trades only.
    </div>
  </div>

  <div class="card">
    <div class="kpi">
      <div>
        <h3 style="margin-bottom:6px">Discipline Score</h3>
        <div class="value"><?= (int)$discipline ?></div>
        <div class="sub">PF, expectancy, breaks & consistency</div>
      </div>
      <div class="badge">
        Break trades: <?= (int)$unrev ?>
      </div>
    </div>

    <div style="height:240px; margin-top:10px">
      <canvas id="disciplineRadar"></canvas>
    </div>

    <div class="sub" style="margin-top:8px">
      Rule adherence avg:
      <?= ($avg_rules_score === null) ? "—" : number_format((float)$avg_rules_score, 0) . "%" ?>
    </div>
  </div>

  <div class="card">
    <h3>Top rule breaks</h3>

    <?php if (empty($topBreaks)): ?>
      <div class="sub" style="margin-top:10px">No rule-break data yet.</div>
      <div class="sub" style="margin-top:6px">Add reviews + rule checks to populate this.</div>
    <?php else: ?>
      <div class="list">
        <?php foreach ($topBreaks as $b): ?>
          <div class="list-row">
            <div class="list-title"><?= e($b['rule_name']) ?></div>
            <div class="badge bad"><?= (int)$b['breaks'] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div style="margin-top:12px">
      <a class="btn secondary" href="/trading-journal/review_queue.php">Open review queue</a>
    </div>
  </div>
</div>

<!-- Unreviewed CTA -->
<div class="card cta-panel" style="margin-top:14px">
  <div>
    <h3 style="margin:0">Unreviewed trades</h3>
    <div class="sub">Review trades to track rules, discipline, and execution quality.</div>
  </div>
  <div style="display:flex;gap:10px;align-items:center">
    <span class="badge"><?= (int)$unrev ?> pending</span>
    <a class="btn" href="/trading-journal/review_queue.php">Review now</a>
  </div>
</div>

<!-- Line chart -->
<div class="card" style="margin-top:14px">
  <h3>Daily Net R (this month)</h3>
  <div style="height:260px">
    <canvas id="netRLine"></canvas>
  </div>
</div>

<!-- ✅ Monthly Calendar (clickable days) -->
<div class="card monthly-card" style="margin-top:14px">
  <div class="monthly-head">
    <div class="monthly-left">
      <div class="monthly-nav">
        <a href="/trading-journal/dashboard.php?ym=<?= e($prevYm) ?>" aria-label="Previous month">‹</a>
        <div class="monthly-title"><?= e($monthStartDT->format("F Y")) ?></div>
        <a href="/trading-journal/dashboard.php?ym=<?= e($nextYm) ?>" aria-label="Next month">›</a>
      </div>

      <div class="monthly-stats">
        Monthly stats:
        <b><?= e(money($monthPnl)) ?></b>
        • <b><?= (int)$activeDays ?></b> days
        • <b><?= (int)$monthTrades ?></b> trades
      </div>
    </div>
  </div>

  <div style="margin-top:12px">
    <div class="monthly-grid" style="margin-bottom:8px">
      <div class="monthly-dow">Sun</div>
      <div class="monthly-dow">Mon</div>
      <div class="monthly-dow">Tue</div>
      <div class="monthly-dow">Wed</div>
      <div class="monthly-dow">Thu</div>
      <div class="monthly-dow">Fri</div>
      <div class="monthly-dow">Sat</div>
    </div>

    <div class="monthly-grid">
      <?php foreach ($gridDays as $d): ?>
        <?php
          $key = $d->format("Y-m-d");
          $inMonth = ($d->format("Y-m") === $ym);
          $cell = $dayMap[$key] ?? ['trades'=>0,'pnl'=>0.0];
          $tr = (int)$cell['trades'];
          $pnl = (float)$cell['pnl'];

          $pillCls = "daypill";
          if ($tr > 0) $pillCls .= ($pnl >= 0 ? " good" : " bad");

          $href = "/trading-journal/log.php?date=" . urlencode($key);
          $aria = "Open trades for " . $d->format("M d");
        ?>
        <a class="daylink" href="<?= e($href) ?>" aria-label="<?= e($aria) ?>">
          <div class="daycell <?= $inMonth ? "" : "muted" ?>">
            <div class="daynum"><?= e($d->format("d")) ?></div>

            <div class="daymeta">
              <?php if ($tr > 0): ?>
                <div class="<?= $pillCls ?>"><?= e(money($pnl)) ?></div>
                <div class="small"><?= $tr ?> trade<?= $tr>1?'s':'' ?></div>
              <?php else: ?>
                <div class="small">—</div>
              <?php endif; ?>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
  const winRate = <?= json_encode($win_rate) ?>;
  const lossRate = Math.max(0, 100 - winRate);

  const grossWin = <?= json_encode($gross_win_r) ?>;
  const grossLoss = <?= json_encode($gross_loss_r) ?>;

  const lineLabels = <?= json_encode($lineLabels) ?>;
  const lineValues = <?= json_encode($lineValues) ?>;

  const radarLabels = <?= json_encode($radarLabels) ?>;
  const radarValues = <?= json_encode($radarValues) ?>;

  new Chart(document.getElementById("pfDonut"), {
    type: "doughnut",
    data: {
      labels: ["Win R", "Loss R"],
      datasets: [{ data: [grossWin, grossLoss], borderWidth: 0 }]
    },
    options: { plugins: { legend: { display: false } }, cutout: "70%" }
  });

  new Chart(document.getElementById("wlDonut"), {
    type: "doughnut",
    data: {
      labels: ["Win %", "Loss %"],
      datasets: [{ data: [winRate, lossRate], borderWidth: 0 }]
    },
    options: { plugins: { legend: { position: "bottom" } }, cutout: "70%" }
  });

  new Chart(document.getElementById("netRLine"), {
    type: "line",
    data: {
      labels: lineLabels,
      datasets: [{
        label: "Net R",
        data: lineValues,
        tension: 0.35,
        pointRadius: 2
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: { x: { grid: { display: false } } }
    }
  });

  new Chart(document.getElementById("disciplineRadar"), {
    type: "radar",
    data: {
      labels: radarLabels,
      datasets: [{
        label: "Score",
        data: radarValues,
        borderWidth: 2,
        pointRadius: 2
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: {
        r: {
          suggestedMin: 0,
          suggestedMax: 100,
          ticks: { display: false }
        }
      }
    }
  });
</script>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>
