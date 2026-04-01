<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "log";
$pageTitle = "Edit Trade • NXLOG Analytics";

$user_id = (int)($_SESSION['user_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);

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

function get_or_create_tag(mysqli $conn, int $user_id, string $type, string $name): int {
  $name = trim($name);
  if ($name === "") return 0;

  $sel = $conn->prepare("SELECT id FROM trade_tags WHERE user_id=? AND tag_type=? AND name=? LIMIT 1");
  $sel->bind_param("iss", $user_id, $type, $name);
  $sel->execute();
  $row = $sel->get_result()->fetch_assoc();
  if ($row) return (int)$row['id'];

  $ins = $conn->prepare("INSERT INTO trade_tags (user_id, tag_type, name) VALUES (?,?,?)");
  $ins->bind_param("iss", $user_id, $type, $name);
  $ins->execute();
  return (int)$conn->insert_id;
}

function set_trade_strategy(mysqli $conn, int $trade_id, int $user_id, string $strategy_name): void {
  $del = $conn->prepare("
    DELETE tm FROM trade_tag_map tm
    INNER JOIN trade_tags tt ON tt.id = tm.tag_id
    WHERE tm.trade_id = ? AND tt.user_id = ? AND tt.tag_type = 'strategy'
  ");
  $del->bind_param("ii", $trade_id, $user_id);
  $del->execute();

  if (trim($strategy_name) === "") return;

  $tag_id = get_or_create_tag($conn, $user_id, "strategy", $strategy_name);
  $ins = $conn->prepare("INSERT IGNORE INTO trade_tag_map (trade_id, tag_id) VALUES (?,?)");
  $ins->bind_param("ii", $trade_id, $tag_id);
  $ins->execute();
}

$stmt = $conn->prepare("SELECT * FROM trades WHERE id=? AND user_id=? LIMIT 1");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$trade = $stmt->get_result()->fetch_assoc();

if (!$trade) {
  http_response_code(404);
  exit("Trade not found.");
}

/** current strategy name */
$curStr = "";
$cs = $conn->prepare("
  SELECT tt.name
  FROM trade_tag_map tm
  INNER JOIN trade_tags tt ON tt.id = tm.tag_id
  WHERE tm.trade_id = ? AND tt.user_id = ? AND tt.tag_type='strategy'
  LIMIT 1
");
$cs->bind_param("ii", $id, $user_id);
$cs->execute();
$cur = $cs->get_result()->fetch_assoc();
if ($cur) $curStr = (string)$cur['name'];

/** strategy options */
$tagRows = [];
$ts = $conn->prepare("SELECT name FROM trade_tags WHERE user_id=? AND tag_type='strategy' ORDER BY name ASC");
$ts->bind_param("i", $user_id);
$ts->execute();
$tagRows = $ts->get_result()->fetch_all(MYSQLI_ASSOC);

$error = "";
$ok = "";

$uploadDir = __DIR__ . "/uploads/trade_screenshots/";
$uploadRelative = "uploads/trade_screenshots/";

if (!is_dir($uploadDir)) {
  @mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $symbol = strtoupper(trim($_POST['symbol'] ?? ''));
  $market = trim($_POST['market'] ?? 'FX');
  $direction = ($_POST['direction'] ?? 'BUY') === 'SELL' ? 'SELL' : 'BUY';
  $session = trim($_POST['session'] ?? '');

  $entry_time = dtlocal_to_mysql($_POST['entry_time'] ?? '');
  $entry_price = (float)($_POST['entry_price'] ?? 0);
  $stop_loss = (float)($_POST['stop_loss'] ?? 0);

  $take_profit_raw = trim($_POST['take_profit'] ?? '');
  $take_profit = ($take_profit_raw === '') ? null : (float)$take_profit_raw;

  $position_size_raw = trim($_POST['position_size'] ?? '');
  $position_size = ($position_size_raw === '') ? null : (float)$position_size_raw;

  $risk_amount = (float)($_POST['risk_amount'] ?? 0);

  $setup = trim($_POST['setup'] ?? '');
  $legacy_tags = trim($_POST['legacy_tags'] ?? '');
  $notes_pre = trim($_POST['notes_pre'] ?? '');

  $strategy_existing = trim($_POST['strategy_existing'] ?? '');
  $strategy_new = trim($_POST['strategy_new'] ?? '');
  $strategy_name = $strategy_new !== "" ? $strategy_new : $strategy_existing;

  $delete_screenshot = isset($_POST['delete_screenshot']) ? 1 : 0;
  $screenshot_path = $trade['screenshot_path'] ?? null;

  if ($symbol === "" || $entry_time === "" || $entry_price == 0 || $stop_loss == 0 || $risk_amount <= 0) {
    $error = "Symbol, entry time, entry price, stop loss and risk amount are required.";
  }

  if (!$error && $delete_screenshot && !empty($screenshot_path)) {
    $oldPath = __DIR__ . "/" . $screenshot_path;
    if (is_file($oldPath)) {
      @unlink($oldPath);
    }
    $screenshot_path = null;
  }

  if (
    !$error &&
    isset($_FILES['screenshot']) &&
    !empty($_FILES['screenshot']['name']) &&
    (int)($_FILES['screenshot']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
  ) {
    if ((int)$_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
      $error = "Screenshot upload failed.";
    } else {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mime = finfo_file($finfo, $_FILES['screenshot']['tmp_name']);
      finfo_close($finfo);

      $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
      ];

      if (!isset($allowed[$mime])) {
        $error = "Only JPG, PNG, or WEBP screenshots are allowed.";
      } else {
        $filename = "trade_" . $id . "_" . time() . "." . $allowed[$mime];
        $targetFs = $uploadDir . $filename;
        $targetDb = $uploadRelative . $filename;

        if (!move_uploaded_file($_FILES['screenshot']['tmp_name'], $targetFs)) {
          $error = "Unable to save uploaded screenshot.";
        } else {
          if (!empty($trade['screenshot_path'])) {
            $oldPath = __DIR__ . "/" . $trade['screenshot_path'];
            if (is_file($oldPath)) {
              @unlink($oldPath);
            }
          }
          $screenshot_path = $targetDb;
        }
      }
    }
  }

  if (!$error) {
    $upd = $conn->prepare("
      UPDATE trades
      SET symbol=?, market=?, direction=?, session=?,
          entry_time=?, entry_price=?, stop_loss=?, take_profit=?,
          position_size=?, risk_amount=?, setup=?, tags=?, notes_pre=?,
          screenshot_path=?
      WHERE id=? AND user_id=?
    ");
    $upd->bind_param(
      "sssssdddddssssii",
      $symbol, $market, $direction, $session,
      $entry_time, $entry_price, $stop_loss, $take_profit,
      $position_size, $risk_amount, $setup, $legacy_tags, $notes_pre,
      $screenshot_path,
      $id, $user_id
    );
    $upd->execute();

    set_trade_strategy($conn, $id, $user_id, $strategy_name);

    $ok = "Trade updated.";

    $stmt = $conn->prepare("SELECT * FROM trades WHERE id=? AND user_id=? LIMIT 1");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $trade = $stmt->get_result()->fetch_assoc();

    $cs->execute();
    $cur = $cs->get_result()->fetch_assoc();
    $curStr = $cur ? (string)$cur['name'] : "";
  }
}

require_once __DIR__ . "/partials/app_header.php";
?>

<style>
.tradeedit-wrap{ display:grid; gap:14px; }

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

.edit-shell{
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
  grid-template-columns:repeat(4,minmax(0,1fr));
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
.field select{
  min-height:44px;
}
.field textarea{
  min-height:170px;
  resize:vertical;
}
.field-help{
  color:var(--muted);
  font-size:11px;
  font-weight:700;
  line-height:1.5;
}

.span-2{ grid-column:span 2; }
.span-4{ grid-column:1 / -1; }

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

.screenshot-preview{
  display:block;
  width:100%;
  max-width:100%;
  border-radius:16px;
  border:1px solid var(--border);
}
.checkbox-row{
  display:flex;
  align-items:center;
  gap:8px;
  flex-wrap:wrap;
  margin-top:8px;
  color:var(--muted);
  font-size:12px;
  font-weight:700;
}

@media (max-width: 1100px){
  .edit-shell{ grid-template-columns:1fr; }
}
@media (max-width: 820px){
  .form-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); }
}
@media (max-width: 600px){
  .form-grid{ grid-template-columns:1fr; }
  .span-2{ grid-column:auto; }
}
</style>

<div class="tradeedit-wrap">

  <div class="page-head">
    <div>
      <h1>Edit Trade</h1>
      <p>Update execution details, improve record quality, and keep the journal accurate.</p>
    </div>

    <div class="page-head-actions">
      <a class="btn secondary" href="/trading-journal/log_view.php?id=<?= (int)$trade['id'] ?>">Back to Trade</a>
    </div>
  </div>

  <div class="edit-shell">

    <div class="form-panel">
      <h3 class="panel-title">Trade Details</h3>
      <div class="panel-sub">Edit the core trade information, strategy, notes, and screenshot.</div>

      <?php if ($error): ?>
        <div class="alert err"><?= e($error) ?></div>
      <?php endif; ?>

      <?php if ($ok): ?>
        <div class="alert ok"><?= e($ok) ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data">
        <div class="form-grid">

          <div class="field">
            <label for="symbol">Symbol</label>
            <input id="symbol" name="symbol" value="<?= e($trade['symbol']) ?>" required>
          </div>

          <div class="field">
            <label for="market">Market</label>
            <select id="market" name="market">
              <?php foreach (["FX","Crypto","Synthetics","Stocks","Indices","Commodities"] as $m): ?>
                <option value="<?= e($m) ?>" <?= ($trade['market'] === $m ? 'selected' : '') ?>><?= e($m) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label for="direction">Direction</label>
            <select id="direction" name="direction">
              <option value="BUY" <?= $trade['direction'] === 'BUY' ? 'selected' : '' ?>>BUY</option>
              <option value="SELL" <?= $trade['direction'] === 'SELL' ? 'selected' : '' ?>>SELL</option>
            </select>
          </div>

          <div class="field">
            <label for="session">Session</label>
            <select id="session" name="session">
              <option value="" <?= empty($trade['session']) ? 'selected' : '' ?>>(optional)</option>
              <?php foreach (["Asia","London","NY"] as $s): ?>
                <option value="<?= e($s) ?>" <?= ($trade['session'] === $s ? 'selected' : '') ?>><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field span-2">
            <label for="entry_time">Entry Time</label>
            <input id="entry_time" name="entry_time" type="datetime-local" value="<?= e(mysql_to_dtlocal($trade['entry_time'])) ?>" required>
          </div>

          <div class="field">
            <label for="entry_price">Entry Price</label>
            <input id="entry_price" name="entry_price" type="number" step="0.00000001" value="<?= e((string)$trade['entry_price']) ?>" required>
          </div>

          <div class="field">
            <label for="stop_loss">Stop Loss</label>
            <input id="stop_loss" name="stop_loss" type="number" step="0.00000001" value="<?= e((string)$trade['stop_loss']) ?>" required>
          </div>

          <div class="field">
            <label for="take_profit">Take Profit</label>
            <input id="take_profit" name="take_profit" type="number" step="0.00000001" value="<?= $trade['take_profit'] === null ? '' : e((string)$trade['take_profit']) ?>" placeholder="optional">
          </div>

          <div class="field">
            <label for="risk_amount">Risk Amount (1R)</label>
            <input id="risk_amount" name="risk_amount" type="number" step="0.01" value="<?= e((string)$trade['risk_amount']) ?>" required>
            <div class="field-help">Keep this accurate so analytics and R calculations stay meaningful.</div>
          </div>

          <div class="field">
            <label for="position_size">Position Size</label>
            <input id="position_size" name="position_size" type="number" step="0.00000001" value="<?= $trade['position_size'] === null ? '' : e((string)$trade['position_size']) ?>" placeholder="optional">
          </div>

          <div class="field">
            <label for="setup">Setup</label>
            <input id="setup" name="setup" value="<?= e($trade['setup'] ?? '') ?>">
          </div>

          <div class="field span-2">
            <label for="strategy_existing">Strategy</label>
            <select id="strategy_existing" name="strategy_existing">
              <option value="">(none)</option>
              <?php foreach ($tagRows as $t): ?>
                <option value="<?= e($t['name']) ?>" <?= ($curStr === $t['name']) ? 'selected' : '' ?>><?= e($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field span-2">
            <label for="strategy_new">Or Create Strategy</label>
            <input id="strategy_new" name="strategy_new" placeholder="e.g. Sweep + MSS">
          </div>

          <div class="field span-4">
            <label for="legacy_tags">Legacy Tags</label>
            <input id="legacy_tags" name="legacy_tags" value="<?= e($trade['tags'] ?? '') ?>" placeholder="free text (temporary)">
          </div>

          <div class="field span-4">
            <label for="notes_pre">Pre-Trade Plan</label>
            <textarea id="notes_pre" name="notes_pre"><?= e($trade['notes_pre'] ?? '') ?></textarea>
          </div>

          <div class="field span-4">
            <label for="screenshot">Trade Screenshot</label>

            <?php if (!empty($trade['screenshot_path'])): ?>
              <img class="screenshot-preview" src="/trading-journal/<?= e($trade['screenshot_path']) ?>" alt="Trade screenshot">
              <label class="checkbox-row">
                <input type="checkbox" name="delete_screenshot" value="1">
                Delete current screenshot
              </label>
            <?php endif; ?>

            <input id="screenshot" name="screenshot" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            <div class="field-help">Allowed formats: JPG, PNG, WEBP.</div>
          </div>

        </div>

        <div class="actions">
          <button class="btn" type="submit">Save Changes</button>
          <a class="btn secondary" href="/trading-journal/log_view.php?id=<?= (int)$trade['id'] ?>">Cancel</a>
        </div>
      </form>
    </div>

    <div class="helper-panel">
      <h3 class="panel-title">Edit Guide</h3>
      <div class="panel-sub">Use edits to improve journal accuracy, not to rewrite history unrealistically.</div>

      <div class="helper-list">
        <div class="helper-item">
          <div class="helper-item-title">Correct Data Quality</div>
          <div class="helper-item-text">Fix missing or inaccurate entry details so your analytics, reviews, and reporting remain reliable.</div>
        </div>

        <div class="helper-item">
          <div class="helper-item-title">Keep Strategy Naming Consistent</div>
          <div class="helper-item-text">Use one clean strategy label for similar setups. This makes strategy-level analytics far more useful later.</div>
        </div>

        <div class="helper-item">
          <div class="helper-item-title">Use Screenshots Well</div>
          <div class="helper-item-text">Upload a clean chart screenshot that clearly shows the setup, entry context, and important levels.</div>
        </div>
      </div>

      <div class="status-box">
        <div class="status-title">Current Strategy</div>
        <div class="status-value"><?= $curStr ? e($curStr) : "No strategy assigned yet." ?></div>
      </div>

      <div class="status-box">
        <div class="status-title">Screenshot Status</div>
        <div class="status-value"><?= !empty($trade['screenshot_path']) ? "Screenshot attached." : "No screenshot attached yet." ?></div>
      </div>
    </div>

  </div>

</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>