<?php
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "insights";
$pageTitle = "Insights • NXLOG Analytics";

require_once __DIR__ . "/partials/app_header.php";
?>

<div class="card">
  <h2>Insights</h2>
  <p class="small">
    System-generated learning will live here: repeated mistakes, best conditions, and execution leaks.
  </p>
  <div class="small" style="color:var(--muted)">
    Coming next: “Top leak”, “Best session”, “Best setup conditions”.
  </div>
</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>
