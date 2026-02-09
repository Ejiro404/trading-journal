<?php
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "reports";
$pageTitle = "Reports • NXLOG Analytics";

require_once __DIR__ . "/partials/app_header.php";
?>

<div class="card">
  <h2>Reports</h2>
  <p class="small">
    Weekly and monthly summaries will live here (PDF/CSV export later).
  </p>
  <div class="small" style="color:var(--muted)">
    Planned: weekly review, monthly summary, export.
  </div>
</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>
