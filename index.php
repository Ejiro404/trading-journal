<?php
require_once __DIR__ . "/config/auth.php";

$logged_in = !empty($_SESSION['user_id']);
$nextUrl = $logged_in ? "/trading-journal/dashboard.php" : "/trading-journal/auth/login.php";

$appName = "Trading Journal";
$appVersion = "v1.0.0";
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
  <title><?= htmlspecialchars($appName) ?></title>
</head>
<body>
<div class="container">

  <div class="topbar">
    <div class="logo">
      <img src="/trading-journal/assets/images/logo.png" alt="Logo" onerror="this.style.display='none'">
      <div>
        <div style="font-weight:900"><?= htmlspecialchars($appName) ?></div>
        <div class="sub">R-first analytics</div>
      </div>
    </div>

    <div class="pills">
      <a class="pill" href="/trading-journal/trades.php">📒 Trades</a>
      <a class="pill" href="/trading-journal/rules.php">🧠 Rules</a>
      <button id="themeBtn" class="btn secondary" type="button">🌙</button>
      <a class="btn" href="<?= e($nextUrl) ?>">Continue</a>
    </div>
  </div>

  <div style="height:14px"></div>

  <div class="card" style="max-width:900px;margin:0 auto;">
    <h2 style="margin:0 0 6px 0">Journal smarter. Trade cleaner.</h2>
    <p class="small" style="margin-top:0;color:var(--muted)">
      R-first journaling, rule-break tracking, and dashboards that actually improve execution.
    </p>

    <div style="height:14px"></div>

    <div class="grid grid-3">
      <div class="card">
        <h3>R-first analytics</h3>
        <div class="small">Expectancy, PF, Net R — from closed trades.</div>
      </div>

      <div class="card">
        <h3>Discipline tracking</h3>
        <div class="small">Rule breaks → cost. Fix the real leak.</div>
      </div>

      <div class="card">
        <h3>Consistency</h3>
        <div class="small">Heatmap progress. Track momentum.</div>
      </div>
    </div>

    <div style="height:14px"></div>

    <div class="form-actions">
      <a class="btn" href="<?= e($nextUrl) ?>"><?= $logged_in ? "Go to Dashboard" : "Login to Continue" ?></a>
      <?php if (!$logged_in): ?>
        <a class="btn secondary" href="/trading-journal/auth/register.php">Create account</a>
      <?php else: ?>
        <a class="btn secondary" href="/trading-journal/trade_new.php">+ Add Trade</a>
      <?php endif; ?>
    </div>

    <div class="small" style="margin-top:10px;color:var(--muted)">
      <?= $logged_in ? "Signed in as: <b>".e((string)($_SESSION['user_name'] ?? "User"))."</b>" : "Not signed in yet." ?>
    </div>
  </div>

  <div class="footer">
    <div class="small"><?= htmlspecialchars($appVersion) ?></div>
    <div class="small">© <?= date("Y") ?> <?= htmlspecialchars($appName) ?>. All rights reserved.</div>
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
</script>
</body>
</html>
