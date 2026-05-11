<?php
require_once __DIR__ . "/config/auth.php";

$logged_in = !empty($_SESSION['user_id']);
$nextUrl = $logged_in ? "/trading-journal/dashboard.php" : "/trading-journal/auth/login.php";

$appName = "NXLOG Analytics";
$appVersion = "Beta";
$year = date("Y");

if (!function_exists('e')) {
  function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($appName) ?> • Trading Journal & Execution Analytics</title>

  <script>
    (function(){
      const saved = localStorage.getItem("tj_theme") || localStorage.getItem("nx_theme");
      const prefersDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
      const useDark = saved ? (saved === "dark") : prefersDark;
      document.documentElement.classList.toggle("dark", useDark);
    })();
  </script>

  <link rel="stylesheet" href="/trading-journal/assets/css/style.css">

  <style>
    .landing-shell{
      min-height:100vh;
      background:
        radial-gradient(circle at top left, rgba(109,94,252,.16), transparent 34%),
        radial-gradient(circle at top right, rgba(34,197,94,.08), transparent 28%),
        var(--bg);
      color:var(--text);
      overflow:hidden;
    }

    .landing-container{
      width:min(1180px,100%);
      margin:0 auto;
      padding:22px;
    }

    .landing-nav{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:14px;
      padding:14px;
      border:1px solid var(--border);
      background:color-mix(in srgb, var(--card) 88%, transparent);
      backdrop-filter:blur(18px);
      border-radius:22px;
      box-shadow:var(--shadow);
    }

    .landing-brand{
      display:flex;
      align-items:center;
      gap:12px;
      min-width:0;
    }

    .brand-logo{
      width:46px;
      height:46px;
      border-radius:16px;
      border:1px solid rgba(109,94,252,.35);
      background:var(--card);
      object-fit:cover;
      box-shadow:var(--shadow);
      flex:0 0 auto;
    }

    .brand-title{
      font-size:15px;
      font-weight:950;
      letter-spacing:-.03em;
      line-height:1;
    }

    .brand-sub{
      margin-top:4px;
      color:var(--muted);
      font-size:12px;
      font-weight:800;
    }

    .nav-actions{
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
    }

    .theme-toggle{
      width:44px;
      height:44px;
      border-radius:15px;
      border:1px solid var(--border);
      background:
        radial-gradient(circle at top left, rgba(109,94,252,.18), transparent 38%),
        rgba(255,255,255,.04);
      color:var(--text);
      display:grid;
      place-items:center;
      cursor:pointer;
      position:relative;
      backdrop-filter:blur(12px);
      box-shadow:inset 0 1px 0 rgba(255,255,255,.05);
      transition:
        transform .16s ease,
        box-shadow .16s ease,
        border-color .16s ease,
        background .16s ease;
    }

    .theme-toggle:hover{
      transform:translateY(-1px);
      box-shadow:
        var(--shadow),
        0 0 0 4px rgba(109,94,252,.10);
      border-color:rgba(109,94,252,.38);
    }

    .theme-icon{
      position:absolute;
      width:19px;
      height:19px;
      transition:
        opacity .2s ease,
        transform .25s ease;
    }

    .theme-sun{
      opacity:0;
      transform:rotate(-90deg) scale(.7);
    }

    .theme-moon{
      opacity:1;
      transform:rotate(0deg) scale(1);
    }

    html.dark .theme-sun{
      opacity:1;
      transform:rotate(0deg) scale(1);
    }

    html.dark .theme-moon{
      opacity:0;
      transform:rotate(90deg) scale(.7);
    }

    .hero{
      display:grid;
      grid-template-columns:1.05fr .95fr;
      gap:22px;
      align-items:center;
      padding:58px 0 26px;
    }

    .hero-copy{
      min-width:0;
    }

    .kicker{
      display:inline-flex;
      align-items:center;
      gap:8px;
      border:1px solid var(--border);
      background:color-mix(in srgb, var(--pill) 86%, transparent);
      border-radius:999px;
      padding:7px 12px;
      font-size:12px;
      font-weight:950;
      color:var(--muted);
      margin-bottom:18px;
    }

    .kicker-dot{
      width:8px;
      height:8px;
      border-radius:999px;
      background:var(--accent);
      box-shadow:0 0 0 4px rgba(109,94,252,.12);
    }

    .hero h1{
      margin:0;
      font-size:60px;
      line-height:.96;
      letter-spacing:-.06em;
      font-weight:950;
      max-width:760px;
    }

    .hero h1 span{
      color:var(--accent);
    }

    .hero-sub{
      margin:20px 0 0;
      max-width:660px;
      color:var(--muted);
      line-height:1.8;
      font-size:15px;
      font-weight:700;
    }

    .hero-actions{
      display:flex;
      align-items:center;
      gap:12px;
      flex-wrap:wrap;
      margin-top:26px;
    }

    .hero-note{
      margin-top:14px;
      color:var(--muted);
      font-size:12px;
      font-weight:800;
    }

    .preview-card{
      border:1px solid var(--border);
      background:
        radial-gradient(circle at top left, rgba(109,94,252,.16), transparent 34%),
        color-mix(in srgb, var(--card) 92%, transparent);
      backdrop-filter:blur(18px);
      border-radius:28px;
      box-shadow:var(--shadow);
      padding:18px;
      min-width:0;
    }

    .terminal-head{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:12px;
      margin-bottom:14px;
    }

    .terminal-title{
      font-size:13px;
      font-weight:950;
      color:var(--muted);
      text-transform:uppercase;
      letter-spacing:.06em;
    }

    .status-pill{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid rgba(34,197,94,.26);
      background:rgba(34,197,94,.08);
      color:#16a34a;
      font-size:12px;
      font-weight:950;
    }

    .metric-grid{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:12px;
    }

    .metric-card{
      border:1px solid var(--border);
      background:var(--pill);
      border-radius:18px;
      padding:14px;
      min-width:0;
    }

    .metric-label{
      color:var(--muted);
      font-size:11px;
      font-weight:950;
      text-transform:uppercase;
      letter-spacing:.05em;
      margin-bottom:8px;
    }

    .metric-value{
      font-size:28px;
      line-height:1;
      font-weight:950;
      letter-spacing:-.04em;
    }

    .metric-sub{
      margin-top:7px;
      color:var(--muted);
      font-size:12px;
      font-weight:750;
    }

    .process{
      display:grid;
      grid-template-columns:repeat(3,minmax(0,1fr));
      gap:14px;
      margin-top:18px;
    }

    .process-card{
      border:1px solid var(--border);
      background:var(--card);
      border-radius:22px;
      box-shadow:var(--shadow);
      padding:18px;
      min-width:0;
    }

    .process-num{
      width:36px;
      height:36px;
      border-radius:14px;
      display:grid;
      place-items:center;
      background:var(--pill);
      border:1px solid var(--border);
      color:var(--accent);
      font-weight:950;
      margin-bottom:12px;
    }

    .process-card h3{
      margin:0 0 8px;
      font-size:17px;
      font-weight:950;
      letter-spacing:-.02em;
    }

    .process-card p{
      margin:0;
      color:var(--muted);
      line-height:1.65;
      font-size:13px;
      font-weight:700;
    }

    .feature-section{
      padding:22px 0 20px;
    }

    .section-head{
      display:flex;
      justify-content:space-between;
      align-items:flex-end;
      gap:16px;
      flex-wrap:wrap;
      margin-bottom:14px;
    }

    .section-head h2{
      margin:0;
      font-size:30px;
      line-height:1.05;
      letter-spacing:-.04em;
      font-weight:950;
    }

    .section-head p{
      margin:0;
      max-width:520px;
      color:var(--muted);
      line-height:1.7;
      font-size:13px;
      font-weight:700;
    }

    .feature-grid{
      display:grid;
      grid-template-columns:repeat(4,minmax(0,1fr));
      gap:14px;
    }

    .feature-card{
      border:1px solid var(--border);
      background:var(--card);
      border-radius:20px;
      box-shadow:var(--shadow);
      padding:16px;
      min-width:0;
    }

    .feature-icon{
      width:40px;
      height:40px;
      border-radius:15px;
      display:grid;
      place-items:center;
      border:1px solid var(--border);
      background:var(--pill);
      margin-bottom:12px;
      color:var(--accent);
    }

    .feature-card h3{
      margin:0 0 7px;
      font-size:15px;
      font-weight:950;
    }

    .feature-card p{
      margin:0;
      color:var(--muted);
      line-height:1.6;
      font-size:12px;
      font-weight:700;
    }

    .final-cta{
      margin-top:18px;
      border:1px solid var(--border);
      background:
        radial-gradient(circle at top left, rgba(109,94,252,.16), transparent 34%),
        var(--card);
      border-radius:26px;
      box-shadow:var(--shadow);
      padding:24px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:16px;
      flex-wrap:wrap;
    }

    .final-cta h2{
      margin:0 0 6px;
      font-size:26px;
      line-height:1.05;
      font-weight:950;
      letter-spacing:-.04em;
    }

    .final-cta p{
      margin:0;
      color:var(--muted);
      font-size:13px;
      line-height:1.7;
      font-weight:700;
    }

    .landing-footer{
      padding:18px 0 4px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
      color:var(--muted);
      font-size:12px;
      font-weight:800;
    }

    @media (max-width:980px){
      .hero{
        grid-template-columns:1fr;
        padding-top:38px;
      }

      .hero h1{
        font-size:46px;
      }

      .feature-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
      }
    }

    @media (max-width:720px){
      .landing-container{
        padding:14px;
      }

      .landing-nav{
        border-radius:20px;
      }

      .brand-sub{
        display:none;
      }

      .nav-actions .pill{
        display:none;
      }

      .hero{
        padding:32px 0 18px;
        gap:16px;
      }

      .hero h1{
        font-size:38px;
      }

      .hero-sub{
        font-size:13px;
        line-height:1.7;
      }

      .hero-actions{
        display:grid;
        grid-template-columns:1fr;
      }

      .hero-actions .btn{
        width:100%;
      }

      .metric-grid,
      .process,
      .feature-grid{
        grid-template-columns:1fr;
      }

      .section-head h2{
        font-size:24px;
      }

      .final-cta{
        display:grid;
      }

      .final-cta .btn{
        width:100%;
      }
    }

    @media (max-width:420px){
      .hero h1{
        font-size:34px;
      }

      .brand-logo{
        width:42px;
        height:42px;
      }

      .theme-toggle{
        width:42px;
        height:42px;
      }
    }
  </style>
