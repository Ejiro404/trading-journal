<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";

$first_name = $last_name = $username = $email = "";
$error = "";

if (!function_exists('e')) {
  function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $first_name = trim($_POST['first_name'] ?? '');
  $last_name  = trim($_POST['last_name'] ?? '');
  $username   = strtolower(trim($_POST['username'] ?? ''));
  $email      = strtolower(trim($_POST['email'] ?? ''));
  $password   = (string)($_POST['password'] ?? '');
  $name       = trim($first_name . ' ' . $last_name);

  if ($first_name === "" || $last_name === "" || $username === "" || $email === "" || $password === "") {
    $error = "All fields are required.";
  } elseif (!preg_match('/^[a-z0-9_]{3,20}$/', $username)) {
    $error = "Username must be 3–20 characters using lowercase letters, numbers, or underscore.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Invalid email address.";
  } elseif (strlen($password) < 6) {
    $error = "Password must be at least 6 characters.";
  } else {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
    $stmt->bind_param("ss", $email, $username);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
      $error = "An account with this email or username already exists. Please sign in instead.";
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);

      $stmt2 = $conn->prepare("
        INSERT INTO users (first_name, last_name, username, name, email, password_hash)
        VALUES (?,?,?,?,?,?)
      ");
      $stmt2->bind_param("ssssss", $first_name, $last_name, $username, $name, $email, $hash);
      $stmt2->execute();

      $new_user_id = (int)$conn->insert_id;

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

      session_regenerate_id(true);

      $_SESSION['user_id'] = $new_user_id;
      $_SESSION['user_name'] = $name;
      $_SESSION['user_email'] = $email;

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

  <script>
    (function(){
      const saved = localStorage.getItem("tj_theme");
      const prefersDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
      const useDark = saved ? (saved === "dark") : prefersDark;
      document.documentElement.classList.toggle("dark", useDark);
    })();
  </script>

  <link rel="stylesheet" href="/trading-journal/assets/css/style.css">
  <link rel="stylesheet" href="/trading-journal/assets/css/auth.css">
  <title>Create account • <?= e($appName) ?></title>

  <style>
    .theme-corner{
      position:absolute;
      top:16px;
      right:16px;
      z-index:10;
      width:46px;
      height:46px;
      border-radius:14px;
      border:1px solid var(--border);
      background:rgba(255,255,255,0.04);
      backdrop-filter:blur(12px);
      color:var(--text);
      display:flex;
      align-items:center;
      justify-content:center;
      cursor:pointer;
      transition:
        background .2s ease,
        border-color .2s ease,
        transform .18s ease,
        box-shadow .2s ease;
    }

    .theme-corner:hover{
      transform:translateY(-1px);
      box-shadow:var(--shadow);
      background:rgba(255,255,255,0.07);
    }

    .theme-corner:active{
      transform:translateY(0);
    }

    .theme-icon{
      position:absolute;
      width:19px;
      height:19px;
      transition:
        opacity .2s ease,
        transform .25s ease;
    }

    .sun-icon{
      opacity:0;
      transform:rotate(-90deg) scale(.7);
    }

    .moon-icon{
      opacity:1;
      transform:rotate(0deg) scale(1);
    }

    html.dark .sun-icon{
      opacity:1;
      transform:rotate(0deg) scale(1);
    }

    html.dark .moon-icon{
      opacity:0;
      transform:rotate(90deg) scale(.7);
    }

    .auth-input{
      box-sizing:border-box;
      font-size:16px;
    }

    .auth-password{
      position:relative;
    }

    .auth-password .auth-input{
      padding-right:64px;
    }

    .auth-eye{
      position:absolute;
      right:10px;
      top:50%;
      transform:translateY(-50%);
      border:1px solid var(--border);
      background:var(--pill);
      color:var(--text);
      width:44px;
      height:36px;
      border-radius:12px;
      cursor:pointer;
      display:flex;
      align-items:center;
      justify-content:center;
      transition:background .18s ease, border-color .18s ease, transform .18s ease;
    }

    .auth-eye:hover{
      transform:translateY(-50%) scale(1.02);
    }

    .auth-btn{
      margin-top:18px;
    }

    .register-shell{
      min-height:100vh;
      min-height:100svh;
      display:flex;
      flex-direction:column;
    }

    .register-top{
      padding:20px 20px 0;
    }

    .register-top-inner{
      width:min(1100px, 100%);
      margin:0 auto;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
    }

    .register-brandline{
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
    }

    .register-main{
      width:min(1100px, 100%);
      margin:0 auto;
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:28px;
      align-items:start;
      padding:18px 20px 14px;
      box-sizing:border-box;
      flex:0 0 auto;
    }

    .register-side{
      display:flex;
      flex-direction:column;
      gap:16px;
    }

    .register-side-panel{
      border:1px solid var(--border);
      background:var(--card);
      border-radius:24px;
      box-shadow:var(--shadow);
      padding:24px;
    }

    .register-kicker{
      display:inline-flex;
      border:1px solid var(--border);
      background:var(--pill);
      border-radius:999px;
      padding:6px 10px;
      font-size:12px;
      font-weight:800;
      margin-bottom:14px;
    }

    .register-hero-title{
      margin:0 0 12px;
      font-size:38px;
      line-height:1.05;
      font-weight:900;
      letter-spacing:-0.03em;
    }

    .register-hero-sub{
      margin:0;
      color:var(--muted);
      line-height:1.7;
      font-size:14px;
    }

    .register-feature-grid{
      display:grid;
      grid-template-columns:repeat(2, minmax(0, 1fr));
      gap:12px;
      margin-top:18px;
    }

    .register-feature{
      border:1px solid var(--border);
      background:var(--pill);
      border-radius:18px;
      padding:14px;
    }

    .register-feature-title{
      font-size:13px;
      font-weight:900;
      margin-bottom:6px;
    }

    .register-feature-text{
      font-size:12px;
      line-height:1.6;
      color:var(--muted);
      font-weight:700;
    }

    .register-note{
      border:1px dashed var(--border);
      border-radius:18px;
      padding:14px;
      line-height:1.7;
      font-size:13px;
      color:var(--muted);
      font-weight:700;
    }

    .register-card-wrap{
      position:relative;
      width:100%;
    }

    .register-card-inner{
      display:flex;
      flex-direction:column;
      gap:10px;
    }

    .register-name-grid{
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:12px;
    }

    .register-meta{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
      margin-top:8px;
      font-size:12px;
      color:var(--muted);
      font-weight:700;
    }

    .auth-links{
      margin-top:14px;
    }

    .register-footer-wrap{
      padding:10px 20px 20px;
      margin-top:auto;
    }

    .register-footer-inner{
      width:min(1100px, 100%);
      margin:0 auto;
    }

    .username-help{
      margin-top:6px;
      color:var(--muted);
      font-size:12px;
      font-weight:700;
      line-height:1.5;
    }

    @media (max-width: 900px) {
      .register-top{
        padding:16px 16px 0;
      }

      .register-main{
        width:100%;
        grid-template-columns:1fr;
        gap:16px;
        padding:16px;
      }

      .register-feature-grid{
        grid-template-columns:1fr;
      }

      .register-side-panel{
        padding:20px;
        border-radius:22px;
      }

      .register-hero-title{
        font-size:30px;
        line-height:1.08;
      }

      .auth-card.register-card-wrap{
        border-radius:22px;
        padding:22px;
      }

      .theme-corner{
        top:14px;
        right:14px;
        width:42px;
        height:42px;
        border-radius:13px;
      }

      .auth-title{
        padding-right:48px;
      }

      .register-footer-wrap{
        padding-top:14px;
      }
    }

    @media (max-width: 560px) {
      .register-top-inner{
        justify-content:center;
      }

      .register-main{
        padding:14px;
      }

      .register-side-panel{
        padding:18px;
      }

      .register-hero-title{
        font-size:26px;
      }

      .register-hero-sub{
        font-size:13px;
      }

      .register-feature{
        padding:13px;
      }

      .register-note{
        font-size:12px;
      }

      .auth-card.register-card-wrap{
        padding:20px;
      }

      .register-name-grid{
        grid-template-columns:1fr;
        gap:0;
      }

      .register-meta{
        flex-direction:column;
        align-items:flex-start;
        gap:6px;
      }

      .register-footer-wrap{
        padding:8px 14px 18px;
      }

      .auth-footer{
        flex-direction:column;
        gap:12px;
        text-align:center;
      }
    }
  </style>
</head>

<body class="auth-bg">
  <div class="register-shell">

    <div class="register-top">
      <div class="register-top-inner">
        <div class="auth-brand register-brandline">
          <div class="auth-logo">TJ</div>
          <?php if ($appBadge): ?>
            <span class="auth-badge"><?= e($appBadge) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <main class="register-main">

      <section class="register-side">
        <div class="register-side-panel">
          <div class="register-kicker">Start Your Journal</div>
          <h1 class="register-hero-title">Build discipline. Track execution. Review with clarity.</h1>
          <p class="register-hero-sub">
            Create your NXLOG account and start logging trades with structure from day one — with room for analytics, review, and performance growth.
          </p>

          <div class="register-feature-grid">
            <div class="register-feature">
              <div class="register-feature-title">Clean Trade Logging</div>
              <div class="register-feature-text">Record setups, risk, notes, and outcomes in a structured way.</div>
            </div>

            <div class="register-feature">
              <div class="register-feature-title">Review Workflow</div>
              <div class="register-feature-text">Turn closed trades into lessons through a proper review queue.</div>
            </div>

            <div class="register-feature">
              <div class="register-feature-title">Strategy Tracking</div>
              <div class="register-feature-text">Organize trades by setup and strategy for clearer performance analysis.</div>
            </div>

            <div class="register-feature">
              <div class="register-feature-title">Trader Growth</div>
              <div class="register-feature-text">Use journaling to improve discipline, consistency, and execution quality.</div>
            </div>
          </div>
        </div>

        <div class="register-note">
          Your account starts with default review rules so you can begin tracking common execution mistakes immediately.
        </div>
      </section>

      <section class="auth-wrap" style="padding:0; width:100%; margin:0;">
        <div class="auth-card register-card-wrap" style="width:100%;">
          <button class="theme-corner" type="button" id="themeBtn" aria-label="Toggle theme">
            <svg class="theme-icon sun-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/>
              <path d="M12 2V4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M12 20V22" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M4 12H2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M22 12H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M19.78 4.22L17.66 6.34" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M6.34 17.66L4.22 19.78" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M19.78 19.78L17.66 17.66" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M6.34 6.34L4.22 4.22" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>

            <svg class="theme-icon moon-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M21 12.8A9 9 0 1 1 11.2 3c0 .2-.1.5-.1.8A7.5 7.5 0 0 0 18.6 11c.8 0 1.6-.1 2.4-.4Z"
                stroke="currentColor"
                stroke-width="2"
                stroke-linejoin="round"/>
            </svg>
          </button>

          <div class="register-card-inner">
            <h2 class="auth-title">Create your <?= e($appName) ?> account</h2>
            <p class="auth-sub">Get started — it only takes a minute.</p>

            <?php if ($error): ?>
              <div class="auth-alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="on">
              <div class="register-name-grid">
                <div>
                  <label class="auth-label">First Name</label>
                  <input class="auth-input" name="first_name" value="<?= e($first_name) ?>" placeholder="First name" required>
                </div>

                <div>
                  <label class="auth-label">Last Name</label>
                  <input class="auth-input" name="last_name" value="<?= e($last_name) ?>" placeholder="Last name" required>
                </div>
              </div>

              <label class="auth-label" style="margin-top:14px">Username</label>
              <input
                class="auth-input"
                name="username"
                value="<?= e($username) ?>"
                placeholder="e.g. nx_trader"
                required
              >
              <div class="username-help">
                Use 3–20 lowercase letters, numbers, or underscore.
              </div>

              <label class="auth-label" style="margin-top:14px">Email</label>
              <input class="auth-input" name="email" type="email" value="<?= e($email) ?>" placeholder="you@email.com" required>

              <label class="auth-label" style="margin-top:14px">Password</label>
              <div class="auth-password">
                <input class="auth-input" id="password" name="password" type="password" placeholder="Min 6 characters" required>

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

              <button class="auth-btn" type="submit">Create Account</button>

              <div class="register-meta">
                <div>Default review rules are created automatically</div>
                <div><?= e($appBadge) ?> Access</div>
              </div>

              <div class="auth-links">
                <div>Already have an account? <a href="/trading-journal/auth/login.php"><b>Sign in</b></a></div>
              </div>
            </form>
          </div>
        </div>
      </section>

    </main>

    <div class="register-footer-wrap">
      <footer class="auth-footer register-footer-inner">
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
  const themeBtn = document.getElementById("themeBtn");

  themeBtn.addEventListener("click", () => {
    const isDark = document.documentElement.classList.toggle("dark");
    localStorage.setItem("tj_theme", isDark ? "dark" : "light");
  });

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
</script>
</body>
</html>