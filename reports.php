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
.reports-wrap{
  display:grid;
  gap:14px;
  width:100%;
  max-width:100%;
  overflow:hidden;
}

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
  line-height:1.05;
  font-weight:900;
  letter-spacing:-.03em;
}

.page-head p{
  margin:6px 0 0;
  color:var(--muted);
  line-height:1.6;
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
  min-width:0;
}

.hero-panel,
.roadmap-panel{
  padding:16px;
}

.hero-inner{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:12px;
  flex-wrap:wrap;
}

.hero-title{
  font-size:18px;
  font-weight:900;
  letter-spacing:-.02em;
}

.hero-sub{
  margin-top:6px;
  color:var(--muted);
  font-size:13px;
  font-weight:700;
  line-height:1.6;
  max-width:760px;
}

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
  line-height:1.1;
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
  justify-content:center;
  gap:6px;
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:999px;
  padding:6px 10px;
  font-size:12px;
  font-weight:900;
  color:#eab308;
  white-space:nowrap;
}

.roadmap-panel h3{
  margin:0 0 12px;
  font-size:20px;
  line-height:1.1;
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
  min-width:0;
}

.roadmap-item-title{
  font-weight:900;
  margin-bottom:4px;
  line-height:1.35;
}

.roadmap-item-sub{
  color:var(--muted);
  font-size:12px;
  font-weight:700;
  line-height:1.5;
}

@media (max-width:1000px){
  .feature-grid{
    grid-template-columns:1fr;
  }
}

@media (max-width:720px){
  .reports-wrap{
    gap:10px;
    overflow:visible;
  }

  .page-head{
    display:grid;
    gap:10px;
  }

  .page-head h1{
    font-size:22px;
  }

  .page-head p{
    font-size:12px;
    line-height:1.45;
  }

  .page-head-actions{
    display:grid;
    grid-template-columns:1fr;
    gap:8px;
    width:100%;
  }

  .page-head-actions .btn{
    width:100%;
    min-height:36px;
    padding:8px 10px;
    font-size:12px;
  }

  .hero-panel,
  .feature-panel,
  .roadmap-panel{
    padding:13px;
    border-radius:16px;
  }

  .hero-inner{
    display:grid;
    gap:10px;
  }

  .hero-title{
    font-size:16px;
  }

  .hero-sub{
    font-size:11px;
    line-height:1.5;
  }

  .feature-grid{
    gap:10px;
  }

  .feature-title{
    font-size:16px;
    margin-bottom:6px;
  }

  .feature-sub{
    font-size:11px;
    line-height:1.5;
  }

  .roadmap-panel h3{
    font-size:17px;
    margin-bottom:10px;
  }

  .roadmap-list{
    gap:8px;
  }

  .roadmap-item{
    display:grid;
    gap:8px;
    padding:10px;
    border-radius:13px;
  }

  .roadmap-item-title{
    font-size:13px;
  }

  .roadmap-item-sub{
    font-size:10px;
  }

  .status-pill{
    width:max-content;
    font-size:10px;
    padding:5px 8px;
  }
}

@media (max-width:390px){
  .page-head h1{
    font-size:20px;
  }

  .feature-title{
    font-size:15px;
  }
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
    <div class="hero-inner">
      <div>
        <div class="hero-title">Reports Workspace</div>
        <div class="hero-sub">
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