<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "log";
$pageTitle = "Close Trade • NXLOG Analytics";

$user_id = (int)($_SESSION['user_id'] ?? 0);
$trade_id = (int)($_GET['id'] ?? 0);

if (!function_exists('e')) {
  function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
  }
}

function mysql_to_dtlocal($v) {
  $v = (string)$v;
  if ($v === "") return "";
  return str_replace(" ", "T", substr($v, 0, 16));
}

function dtlocal_to_mysql($v) {
  $v = trim((string)$v);
  if ($v === "") return "";
  return str_replace("T", " ", $v) . ":00";
}

function fmt_dt($value) {
  if (!$value) return '-';
  $ts = strtotime((string)$value);
  return $ts ? date('d M Y, h:i A', $ts) : '-';
}

$stmt = $conn->prepare("SELECT * FROM trades WHERE id=? AND user_id=? LIMIT 1");
$stmt->bind_param("ii", $trade_id, $user_id);
$stmt->execute();
$trade = $stmt->get_result()->fetch_assoc();

if (!$trade) {
  http_response_code(404);
  exit("Trade not found.");
}

$error = "";
$ok = "";

$isAlreadyClosed = (
  !empty($trade['exit_time']) ||
  $trade['exit_price'] !== null ||
  $trade['pnl_amount'] !== null ||
  $trade['r_multiple'] !== null
);

