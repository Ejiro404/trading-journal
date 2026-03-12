<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "log";
$pageTitle = "New Trade • NXLOG Analytics";

$user_id = (int)($_SESSION['user_id'] ?? 0);
$error = "";

if (!function_exists('e')) {
  function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
  }
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

function map_trade_tag(mysqli $conn, int $trade_id, int $tag_id): void {
  if ($tag_id <= 0) return;
  $m = $conn->prepare("INSERT IGNORE INTO trade_tag_map (trade_id, tag_id) VALUES (?,?)");
  $m->bind_param("ii", $trade_id, $tag_id);
  $m->execute();
}

/** Load existing strategy tags for dropdown */
$tagRows = [];
$ts = $conn->prepare("SELECT name FROM trade_tags WHERE user_id=? AND tag_type='strategy' ORDER BY name ASC");
$ts->bind_param("i", $user_id);
$ts->execute();
$tagRows = $ts->get_result()->fetch_all(MYSQLI_ASSOC);

/** Auto suggest data */
$suggestMap = [];
$suggestSym = [];

$rec = $conn->prepare("
  SELECT t.symbol, t.session, tt.name AS strategy, t.entry_time
  FROM trades t
  INNER JOIN trade_tag_map tm ON tm.trade_id = t.id
  INNER JOIN trade_tags tt ON tt.id = tm.tag_id
  WHERE t.user_id = ?
    AND tt.user_id = ?
    AND tt.tag_type = 'strategy'
  ORDER BY t.entry_time DESC
  LIMIT 200
");
$rec->bind_param("ii", $user_id, $user_id);
$rec->execute();
$recent = $rec->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($recent as $r) {
  $sym = strtoupper(trim((string)$r['symbol']));
  $ses = trim((string)$r['session']);
  $str = (string)$r['strategy'];

  if ($sym === "" || $str === "") continue;

  $k1 = $sym . "|" . $ses;
  if (!isset($suggestMap[$k1])) $suggestMap[$k1] = $str;
  if (!isset($suggestSym[$sym])) $suggestSym[$sym] = $str;
}

/** Defaults / sticky form values */
$symbol = strtoupper(trim($_POST['symbol'] ?? ''));
$market = trim($_POST['market'] ?? 'FX');
$direction = (($_POST['direction'] ?? 'BUY') === 'SELL') ? 'SELL' : 'BUY';
$session = trim($_POST['session'] ?? '');
$entry_time_input = trim($_POST['entry_time'] ?? '');
$entry_price_input = trim($_POST['entry_price'] ?? '');
$stop_loss_input = trim($_POST['stop_loss'] ?? '');
$take_profit_input = trim($_POST['take_profit'] ?? '');
$position_size_input = trim($_POST['position_size'] ?? '');
$risk_amount_input = trim($_POST['risk_amount'] ?? '');
$setup = trim($_POST['setup'] ?? '');
$legacy_tags = trim($_POST['legacy_tags'] ?? '');
$notes_pre = trim($_POST['notes_pre'] ?? '');
$strategy_existing = trim($_POST['strategy_existing'] ?? '');
$strategy_new = trim($_POST['strategy_new'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $entry_time = dtlocal_to_mysql($entry_time_input);
  $entry_price = (float)($entry_price_input !== '' ? $entry_price_input : 0);
  $stop_loss = (float)($stop_loss_input !== '' ? $stop_loss_input : 0);

  $take_profit = ($take_profit_input === '') ? null : (float)$take_profit_input;
  $position_size = ($position_size_input === '') ? null : (float)$position_size_input;
  $risk_amount = (float)($risk_amount_input !== '' ? $risk_amount_input : 0);

  $strategy_name = $strategy_new !== "" ? $strategy_new : $strategy_existing;

  if ($symbol === "" || $entry_time === "" || $entry_price == 0 || $stop_loss == 0 || $risk_amount <= 0) {
    $error = "Symbol, entry time, entry price, stop loss and risk amount are required.";
  } else {
    $sql = "
      INSERT INTO trades
      (user_id, symbol, market, direction, session,
       entry_time, entry_price, stop_loss, take_profit,
       position_size, risk_amount,
       setup, tags, notes_pre, is_reviewed)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
      "isssssdddddsss",
      $user_id, $symbol, $market, $direction, $session,
      $entry_time, $entry_price, $stop_loss,
      $take_profit, $position_size, $risk_amount,
      $setup, $legacy_tags, $notes_pre
    );
    $stmt->execute();

    $trade_id = (int)$conn->insert_id;

    if ($strategy_name !== "") {
      $tag_id = get_or_create_tag($conn, $user_id, "strategy", $strategy_name);
      map_trade_tag($conn, $trade_id, $tag_id);
    }

    header("Location: /trading-journal/log_view.php?id=" . $trade_id);
    exit;
  }
}

require_once __DIR__ . "/partials/app_header.php";
?>

<style>
.newtrade-wrap{ display:grid; gap:14px; }

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

.form-shell{
  display:grid;
  grid-template-columns: 1.1fr .9fr;
  gap:14px;
}

.form-panel,
.helper-panel,
.notes-panel{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:18px;
  box-shadow:var(--shadow);
}

.form-panel,
.helper-panel,
.notes-panel{
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
  min-height:160px;
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

.suggest-box{
  margin-top:12px;
  border:1px dashed var(--border);
  background:var(--pill);
  border-radius:14px;
  padding:12px 14px;
}
.suggest-title{
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:.03em;
  color:var(--muted);
  font-weight:900;
  margin-bottom:6px;
}
#autoHint{
  color:var(--text);
  font-size:13px;
  font-weight:700;
}

@media (max-width: 1100px){
  .form-shell{ grid-template-columns:1fr; }
}
@media (max-width: 820px){
  .form-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); }
}
@media (max-width: 600px){
  .form-grid{ grid-template-columns:1fr; }
  .span-2{ grid-column:auto; }
}
</style>

