<?php
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "reports";
$pageTitle = "Reports • NXLOG Analytics";

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

require_once __DIR__ . "/partials/app_header.php";
?>

<style>
.reports-wrap{ display:grid; gap:14px; }

.page-head{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:12px;
  flex-wrap:wrap;
}
.page-head h1{
  margin:0;
  font-size:28px;
  font-weight:900;
}
.page-head p{
  margin:6px 0 0;
  color:var(--muted);
}
.page-head-actions{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
}

.hero-panel,
.feature-panel,
.roadmap-panel{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:18px;
  box-shadow:var(--shadow);
}

.hero-panel,
.roadmap-panel{ padding:16px; }

.feature-grid{
  display:grid;
  grid-template-columns:repeat(3,minmax(0,1fr));
  gap:14px;
}
.feature-panel{
  padding:16px;
}
.feature-title{
  margin:0 0 8px;
  font-size:18px;
  font-weight:900;
}
.feature-sub{
  color:var(--muted);
  font-size:13px;
  font-weight:700;
  line-height:1.6;
}

.status-pill{
  display:inline-flex;
  align-items:center;
  gap:6px;
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:999px;
  padding:6px 10px;
  font-size:12px;
  font-weight:900;
  color:#eab308;
}

.roadmap-panel h3{
  margin:0 0 12px;
  font-size:20px;
  font-weight:900;
}
.roadmap-list{
  display:grid;
  gap:10px;
}
.roadmap-item{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:12px;
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:14px;
  padding:12px 14px;
}
.roadmap-item-title{
  font-weight:900;
  margin-bottom:4px;
}
.roadmap-item-sub{
  color:var(--muted);
  font-size:12px;
  font-weight:700;
}

@media (max-width: 1000px){
  .feature-grid{ grid-template-columns:1fr; }
}
</style>

<div class="reports-wrap">

  <div class="page-head">
    <div>
      <h1>Reports</h1>
      <p>Weekly and monthly summaries will live here, with export-ready reporting later.</p>
    </div>

    <div class="page-head-actions">
      <a class="btn secondary" href="/trading-journal/dashboard.php">Back to Dashboard</a>
      <a class="btn" href="/trading-journal/analytics.php">Open Analytics</a>
    </div>
  </div>

  <div class="hero-panel">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
      <div>
        <strong>Reports Workspace</strong>
        <div style="margin-top:6px;color:var(--muted);font-size:13px;font-weight:700">
          This section will become the structured reporting center for trader summaries, performance exports, and review snapshots.
        </div>
      </div>
      <span class="status-pill">Coming Soon</span>
    </div>
  </div>

  <div class="feature-grid">
    <div class="feature-panel">
      <h3 class="feature-title">Weekly Review</h3>
      <div class="feature-sub">
        A clean summary of trade count, win rate, net performance, major mistakes, and execution notes for the week.
      </div>
    </div>

    <div class="feature-panel">
      <h3 class="feature-title">Monthly Summary</h3>
      <div class="feature-sub">
        A broader performance report covering profitability, discipline trends, strategy output, and consistency over the month.
      </div>
    </div>

    <div class="feature-panel">
      <h3 class="feature-title">Export Options</h3>
      <div class="feature-sub">
        PDF and CSV export support will be added here so users can save, share, or archive their reporting data.
      </div>
    </div>
  </div>

  <div class="roadmap-panel">
    <h3>Planned Report Modules</h3>

    <div class="roadmap-list">
      <div class="roadmap-item">
        <div>
          <div class="roadmap-item-title">Weekly performance report</div>
          <div class="roadmap-item-sub">Summarizes weekly R, wins, losses, review count, and top mistakes.</div>
        </div>
        <span class="status-pill">Planned</span>
      </div>

      <div class="roadmap-item">
        <div>
          <div class="roadmap-item-title">Monthly performance report</div>
          <div class="roadmap-item-sub">Summarizes monthly profitability, discipline score movement, and active trading days.</div>
        </div>
        <span class="status-pill">Planned</span>
      </div>

      <div class="roadmap-item">
        <div>
          <div class="roadmap-item-title">PDF / CSV export</div>
          <div class="roadmap-item-sub">Lets users export reports for personal records, mentors, or accountability tracking.</div>
        </div>
        <span class="status-pill">Planned</span>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>