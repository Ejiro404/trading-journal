<?php
// partials/app_header.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../config/auth.php";
require_login();

$pageTitle = $pageTitle ?? "NXLOG Analytics";
$current   = $current ?? "";
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<link rel="stylesheet" href="/trading-journal/assets/css/style.css">

<script>
(function () {
  var saved = localStorage.getItem("tj_theme") || localStorage.getItem("nx_theme");
  if (saved !== "dark" && saved !== "light") saved = null;

  var prefersDark = false;
  try { prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches; } catch(e){}

  var useDark = saved ? (saved === "dark") : prefersDark;
  document.documentElement.classList.toggle("dark", useDark);

  var sb = localStorage.getItem("nx_sidebar");
  if (sb === "collapsed") document.documentElement.classList.add("sb-collapsed");
})();
</script>

<style>
*{ box-sizing:border-box; }

html, body{
  max-width:100%;
  overflow-x:hidden;
}

input, select, textarea, button{ font-size:16px; }

body.nav-open{ overflow:hidden; }

.app{
  width:100%;
  max-width:100%;
  overflow-x:hidden;
}

.sidebar{
  position:fixed !important;
  top:0;
  left:0;
  bottom:0;
  width:260px !important;
  height:100vh;
  overflow-y:auto;
  overflow-x:hidden;
  z-index:9990;
}

.main{
  margin-left:260px !important;
  width:calc(100% - 260px);
  max-width:calc(100% - 260px);
  min-width:0;
}

html.sb-collapsed .sidebar{ width:86px !important; }

html.sb-collapsed .main{
  margin-left:86px !important;
  width:calc(100% - 86px);
  max-width:calc(100% - 86px);
}

.sidebar-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  margin-bottom:10px;
}

.logo{
  display:flex;
  align-items:center;
  gap:10px;
  min-width:0;
}

.logo-img{
  width:44px;
  height:44px;
  border-radius:15px;
  border:1px solid rgba(109,94,252,.32);
  background:var(--card);
  object-fit:cover;
  flex:0 0 auto;
  box-shadow:var(--shadow), inset 0 1px 0 rgba(255,255,255,.08);
}

.logo-text{
  display:flex;
  flex-direction:column;
  line-height:1.05;
  min-width:0;
}

.logo-title{
  font-weight:950;
  letter-spacing:-.03em;
}

.logo-sub{
  margin-top:3px;
  font-size:12px;
  font-weight:750;
  color:var(--muted);
}

.sidebar-toggle,
.mobile-menu-btn{
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
  backdrop-filter:blur(12px);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.05);
  transition:transform .16s ease, box-shadow .16s ease, border-color .16s ease, background .16s ease;
}

.sidebar-toggle:hover,
.mobile-menu-btn:hover{
  transform:translateY(-1px);
  box-shadow:var(--shadow), 0 0 0 4px rgba(109,94,252,.10);
  border-color:rgba(109,94,252,.38);
}

.sidebar-toggle:active,
.mobile-menu-btn:active{ transform:translateY(0); }

.collapse-icon,
.mobile-menu-icon{
  width:21px;
  height:21px;
  display:block;
}

.collapse-panel,
.collapse-arrow,
.mobile-menu-line{
  transition:transform .22s ease, opacity .18s ease;
  transform-origin:center;
}

html.sb-collapsed .collapse-arrow{ transform:rotate(180deg); }

.mobile-menu-btn{
  display:none;
  flex:0 0 auto;
}

html.sb-collapsed .sidebar .txt,
html.sb-collapsed .sidebar .logo-text{
  display:none !important;
}

html.sb-collapsed .sidebar .nav-item{ justify-content:center; }

.nav-item .ico{
  width:38px;
  height:38px;
  border-radius:14px;
  border:1px solid var(--border);
  background:var(--pill);
  display:grid;
  place-items:center;
  flex:0 0 auto;
}

