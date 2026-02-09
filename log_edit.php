<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "log";
$pageTitle = "Edit Trade • NXLOG Analytics";

$user_id = (int)$_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);

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
  // Remove existing strategy mappings for this trade (only strategy type)
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
if (!$trade) { http_response_code(404); exit("Trade not found."); }

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

$error = $ok = "";

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

  if ($symbol === "" || $entry_time === "" || $entry_price == 0 || $stop_loss == 0 || $risk_amount <= 0) {
    $error = "Symbol, entry time, entry price, stop loss and risk amount are required.";
  } else {
    if ($take_profit === null) {
      $upd = $conn->prepare("
        UPDATE trades
        SET symbol=?, market=?, direction=?, session=?,
            entry_time=?, entry_price=?, stop_loss=?, take_profit=NULL,
            position_size=?, risk_amount=?, setup=?, tags=?, notes_pre=?
        WHERE id=? AND user_id=?
      ");
      $upd->bind_param(
        "sssssdddddsssii",
        $symbol, $market, $direction, $session,
        $entry_time, $entry_price, $stop_loss,
        $position_size, $risk_amount, $setup, $legacy_tags, $notes_pre,
        $id, $user_id
      );
    } else {
      $upd = $conn->prepare("
        UPDATE trades
        SET symbol=?, market=?, direction=?, session=?,
            entry_time=?, entry_price=?, stop_loss=?, take_profit=?,
            position_size=?, risk_amount=?, setup=?, tags=?, notes_pre=?
        WHERE id=? AND user_id=?
      ");
      $upd->bind_param(
        "sssssddddddsssii",
        $symbol, $market, $direction, $session,
        $entry_time, $entry_price, $stop_loss, $take_profit,
        $position_size, $risk_amount, $setup, $legacy_tags, $notes_pre,
        $id, $user_id
      );
    }

    $upd->execute();
    set_trade_strategy($conn, $id, $user_id, $strategy_name);

    $ok = "Trade updated.";

    // refresh
    $stmt = $conn->prepare("SELECT * FROM trades WHERE id=? AND user_id=? LIMIT 1");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $trade = $stmt->get_result()->fetch_assoc();

    // refresh current strategy
    $cs->execute();
    $cur = $cs->get_result()->fetch_assoc();
    $curStr = $cur ? (string)$cur['name'] : "";
  }
}

require_once __DIR__ . "/partials/app_header.php";
?>

<div class="card">
  <h2>Edit Trade</h2>

  <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="ok"><?= e($ok) ?></p><?php endif; ?>

  <form method="post">
    <div class="row">
      <div class="col">
        <label>Symbol</label>
        <input name="symbol" value="<?= e($trade['symbol']) ?>" required>
      </div>
      <div class="col">
        <label>Market</label>
        <select name="market">
          <?php foreach (["FX","Crypto","Synthetics","Stocks","Indices","Commodities"] as $m): ?>
            <option <?= ($trade['market']===$m?'selected':'') ?>><?= e($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col">
        <label>Direction</label>
        <select name="direction">
          <option value="BUY" <?= $trade['direction']==='BUY'?'selected':'' ?>>BUY</option>
          <option value="SELL" <?= $trade['direction']==='SELL'?'selected':'' ?>>SELL</option>
        </select>
      </div>
      <div class="col">
        <label>Session</label>
        <select name="session">
          <option value="" <?= empty($trade['session'])?'selected':'' ?>>(optional)</option>
          <?php foreach (["Asia","London","NY"] as $s): ?>
            <option value="<?= e($s) ?>" <?= ($trade['session']===$s)?'selected':'' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="row">
      <div class="col">
        <label>Entry time</label>
        <input name="entry_time" type="datetime-local" value="<?= e(mysql_to_dtlocal($trade['entry_time'])) ?>" required>
      </div>
      <div class="col">
        <label>Entry price</label>
        <input name="entry_price" type="number" step="0.00000001" value="<?= e((string)$trade['entry_price']) ?>" required>
      </div>
      <div class="col">
        <label>Stop loss</label>
        <input name="stop_loss" type="number" step="0.00000001" value="<?= e((string)$trade['stop_loss']) ?>" required>
      </div>
      <div class="col">
        <label>Take profit (optional)</label>
        <input name="take_profit" type="number" step="0.00000001" value="<?= $trade['take_profit']===null?'':e((string)$trade['take_profit']) ?>">
      </div>
    </div>

    <div class="row">
      <div class="col">
        <label>Risk amount (1R)</label>
        <input name="risk_amount" type="number" step="0.01" value="<?= e((string)$trade['risk_amount']) ?>" required>
      </div>
      <div class="col">
        <label>Position size (optional)</label>
        <input name="position_size" type="number" step="0.00000001" value="<?= $trade['position_size']===null?'':e((string)$trade['position_size']) ?>">
      </div>
      <div class="col">
        <label>Setup</label>
        <input name="setup" value="<?= e($trade['setup'] ?? '') ?>">
      </div>
    </div>

    <div class="row">
      <div class="col">
        <label>Strategy (current: <?= $curStr ? e($curStr) : "none" ?>)</label>
        <select name="strategy_existing">
          <option value="">(none)</option>
          <?php foreach ($tagRows as $t): ?>
            <option value="<?= e($t['name']) ?>" <?= ($curStr === $t['name'])?'selected':'' ?>><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col">
        <label>Or create strategy (optional)</label>
        <input name="strategy_new" placeholder="e.g. Sweep + MSS">
      </div>

      <div class="col">
        <label>Legacy tags (optional)</label>
        <input name="legacy_tags" value="<?= e($trade['tags'] ?? '') ?>">
      </div>
    </div>

    <label>Pre-trade plan</label>
    <textarea name="notes_pre"><?= e($trade['notes_pre'] ?? '') ?></textarea>

    <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn" type="submit">Save changes</button>
      <a class="btn ghost" href="/trading-journal/log_view.php?id=<?= (int)$trade['id'] ?>">Back</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>
