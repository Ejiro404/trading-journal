<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$user_id = (int)$_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT id, symbol, entry_time FROM trades WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$trade = $stmt->get_result()->fetch_assoc();

if (!$trade) {
  http_response_code(404);
  exit("Trade not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $del = $conn->prepare("DELETE FROM trades WHERE id = ? AND user_id = ?");
  $del->bind_param("ii", $id, $user_id);
  $del->execute();
  header("Location: /trading-journal/trades.php");
  exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/trading-journal/assets/css/style.css">
  <title>Delete Trade • Trading Journal</title>
</head>
<body>
<div class="container">
  <div class="nav">
    <a class="btn" href="/trading-journal/dashboard.php">Dashboard</a>
    <a class="btn" href="/trading-journal/trades.php">Trades</a>
    <a class="btn" href="/trading-journal/logout.php">Logout</a>
  </div>

  <div class="card">
    <h2>Delete Trade</h2>
    <p class="small">
      You’re about to delete <b><?= e($trade['symbol']) ?></b> from <b><?= e($trade['entry_time']) ?></b>.
      This cannot be undone.
    </p>

    <form method="post">
      <button class="btn danger" type="submit">Yes, delete</button>
      <a class="btn" href="/trading-journal/trade_view.php?id=<?= (int)$trade['id'] ?>">Cancel</a>
    </form>
  </div>
</div>
</body>
</html>