.nav-btn{
  width:100%;
  text-align:left;
  background:transparent;
  border:1px solid transparent;
  cursor:pointer;
}

.nav-item{ position:relative; }

html.sb-collapsed .nav-item[data-title]:hover::after{
  content:attr(data-title);
  position:absolute;
  left:92px;
  top:50%;
  transform:translateY(-50%);
  background:var(--card);
  border:1px solid var(--border);
  box-shadow:var(--shadow);
  color:var(--text);
  padding:8px 10px;
  border-radius:12px;
  font-size:12px;
  font-weight:800;
  white-space:nowrap;
  z-index:9999;
}

.sidebar-foot{
  margin-top:auto !important;
  padding-top:10px;
  border-top:1px solid var(--border);
}

.nav-item:hover{
  border-color:rgba(109,94,252,.22) !important;
  box-shadow:var(--shadow), 0 0 0 4px rgba(109,94,252,.10);
}

.nav-item.active{
  box-shadow:var(--shadow), 0 0 0 4px rgba(109,94,252,.12);
}

.theme-icon-wrap{
  position:relative;
  width:19px;
  height:19px;
  display:block;
}

.theme-svg{
  position:absolute;
  inset:0;
  width:19px;
  height:19px;
  transition:opacity .2s ease, transform .25s ease;
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

.topbar{
  min-width:0;
  margin:14px 16px 0;
  padding:14px 16px;
  border:1px solid var(--border);
  border-radius:20px;
  background:
    radial-gradient(circle at top left, rgba(109,94,252,.10), transparent 34%),
    color-mix(in srgb, var(--card) 92%, transparent);
  box-shadow:var(--shadow);
  backdrop-filter:blur(16px);
}

.topbar-title{
  min-width:0;
  overflow:hidden;
  text-overflow:ellipsis;
  white-space:nowrap;
  font-size:14px;
  letter-spacing:-.01em;
}

.topbar-title strong{
  display:inline-flex;
  align-items:center;
  gap:8px;
}

.topbar-title strong::before{
  content:"";
  width:8px;
  height:8px;
  border-radius:999px;
  background:var(--accent);
  box-shadow:0 0 0 4px rgba(109,94,252,.12);
}

.mobile-top-logo{ display:none; }

.container{
  width:100%;
  max-width:100%;
  min-width:0;
}

.mobile-sidebar-backdrop{ display:none; }

@media (max-width:900px){
  .main,
  html.sb-collapsed .main{
    margin-left:0 !important;
    width:100% !important;
    max-width:100% !important;
  }

  .sidebar,
  html.sb-collapsed .sidebar{
    position:fixed !important;
    top:0;
    left:0;
    bottom:0;
    width:min(310px, 86vw) !important;
    max-width:86vw;
    height:100vh;
    z-index:9998;
    transform:translateX(-105%);
    transition:transform .22s ease;
    box-shadow:0 24px 80px rgba(0,0,0,.36);
  }

  body.nav-open .sidebar{ transform:translateX(0); }

  html.sb-collapsed .sidebar .txt,
  html.sb-collapsed .sidebar .logo-text{
    display:flex !important;
  }

  html.sb-collapsed .sidebar .nav-item{ justify-content:flex-start; }

  .mobile-sidebar-backdrop{
    display:block;
    position:fixed;
    inset:0;
    z-index:9997;
    background:rgba(2,6,23,.46);
    backdrop-filter:blur(6px);
    opacity:0;
    pointer-events:none;
    transition:opacity .2s ease;
  }

  body.nav-open .mobile-sidebar-backdrop{
    opacity:1;
    pointer-events:auto;
  }

  .mobile-menu-btn{
    display:grid;
    width:46px;
    height:46px;
    border-radius:16px;
  }

  .sidebar-toggle{ display:none; }

  .topbar{
    position:sticky;
    top:0;
    z-index:9000;
    display:flex;
    align-items:center;
    gap:12px;
    width:100%;
    max-width:100%;
    margin:0;
    padding:14px 16px;
    border-radius:0;
    border-top:0;
    border-left:0;
    border-right:0;
    box-shadow:none;
    background:color-mix(in srgb, var(--bg) 92%, transparent);
    backdrop-filter:blur(18px);
  }

  .mobile-top-logo{
    display:block;
    width:46px;
    height:46px;
    border-radius:16px;
    object-fit:cover;
    border:1px solid rgba(109,94,252,.22);
    box-shadow:var(--shadow);
    flex:0 0 auto;
  }

  .topbar-title{
    flex:1;
    font-size:18px;
    font-weight:950;
    letter-spacing:-.035em;
  }

  .topbar-title strong::before{ display:none; }

  .container{
    padding-left:14px !important;
    padding-right:14px !important;
    padding-top:14px !important;
    width:100%;
    max-width:100%;
    overflow-x:hidden;
  }
}

@media (max-width:560px){
  .topbar{
    padding:12px 14px;
    gap:10px;
  }

  .mobile-top-logo{
    width:42px;
    height:42px;
    border-radius:14px;
  }

  .mobile-menu-btn{
    width:42px;
    height:42px;
    border-radius:14px;
  }

  .topbar-title{ font-size:17px; }

  .container{
    padding-left:12px !important;
    padding-right:12px !important;
  }
}
</style>
</head>

<body>
<div class="mobile-sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="app">

  <aside class="sidebar" id="sidebar">

    <div class="sidebar-head">
      <div class="logo">
        <img class="logo-img" src="/trading-journal/assets/img/logo.png" alt="NXLOG">
        <div class="logo-text">
          <div class="logo-title">NXLOG</div>
          <div class="logo-sub">Analytics</div>
        </div>
      </div>

      <button class="sidebar-toggle" id="sidebarToggleTop" type="button" aria-label="Collapse sidebar">
        <svg class="collapse-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <rect class="collapse-panel" x="4" y="5" width="16" height="14" rx="4" stroke="currentColor" stroke-width="2"/>
          <path class="collapse-panel" d="M10 5v14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path class="collapse-arrow" d="M15 9l-3 3 3 3" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>

    <nav class="sidebar-nav">

      <a class="nav-item <?= $current==='dashboard'?'active':'' ?>" href="/trading-journal/dashboard.php" data-title="Dashboard">
        <span class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span>
        <span class="txt">Dashboard</span>
      </a>

      <a class="nav-item <?= $current==='log'?'active':'' ?>" href="/trading-journal/log.php" data-title="Log">
        <span class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M7 4h11a2 2 0 0 1 2 2v14a2 2 0 0 0-2-2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M7 8h9M7 12h9M7 16h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
        <span class="txt">Log</span>
      </a>

      <a class="nav-item <?= $current==='trade-history'?'active':'' ?>" href="/trading-journal/trade-history.php" data-title="Trade History">
        <span class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M8 6h12M8 12h12M8 18h12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M4 6h.01M4 12h.01M4 18h.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
        <span class="txt">Trade History</span>
      </a>

      <a class="nav-item <?= $current==='review'?'active':'' ?>" href="/trading-journal/review_queue.php" data-title="Review">
        <span class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M9 11l3 3L22 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span>
        <span class="txt">Review</span>
      </a>

      <a class="nav-item <?= $current==='analytics'?'active':'' ?>" href="/trading-journal/analytics.php" data-title="Analytics">
        <span class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M4 19V5M4 19h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M8 15V9M12 19V7M16 12V9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
        <span class="txt">Analytics</span>
      </a>

      <a class="nav-item <?= $current==='insights'?'active':'' ?>" href="/trading-journal/insights.php" data-title="Insights">
        <span class="ico" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18">
            <path d="M12 3v3M12 18v3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M3 12h3M18 12h3M4.9 19.1 7 17M17 7l2.1-2.1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M9 12a3 3 0 1 1 6 0c0 1.2-.7 2.1-1.5 2.8-.5.5-.8 1-.8 1.7h-1.4c0-.7-.3-1.2-.8-1.7C9.7 14.1 9 13.2 9 12Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
          </svg>
        </span>
        <span class="txt">Insights</span>
      </a>

      <a class="nav-item <?= $current==='state'?'active':'' ?>" href="/trading-journal/state.php" data-title="State">
        <span class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M3 12h4l2-6 4 12 2-6h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        <span class="txt">State</span>
      </a>

      <a class="nav-item <?= $current==='reports'?'active':'' ?>" href="/trading-journal/reports.php" data-title="Reports">
        <span class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M14 2v6h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M8 13h8M8 17h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
        <span class="txt">Reports</span>
      </a>

      <a class="nav-item <?= $current==='rules'?'active':'' ?>" href="/trading-journal/rules.php" data-title="Rules">
        <span class="ico" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18">
            <path d="M8 5h13M8 12h13M8 19h13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M3.5 5.5l1 1 2-2M3.5 12.5l1 1 2-2M3.5 19.5l1 1 2-2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
        <span class="txt">Rules</span>
      </a>

      <a class="nav-item <?= $current==='mt5'?'active':'' ?>" href="/trading-journal/mt5_accounts.php" data-title="MT5 Accounts">
  <span class="ico" aria-hidden="true">
    <svg viewBox="0 0 24 24" width="18" height="18">
      <path d="M4 6h16M4 12h16M4 18h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M8 4v4M16 10v4M12 16v4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>
  </span>
  <span class="txt">MT5 Accounts</span>
</a>

      <a class="nav-item <?= $current==='settings'?'active':'' ?>" href="/trading-journal/settings.php" data-title="Settings">
        <span class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M19.4 15a7.8 7.8 0 0 0 .1-2l2-1.2-2-3.4-2.3.7a7.3 7.3 0 0 0-1.7-1L15 5H9l-.5 3.1a7.3 7.3 0 0 0-1.7 1L4.5 8.4l-2 3.4 2 1.2a7.8 7.8 0 0 0 .1 2l-2 1.2 2 3.4 2.3-.7a7.3 7.3 0 0 0 1.7 1L9 23h6l.5-3.1a7.3 7.3 0 0 0 1.7-1l2.3.7 2-3.4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span>
        <span class="txt">Settings</span>
      </a>

      <a class="nav-item" href="/trading-journal/logout.php" data-title="Logout">
        <span class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M10 17h-1a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M15 12H3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M15 7l5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        <span class="txt">Logout</span>
      </a>

    </nav>

    <div class="sidebar-foot">
      <button class="nav-item nav-btn" id="themeToggle" type="button" data-title="Theme">
        <span class="ico" aria-hidden="true">
          <span class="theme-icon-wrap">
            <svg class="theme-svg theme-sun" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/>
              <path d="M12 2V4M12 20V22M4 12H2M22 12H20M19.78 4.22L17.66 6.34M6.34 17.66L4.22 19.78M19.78 19.78L17.66 17.66M6.34 6.34L4.22 4.22" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <svg class="theme-svg theme-moon" viewBox="0 0 24 24" fill="none">
              <path d="M21 12.8A9 9 0 1 1 11.2 3c0 .2-.1.5-.1.8A7.5 7.5 0 0 0 18.6 11c.8 0 1.6-.1 2.4-.4Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
            </svg>
          </span>
        </span>
        <span class="txt" id="themeText">Theme</span>
      </button>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <img class="mobile-top-logo" src="/trading-journal/assets/img/logo.png" alt="NXLOG">

      <div class="topbar-title"><strong><?= e($pageTitle) ?></strong></div>

      <button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="Open navigation">
        <svg class="mobile-menu-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path class="mobile-menu-line" d="M5 7h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path class="mobile-menu-line" d="M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path class="mobile-menu-line" d="M5 17h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </button>
    </div>

    <div class="container">