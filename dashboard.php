<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "dashboard";
$pageTitle = "Dashboard • NXLOG Analytics";

$user_id = (int)$_SESSION['user_id'];

/** KPI: closed trades only */
$stmt = $conn->prepare("
  SELECT
    COUNT(*) AS total,
    SUM(COALESCE(r_multiple,0)) AS net_r,
    AVG(COALESCE(r_multiple,0)) AS avg_r,
    SUM(CASE WHEN r_multiple > 0 THEN 1 ELSE 0 END) AS wins,
    SUM(CASE WHEN is_reviewed = 0 THEN 1 ELSE 0 END) AS unreviewed
  FROM trades
  WHERE user_id = ?
    AND r_multiple IS NOT NULL
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$k = $stmt->get_result()->fetch_assoc();

$total = (int)($k['total'] ?? 0);
$net_r = (float)($k['net_r'] ?? 0);
$avg_r = (float)($k['avg_r'] ?? 0);
$wins = (int)($k['wins'] ?? 0);
$unreviewed = (int)($k['unreviewed'] ?? 0);
$win_rate = $total > 0 ? ($wins / $total) * 100 : 0;

/** Rule adherence = AVG(trade_reviews.rules_score) */
$rulesStmt = $conn->prepare("
  SELECT AVG(r.rules_score) AS avg_score
  FROM trade_reviews r
  INNER JOIN trades t ON t.id = r.trade_id
  WHERE t.user_id = ?
    AND r.rules_score IS NOT NULL
");
$rulesStmt->bind_param("i", $user_id);
$rulesStmt->execute();
$rulesRow = $rulesStmt->get_result()->fetch_assoc();
$avg_rules = $rulesRow && $rulesRow['avg_score'] !== null ? (float)$rulesRow['avg_score'] : null;

/** Recent trades */
$rstmt = $conn->prepare("
  SELECT id, entry_time, symbol, direction, r_multiple, is_reviewed
  FROM trades
  WHERE user_id = ?
  ORDER BY entry_time DESC
  LIMIT 10
");
$rstmt->bind_param("i", $user_id);
$rstmt->execute();
$recent = $rstmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . "/partials/app_header.php";
?>

<div class="card">
  <h2>Dashboard</h2>
  <p class="small">Execution & consistency, measured through structure—not emotion.</p>
</div>

<div class="grid grid-4">
  <div class="card">
    <h3>Net R</h3>
    <div class="value"><?= number_format($net_r, 2) ?>R</div>
    <div class="small">Closed trades only</div>
  </div>

  <div class="card">
    <h3>Win Rate</h3>
    <div class="value"><?= number_format($win_rate, 1) ?>%</div>
    <div class="small"><?= $wins ?> wins / <?= $total ?> trades</div>
  </div>

  <div class="card">
    <h3>Avg R</h3>
    <div class="value"><?= $total ? number_format($avg_r, 2) . "R" : "—" ?></div>
    <div class="small">Average per closed trade</div>
  </div>

  <div class="card">
    <h3>Rule Adherence</h3>
    <div class="value"><?= $avg_rules === null ? "—" : number_format($avg_rules, 0) . "%" ?></div>
    <div class="small">Avg review score</div>
  </div>
</div>

<div class="card">
  <h3>Execution & Consistency</h3>
  <div class="row">
    <div class="col">
      <div class="small" style="color:var(--muted)">Next action</div>
      <div style="font-weight:900;font-size:18px;margin-top:6px">Review queue</div>
      <div class="small">Trades awaiting structured review: <b><?= (int)$unreviewed ?></b></div>
      <div style="margin-top:10px">
        <a class="btn" href="/trading-journal/review_queue.php">Open Review</a>
      </div>
    </div>

    <div class="col">
      <div class="small" style="color:var(--muted)">Focus</div>
      <div class="small">Log cleanly. Review honestly. Refine consistently.</div>
      <div style="margin-top:10px">
        <a class="btn ghost" href="/trading-journal/log_new.php">Start Logging</a>
        <a class="btn ghost" href="/trading-journal/analytics.php">Analyze</a>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <h3>Recent Activity</h3>
  <?php if (!$recent): ?>
    <p class="small">No trades logged yet. Start by adding your first trade.</p>
    <a class="btn" href="/trading-journal/log_new.php">Start Logging</a>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Date</th><th>Symbol</th><th>Side</th><th>R</th><th>Review</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($recent as $t): ?>
        <tr>
          <td><?= e($t['entry_time']) ?></td>
          <td><?= e($t['symbol']) ?></td>
          <td><?= e($t['direction']) ?></td>
          <td>
            <?= $t['r_multiple'] === null ? '<span class="badge">open</span>' : '<span class="badge">'.number_format((float)$t['r_multiple'],2).'R</span>' ?>
          </td>
          <td><?= (int)$t['is_reviewed']===1 ? '<span class="badge good">Reviewed</span>' : '<span class="badge">Pending</span>' ?></td>
          <td><a class="btn secondary" href="/trading-journal/log_view.php?id=<?= (int)$t['id'] ?>">Open</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>
