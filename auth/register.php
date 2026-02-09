<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";

$name = $email = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = strtolower(trim($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if ($name === "" || $email === "" || $password === "") {
    $error = "All fields are required.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Invalid email address.";
  } elseif (strlen($password) < 6) {
    $error = "Password must be at least 6 characters.";
  } else {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
      $error = "Email already exists. Please login.";
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt2 = $conn->prepare("INSERT INTO users (name, email, password_hash) VALUES (?,?,?)");
      $stmt2->bind_param("sss", $name, $email, $hash);
      $stmt2->execute();

      $new_user_id = (int)$conn->insert_id;

      // Auto-create default rules for this new user (if tables exist)
      try {
        $defaults = [
          "Moved stop loss",
          "Entered without confirmation",
          "FOMO entry",
          "Revenge trade",
          "Over-leveraged / oversize",
          "Did not follow session plan",
          "Closed too early",
          "Ignored higher timeframe bias"
        ];
        $rstmt = $conn->prepare("INSERT INTO trade_rules (user_id, name, is_active) VALUES (?, ?, 1)");
        foreach ($defaults as $ruleName) {
          $rstmt->bind_param("is", $new_user_id, $ruleName);
          $rstmt->execute();
        }
      } catch (Throwable $e) {}

      header("Location: /trading-journal/auth/login.php");
      exit;
    }
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <script>
    (function(){
      const saved = localStorage.getItem("tj_theme");
      const prefersDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
      const useDark = saved ? (saved === "dark") : prefersDark;
      document.documentElement.classList.toggle("dark", useDark);
    })();
  </script>

  <link rel="stylesheet" href="/trading-journal/assets/css/style.css">
  <title>Register • Trading Journal</title>

  <style>
    .pw-wrap{ position:relative; }
    .pw-btn{
      position:absolute; right:10px; top:50%; transform:translateY(-50%);
      border:1px solid var(--border); background:var(--pill); color:var(--text);
      padding:6px 10px; border-radius:10px; cursor:pointer; font-weight:800; font-size:12px;
    }
  </style>
</head>
<body>
<div class="container">

  <div class="nav">
    <a class="btn secondary" href="/trading-journal/auth/login.php">Login</a>
    <button class="btn secondary" type="button" id="themeBtn">🌙</button>
  </div>

  <div class="card" style="max-width:560px;margin:0 auto;">
    <h2>Create account</h2>
    <p class="small">Start journaling your trades with R-first analytics.</p>

    <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>

    <form method="post">
      <div class="form-grid" style="grid-template-columns: 1fr;">
        <div>
          <label>Name</label>
          <input name="name" value="<?= e($name) ?>" placeholder="Your name" required>
        </div>

        <div>
          <label>Email</label>
          <input name="email" type="email" value="<?= e($email) ?>" placeholder="you@email.com" required>
        </div>

        <div>
          <label>Password</label>
          <div class="pw-wrap">
            <input id="password" name="password" type="password" placeholder="Min 6 characters" required>
            <button class="pw-btn" type="button" id="pwToggle">Show</button>
          </div>
          <div class="small-hint">Tip: use a password you won’t forget.</div>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn" type="submit">Create account</button>
        <span class="small">Already have an account? <a href="/trading-journal/auth/login.php"><b>Login</b></a></span>
      </div>
    </form>
  </div>
</div>

<script>
  const themeBtn = document.getElementById("themeBtn");
  function syncThemeIcon(){
    const isDark = document.documentElement.classList.contains("dark");
    themeBtn.textContent = isDark ? "☀️" : "🌙";
  }
  syncThemeIcon();
  themeBtn.addEventListener("click", () => {
    const isDark = document.documentElement.classList.toggle("dark");
    localStorage.setItem("tj_theme", isDark ? "dark" : "light");
    syncThemeIcon();
  });

  const pw = document.getElementById("password");
  const pwToggle = document.getElementById("pwToggle");
  pwToggle.addEventListener("click", () => {
    const isHidden = pw.type === "password";
    pw.type = isHidden ? "text" : "password";
    pwToggle.textContent = isHidden ? "Hide" : "Show";
    pw.focus();
  });
</script>
</body>
</html>
