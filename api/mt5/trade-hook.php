<?php
// api/mt5/trade-hook.php
require_once __DIR__ . "/../../config/db.php";

$API_KEY = "nxlog_local_testing_2026";

$hdr = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!hash_equals($API_KEY, $hdr)) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
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

    http_response_code(400);
    echo "Invalid JSON: " . json_last_error_msg();
    exit;
}

$user_id     = (int)($data['user_id'] ?? 0);
$event       = strtolower(trim((string)($data['event'] ?? '')));
$ticket      = trim((string)($data['ticket'] ?? ''));
$symbol      = trim((string)($data['symbol'] ?? ''));
$direction   = strtoupper(trim((string)($data['direction'] ?? '')));

if ($user_id <= 0 || $ticket === '' || $symbol === '' || ($direction !== 'BUY' && $direction !== 'SELL')) {
    http_response_code(422);
    echo "Missing required fields";
    exit;
}

$entry_time  = trim((string)($data['entry_time'] ?? ''));
$entry_price = isset($data['entry_price']) && $data['entry_price'] !== '' ? (float)$data['entry_price'] : null;

$exit_time   = trim((string)($data['exit_time'] ?? ''));
$exit_price  = isset($data['exit_price']) && $data['exit_price'] !== '' ? (float)$data['exit_price'] : null;
$pnl_amount  = isset($data['pnl_amount']) && $data['pnl_amount'] !== '' ? (float)$data['pnl_amount'] : null;

$source = 'mt5';
$external_id = $ticket;

// OPEN
if ($event === 'open') {
    $sql = "
        INSERT INTO trades
            (user_id, symbol, direction, entry_time, entry_price, source, external_id)
        VALUES
            (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            symbol = VALUES(symbol),
            direction = VALUES(direction),
            entry_time = VALUES(entry_time),
            entry_price = VALUES(entry_price),
            updated_at = CURRENT_TIMESTAMP
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo "Prepare failed: " . $conn->error;
        exit;
    }

    $stmt->bind_param(
        "isssdss",
        $user_id,
        $symbol,
        $direction,
        $entry_time,
        $entry_price,
        $source,
        $external_id
    );

    if (!$stmt->execute()) {
        http_response_code(500);
        echo "DB error: " . $stmt->error;
        exit;
    }

    echo "OK";
    exit;
}

// CLOSE
if ($event === 'close') {
    if ($exit_time === '' || $exit_price === null || $pnl_amount === null) {
        http_response_code(422);
        echo "Close event requires exit_time, exit_price and pnl_amount";
        exit;
    }

    $outcome = null;
    if ($pnl_amount > 0) $outcome = 'win';
    elseif ($pnl_amount < 0) $outcome = 'loss';
    else $outcome = 'breakeven';

    $sql = "
        UPDATE trades
        SET
            exit_time = ?,
            exit_price = ?,
            pnl_amount = ?,
            outcome = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE user_id = ? AND source = ? AND external_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo "Prepare failed: " . $conn->error;
        exit;
    }

    $stmt->bind_param(
        "sddsiss",
        $exit_time,
        $exit_price,
        $pnl_amount,
        $outcome,
        $user_id,
        $source,
        $external_id
    );

    if (!$stmt->execute()) {
        http_response_code(500);
        echo "DB error: " . $stmt->error;
        exit;
    }

    if ($stmt->affected_rows < 1) {
        http_response_code(404);
        echo "Trade not found for close event";
        exit;
    }

    echo "OK";
    exit;
}

http_response_code(422);
echo "Invalid event";