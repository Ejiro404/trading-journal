<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "log";
$pageTitle = "New Trade • NXLOG Analytics";

$user_id = (int)$_SESSION['user_id'];
$error = "";

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
$ts = $conn->prepare("SELECT id, name FROM trade_tags WHERE user_id=? AND tag_type='strategy' ORDER BY name ASC");
$ts->bind_param("i", $user_id);
$ts->execute();
$tagRows = $ts->get_result()->fetch_all(MYSQLI_ASSOC);

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

  // Strategy tag (select OR create)
  $strategy_existing = trim($_POST['strategy_existing'] ?? '');
  $strategy_new = trim($_POST['strategy_new'] ?? '');
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

    // Map strategy tag if provided
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

<div class="card">
  <h2>New Trade</h2>
  <p class="small">Log with clarity. Risk first.</p>

  <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>

  <form method="post">
    <div class="row">
      <div class="col">
        <label>Symbol</label>
        <input name="symbol" placeholder="EURUSD, XAUUSD..." required>
      </div>
      <div class="col">
        <label>Market</label>
        <select name="market">
          <?php foreach (["FX","Crypto","Synthetics","Stocks","Indices","Commodities"] as $m): ?>
            <option><?= e($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col">
        <label>Direction</label>
        <select name="direction">
          <option value="BUY">BUY</option>
          <option value="SELL">SELL</option>
        </select>
      </div>
      <div class="col">
        <label>Session</label>
        <select name="session">
          <option value="">(optional)</option>
          <?php foreach (["Asia","London","NY"] as $s): ?>
            <option value="<?= e($s) ?>"><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="row">
      <div class="col">
        <label>Entry time</label>
        <input name="entry_time" type="datetime-local" required>
      </div>
      <div class="col">
        <label>Entry price</label>
        <input name="entry_price" type="number" step="0.00000001" required>
      </div>
      <div class="col">
        <label>Stop loss</label>
        <input name="stop_loss" type="number" step="0.00000001" required>
      </div>
      <div class="col">
        <label>Take profit (optional)</label>
        <input name="take_profit" type="number" step="0.00000001">
      </div>
    </div>

    <div class="row">
      <div class="col">
        <label>Risk amount (1R)</label>
        <input name="risk_amount" type="number" step="0.01" required>
      </div>
      <div class="col">
        <label>Position size (optional)</label>
        <input name="position_size" type="number" step="0.00000001">
      </div>
      <div class="col">
        <label>Setup (optional)</label>
        <input name="setup">
      </div>
    </div>

    <div class="row">
      <div class="col">
        <label>Strategy (select)</label>
        <select name="strategy_existing">
          <option value="">(none)</option>
          <?php foreach ($tagRows as $t): ?>
            <option value="<?= e($t['name']) ?>"><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="small">Choose one existing strategy tag.</div>
      </div>

      <div class="col">
        <label>Or create strategy (optional)</label>
        <input name="strategy_new" placeholder="e.g. Sweep + MSS">
        <div class="small">If filled, it will be created and used.</div>
      </div>

      <div class="col">
        <label>Legacy tags (optional)</label>
        <input name="legacy_tags" placeholder="free text (temporary)">
      </div>
    </div>

    <label>Pre-trade plan (optional)</label>
    <textarea name="notes_pre" placeholder="Conditions, bias, invalidation..."></textarea>

    <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn" type="submit">Save</button>
      <a class="btn ghost" href="/trading-journal/log.php">Back to Log</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>
