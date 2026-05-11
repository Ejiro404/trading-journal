<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "trade-history";
$pageTitle = "Delete Trade • NXLOG Analytics";

$user_id = (int)($_SESSION['user_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);

if (!function_exists('e')) {
  function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
  }
}

function fmt_dt($value) {
  if (!$value) return '-';
  $ts = strtotime((string)$value);
  return $ts ? date('d M Y, h:i A', $ts) : '-';
}

if ($id <= 0) {
  http_response_code(400);
  exit("Invalid trade ID.");
}

$stmt = $conn->prepare("
  SELECT id, symbol, entry_time, screenshot_path
  FROM trades
  WHERE id = ? AND user_id = ?
  LIMIT 1
");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$trade = $stmt->get_result()->fetch_assoc();

if (!$trade) {
  http_response_code(404);
  exit("Trade not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $conn->begin_transaction();

    $reviewIds = [];
    $rv = $conn->prepare("SELECT id FROM trade_reviews WHERE trade_id = ?");
    $rv->bind_param("i", $id);
    $rv->execute();
    $reviewRows = $rv->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($reviewRows as $row) {
      $reviewIds[] = (int)$row['id'];
    }

    if (!empty($reviewIds)) {
      foreach ($reviewIds as $reviewId) {
        $dc = $conn->prepare("DELETE FROM trade_rule_checks WHERE review_id = ?");
        $dc->bind_param("i", $reviewId);
        $dc->execute();
      }
    }

    $dr = $conn->prepare("DELETE FROM trade_reviews WHERE trade_id = ?");
    $dr->bind_param("i", $id);
    $dr->execute();

    $dtm = $conn->prepare("DELETE FROM trade_tag_map WHERE trade_id = ?");
    $dtm->bind_param("i", $id);
    $dtm->execute();

    $del = $conn->prepare("DELETE FROM trades WHERE id = ? AND user_id = ?");
    $del->bind_param("ii", $id, $user_id);
    $del->execute();

    $conn->commit();

    if (!empty($trade['screenshot_path'])) {
      $screenshotFs = __DIR__ . "/" . $trade['screenshot_path'];
      if (is_file($screenshotFs)) {
        @unlink($screenshotFs);
      }
    }

    header("Location: /trading-journal/trade-history.php");
    exit;
  } catch (Throwable $e) {
    $conn->rollback();
    $error = "Could not delete trade. Please try again.";
  }
}

require_once __DIR__ . "/partials/app_header.php";
?>

<style>
.delete-wrap{
  display:grid;
  gap:14px;
  width:100%;
  max-width:100%;
  overflow:hidden;
}

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
  line-height:1.05;
  font-weight:900;
  letter-spacing:-.03em;
}

.page-head p{
  margin:6px 0 0;
  color:var(--muted);
  line-height:1.6;
}

.confirm-card{
  background:var(--card);
  border:1px solid rgba(239,68,68,.35);
  border-radius:18px;
  box-shadow:var(--shadow);
  padding:18px;
  min-width:0;
}

.warning-pill{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  border:1px solid rgba(239,68,68,.35);
  background:rgba(239,68,68,.10);
  color:#ef4444;
  border-radius:999px;
  padding:6px 10px;
  font-size:12px;
  font-weight:900;
  margin-bottom:12px;
}

.confirm-title{
  margin:0 0 8px;
  font-size:22px;
  line-height:1.1;
  font-weight:900;
}

.confirm-text{
  margin:0;
  color:var(--muted);
  font-size:14px;
  font-weight:700;
  line-height:1.7;
}

.trade-summary{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:12px;
  margin-top:16px;
}

.summary-box{
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:14px;
  padding:12px 14px;
}

.summary-label{
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:.03em;
  color:var(--muted);
  font-weight:900;
  margin-bottom:6px;
}

.summary-value{
  font-size:15px;
  font-weight:900;
  line-height:1.35;
  word-break:break-word;
}

.alert{
  border:1px solid rgba(239,68,68,.35);
  background:rgba(239,68,68,.10);
  color:#ef4444;
  border-radius:14px;
  padding:12px 14px;
  font-size:14px;
  font-weight:800;
  margin-top:14px;
}

.actions{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  margin-top:18px;
}

@media (max-width:720px){
  .delete-wrap{
    gap:10px;
    overflow:visible;
  }

  .page-head{
    display:grid;
    gap:8px;
  }

  .page-head h1{
    font-size:22px;
  }

  .page-head p{
    font-size:12px;
    line-height:1.45;
  }

  .confirm-card{
    padding:13px;
    border-radius:16px;
  }

  .warning-pill{
    font-size:10px;
    padding:5px 8px;
    margin-bottom:10px;
  }

  .confirm-title{
    font-size:18px;
  }

  .confirm-text{
    font-size:12px;
    line-height:1.55;
  }

  .trade-summary{
    grid-template-columns:1fr;
    gap:8px;
    margin-top:12px;
  }

  .summary-box{
    padding:10px;
    border-radius:13px;
  }

  .summary-label{
    font-size:10px;
    margin-bottom:5px;
  }

  .summary-value{
    font-size:13px;
  }

  .actions{
    display:grid;
    grid-template-columns:1fr;
    gap:8px;
  }

  .actions .btn{
    width:100%;
    min-height:36px;
    padding:8px 10px;
    font-size:12px;
  }

  .alert{
    font-size:12px;
    padding:10px 12px;
  }
}
</style>

<div class="delete-wrap">

  <div class="page-head">
    <div>
      <h1>Delete Trade</h1>
      <p>Confirm before permanently removing this trade from your journal.</p>
    </div>
  </div>

  <div class="confirm-card">
    <div class="warning-pill">Permanent Action</div>

    <h2 class="confirm-title">Are you sure you want to delete this trade?</h2>

    <p class="confirm-text">
      You’re about to delete this trade and its related review/tag data. This action cannot be undone.
    </p>

    <div class="trade-summary">
      <div class="summary-box">
        <div class="summary-label">Symbol</div>
        <div class="summary-value"><?= e($trade['symbol'] ?? '-') ?></div>
      </div>

      <div class="summary-box">
        <div class="summary-label">Entry Time</div>
        <div class="summary-value"><?= e(fmt_dt($trade['entry_time'] ?? null)) ?></div>
      </div>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="actions">
        <button class="btn danger" type="submit">Yes, Delete Trade</button>
        <a class="btn secondary" href="/trading-journal/log_view.php?id=<?= (int)$trade['id'] ?>">Cancel</a>
      </div>
    </form>
  </div>

</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>