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
/* ===== THEME BOOT (AUTO fallback) ===== */
(function () {
  var saved = localStorage.getItem("nx_theme"); // 'dark' | 'light' | null
  if (saved !== "dark" && saved !== "light") saved = null;

  var prefersDark = false;
  try { prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches; } catch(e){}

  var useDark = saved ? (saved === "dark") : prefersDark;
  document.documentElement.classList.toggle("dark", useDark);
})();
</script>

<style>
/* Keep your existing CSS file intact — only small overrides here */

/* Make footer not "far away" */
.sidebar-foot{
  margin-top:12px !important;   /* overrides margin-top:auto from style.css */
  padding-top:10px;
  border-top:1px solid var(--border);
}

/* Nav item icon container (works with your .nav-item) */
.nav-item .ico{
  width:38px;height:38px;
  border-radius:14px;
  border:1px solid var(--border);
  background:var(--pill);
  display:grid;
  place-items:center;
  flex:0 0 auto;
}

/* Button that looks exactly like links */
.nav-btn{
  width:100%;
  text-align:left;
  background:transparent;
  border:1px solid transparent;
  cursor:pointer;
}
.nav-btn:hover{
  background:var(--pill);
  border-color:var(--border);
  box-shadow:var(--shadow);
  transform:translateY(-1px);
}

/* Collapsed sidebar */
.sb-collapsed .sidebar{ width:86px; }
.sb-collapsed .sidebar .txt,
.sb-collapsed .logo-text{ display:none; }
.sb-collapsed .sidebar .nav-item{ justify-content:center; }
.sb-collapsed .sidebar .nav-item .ico{ margin:0; }

/* Tooltip on hover when collapsed */
.nav-item{ position:relative; }
.nav-item[data-title]:hover::after{
  content: attr(data-title);
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
html:not(.sb-collapsed) .nav-item[data-title]:hover::after{ display:none; }
</style>
</head>

<body>
<div class="app">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <!-- DO NOT change your logo title/sub (kept as-is) -->
    <div class="sidebar-head">
      <div class="logo">
        <div class="logo-title">NXLOG</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a class="nav-item <?= $current==='dashboard'?'active':'' ?>" href="/trading-journal/dashboard.php" data-title="Dashboard">
        <span class="ico" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18">
            <path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
          </svg>
        </span>
        <span class="txt">Dashboard</span>
      </a>

      <a class="nav-item <?= $current==='log'?'active':'' ?>" href="/trading-journal/log.php" data-title="Log">
        <span class="ico" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18">
            <path d="M7 4h11a2 2 0 0 1 2 2v14a2 2 0 0 0-2-2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
            <path d="M7 8h9M7 12h9M7 16h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </span>
        <span class="txt">Log</span>
      </a>

      <a class="nav-item <?= $current==='review'?'active':'' ?>" href="/trading-journal/review_queue.php" data-title="Review">
        <span class="ico" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18">
            <path d="M9 11l3 3L22 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
          </svg>
        </span>
        <span class="txt">Review</span>
      </a>

      <a class="nav-item <?= $current==='analytics'?'active':'' ?>" href="/trading-journal/analytics.php" data-title="Analytics">
        <span class="ico" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18">
            <path d="M4 19V5M4 19h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M8 15V9M12 19V7M16 12V9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </span>
        <span class="txt">Analytics</span>
      </a>

      <a class="nav-item <?= $current==='state'?'active':'' ?>" href="/trading-journal/state.php" data-title="State">
        <span class="ico" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18">
            <path d="M3 12h4l2-6 4 12 2-6h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
        <span class="txt">State</span>
      </a>

      <a class="nav-item <?= $current==='reports'?'active':'' ?>" href="/trading-journal/reports.php" data-title="Reports">
        <span class="ico" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
            <path d="M14 2v6h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
            <path d="M8 13h8M8 17h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </span>
        <span class="txt">Reports</span>
      </a>

      <a class="nav-item <?= $current==='settings'?'active':'' ?>" href="/trading-journal/settings.php" data-title="Settings">
        <span class="ico" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18">
            <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z" fill="none" stroke="currentColor" stroke-width="2"/>
            <path d="M19.4 15a7.8 7.8 0 0 0 .1-2l2-1.2-2-3.4-2.3.7a7.3 7.3 0 0 0-1.7-1L15 5H9l-.5 3.1a7.3 7.3 0 0 0-1.7 1L4.5 8.4l-2 3.4 2 1.2a7.8 7.8 0 0 0 .1 2l-2 1.2 2 3.4 2.3-.7a7.3 7.3 0 0 0 1.7 1L9 23h6l.5-3.1a7.3 7.3 0 0 0 1.7-1l2.3.7 2-3.4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
          </svg>
        </span>
        <span class="txt">Settings</span>
      </a>

      <a class="nav-item" href="/trading-journal/logout.php" data-title="Logout">
        <span class="ico" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18">
            <path d="M10 17h-1a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M15 12H3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M15 7l5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
        <span class="txt">Logout</span>
      </a>
    </nav>

    <!-- Theme toggle (NOT distant, styled like nav-item) -->
    <div class="sidebar-foot">
      <button class="nav-item nav-btn" id="themeToggle" type="button" data-title="Theme">
        <span class="ico" id="themeIcon" aria-hidden="true"></span>
        <span class="txt" id="themeText">Theme</span>
      </button>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <div class="topbar">
      <!-- Sidebar collapse button -->
      <button class="iconbtn" id="sidebarBtn" type="button" aria-label="Toggle sidebar">
        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
          <path d="M4 6h16M4 12h16M4 18h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </button>

      <!-- Removed username from topbar as requested -->
      <div class="topbar-title"><strong><?= e($pageTitle) ?></strong></div>
      <div></div>
    </div>

    <div class="container">