<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "analytics";
$pageTitle = "Analytics • NXLOG Analytics";

$user_id = (int)$_SESSION['user_id'];

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

/** strategy breakdown (real tags) */
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

<div class="card">
  <h2>Analytics</h2>
  <p class="small">Observe performance patterns. Refine execution.</p>
</div>

<div class="grid grid-4">
  <div class="card"><h3>Total R</h3><div class="value"><?= number_format($net_r,2) ?>R</div></div>
  <div class="card"><h3>Win Rate</h3><div class="value"><?= number_format($win_rate,1) ?>%</div></div>
  <div class="card"><h3>Avg R</h3><div class="value"><?= $total?number_format($avg_r,2)."R":"—" ?></div></div>
  <div class="card"><h3>Trades</h3><div class="value"><?= $total ?></div></div>
</div>

<div class="card">
  <h3>By Strategy</h3>
  <?php if (!$strategies): ?>
    <p class="small">No closed trades yet.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Strategy</th>
          <th>Trades</th>
          <th>Win rate</th>
          <th>Avg R</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($strategies as $r): ?>
          <?php
            $tr = (int)$r['trades'];
            $wr = $tr > 0 ? ((int)$r['wins'] / $tr) * 100 : 0;
          ?>
          <tr>
            <td><?= e($r['strategy']) ?></td>
            <td><?= $tr ?></td>
            <td><?= number_format($wr, 1) ?>%</td>
            <td><?= number_format((float)$r['avg_r'], 2) ?>R</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>
