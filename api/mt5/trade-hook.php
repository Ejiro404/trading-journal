<?php
// api/mt5/trade-hook.php
require_once __DIR__ . "/../../config/db.php";

header("Content-Type: application/json");

function respond(int $status, array $payload): void {
  http_response_code($status);
  echo json_encode($payload);
  exit;
}

function clean_str($value): string {
  return trim((string)$value);
}

function nullable_float($value): ?float {
  if ($value === null || $value === '') return null;
  return (float)$value;
}

function nullable_int($value): ?int {
  if ($value === null || $value === '') return null;
  return (int)$value;
}

function normalize_datetime($value): ?string {
  $value = trim((string)$value);
  if ($value === '') return null;

  $value = str_replace(".", "-", $value);
  $ts = strtotime($value);
  if (!$ts) return null;

  return date("Y-m-d H:i:s", $ts);
}

function touch_account_sync(mysqli $conn, int $mt5_account_id, int $user_id): void {
  $syncStmt = $conn->prepare("
    UPDATE mt5_accounts
    SET last_sync_at = NOW()
    WHERE id = ? AND user_id = ?
  ");

  if ($syncStmt) {
    $syncStmt->bind_param("ii", $mt5_account_id, $user_id);
    $syncStmt->execute();
  }
}

$raw = file_get_contents("php://input");
$raw = rtrim((string)$raw, "\x00 \t\n\r\0\x0B");

$data = json_decode($raw, true);

if (!is_array($data)) {
  file_put_contents(
    __DIR__ . "/mt5_debug.log",
    "[" . date("Y-m-d H:i:s") . "] JSON ERROR: " . json_last_error_msg() . PHP_EOL .
    "RAW: " . $raw . PHP_EOL . PHP_EOL,
    FILE_APPEND
  );

  respond(400, [
    "ok" => false,
    "error" => "Invalid JSON",
    "details" => json_last_error_msg()
  ]);
}

$sync_token = clean_str($data['sync_token'] ?? '');
$event = strtolower(clean_str($data['event'] ?? ''));

$ticket = clean_str($data['ticket'] ?? '');
$symbol = strtoupper(clean_str($data['symbol'] ?? ''));
$direction = strtoupper(clean_str($data['direction'] ?? ''));

if ($sync_token === '') {
  respond(401, [
    "ok" => false,
    "error" => "Missing sync token"
  ]);
}

if (!in_array($event, ['open', 'update', 'close', 'history'], true)) {
  respond(422, [
    "ok" => false,
    "error" => "Invalid event",
    "allowed" => ["open", "update", "close", "history"]
  ]);
}

if ($ticket === '' || $symbol === '' || !in_array($direction, ['BUY', 'SELL'], true)) {
  respond(422, [
    "ok" => false,
    "error" => "Missing required trade fields"
  ]);
}

$acctStmt = $conn->prepare("
  SELECT id, user_id, account_login, account_server, is_active
  FROM mt5_accounts
  WHERE sync_token = ?
  LIMIT 1
");

if (!$acctStmt) {
  respond(500, [
    "ok" => false,
    "error" => "Account prepare failed",
    "details" => $conn->error
  ]);
}

$acctStmt->bind_param("s", $sync_token);
$acctStmt->execute();
$account = $acctStmt->get_result()->fetch_assoc();

if (!$account) {
  respond(401, [
    "ok" => false,
    "error" => "Invalid sync token"
  ]);
}

if ((int)$account['is_active'] !== 1) {
  respond(403, [
    "ok" => false,
    "error" => "MT5 account is disabled"
  ]);
}

$mt5_account_id = (int)$account['id'];
$user_id = (int)$account['user_id'];
$account_login = (int)$account['account_login'];

$mt5_login = nullable_int($data['mt5_login'] ?? $data['account_login'] ?? $account_login);
$mt5_ticket = $ticket;
$mt5_position_id = nullable_int($data['position_id'] ?? $data['mt5_position_id'] ?? null);
$mt5_deal_id = nullable_int($data['deal_id'] ?? $data['mt5_deal_id'] ?? null);

$entry_time = normalize_datetime($data['entry_time'] ?? null);
$exit_time = normalize_datetime($data['exit_time'] ?? null);

$entry_price = nullable_float($data['entry_price'] ?? null);
$exit_price = nullable_float($data['exit_price'] ?? null);

$stop_loss = nullable_float($data['stop_loss'] ?? $data['sl'] ?? null);
$take_profit = nullable_float($data['take_profit'] ?? $data['tp'] ?? null);
$position_size = nullable_float($data['volume'] ?? $data['lot_size'] ?? $data['position_size'] ?? null);
$pnl_amount = nullable_float($data['pnl_amount'] ?? $data['profit'] ?? null);

$source = "mt5";
$external_id = $mt5_ticket;
$sync_status = $event;

if ($entry_time === null) {
  $entry_time = date("Y-m-d H:i:s");
}

$outcome = null;
if ($pnl_amount !== null) {
  if ($pnl_amount > 0) $outcome = "win";
  elseif ($pnl_amount < 0) $outcome = "loss";
  else $outcome = "breakeven";
}

$existing = null;

if ($mt5_deal_id !== null) {
  $findStmt = $conn->prepare("
    SELECT id, risk_amount
    FROM trades
    WHERE mt5_account_id = ? AND mt5_deal_id = ?
    LIMIT 1
  ");
  $findStmt->bind_param("ii", $mt5_account_id, $mt5_deal_id);
  $findStmt->execute();
  $existing = $findStmt->get_result()->fetch_assoc();
}

if (!$existing) {
  $findStmt = $conn->prepare("
    SELECT id, risk_amount
    FROM trades
    WHERE mt5_account_id = ? AND mt5_ticket = ?
    LIMIT 1
  ");
  $findStmt->bind_param("is", $mt5_account_id, $mt5_ticket);
  $findStmt->execute();
  $existing = $findStmt->get_result()->fetch_assoc();
}

if (!$existing && $mt5_position_id !== null) {
  $findStmt = $conn->prepare("
    SELECT id, risk_amount
    FROM trades
    WHERE mt5_account_id = ? AND mt5_position_id = ?
    LIMIT 1
  ");
  $findStmt->bind_param("ii", $mt5_account_id, $mt5_position_id);
  $findStmt->execute();
  $existing = $findStmt->get_result()->fetch_assoc();
}

if (!$existing) {
  $findStmt = $conn->prepare("
    SELECT id, risk_amount
    FROM trades
    WHERE user_id = ? AND source = 'mt5' AND external_id = ?
    LIMIT 1
  ");
  $findStmt->bind_param("is", $user_id, $external_id);
  $findStmt->execute();
  $existing = $findStmt->get_result()->fetch_assoc();
}

$trade_id = $existing ? (int)$existing['id'] : 0;

$r_multiple = null;
if ($existing && $pnl_amount !== null) {
  $risk_amount = (float)($existing['risk_amount'] ?? 0);
  if ($risk_amount > 0) {
    $r_multiple = $pnl_amount / $risk_amount;
  }
}

/**
 * HISTORY EVENT
 */
if ($event === "history") {
  if ($entry_time === null || $entry_price === null || $exit_time === null || $exit_price === null || $pnl_amount === null) {
    respond(422, [
      "ok" => false,
      "error" => "History event requires entry_time, entry_price, exit_time, exit_price, and pnl_amount/profit"
    ]);
  }

  if ($trade_id > 0) {
    $sql = "
      UPDATE trades
      SET
        symbol = ?,
        direction = ?,
        entry_time = ?,
        entry_price = ?,
        exit_time = ?,
        exit_price = ?,
        stop_loss = ?,
        take_profit = ?,
        position_size = ?,
        pnl_amount = ?,
        r_multiple = ?,
        outcome = ?,
        source = ?,
        external_id = ?,
        mt5_account_id = ?,
        mt5_login = ?,
        mt5_ticket = ?,
        mt5_position_id = COALESCE(?, mt5_position_id),
        mt5_deal_id = COALESCE(?, mt5_deal_id),
        mt5_last_event_at = NOW(),
        sync_status = ?,
        synced_at = NOW(),
        updated_at = CURRENT_TIMESTAMP
      WHERE id = ? AND user_id = ?
      LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) respond(500, ["ok" => false, "error" => "Prepare failed", "details" => $conn->error]);

    $stmt->bind_param(
      "sssdsddddddsssiisiisii",
      $symbol,
      $direction,
      $entry_time,
      $entry_price,
      $exit_time,
      $exit_price,
      $stop_loss,
      $take_profit,
      $position_size,
      $pnl_amount,
      $r_multiple,
      $outcome,
      $source,
      $external_id,
      $mt5_account_id,
      $mt5_login,
      $mt5_ticket,
      $mt5_position_id,
      $mt5_deal_id,
      $sync_status,
      $trade_id,
      $user_id
    );
  } else {
    $sql = "
      INSERT INTO trades
        (
          user_id,
          mt5_account_id,
          symbol,
          direction,
          entry_time,
          entry_price,
          exit_time,
          exit_price,
          stop_loss,
          take_profit,
          position_size,
          pnl_amount,
          r_multiple,
          outcome,
          source,
          external_id,
          mt5_login,
          mt5_ticket,
          mt5_position_id,
          mt5_deal_id,
          mt5_last_event_at,
          sync_status,
          synced_at
        )
      VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) respond(500, ["ok" => false, "error" => "Prepare failed", "details" => $conn->error]);

    $stmt->bind_param(
      "iisssdsddddddsssisiis",
      $user_id,
      $mt5_account_id,
      $symbol,
      $direction,
      $entry_time,
      $entry_price,
      $exit_time,
      $exit_price,
      $stop_loss,
      $take_profit,
      $position_size,
      $pnl_amount,
      $r_multiple,
      $outcome,
      $source,
      $external_id,
      $mt5_login,
      $mt5_ticket,
      $mt5_position_id,
      $mt5_deal_id,
      $sync_status
    );
  }

  if (!$stmt->execute()) {
    respond(500, [
      "ok" => false,
      "error" => "DB error",
      "details" => $stmt->error
    ]);
  }

  touch_account_sync($conn, $mt5_account_id, $user_id);

  respond(200, [
    "ok" => true,
    "event" => "history",
    "trade_id" => $trade_id > 0 ? $trade_id : $conn->insert_id
  ]);
}

/**
 * OPEN EVENT
 */
if ($event === "open") {
  if ($trade_id > 0) {
    $sql = "
      UPDATE trades
      SET
        symbol = ?,
        direction = ?,
        entry_time = ?,
        entry_price = ?,
        stop_loss = ?,
        take_profit = ?,
        position_size = ?,
        source = ?,
        external_id = ?,
        mt5_account_id = ?,
        mt5_login = ?,
        mt5_ticket = ?,
        mt5_position_id = COALESCE(?, mt5_position_id),
        mt5_deal_id = COALESCE(?, mt5_deal_id),
        mt5_last_event_at = NOW(),
        sync_status = ?,
        synced_at = NOW(),
        updated_at = CURRENT_TIMESTAMP
      WHERE id = ? AND user_id = ?
      LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) respond(500, ["ok" => false, "error" => "Prepare failed", "details" => $conn->error]);

    $stmt->bind_param(
      "sssddddssiisiisii",
      $symbol,
      $direction,
      $entry_time,
      $entry_price,
      $stop_loss,
      $take_profit,
      $position_size,
      $source,
      $external_id,
      $mt5_account_id,
      $mt5_login,
      $mt5_ticket,
      $mt5_position_id,
      $mt5_deal_id,
      $sync_status,
      $trade_id,
      $user_id
    );
  } else {
    $sql = "
      INSERT INTO trades
        (
          user_id,
          mt5_account_id,
          symbol,
          direction,
          entry_time,
          entry_price,
          stop_loss,
          take_profit,
          position_size,
          source,
          external_id,
          mt5_login,
          mt5_ticket,
          mt5_position_id,
          mt5_deal_id,
          mt5_last_event_at,
          sync_status,
          synced_at
        )
      VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) respond(500, ["ok" => false, "error" => "Prepare failed", "details" => $conn->error]);

    $stmt->bind_param(
      "iisssddddssisiis",
      $user_id,
      $mt5_account_id,
      $symbol,
      $direction,
      $entry_time,
      $entry_price,
      $stop_loss,
      $take_profit,
      $position_size,
      $source,
      $external_id,
      $mt5_login,
      $mt5_ticket,
      $mt5_position_id,
      $mt5_deal_id,
      $sync_status
    );
  }

  if (!$stmt->execute()) {
    respond(500, [
      "ok" => false,
      "error" => "DB error",
      "details" => $stmt->error
    ]);
  }

  touch_account_sync($conn, $mt5_account_id, $user_id);

  respond(200, [
    "ok" => true,
    "event" => "open",
    "trade_id" => $trade_id > 0 ? $trade_id : $conn->insert_id
  ]);
}

/**
 * UPDATE EVENT
 */
if ($event === "update") {
  if ($trade_id <= 0) {
    respond(404, [
      "ok" => false,
      "error" => "Trade not found for update"
    ]);
  }

  $sql = "
    UPDATE trades
    SET
      symbol = ?,
      direction = ?,
      stop_loss = ?,
      take_profit = ?,
      position_size = ?,
      mt5_position_id = COALESCE(?, mt5_position_id),
      mt5_deal_id = COALESCE(?, mt5_deal_id),
      mt5_last_event_at = NOW(),
      sync_status = ?,
      synced_at = NOW(),
      updated_at = CURRENT_TIMESTAMP
    WHERE id = ? AND user_id = ?
    LIMIT 1
  ";

  $stmt = $conn->prepare($sql);
  if (!$stmt) respond(500, ["ok" => false, "error" => "Prepare failed", "details" => $conn->error]);

  $stmt->bind_param(
    "ssdddiisii",
    $symbol,
    $direction,
    $stop_loss,
    $take_profit,
    $position_size,
    $mt5_position_id,
    $mt5_deal_id,
    $sync_status,
    $trade_id,
    $user_id
  );

  if (!$stmt->execute()) {
    respond(500, [
      "ok" => false,
      "error" => "DB error",
      "details" => $stmt->error
    ]);
  }

  touch_account_sync($conn, $mt5_account_id, $user_id);

  respond(200, [
    "ok" => true,
    "event" => "update",
    "trade_id" => $trade_id
  ]);
}

/**
 * CLOSE EVENT
 */
if ($event === "close") {
  if ($trade_id <= 0) {
    respond(404, [
      "ok" => false,
      "error" => "Trade not found for close"
    ]);
  }

  if ($exit_time === null || $exit_price === null || $pnl_amount === null) {
    respond(422, [
      "ok" => false,
      "error" => "Close event requires exit_time, exit_price, and pnl_amount/profit"
    ]);
  }

  $sql = "
    UPDATE trades
    SET
      exit_time = ?,
      exit_price = ?,
      pnl_amount = ?,
      r_multiple = ?,
      outcome = ?,
      mt5_position_id = COALESCE(?, mt5_position_id),
      mt5_deal_id = COALESCE(?, mt5_deal_id),
      mt5_last_event_at = NOW(),
      sync_status = ?,
      synced_at = NOW(),
      updated_at = CURRENT_TIMESTAMP
    WHERE id = ? AND user_id = ?
    LIMIT 1
  ";

  $stmt = $conn->prepare($sql);
  if (!$stmt) respond(500, ["ok" => false, "error" => "Prepare failed", "details" => $conn->error]);

  $stmt->bind_param(
    "sdddsiisii",
    $exit_time,
    $exit_price,
    $pnl_amount,
    $r_multiple,
    $outcome,
    $mt5_position_id,
    $mt5_deal_id,
    $sync_status,
    $trade_id,
    $user_id
  );

  if (!$stmt->execute()) {
    respond(500, [
      "ok" => false,
      "error" => "DB error",
      "details" => $stmt->error
    ]);
  }

  touch_account_sync($conn, $mt5_account_id, $user_id);

  respond(200, [
    "ok" => true,
    "event" => "close",
    "trade_id" => $trade_id
  ]);
}

respond(422, [
  "ok" => false,
  "error" => "Unhandled event"
]);