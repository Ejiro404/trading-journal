<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$user_id = (int)$_SESSION['user_id'];
$error = $ok = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
  $name = trim($_POST['name'] ?? '');
  if ($name === '') $error = "Rule name cannot be empty.";
  else {
    try {
      $stmt = $conn->prepare("INSERT INTO trade_rules (user_id, name, is_active) VALUES (?, ?, 1)");
      $stmt->bind_param("is", $user_id, $name);
      $stmt->execute();
      $ok = "Rule added.";
    } catch (Throwable $e) {
      $error = "Could not add rule (maybe it already exists).";
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
  $rule_id = (int)($_POST['rule_id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  $is_active = isset($_POST['is_active']) ? 1 : 0;

  if ($rule_id <= 0) $error = "Invalid rule.";
  elseif ($name === '') $error = "Rule name cannot be empty.";
  else {
    $stmt = $conn->prepare("UPDATE trade_rules SET name=?, is_active=? WHERE id=? AND user_id=?");
    $stmt->bind_param("siii", $name, $is_active, $rule_id, $user_id);
    $stmt->execute();
    $ok = "Rule updated.";
  }
}

$stmt = $conn->prepare("SELECT id, name, is_active, created_at FROM trade_rules WHERE user_id=? ORDER BY is_active DESC, name ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/trading-journal/assets/css/style.css">
  <title>Rules • Trading Journal</title>
  <script>
    (function(){
      const saved = localStorage.getItem("tj_theme");
      const prefersDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
      const useDark = saved ? (saved === "dark") : prefersDark;
      document.documentElement.classList.toggle("dark", useDark);
    })();
  </script>
</head>
<body>
<div class="container">
  <div class="nav">
    <a class="btn secondary" href="/trading-journal/dashboard.php">Dashboard</a>
    <a class="btn secondary" href="/trading-journal/trades.php">Trades</a>
    <a class="btn" href="/trading-journal/trade_new.php">+ Add Trade</a>
    <a class="btn secondary" href="/trading-journal/rules.php">Rules</a>
    <a class="btn secondary" href="/trading-journal/logout.php">Logout</a>
    <button class="btn secondary" type="button" id="themeBtn">🌙</button>
  </div>

  <div class="card">
    <h2>Rule Breaks Manager</h2>
    <p class="small">Add, rename, or disable rules. Disabled rules won’t show in the trade checklist.</p>
    <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
    <?php if ($ok): ?><p class="ok"><?= e($ok) ?></p><?php endif; ?>

    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="add">
      <div class="form-span-3">
        <label>New rule name</label>
        <input name="name" placeholder="e.g. Traded against HTF bias" required>
      </div>
      <div>
        <label>&nbsp;</label>
        <button class="btn" type="submit">Add rule</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>Your rules</h2>
    <table class="table">
      <thead>
        <tr>
          <th>Rule</th>
          <th>Active</th>
          <th>Created</th>
          <th>Save</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rules as $r): ?>
        <tr>
          <td style="width:55%">
            <form method="post" style="margin:0">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="rule_id" value="<?= (int)$r['id'] ?>">
              <input name="name" value="<?= e($r['name']) ?>">
          </td>
          <td>
              <label style="display:flex;gap:10px;align-items:center;margin:0">
                <input type="checkbox" name="is_active" <?= ((int)$r['is_active']===1) ? "checked" : "" ?>>
                <span class="small">Active</span>
              </label>
          </td>
          <td class="small"><?= e($r['created_at']) ?></td>
          <td>
              <button class="btn" type="submit">Save</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
  const btn = document.getElementById("themeBtn");
  function syncIcon(){
    const isDark = document.documentElement.classList.contains("dark");
    btn.textContent = isDark ? "☀️" : "🌙";
  }
  syncIcon();
  btn.addEventListener("click", () => {
    const isDark = document.documentElement.classList.toggle("dark");
    localStorage.setItem("tj_theme", isDark ? "dark" : "light");
    syncIcon();
  });
</script>
</body>
</html>