<div class="newtrade-wrap">

  <div class="page-head">
    <div>
      <h1>New Trade</h1>
      <p>Log with clarity. Risk first. Keep the record structured from entry.</p>
    </div>

    <div class="page-head-actions">
      <a class="btn secondary" href="/trading-journal/log.php">Back to Log</a>
    </div>
  </div>

  <div class="form-shell">

    <div class="form-panel">
      <h3 class="panel-title">Trade Entry</h3>
      <div class="panel-sub">Capture the setup, execution details, and pre-trade context cleanly.</div>

      <?php if ($error): ?>
        <div class="alert err"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" id="newTradeForm">
        <div class="form-grid">

          <div class="field">
            <label for="symInput">Symbol</label>
            <input id="symInput" name="symbol" value="<?= e($symbol) ?>" placeholder="EURUSD, XAUUSD..." required>
          </div>

          <div class="field">
            <label for="market">Market</label>
            <select id="market" name="market">
              <?php foreach (["FX","Crypto","Synthetics","Stocks","Indices","Commodities"] as $m): ?>
                <option value="<?= e($m) ?>" <?= $market === $m ? 'selected' : '' ?>><?= e($m) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label for="direction">Direction</label>
            <select id="direction" name="direction">
              <option value="BUY" <?= $direction === 'BUY' ? 'selected' : '' ?>>BUY</option>
              <option value="SELL" <?= $direction === 'SELL' ? 'selected' : '' ?>>SELL</option>
            </select>
          </div>

          <div class="field">
            <label for="sessionSel">Session</label>
            <select id="sessionSel" name="session">
              <option value="">(optional)</option>
              <?php foreach (["Asia","London","NY"] as $s): ?>
                <option value="<?= e($s) ?>" <?= $session === $s ? 'selected' : '' ?>><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field span-2">
            <label for="entry_time">Entry Time</label>
            <input id="entry_time" name="entry_time" type="datetime-local" value="<?= e($entry_time_input) ?>" required>
          </div>

          <div class="field">
            <label for="entry_price">Entry Price</label>
            <input id="entry_price" name="entry_price" type="number" step="0.00000001" value="<?= e($entry_price_input) ?>" required>
          </div>

          <div class="field">
            <label for="stop_loss">Stop Loss</label>
            <input id="stop_loss" name="stop_loss" type="number" step="0.00000001" value="<?= e($stop_loss_input) ?>" required>
          </div>

          <div class="field">
            <label for="take_profit">Take Profit</label>
            <input id="take_profit" name="take_profit" type="number" step="0.00000001" value="<?= e($take_profit_input) ?>" placeholder="optional">
          </div>

          <div class="field">
            <label for="risk_amount">Risk Amount (1R)</label>
            <input id="risk_amount" name="risk_amount" type="number" step="0.01" value="<?= e($risk_amount_input) ?>" required>
            <div class="field-help">The monetary amount you are willing to lose if invalidated.</div>
          </div>

          <div class="field">
            <label for="position_size">Position Size</label>
            <input id="position_size" name="position_size" type="number" step="0.00000001" value="<?= e($position_size_input) ?>" placeholder="optional">
          </div>

          <div class="field">
            <label for="setup">Setup</label>
            <input id="setup" name="setup" value="<?= e($setup) ?>" placeholder="optional">
          </div>

          <div class="field span-2">
            <label for="strategySel">Strategy (select)</label>
            <select id="strategySel" name="strategy_existing">
              <option value="">(none)</option>
              <?php foreach ($tagRows as $t): ?>
                <?php $nm = (string)$t['name']; ?>
                <option value="<?= e($nm) ?>" <?= $strategy_existing === $nm ? 'selected' : '' ?>><?= e($nm) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field span-2">
            <label for="strategyNew">Or Create Strategy</label>
            <input id="strategyNew" name="strategy_new" value="<?= e($strategy_new) ?>" placeholder="e.g. Sweep + MSS">
          </div>

          <div class="field span-4">
            <label for="legacy_tags">Legacy Tags</label>
            <input id="legacy_tags" name="legacy_tags" value="<?= e($legacy_tags) ?>" placeholder="free text (temporary)">
          </div>

          <div class="field span-4">
            <label for="notes_pre">Pre-Trade Plan</label>
            <textarea id="notes_pre" name="notes_pre" placeholder="Conditions, bias, invalidation..."><?= e($notes_pre) ?></textarea>
          </div>

        </div>

        <div class="actions">
          <button class="btn" type="submit">Save Trade</button>
          <a class="btn secondary" href="/trading-journal/log.php">Cancel</a>
        </div>
      </form>
    </div>

    <div style="display:grid; gap:14px;">
      <div class="helper-panel">
        <h3 class="panel-title">Entry Guide</h3>
        <div class="panel-sub">A clean record now makes review and analytics more useful later.</div>

        <div class="helper-list">
          <div class="helper-item">
            <div class="helper-item-title">Risk First</div>
            <div class="helper-item-text">Enter risk amount carefully. This becomes the base for your R tracking and later review quality.</div>
          </div>

          <div class="helper-item">
            <div class="helper-item-title">Strategy Consistency</div>
            <div class="helper-item-text">Use an existing strategy tag where possible so your analytics remain clean and comparable.</div>
          </div>

          <div class="helper-item">
            <div class="helper-item-title">Pre-Trade Notes</div>
            <div class="helper-item-text">Write what you saw before entry — bias, confirmation, invalidation, and execution plan.</div>
          </div>
        </div>

        <div class="suggest-box">
          <div class="suggest-title">Auto Suggest</div>
          <div id="autoHint">Auto-suggest will appear when possible.</div>
        </div>
      </div>

      <div class="notes-panel">
        <h3 class="panel-title">What Good Logging Looks Like</h3>
        <div class="panel-sub">Useful logs are specific, structured, and easy to review later.</div>

        <div class="helper-list">
          <div class="helper-item">
            <div class="helper-item-title">Good Example</div>
            <div class="helper-item-text">“London session sweep into bullish MSS. Entry taken after confirmation candle. Invalid below sweep low.”</div>
          </div>

          <div class="helper-item">
            <div class="helper-item-title">Avoid</div>
            <div class="helper-item-text">“I just felt like price was going up.”</div>
          </div>
        </div>
      </div>
    </div>

  </div>

