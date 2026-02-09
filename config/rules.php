<?php
declare(strict_types=1);

function get_active_rules(mysqli $conn, int $user_id): array {
  $stmt = $conn->prepare("SELECT id, name FROM trade_rules WHERE user_id=? AND is_active=1 ORDER BY name");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function get_trade_rule_ids(mysqli $conn, int $trade_id): array {
  $stmt = $conn->prepare("SELECT rule_id FROM trade_rule_breaks WHERE trade_id=?");
  $stmt->bind_param("i", $trade_id);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  return array_map(fn($r) => (int)$r['rule_id'], $rows);
}

function set_trade_rule_ids(mysqli $conn, int $trade_id, array $rule_ids): void {
  $del = $conn->prepare("DELETE FROM trade_rule_breaks WHERE trade_id=?");
  $del->bind_param("i", $trade_id);
  $del->execute();

  if (!$rule_ids) return;

  $ins = $conn->prepare("INSERT INTO trade_rule_breaks (trade_id, rule_id) VALUES (?, ?)");
  foreach ($rule_ids as $rid) {
    $rid = (int)$rid;
    $ins->bind_param("ii", $trade_id, $rid);
    $ins->execute();
  }
}