$exit_time_input = !empty($trade['exit_time']) ? mysql_to_dtlocal($trade['exit_time']) : '';
$exit_price_input = $trade['exit_price'] !== null ? (string)$trade['exit_price'] : '';
$pnl_input = $trade['pnl_amount'] !== null ? (string)$trade['pnl_amount'] : '';
$exit_reason = (string)($trade['exit_reason'] ?? '');
$notes_post = (string)($trade['notes_post'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $exit_time_input = trim($_POST['exit_time'] ?? '');
  $exit_price_input = trim($_POST['exit_price'] ?? '');
  $pnl_input = trim($_POST['pnl_amount'] ?? '');
  $exit_reason = trim($_POST['exit_reason'] ?? '');
  $notes_post = trim($_POST['notes_post'] ?? '');

  $exit_time = dtlocal_to_mysql($exit_time_input);
  $exit_price = ($exit_price_input === '') ? null : (float)$exit_price_input;
  $pnl_amount = ($pnl_input === '') ? null : (float)$pnl_input;

  $risk_amount = (float)($trade['risk_amount'] ?? 0);
  $r_multiple = null;
  if ($pnl_amount !== null && $risk_amount > 0) {
    $r_multiple = $pnl_amount / $risk_amount;
  }

  $outcome = 'breakeven';
  if ($pnl_amount !== null) {
    if ($pnl_amount > 0) $outcome = 'win';
    elseif ($pnl_amount < 0) $outcome = 'loss';
  }

  if ($exit_time === "" || $exit_price === null || $pnl_amount === null) {
    $error = "Exit time, exit price, and P/L are required.";
  } else {
    $upd = $conn->prepare("
      UPDATE trades
      SET exit_time=?, exit_price=?, exit_reason=?, pnl_amount=?, r_multiple=?, outcome=?, notes_post=?
      WHERE id=? AND user_id=?
    ");
    $upd->bind_param(
      "sssddssii",
      $exit_time,
      $exit_price,
      $exit_reason,
      $pnl_amount,
      $r_multiple,
      $outcome,
      $notes_post,
      $trade_id,
      $user_id
    );
    $upd->execute();

    $ok = "Trade closed successfully.";

    $stmt = $conn->prepare("SELECT * FROM trades WHERE id=? AND user_id=? LIMIT 1");
    $stmt->bind_param("ii", $trade_id, $user_id);
    $stmt->execute();
    $trade = $stmt->get_result()->fetch_assoc();

    $isAlreadyClosed = true;
  }
}

require_once __DIR__ . "/partials/app_header.php";
?>

<style>
.closetrade-wrap{ display:grid; gap:14px; }

.page-head{
  display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;
}
.page-head h1{ margin:0; font-size:28px; font-weight:900; }
.page-head p{ margin:6px 0 0; color:var(--muted); }
.page-head-actions{ display:flex; gap:10px; flex-wrap:wrap; }

.close-shell{
  display:grid;
  grid-template-columns:1.1fr .9fr;
  gap:14px;
}

.form-panel,
.helper-panel{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:18px;
  box-shadow:var(--shadow);
  padding:16px;
}

.panel-title{
  margin:0 0 4px;
  font-size:20px;
  font-weight:900;
}
.panel-sub{
  color:var(--muted);
  font-size:13px;
  font-weight:700;
  margin-bottom:14px;
}

.trade-summary{
  display:grid;
  grid-template-columns:repeat(4,minmax(0,1fr));
  gap:12px;
  margin-bottom:14px;
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
  font-size:16px;
  font-weight:900;
  line-height:1.3;
}

.alert{
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:14px;
  padding:12px 14px;
  font-size:14px;
  font-weight:800;
  margin-bottom:14px;
}
.alert.err{ color:#ef4444; }
.alert.ok{ color:#16a34a; }

.form-grid{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:12px;
}
.field{
  display:grid;
  gap:6px;
}
.field label{
  font-size:12px;
  font-weight:900;
  color:var(--muted);
  text-transform:uppercase;
  letter-spacing:.03em;
}
.field input,
.field select,
.field textarea{
  width:100%;
  border:1px solid var(--border);
  background:var(--bg);
  color:var(--text);
  border-radius:12px;
  padding:10px 12px;
  outline:none;
}
.field input,
.field select{ min-height:44px; }
.field textarea{
  min-height:160px;
  resize:vertical;
}
.span-2{ grid-column:1 / -1; }

.actions{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  margin-top:16px;
}

.helper-list{
  display:grid;
  gap:10px;
}
.helper-item{
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:14px;
  padding:12px 14px;
}
.helper-item-title{
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:.03em;
  color:var(--muted);
  font-weight:900;
  margin-bottom:6px;
}
.helper-item-text{
  font-size:13px;
  line-height:1.6;
  font-weight:700;
}

.status-box{
  border:1px dashed var(--border);
  background:var(--pill);
  border-radius:14px;
  padding:12px 14px;
  margin-top:12px;
}
.status-title{
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:.03em;
  color:var(--muted);
  font-weight:900;
  margin-bottom:6px;
}
.status-value{
  font-size:13px;
  font-weight:700;
}

@media (max-width: 1100px){
  .close-shell{ grid-template-columns:1fr; }
}
@media (max-width: 820px){
  .trade-summary{ grid-template-columns:repeat(2,minmax(0,1fr)); }
  .form-grid{ grid-template-columns:1fr; }
}
@media (max-width: 560px){
  .trade-summary{ grid-template-columns:1fr; }
}
</style>

<div class="closetrade-wrap">

  <div class="page-head">
    <div>
      <h1>Close Trade</h1>
      <p>Complete the trade with exit data so it can flow into analytics and review properly.</p>
    </div>

    <div class="page-head-actions">
      <a class="btn secondary" href="/trading-journal/log_view.php?id=<?= (int)$trade['id'] ?>">Back to Trade</a>
    </div>
  </div>

  <div class="close-shell">

    <div class="form-panel">
      <h3 class="panel-title">Exit Details</h3>
      <div class="panel-sub">Save the final result, post-trade notes, and exit reason.</div>

      <div class="trade-summary">
        <div class="summary-box">
          <div class="summary-label">Symbol</div>
          <div class="summary-value"><?= e($trade['symbol'] ?? '-') ?></div>
        </div>
        <div class="summary-box">
          <div class="summary-label">Direction</div>
          <div class="summary-value"><?= e($trade['direction'] ?? '-') ?></div>
        </div>
        <div class="summary-box">
          <div class="summary-label">Entry Time</div>
          <div class="summary-value"><?= e(fmt_dt($trade['entry_time'] ?? null)) ?></div>
        </div>
        <div class="summary-box">
          <div class="summary-label">Risk Amount</div>
          <div class="summary-value"><?= number_format((float)($trade['risk_amount'] ?? 0), 2) ?></div>
        </div>
      </div>

      <?php if ($error): ?>
        <div class="alert err"><?= e($error) ?></div>
      <?php endif; ?>

      <?php if ($ok): ?>
        <div class="alert ok"><?= e($ok) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="form-grid">
          <div class="field">
            <label for="exit_time">Exit Time</label>
            <input id="exit_time" name="exit_time" type="datetime-local" value="<?= e($exit_time_input) ?>" required>
          </div>

          <div class="field">
            <label for="exit_price">Exit Price</label>
            <input id="exit_price" name="exit_price" type="number" step="0.00000001" value="<?= e($exit_price_input) ?>" required>
          </div>

          <div class="field">
            <label for="pnl_amount">P/L Amount</label>
            <input id="pnl_amount" name="pnl_amount" type="number" step="0.01" value="<?= e($pnl_input) ?>" required>
          </div>

          <div class="field">
            <label for="exit_reason">Exit Reason</label>
            <input id="exit_reason" name="exit_reason" value="<?= e($exit_reason) ?>" placeholder="TP, SL, manual close, partials...">
          </div>

          <div class="field span-2">
            <label for="notes_post">Post-Trade Notes</label>
            <textarea id="notes_post" name="notes_post" placeholder="How the trade ended, emotions, what you observed..."><?= e($notes_post) ?></textarea>
          </div>
        </div>

        <div class="actions">
          <button class="btn" type="submit">Save Close</button>
          <a class="btn secondary" href="/trading-journal/log_view.php?id=<?= (int)$trade['id'] ?>">Cancel</a>
        </div>
      </form>
    </div>

    <div class="helper-panel">
      <h3 class="panel-title">Close Guide</h3>
      <div class="panel-sub">A clean close record makes reviews and analytics far more meaningful.</div>

      <div class="helper-list">
        <div class="helper-item">
          <div class="helper-item-title">P/L Accuracy</div>
          <div class="helper-item-text">Enter the actual profit or loss amount. R-multiple is calculated from this and your stored risk amount.</div>
        </div>

        <div class="helper-item">
          <div class="helper-item-title">Exit Reason</div>
          <div class="helper-item-text">Be specific about why the trade ended: target hit, stop hit, manual close, session end, structure shift, and so on.</div>
        </div>

        <div class="helper-item">
          <div class="helper-item-title">Post-Trade Notes</div>
          <div class="helper-item-text">Capture what happened after entry, what you managed well, and what you would improve next time.</div>
        </div>
      </div>

      <div class="status-box">
        <div class="status-title">Trade Status</div>
        <div class="status-value"><?= $isAlreadyClosed ? "This trade already has close data and can be updated here." : "This trade is still open and needs final exit data." ?></div>
      </div>
    </div>

  </div>

</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>