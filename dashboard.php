<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$user_id = (int)($_SESSION['user_id'] ?? 0);

/** Helpers */
function clamp01($x){ return max(0, min(1, $x)); }
function money($n){
  $sym = "$";
  $n = (float)$n;
  $sign = $n < 0 ? "-" : "";
  return $sign . $sym . number_format(abs($n), 2);
}

/** KPI stats */
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

/** Unreviewed CLOSED trades count */
$unrevStmt = $conn->prepare("
  SELECT COUNT(*) AS c
  FROM trades
  WHERE user_id = ?
    AND r_multiple IS NOT NULL
    AND (is_reviewed = 0 OR is_reviewed IS NULL)
");
$unrevStmt->bind_param("i", $user_id);
$unrevStmt->execute();
$unrev = (int)($unrevStmt->get_result()->fetch_assoc()['c'] ?? 0);

/** Rule adherence avg */
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

/** Month range for line chart */
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

/** Top rule breaks */
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

/** Discipline Score */
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

/** Monthly Calendar */
$ym = preg_replace('/[^0-9\-]/', '', ($_GET['ym'] ?? date('Y-m')));
if (!preg_match('/^\d{4}\-\d{2}$/', $ym)) $ym = date('Y-m');

$monthStartDT = new DateTimeImmutable($ym . "-01");
$monthEndDT   = $monthStartDT->modify("+1 month");

$monthStartStr = $monthStartDT->format("Y-m-d 00:00:00");
$monthEndStr   = $monthEndDT->format("Y-m-d 00:00:00");

$firstDow = (int)$monthStartDT->format("w");
$gridStartDT = $monthStartDT->modify("-{$firstDow} days");

$gridDays = [];
$cursor = $gridStartDT;
while ($cursor < $monthEndDT || (int)$cursor->format("w") !== 6) {
  $gridDays[] = $cursor;
  $cursor = $cursor->modify("+1 day");
}

$calRows = [];
try {
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

$dayMap = [];
foreach ($calRows as $r) {
  $dayMap[$r['d']] = ['trades'=>(int)$r['trades'], 'pnl'=>(float)$r['pnl']];
}

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

$pageTitle = "Dashboard • NXLOG Analytics";
$current   = "dashboard";
require_once __DIR__ . "/partials/app_header.php";
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.dashboard-wrap{ display:grid; gap:14px; }

.page-head{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:12px;
  flex-wrap:wrap;
}
.page-head h1{
  margin:0;
  font-size:28px;
  font-weight:900;
}
.page-head p{
  margin:6px 0 0;
  color:var(--muted);
}
.page-head-actions{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
}

.kpi-grid{
  display:grid;
  grid-template-columns:repeat(4,minmax(0,1fr));
  gap:14px;
}
.kpi-card,
.panel,
.chart-card,
.monthly-card,
.cta-panel{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:18px;
  box-shadow:var(--shadow);
}
.kpi-card{ padding:16px; }
.kpi-head{
  display:flex;
  justify-content:space-between;
  gap:12px;
  align-items:flex-start;
}
.kpi-card h3{
  margin:0 0 8px;
  font-size:13px;
  color:var(--muted);
  font-weight:800;
}
.kpi-value{
  font-size:28px;
  font-weight:900;
  line-height:1.1;
}
.kpi-sub{
  margin-top:6px;
  color:var(--muted);
  font-size:12px;
  font-weight:700;
}

.section-title{
  font-size:13px;
  text-transform:uppercase;
  letter-spacing:.05em;
  color:var(--muted);
  font-weight:900;
  margin-top:4px;
}

.three-grid{
  display:grid;
  grid-template-columns:repeat(3,minmax(0,1fr));
  gap:14px;
}
.chart-card,
.panel{ padding:16px; }

.card-title{
  margin:0 0 10px;
  font-size:19px;
  font-weight:900;
}
.card-sub{
  color:var(--muted);
  font-size:12px;
  font-weight:700;
}

.metric-head{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:12px;
  flex-wrap:wrap;
}
.metric-value{
  font-size:30px;
  font-weight:900;
  line-height:1.1;
}

.list{
  display:grid;
  gap:10px;
  margin-top:8px;
}
.list-row{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:14px;
  padding:12px 14px;
}
.list-title{
  font-weight:800;
}

.badge{
  display:inline-flex;
  align-items:center;
  gap:6px;
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:999px;
  padding:6px 10px;
  font-size:12px;
  font-weight:900;
}
.badge.good{ color:#16a34a; }
.badge.bad{ color:#ef4444; }

.cta-panel{
  padding:18px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:14px;
  flex-wrap:wrap;
}
.cta-left h3{
  margin:0 0 6px;
  font-size:20px;
  font-weight:900;
}
.cta-left p{
  margin:0;
  color:var(--muted);
}

.monthly-card{ padding:16px; }
.monthly-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  flex-wrap:wrap;
}
.monthly-left{
  display:flex;
  align-items:center;
  gap:10px;
  flex-wrap:wrap;
}
.monthly-nav{
  display:flex;
  align-items:center;
  gap:8px;
}
.monthly-nav a{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width:38px;
  height:38px;
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:12px;
  font-weight:900;
  text-decoration:none;
  color:var(--text);
}
.monthly-title{
  font-weight:900;
  font-size:16px;
}
.monthly-stats{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  align-items:center;
  color:var(--muted);
  font-size:12px;
  font-weight:800;
}
.monthly-stats b{ color:var(--text); }

.monthly-grid{
  display:grid;
  grid-template-columns: repeat(7, minmax(0,1fr));
  gap:10px;
}
.monthly-dow{
  font-size:12px;
  font-weight:900;
  color:var(--muted);
  padding:0 2px;
}

.daylink{
  display:block;
  text-decoration:none;
  color:inherit;
}
.daycell{
  min-height:90px;
  border:1px solid var(--border);
  border-radius:14px;
  background:var(--card);
  padding:10px;
  position:relative;
  transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
}
.daycell:hover{
  transform: translateY(-1px);
  box-shadow: var(--shadow);
  border-color: rgba(109,94,252,.35);
}
.daycell.muted{ opacity:.45; }
.daynum{
  position:absolute;
  top:8px;
  left:10px;
  font-size:12px;
  color:var(--muted);
  font-weight:900;
}
.daymeta{
  margin-top:22px;
  display:flex;
  flex-direction:column;
  gap:6px;
}
.daypill{
  display:inline-flex;
  align-items:center;
  gap:6px;
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

.chart-box-sm{ height:220px; }
.chart-box-md{ height:260px; }
.chart-box-lg{ height:240px; }

@media (max-width: 1100px){
  .kpi-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); }
  .three-grid{ grid-template-columns:1fr; }
}
@media (max-width: 720px){
  .kpi-grid{ grid-template-columns:1fr; }
  .monthly-grid{ gap:8px; }
  .daycell{ min-height:82px; }
}
</style>

<div class="dashboard-wrap">

  <div class="page-head">
    <div>
      <h1>Dashboard</h1>
      <p>Your trading performance overview, discipline signals, and monthly activity at a glance.</p>
    </div>

    <div class="page-head-actions">
      <a class="btn secondary" href="/trading-journal/trade-history.php">Trade History</a>
      <a class="btn" href="/trading-journal/review_queue.php">Open Review Queue</a>
    </div>
  </div>

  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-head">
        <div>
          <h3>Net R</h3>
          <div class="kpi-value"><?= number_format($net_r, 2) ?>R</div>
          <div class="kpi-sub">Closed trades only</div>
        </div>
        <div class="badge <?= $net_r >= 0 ? 'good' : 'bad' ?>">
          <?= $net_r >= 0 ? "▲" : "▼" ?> <?= number_format(abs($net_r), 2) ?>R
        </div>
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-head">
        <div>
          <h3>Expectancy</h3>
          <div class="kpi-value"><?= number_format($expectancy, 2) ?>R</div>
          <div class="kpi-sub">Net R ÷ Trades</div>
        </div>
        <div class="badge"><?= $total ?> trades</div>
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-head">
        <div>
          <h3>Profit Factor</h3>
          <div class="kpi-value"><?= $profit_factor > 0 ? number_format($profit_factor, 2) : "-" ?></div>
          <div class="kpi-sub">Gross Win R ÷ Gross Loss R</div>
        </div>
        <div style="width:74px;height:74px">
          <canvas id="pfDonut"></canvas>
        </div>
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-head">
        <div>
          <h3>Win Rate</h3>
          <div class="kpi-value"><?= number_format($win_rate, 1) ?>%</div>
          <div class="kpi-sub">Wins: <?= $wins ?> / <?= $total ?></div>
        </div>
        <div class="badge"><?= number_format($avg_r, 2) ?>R avg</div>
      </div>
    </div>
  </div>

  <div class="section-title">Execution & Consistency</div>

  <div class="three-grid">
    <div class="chart-card">
      <h3 class="card-title">Win % vs Loss %</h3>
      <div class="chart-box-sm">
        <canvas id="wlDonut"></canvas>
      </div>
      <div class="card-sub" style="margin-top:10px">Uses closed trades only.</div>
    </div>

    <div class="chart-card">
      <div class="metric-head">
        <div>
          <h3 class="card-title" style="margin-bottom:6px">Discipline Score</h3>
          <div class="metric-value"><?= (int)$discipline ?></div>
          <div class="card-sub">PF, expectancy, breaks & consistency</div>
        </div>
        <div class="badge"><?= (int)$unrev ?> pending</div>
      </div>

      <div class="chart-box-lg" style="margin-top:10px">
        <canvas id="disciplineRadar"></canvas>
      </div>

      <div class="card-sub" style="margin-top:8px">
        Rule adherence avg:
        <?= ($avg_rules_score === null) ? "—" : number_format((float)$avg_rules_score, 0) . "%" ?>
      </div>
    </div>

    <div class="panel">
      <h3 class="card-title">Top Rule Breaks</h3>

      <?php if (empty($topBreaks)): ?>
        <div class="card-sub" style="margin-top:8px">No rule-break data yet.</div>
        <div class="card-sub" style="margin-top:6px">Add reviews + rule checks to populate this.</div>
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

  <div class="cta-panel">
    <div class="cta-left">
      <h3>Unreviewed Trades</h3>
      <p>Review trades to track rules, discipline, and execution quality.</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <span class="badge"><?= (int)$unrev ?> pending</span>
      <a class="btn" href="/trading-journal/review_queue.php">Review now</a>
    </div>
  </div>

  <div class="chart-card">
    <h3 class="card-title">Daily Net R (this month)</h3>
    <div class="chart-box-md">
      <canvas id="netRLine"></canvas>
    </div>
  </div>

  <div class="monthly-card">
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
                  <div class="card-sub"><?= $tr ?> trade<?= $tr>1?'s':'' ?></div>
                <?php else: ?>
                  <div class="card-sub">—</div>
                <?php endif; ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
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
  options: {
    plugins: { legend: { display: false } },
    cutout: "70%"
  }
});

new Chart(document.getElementById("wlDonut"), {
  type: "doughnut",
  data: {
    labels: ["Win %", "Loss %"],
    datasets: [{ data: [winRate, lossRate], borderWidth: 0 }]
  },
  options: {
    plugins: { legend: { position: "bottom" } },
    cutout: "70%"
  }
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