<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "settings";
$pageTitle = "Settings • NXLOG Analytics";

require_once __DIR__ . "/partials/app_header.php";
?>

<div class="card">
  <h2>Settings</h2>
  <p class="small">Basic account preferences (MVP).</p>

  <div class="small">
    <b>Name:</b> <?= e($_SESSION['user_name'] ?? '') ?><br>
    <b>Support:</b> support@example.com
  </div>

  <div style="margin-top:12px">
    <a class="btn" href="/trading-journal/logout.php">Logout</a>
  </div>
</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>
