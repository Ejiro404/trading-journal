<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";

$email = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = strtolower(trim($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if ($email === "" || $password === "") {
    $error = "Email and password are required.";
  } else {
    $stmt = $conn->prepare("SELECT id, name, password_hash FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || !password_verify($password, $user['password_hash'])) {
      $error = "Invalid login details.";
    } else {
      $_SESSION['user_id'] = (int)$user['id'];
      $_SESSION['user_name'] = $user['name'];
      header("Location: /trading-journal/dashboard.php");
      exit;
    }
  }
}

/** Brand */
$appName = "NXLOG Analytics"; 
$appBadge = "BETA";
$year = date("Y");

/** Social links */
$social = [
  "instagram" => "https://www.instagram.com/nxloganalytics",
  "x"         => "#",
  "threads"   => "#",
  "discord"   => "https://discord.gg/tyNntXG",
];
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/trading-journal/assets/css/style.css">
  <link rel="stylesheet" href="/trading-journal/assets/css/auth.css">
  <title>Login • <?= htmlspecialchars($appName) ?></title>
  
  <script>
    (function () {
      const saved = localStorage.getItem("nx_theme");
      const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
      const dark = saved ? saved === "dark" : prefersDark;
      document.documentElement.classList.toggle("dark", dark);
    })();
  </script>

  <style>
    
    .theme-toggle {
      position: absolute;
      top: 16px;
      right: 16px;
      border: 1px solid var(--border);
      background: var(--pill);
      border-radius: 999px;
      padding: 8px 10px;
      cursor: pointer;
      font-weight: 700;
      line-height: 1;
    }
  </style>
</head>


<body class="auth-bg">

  <div class="auth-top">
    <div class="auth-brand">
      <div class="auth-logo">TJ</div>
      <?php if ($appBadge): ?><span class="auth-badge"><?= htmlspecialchars($appBadge) ?></span><?php endif; ?>
    </div>
  </div>

  <main class="auth-wrap">
    <div class="auth-card">

    <!-- TOGGLE BUTTON (added) -->
      <button id="themeBtn" class="theme-toggle" type="button" aria-label="Toggle theme">🌙</button>
      <h2 class="auth-title">Sign in to <?= htmlspecialchars($appName) ?></h2>
      <p class="auth-sub">Welcome back! Sign in to your account below.</p>

      <?php if ($error): ?>
        <div class="auth-alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="on">
        <label class="auth-label">Email</label>
        <input class="auth-input" name="email" type="email" value="<?= e($email) ?>" placeholder="Enter your email" required>

        <label class="auth-label" style="margin-top:14px">Password</label>
        <div class="auth-password">
          <input class="auth-input" id="password" name="password" type="password" placeholder="Enter your password" required>

          <button class="auth-eye" type="button" id="pwToggle" aria-label="Show password">
            <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="2"/>
              <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
            </svg>
            <svg id="eyeOff" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" style="display:none">
              <path d="M3 3l18 18" stroke="currentColor" stroke-width="2"/>
              <path d="M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-4.4" stroke="currentColor" stroke-width="2"/>
              <path d="M9.9 5.1C10.6 5 11.3 5 12 5c6.5 0 10 7 10 7a18.8 18.8 0 0 1-4.1 5.1" stroke="currentColor" stroke-width="2"/>
              <path d="M6.1 6.1C3.4 8.1 2 12 2 12s3.5 7 10 7c1.3 0 2.5-.2 3.6-.6" stroke="currentColor" stroke-width="2"/>
            </svg>
          </button>
        </div>

        <button class="auth-btn" type="submit">Sign In</button>

        <div class="auth-links">
          <div>Don’t have an account? <a href="/trading-journal/auth/register.php"><b>Sign Up</b></a></div>
          <div>Forgot your password? <a href="#"><b>Reset Password</b></a></div>
        </div>
      </form>
    </div>
  </main>

  <footer class="auth-footer">
    <div class="auth-social">

      <!-- Instagram -->
      <a class="social" href="<?= htmlspecialchars($social['instagram']) ?>" target="_blank" rel="noopener" aria-label="Instagram">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
          <rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="2"/>
          <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/>
          <circle cx="17.5" cy="6.5" r="1" fill="currentColor"/>
        </svg>
      </a>

      <!-- X -->
      <a class="social" href="<?= htmlspecialchars($social['x']) ?>" target="_blank" rel="noopener" aria-label="X">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
          <path d="M18.5 3H21L14.5 10.4L22 21H16.2L11.7 14.7L6.3 21H3.8L10.8 12.8L3.5 3H9.5L13.6 8.8L18.5 3Z" fill="currentColor"/>
        </svg>
      </a>

      <!-- Threads -->
      <a class="social" href="<?= htmlspecialchars($social['threads']) ?>" target="_blank" rel="noopener" aria-label="Threads">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M12 2c5.5 0 10 4.3 10 9.8 0 5.9-4.7 10.2-10.4 10.2C6.2 22 2 18 2 12.3 2 6.6 6.4 2 12 2Z" stroke="currentColor" stroke-width="2"/>
          <path d="M16.8 11.5c0 3.2-1.9 5.4-5 5.4-2.4 0-4.2-1.4-4.2-3.5 0-2.1 1.7-3.5 4.4-3.5 1.6 0 3 .4 4.2 1.2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M8.7 7.8c1-.7 2.1-1 3.5-1 2.6 0 4.4 1.3 4.7 3.6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </a>

      <!-- Discord -->
      <a class="social" href="<?= htmlspecialchars($social['discord']) ?>" target="_blank" rel="noopener" aria-label="Discord">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M8 8.5c2-1 6-1 8 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M7 18c3 2 7 2 10 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M6.5 7.5c-1.5 2.5-2 5.5-1.5 9.5 2 2 12 2 14 0 .5-4-.1-7-1.6-9.5-2.2-1.1-7.1-1.1-10.9 0Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
          <circle cx="9.5" cy="13" r="1.2" fill="currentColor"/>
          <circle cx="14.5" cy="13" r="1.2" fill="currentColor"/>
        </svg>
      </a>

    </div>

    <div class="auth-copy">© <?= $year ?> <?= htmlspecialchars($appName) ?>. All rights reserved.</div>
  </footer>

<script>
  // Password show/hide + eye swap
  const pw = document.getElementById("password");
  const pwToggle = document.getElementById("pwToggle");
  const eyeOpen = document.getElementById("eyeOpen");
  const eyeOff = document.getElementById("eyeOff");

  pwToggle.addEventListener("click", () => {
    const hidden = pw.type === "password";
    pw.type = hidden ? "text" : "password";
    eyeOpen.style.display = hidden ? "none" : "block";
    eyeOff.style.display = hidden ? "block" : "none";
    pwToggle.setAttribute("aria-label", hidden ? "Hide password" : "Show password");
    pw.focus();
  });

   // THEME TOGGLE LOGIC 
  const themeBtn = document.getElementById("themeBtn");
  function syncThemeIcon() {
    themeBtn.textContent = document.documentElement.classList.contains("dark") ? "☀️" : "🌙";
  }
  syncThemeIcon();

  themeBtn.addEventListener("click", () => {
    const dark = document.documentElement.classList.toggle("dark");
    localStorage.setItem("nx_theme", dark ? "dark" : "light");
    syncThemeIcon();
  });
</script>
</body>
</html>
