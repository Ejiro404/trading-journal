<?php
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "insights";
$pageTitle = "Insights • NXLOG Analytics";

require_once __DIR__ . "/partials/app_header.php";
?>

<style>
.insights-wrap{
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
.insight-panel,
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

.status-pill{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  border:1px solid var(--border);
  background:var(--pill);
  color:#eab308;
  border-radius:999px;
  padding:6px 10px;
  font-size:12px;
  font-weight:900;
  white-space:nowrap;
}

.insight-grid{
  display:grid;
  grid-template-columns:repeat(3,minmax(0,1fr));
  gap:14px;
}

.insight-panel{
  padding:16px;
}

.insight-title{
  margin:0 0 8px;
  font-size:18px;
  line-height:1.1;
  font-weight:900;
}

.insight-sub{
  color:var(--muted);
  font-size:13px;
  font-weight:700;
  line-height:1.6;
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
}

.roadmap-title{
  font-weight:900;
  margin-bottom:4px;
  line-height:1.35;
}

.roadmap-sub{
  color:var(--muted);
  font-size:12px;
  font-weight:700;
  line-height:1.5;
}

@media (max-width:1000px){
  .insight-grid{
    grid-template-columns:1fr;
  }
}

@media (max-width:720px){
  .insights-wrap{
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
  .insight-panel,
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

  .status-pill{
    width:max-content;
    font-size:10px;
    padding:5px 8px;
  }

  .insight-grid{
    gap:10px;
  }

  .insight-title{
    font-size:16px;
    margin-bottom:6px;
  }

  .insight-sub{
    font-size:11px;
    line-height:1.5;
  }

  .roadmap-panel h3{
    font-size:17px;
    margin-bottom:10px;
  }

  .roadmap-item{
    display:grid;
    gap:8px;
    padding:10px;
    border-radius:13px;
  }

  .roadmap-title{
    font-size:13px;
  }

  .roadmap-sub{
    font-size:10px;
  }
}

@media (max-width:390px){
  .page-head h1{
    font-size:20px;
  }
}
</style>

<div class="insights-wrap">

  <div class="page-head">
    <div>
      <h1>Insights</h1>
      <p>System-generated learning will live here: repeated mistakes, best conditions, and execution leaks.</p>
    </div>

    <div class="page-head-actions">
      <a class="btn secondary" href="/trading-journal/dashboard.php">Back to Dashboard</a>
      <a class="btn" href="/trading-journal/analytics.php">Open Analytics</a>
    </div>
  </div>

  <div class="hero-panel">
    <div class="hero-inner">
      <div>
        <div class="hero-title">Insight Engine</div>
        <div class="hero-sub">
          This page will become the trader learning layer — surfacing repeated leaks, strongest conditions, and review-based improvement patterns.
        </div>
      </div>

      <span class="status-pill">Coming Soon</span>
    </div>
  </div>

  <div class="insight-grid">
    <div class="insight-panel">
      <h3 class="insight-title">Top Leak</h3>
      <div class="insight-sub">
        Automatically detect the mistake, setup condition, or behavior that damages performance most often.
      </div>
    </div>

    <div class="insight-panel">
      <h3 class="insight-title">Best Session</h3>
      <div class="insight-sub">
        Identify the trading session where execution and outcomes are strongest over time.
      </div>
    </div>

    <div class="insight-panel">
      <h3 class="insight-title">Best Setup Conditions</h3>
      <div class="insight-sub">
        Surface patterns around strategy, state, session, and market context that produce better trades.
      </div>
    </div>
  </div>

  <div class="roadmap-panel">
    <h3>Coming Next</h3>

    <div class="roadmap-list">
      <div class="roadmap-item">
        <div>
          <div class="roadmap-title">Repeated mistake detection</div>
          <div class="roadmap-sub">Find common rule breaks and review tags that appear across losing trades.</div>
        </div>
        <span class="status-pill">Planned</span>
      </div>

      <div class="roadmap-item">
        <div>
          <div class="roadmap-title">Best condition tracking</div>
          <div class="roadmap-sub">Compare performance by session, setup, state, and strategy.</div>
        </div>
        <span class="status-pill">Planned</span>
      </div>

      <div class="roadmap-item">
        <div>
          <div class="roadmap-title">Execution leak summary</div>
          <div class="roadmap-sub">Convert review data into clear behavioral improvement prompts.</div>
        </div>
        <span class="status-pill">Planned</span>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>