<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "review";
$pageTitle = "Review • NXLOG Analytics";

$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
  SELECT id, entry_time, symbol, direction, r_multiple
  FROM trades
  WHERE user_id = ?
    AND r_multiple IS NOT NULL
    AND is_reviewed = 0
  ORDER BY entry_time ASC
  LIMIT 200
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . "/partials/app_header.php";
?>

<div class="card">
  <h2>Review Queue</h2>
  <p class="small">Unreviewed trades. Review is where growth happens.</p>

  <?php if (!$rows): ?>
    <p class="small">You’re clear. No pending reviews.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Date</th><th>Symbol</th><th>Side</th><th>R</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $t): ?>
        <tr>
          <td><?= e($t['entry_time']) ?></td>
          <td><?= e($t['symbol']) ?></td>
          <td><?= e($t['direction']) ?></td>
          <td><?= number_format((float)$t['r_multiple'], 2) ?>R</td>
          <td>
            <a class="btn" href="/trading-journal/review_form.php?trade_id=<?= (int)$t['id'] ?>">Review</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>