</div>

<script>
  const suggestBySymSession = <?= json_encode($suggestMap) ?>;
  const suggestBySym = <?= json_encode($suggestSym) ?>;

  const symInput = document.getElementById("symInput");
  const sessionSel = document.getElementById("sessionSel");
  const strategySel = document.getElementById("strategySel");
  const strategyNew = document.getElementById("strategyNew");
  const autoHint = document.getElementById("autoHint");

  function suggest(){
    if (strategyNew.value.trim() !== "") {
      autoHint.textContent = "Manual strategy entry active.";
      return;
    }

    const sym = (symInput.value || "").trim().toUpperCase();
    const ses = (sessionSel.value || "").trim();

    if (!sym) {
      autoHint.textContent = "Auto-suggest will appear when possible.";
      return;
    }

    let picked = "";
    const k = sym + "|" + ses;
    if (suggestBySymSession[k]) picked = suggestBySymSession[k];
    else if (suggestBySym[sym]) picked = suggestBySym[sym];

    if (!picked) {
      autoHint.textContent = "No suggestion yet for this symbol/session.";
      return;
    }

    const options = Array.from(strategySel.options).map(o => o.value);
    if (options.includes(picked)) {
      strategySel.value = picked;
      autoHint.textContent = "Suggested strategy: " + picked;
    } else {
      autoHint.textContent = "Suggested strategy exists but is not in the dropdown yet: " + picked;
    }
  }

  symInput.addEventListener("input", suggest);
  sessionSel.addEventListener("change", suggest);
  strategyNew.addEventListener("input", () => {
    if (strategyNew.value.trim() !== "") autoHint.textContent = "Manual strategy entry active.";
    else suggest();
  });
</script>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>