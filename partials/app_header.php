<?php
require_once __DIR__ . "/../config/app.php";

if (session_status() === PHP_SESSION_NONE) session_start();

if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$pageTitle = $pageTitle ?? $app["name"];
$current = $current ?? "";
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/trading-journal/assets/css/style.css">
  <title><?= e($pageTitle) ?></title>
  <style>
    .shell{display:grid;grid-template-columns:260px 1fr;gap:14px;align-items:start}
    @media (max-width: 920px){ .shell{grid-template-columns:1fr} }

    .topbar2{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 0}
    .brand2{display:flex;align-items:center;gap:10px}
    .logo2{
      width:36px;height:36px;border-radius:12px;display:grid;place-items:center;
      background:var(--card);border:1px solid var(--border);box-shadow:var(--shadow);
      font-weight:900;color:var(--accent);
    }
    .sub2{font-size:12px;color:var(--muted);font-weight:700}
    .top-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;align-items:center}
    .ghost{background:var(--pill)!important;color:var(--text)!important;border:1px solid var(--border)!important}

    .navdot{width:10px;height:10px;border-radius:999px;background:var(--border)}
    .active .navdot{background:var(--accent)}
    .active{border-color:var(--accent)!important}
  </style>
</head>
<body>
<div class="container">

  <div class="topbar2">
    <div class="brand2">
      <div class="logo2"><?= e($app["logo_text"]) ?></div>
      <div>
        <div style="font-weight:900;line-height:1.1"><?= e($app["name"]) ?></div>
        <div class="sub2"><?= e($app["version"]) ?> • Structured journaling</div>
      </div>
    </div>

    <div class="top-actions">
      <!-- FIXED: New Trade now points to log_new.php -->
      <a class="btn ghost" href="/trading-journal/log_new.php">New Trade</a>
      <a class="btn ghost" href="/trading-journal/review_queue.php">Review</a>
      <a class="btn" href="/trading-journal/logout.php">Logout</a>
    </div>
  </div>

  <div class="shell">
    <aside class="card" style="height:fit-content">
      <div style="font-weight:900;margin-bottom:10px">Workspace</div>

      <div style="display:flex;flex-direction:column;gap:8px">
        <a class="btn <?= $current==='dashboard'?'active':'' ?> <?= $current==='dashboard'?'':'ghost' ?>" href="/trading-journal/dashboard.php">
          <span class="navdot"></span> Dashboard
        </a>

        <!-- FIXED: Log points to log.php -->
        <a class="btn <?= $current==='log'?'active':'' ?> <?= $current==='log'?'':'ghost' ?>" href="/trading-journal/log.php">
          <span class="navdot"></span> Log
        </a>

        <a class="btn <?= $current==='review'?'active':'' ?> <?= $current==='review'?'':'ghost' ?>" href="/trading-journal/review_queue.php">
          <span class="navdot"></span> Review
        </a>

        <a class="btn <?= $current==='analytics'?'active':'' ?> <?= $current==='analytics'?'':'ghost' ?>" href="/trading-journal/analytics.php">
          <span class="navdot"></span> Analytics
        </a>

        <a class="btn <?= $current==='state'?'active':'' ?> <?= $current==='state'?'':'ghost' ?>" href="/trading-journal/state.php">
          <span class="navdot"></span> State
        </a>

        <a class="btn <?= $current==='reports'?'active':'' ?> <?= $current==='reports'?'':'ghost' ?>" href="/trading-journal/reports.php">
          <span class="navdot"></span> Reports
        </a>

        <a class="btn <?= $current==='insights'?'active':'' ?> <?= $current==='insights'?'':'ghost' ?>" href="/trading-journal/insights.php">
          <span class="navdot"></span> Insights
        </a>

        <a class="btn <?= $current==='settings'?'active':'' ?> <?= $current==='settings'?'':'ghost' ?>" href="/trading-journal/settings.php">
          <span class="navdot"></span> Settings
        </a>
      </div>

      <div style="margin-top:14px" class="small">
        Signed in as <b><?= e($_SESSION['user_name'] ?? 'User') ?></b>
      </div>
    </aside>

    <main>
