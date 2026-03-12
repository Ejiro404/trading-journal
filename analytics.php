<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "analytics";
$pageTitle = "Analytics • NXLOG Analytics";

$user_id = (int)($_SESSION['user_id'] ?? 0);

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/** headline stats */
$stmt = $conn->prepare("
  SELECT
    COUNT(*) total,
    SUM(COALESCE(r_multiple,0)) net_r,
    AVG(COALESCE(r_multiple,0)) avg_r,
    SUM(CASE WHEN r_multiple > 0 THEN 1 ELSE 0 END) wins
  FROM trades
  WHERE user_id = ? AND r_multiple IS NOT NULL
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$s = $stmt->get_result()->fetch_assoc();

$total = (int)($s['total'] ?? 0);
$net_r = (float)($s['net_r'] ?? 0);
$avg_r = (float)($s['avg_r'] ?? 0);
$wins = (int)($s['wins'] ?? 0);
$win_rate = $total > 0 ? ($wins / $total) * 100 : 0;

/** strategy breakdown */
$st = $conn->prepare("
  SELECT
    COALESCE(tt.name, 'Unassigned') AS strategy,
    COUNT(*) AS trades,
    AVG(t.r_multiple) AS avg_r,
    SUM(CASE WHEN t.r_multiple > 0 THEN 1 ELSE 0 END) AS wins
  FROM trades t
  LEFT JOIN trade_tag_map tm
    ON tm.trade_id = t.id
  LEFT JOIN trade_tags tt
    ON tt.id = tm.tag_id
    AND tt.user_id = ?
    AND tt.tag_type = 'strategy'
  WHERE t.user_id = ?
    AND t.r_multiple IS NOT NULL
  GROUP BY COALESCE(tt.name, 'Unassigned')
  ORDER BY trades DESC, avg_r DESC
");
$st->bind_param("ii", $user_id, $user_id);
$st->execute();
$strategies = $st->get_result()->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . "/partials/app_header.php";
?>

<style>
.analytics-wrap{ display:grid; gap:14px; }

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
.table-panel,
.insight-panel{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:18px;
  box-shadow:var(--shadow);
}
.kpi-card{ padding:16px; }
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

.insight-panel{
  padding:16px;
}
.insight-panel h3{
  margin:0 0 8px;
  font-size:19px;
  font-weight:900;
}
.insight-grid{
  display:grid;
  grid-template-columns:repeat(3,minmax(0,1fr));
  gap:12px;
  margin-top:12px;
}
.insight-box{
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:14px;
  padding:14px;
}
.insight-label{
  font-size:12px;
  color:var(--muted);
  font-weight:900;
  text-transform:uppercase;
  letter-spacing:.03em;
  margin-bottom:6px;
}
.insight-value{
  font-size:22px;
  font-weight:900;
}
.insight-sub{
  margin-top:6px;
  color:var(--muted);
  font-size:12px;
  font-weight:700;
}

.table-panel{ overflow:hidden; }
.table-head{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  flex-wrap:wrap;
  padding:16px 16px 0;
}
.table-head h3{
  margin:0;
  font-size:20px;
  font-weight:900;
}
.table-head .sub{
  color:var(--muted);
  font-size:13px;
  font-weight:700;
}

.table-wrap{
  overflow:auto;
  padding-top:12px;
}
.analytics-table{
  width:100%;
  border-collapse:collapse;
  min-width:760px;
}
.analytics-table th,
.analytics-table td{
  padding:14px 16px;
  text-align:left;
  border-bottom:1px solid var(--border);
  vertical-align:middle;
}
.analytics-table th{
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:.03em;
  color:var(--muted);
  font-weight:900;
}
.analytics-table tr:last-child td{
  border-bottom:none;
}

.pill{
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
.pill.good{ color:#16a34a; }
.pill.bad{ color:#ef4444; }
.pill.neutral{ color:#eab308; }

.empty-state{
  padding:28px 18px;
  text-align:center;
}
.empty-state p{
  margin:0 0 14px;
  color:var(--muted);
}

.value-good{ color:#16a34a; font-weight:900; }
.value-bad{ color:#ef4444; font-weight:900; }
.value-neutral{ color:#eab308; font-weight:900; }

@media (max-width: 1100px){
  .kpi-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); }
  .insight-grid{ grid-template-columns:1fr; }
}
@media (max-width: 720px){
  .kpi-grid{ grid-template-columns:1fr; }
}
</style>

<div class="analytics-wrap">

  <div class="page-head">
    <div>
      <h1>Analytics</h1>
      <p>Observe performance patterns, compare strategy output, and refine execution quality.</p>
    </div>

    <div class="page-head-actions">
      <a class="btn secondary" href="/trading-journal/trade-history.php">Trade History</a>
      <a class="btn" href="/trading-journal/dashboard.php">Back to Dashboard</a>
    </div>
  </div>

  <div class="kpi-grid">
    <div class="kpi-card">
      <h3>Total R</h3>
      <div class="kpi-value"><?= number_format($net_r, 2) ?>R</div>
      <div class="kpi-sub">Combined result across closed trades</div>
    </div>

    <div class="kpi-card">
      <h3>Win Rate</h3>
      <div class="kpi-value"><?= number_format($win_rate, 1) ?>%</div>
      <div class="kpi-sub"><?= $wins ?> wins from <?= $total ?> closed trades</div>
    </div>

    <div class="kpi-card">
      <h3>Avg R</h3>
      <div class="kpi-value"><?= $total ? number_format($avg_r, 2) . "R" : "—" ?></div>
      <div class="kpi-sub">Average R per closed trade</div>
    </div>

    <div class="kpi-card">
      <h3>Trades</h3>
      <div class="kpi-value"><?= $total ?></div>
      <div class="kpi-sub">Closed trades included in analytics</div>
    </div>
  </div>

  <div class="insight-panel">
    <h3>Quick Read</h3>
    <div class="insight-grid">
      <div class="insight-box">
        <div class="insight-label">Performance</div>
        <div class="insight-value <?= $net_r > 0 ? 'value-good' : ($net_r < 0 ? 'value-bad' : 'value-neutral') ?>">
          <?= number_format($net_r, 2) ?>R
        </div>
        <div class="insight-sub">Net result from closed trades</div>
      </div>

      <div class="insight-box">
        <div class="insight-label">Efficiency</div>
        <div class="insight-value <?= $avg_r > 0 ? 'value-good' : ($avg_r < 0 ? 'value-bad' : 'value-neutral') ?>">
          <?= $total ? number_format($avg_r, 2) . "R" : "—" ?>
        </div>
        <div class="insight-sub">Average return per trade</div>
      </div>

      <div class="insight-box">
        <div class="insight-label">Hit Rate</div>
        <div class="insight-value <?= $win_rate >= 50 ? 'value-good' : 'value-neutral' ?>">
          <?= number_format($win_rate, 1) ?>%
        </div>
        <div class="insight-sub">Win frequency across closed trades</div>
      </div>
    </div>
  </div>

  <div class="table-panel">
    <div class="table-head">
      <div>
        <h3>By Strategy</h3>
        <div class="sub"><?= count($strategies) ?> strategy bucket<?= count($strategies) === 1 ? '' : 's' ?> found</div>
      </div>
    </div>

    <?php if (!$strategies): ?>
      <div class="empty-state">
        <p>No closed trades yet.</p>
        <a class="btn" href="/trading-journal/log_new.php">Log a trade</a>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="analytics-table">
          <thead>
            <tr>
              <th>Strategy</th>
              <th>Trades</th>
              <th>Win Rate</th>
              <th>Avg R</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($strategies as $r): ?>
              <?php
                $tr = (int)$r['trades'];
                $wr = $tr > 0 ? ((int)$r['wins'] / $tr) * 100 : 0;
                $avgStrategyR = (float)$r['avg_r'];
                $wrClass = $wr >= 50 ? 'good' : 'neutral';
                $avgClass = $avgStrategyR > 0 ? 'good' : ($avgStrategyR < 0 ? 'bad' : 'neutral');
              ?>
              <tr>
                <td><strong><?= e($r['strategy']) ?></strong></td>
                <td><?= $tr ?></td>
                <td>
                  <span class="pill <?= e($wrClass) ?>">
                    <?= number_format($wr, 1) ?>%
                  </span>
                </td>
                <td>
                  <span class="pill <?= e($avgClass) ?>">
                    <?= number_format($avgStrategyR, 2) ?>R
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>