</head>

<body>
<div class="landing-shell">
  <div class="landing-container">

    <header class="landing-nav">
      <a class="landing-brand" href="/trading-journal/index.php">
        <img class="brand-logo" src="/trading-journal/assets/img/logo.png" alt="NXLOG" onerror="this.style.display='none'">
        <div>
          <div class="brand-title"><?= e($appName) ?></div>
          <div class="brand-sub">Execution journal & trader analytics</div>
        </div>
      </a>

      <div class="nav-actions">
        <?php if (!$logged_in): ?>
          <a class="pill" href="/trading-journal/auth/login.php">Login</a>
          <a class="pill" href="/trading-journal/auth/register.php">Create Account</a>
        <?php else: ?>
          <span class="pill">Signed in as <?= e((string)($_SESSION['user_name'] ?? "Trader")) ?></span>
        <?php endif; ?>

        <button class="theme-toggle" id="themeBtn" type="button" aria-label="Toggle theme">
          <svg class="theme-icon theme-sun" viewBox="0 0 24 24" fill="none" aria-hidden="true">
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

          <svg class="theme-icon theme-moon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M21 12.8A9 9 0 1 1 11.2 3c0 .2-.1.5-.1.8A7.5 7.5 0 0 0 18.6 11c.8 0 1.6-.1 2.4-.4Z"
              stroke="currentColor"
              stroke-width="2"
              stroke-linejoin="round"/>
          </svg>
        </button>

        <a class="btn" href="<?= e($nextUrl) ?>">
          <?= $logged_in ? "Open Dashboard" : "Start Journaling" ?>
        </a>
      </div>
    </header>

    <main>
      <section class="hero">
        <div class="hero-copy">
          <div class="kicker">
            <span class="kicker-dot"></span>
            <span>Built for serious traders</span>
          </div>

          <h1>
            Journal trades like a <span>performance desk.</span>
          </h1>

          <p class="hero-sub">
            NXLOG Analytics is a premium trading journal for tracking execution quality,
            risk discipline, review habits, mistakes, strategy output, and performance clarity —
            without feeling like another spreadsheet.
          </p>

          <div class="hero-actions">
            <a class="btn" href="<?= e($nextUrl) ?>">
              <?= $logged_in ? "Continue to Dashboard" : "Login to Continue" ?>
            </a>

            <?php if (!$logged_in): ?>
              <a class="btn secondary" href="/trading-journal/auth/register.php">Create Account</a>
            <?php else: ?>
              <a class="btn secondary" href="/trading-journal/log_new.php">Log New Trade</a>
            <?php endif; ?>
          </div>

          <div class="hero-note">
            R-first analytics • Review queue • Strategy tagging • Discipline tracking
          </div>
        </div>

        <aside class="preview-card">
          <div class="terminal-head">
            <div class="terminal-title">Performance Snapshot</div>
            <div class="status-pill">Live Journal</div>
          </div>

          <div class="metric-grid">
            <div class="metric-card">
              <div class="metric-label">Net R</div>
              <div class="metric-value">+12.40R</div>
              <div class="metric-sub">Closed trades only</div>
            </div>

            <div class="metric-card">
              <div class="metric-label">Win Rate</div>
              <div class="metric-value">57.8%</div>
              <div class="metric-sub">Execution sample</div>
            </div>

            <div class="metric-card">
              <div class="metric-label">Discipline</div>
              <div class="metric-value">84</div>
              <div class="metric-sub">Rules + review quality</div>
            </div>

            <div class="metric-card">
              <div class="metric-label">Review Queue</div>
              <div class="metric-value">6</div>
              <div class="metric-sub">Pending lessons</div>
            </div>
          </div>

          <div class="process">
            <div class="process-card">
              <div class="process-num">1</div>
              <h3>Log</h3>
              <p>Capture entry, risk, setup, session, screenshots, and notes.</p>
            </div>

            <div class="process-card">
              <div class="process-num">2</div>
              <h3>Review</h3>
              <p>Convert closed trades into rules, mistakes, and lessons.</p>
            </div>

            <div class="process-card">
              <div class="process-num">3</div>
              <h3>Improve</h3>
              <p>Use analytics to see what is actually affecting execution.</p>
            </div>
          </div>
        </aside>
      </section>

      <section class="feature-section">
        <div class="section-head">
          <div>
            <h2>Built around execution quality.</h2>
          </div>
          <p>
            NXLOG focuses on the things that actually move a trader forward:
            process, risk, repeatable setups, discipline, and review consistency.
          </p>
        </div>

        <div class="feature-grid">
          <div class="feature-card">
            <div class="feature-icon">R</div>
            <h3>R-first tracking</h3>
            <p>Measure performance in R-multiples so risk quality stays clear.</p>
          </div>

          <div class="feature-card">
            <div class="feature-icon">✓</div>
            <h3>Rule checks</h3>
            <p>Track broken rules and expose the real execution leaks.</p>
          </div>

          <div class="feature-card">
            <div class="feature-icon">#</div>
            <h3>Strategy tags</h3>
            <p>Organize trades by setup and compare what works best.</p>
          </div>

          <div class="feature-card">
            <div class="feature-icon">↗</div>
            <h3>Analytics clarity</h3>
            <p>Dashboards, history, reports, and review signals in one flow.</p>
          </div>
        </div>

        <div class="final-cta">
          <div>
            <h2>Ready to trade cleaner?</h2>
            <p>
              Start logging with structure today, then use reviews and analytics
              to build a more disciplined trading process.
            </p>
          </div>

          <a class="btn" href="<?= e($nextUrl) ?>">
            <?= $logged_in ? "Open NXLOG" : "Get Started" ?>
          </a>
        </div>
      </section>
    </main>

    <footer class="landing-footer">
      <div><?= e($appVersion) ?> • <?= e($appName) ?></div>
      <div>© <?= e($year) ?> <?= e($appName) ?>. All rights reserved.</div>
    </footer>

  </div>
</div>

<script>
  const themeBtn = document.getElementById("themeBtn");

  themeBtn.addEventListener("click", () => {
    const isDark = document.documentElement.classList.toggle("dark");
    localStorage.setItem("tj_theme", isDark ? "dark" : "light");
    localStorage.setItem("nx_theme", isDark ? "dark" : "light");
  });
</script>
</body>
</html>