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

if (!function_exists('e')) {
  function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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
  <title>Login • <?= e($appName) ?></title>

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

    .login-shell {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .login-top {
      padding: 20px 20px 0;
    }

    .login-top-inner {
      width: min(1100px, 100%);
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }

    .login-brandline {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    .login-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .login-main {
      width: min(1100px, 100%);
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 28px;
      align-items: start;
      padding: 18px 20px 14px;
      box-sizing: border-box;
      flex: 0 0 auto;
    }

    .login-side {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .login-side-panel {
      border: 1px solid var(--border);
      background: var(--card);
      border-radius: 24px;
      box-shadow: var(--shadow);
      padding: 24px;
    }

    .login-kicker {
      display: inline-flex;
      border: 1px solid var(--border);
      background: var(--pill);
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 12px;
      font-weight: 800;
      margin-bottom: 14px;
    }

    .login-hero-title {
      margin: 0 0 12px;
      font-size: 38px;
      line-height: 1.05;
      font-weight: 900;
      letter-spacing: -0.03em;
    }

    .login-hero-sub {
      margin: 0;
      color: var(--muted);
      line-height: 1.7;
      font-size: 14px;
    }

    .login-feature-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
      margin-top: 18px;
    }

    .login-feature {
      border: 1px solid var(--border);
      background: var(--pill);
      border-radius: 18px;
      padding: 14px;
    }

    .login-feature-title {
      font-size: 13px;
      font-weight: 900;
      margin-bottom: 6px;
    }

    .login-feature-text {
      font-size: 12px;
      line-height: 1.6;
      color: var(--muted);
      font-weight: 700;
    }

    .login-note {
      border: 1px dashed var(--border);
      border-radius: 18px;
      padding: 14px;
      line-height: 1.7;
      font-size: 13px;
      color: var(--muted);
      font-weight: 700;
    }

    .login-card-wrap {
      position: relative;
      width: 100%;
    }

    .login-card-inner {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .login-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 8px;
      font-size: 12px;
      color: var(--muted);
      font-weight: 700;
    }

    .auth-links {
      margin-top: 14px;
    }

    .login-footer-wrap {
      padding: 10px 20px 20px;
      margin-top: 0;
    }

    .login-footer-inner {
      width: min(1100px, 100%);
      margin: 0 auto;
    }

    @media (max-width: 900px) {
      .login-main {
        grid-template-columns: 1fr;
        gap: 18px;
        padding-top: 16px;
      }

      .login-feature-grid {
        grid-template-columns: 1fr;
      }

      .login-hero-title {
        font-size: 30px;
      }

      .login-footer-wrap {
        padding-top: 14px;
      }
    }
  </style>
</head>

<body class="auth-bg">
  <div class="login-shell">

    <div class="login-top">
      <div class="login-top-inner">
        <div class="auth-brand login-brandline">
          <div class="auth-logo">TJ</div>
          <?php if ($appBadge): ?>
            <span class="auth-badge login-badge"><?= e($appBadge) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <main class="login-main">

      <section class="login-side">
        <div class="login-side-panel">
          <div class="login-kicker">Trader Workspace</div>
          <h1 class="login-hero-title">Journal better. Review deeper. Execute cleaner.</h1>
          <p class="login-hero-sub">
            NXLOG helps traders keep a structured record of execution, review behavior, discipline, and performance in one focused workspace.
          </p>

          <div class="login-feature-grid">
            <div class="login-feature">
              <div class="login-feature-title">Log Trades</div>
              <div class="login-feature-text">Capture setups, execution details, outcomes, and notes clearly.</div>
            </div>

            <div class="login-feature">
              <div class="login-feature-title">Review Better</div>
              <div class="login-feature-text">Turn closed trades into feedback and measurable improvement.</div>
            </div>

            <div class="login-feature">
              <div class="login-feature-title">Track State</div>
              <div class="login-feature-text">Measure energy, focus, confidence, and tilt before execution.</div>
            </div>

            <div class="login-feature">
              <div class="login-feature-title">Read Analytics</div>
              <div class="login-feature-text">See patterns in strategy performance and execution quality.</div>
            </div>
          </div>
        </div>

        <div class="login-note">
          Built by traders for traders who want structure, consistency, and a cleaner review process over time.
        </div>
      </section>

      <section class="auth-wrap" style="padding:0; width:100%; margin:0;">
        <div class="auth-card login-card-wrap" style="width:100%;">
          <button id="themeBtn" class="theme-toggle" type="button" aria-label="Toggle theme">🌙</button>

          <div class="login-card-inner">
            <h2 class="auth-title">Sign in to <?= e($appName) ?></h2>
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

              <div class="login-meta">
                <div>Secure access to your trading workspace</div>
                <div><?= e($appBadge) ?> Access</div>
              </div>

              <div class="auth-links">
                <div>Don’t have an account? <a href="/trading-journal/auth/register.php"><b>Sign Up</b></a></div>
                <div>Forgot your password? <a href="#"><b>Reset Password</b></a></div>
              </div>
            </form>
          </div>
        </div>
      </section>

    </main>

    <div class="login-footer-wrap">
      <footer class="auth-footer login-footer-inner">
        <div class="auth-social">

          <a class="social" href="<?= e($social['instagram']) ?>" target="_blank" rel="noopener" aria-label="Instagram">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
              <rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="2"/>
              <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/>
              <circle cx="17.5" cy="6.5" r="1" fill="currentColor"/>
            </svg>
          </a>

          <a class="social" href="<?= e($social['x']) ?>" target="_blank" rel="noopener" aria-label="X">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
              <path d="M18.5 3H21L14.5 10.4L22 21H16.2L11.7 14.7L6.3 21H3.8L10.8 12.8L3.5 3H9.5L13.6 8.8L18.5 3Z" fill="currentColor"/>
            </svg>
          </a>

          <a class="social" href="<?= e($social['threads']) ?>" target="_blank" rel="noopener" aria-label="Threads">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path d="M12 2c5.5 0 10 4.3 10 9.8 0 5.9-4.7 10.2-10.4 10.2C6.2 22 2 18 2 12.3 2 6.6 6.4 2 12 2Z" stroke="currentColor" stroke-width="2"/>
              <path d="M16.8 11.5c0 3.2-1.9 5.4-5 5.4-2.4 0-4.2-1.4-4.2-3.5 0-2.1 1.7-3.5 4.4-3.5 1.6 0 3 .4 4.2 1.2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M8.7 7.8c1-.7 2.1-1 3.5-1 2.6 0 4.4 1.3 4.7 3.6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </a>

          <a class="social" href="<?= e($social['discord']) ?>" target="_blank" rel="noopener" aria-label="Discord">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path d="M8 8.5c2-1 6-1 8 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M7 18c3 2 7 2 10 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M6.5 7.5c-1.5 2.5-2 5.5-1.5 9.5 2 2 12 2 14 0 .5-4-.1-7-1.6-9.5-2.2-1.1-7.1-1.1-10.9 0Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
              <circle cx="9.5" cy="13" r="1.2" fill="currentColor"/>
              <circle cx="14.5" cy="13" r="1.2" fill="currentColor"/>
            </svg>
          </a>

        </div>

        <div class="auth-copy">© <?= e($year) ?> <?= e($appName) ?>. All rights reserved.</div>
      </footer>
    </div>

  </div>

<script>
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