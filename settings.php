<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "settings";
$pageTitle = "Settings • NXLOG Analytics";

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$user_name = trim((string)($_SESSION['user_name'] ?? ''));
$support_email = "support@example.com";

require_once __DIR__ . "/partials/app_header.php";
?>

<style>
.settings-wrap{ display:grid; gap:14px; }

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

.profile-panel,
.preferences-panel,
.support-panel{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:18px;
  box-shadow:var(--shadow);
  padding:16px;
}

.settings-grid{
  display:grid;
  grid-template-columns:1.2fr .8fr;
  gap:14px;
}

.panel-title{
  margin:0 0 12px;
  font-size:20px;
  font-weight:900;
}
.panel-sub{
  color:var(--muted);
  font-size:13px;
  font-weight:700;
  margin-top:-4px;
  margin-bottom:12px;
}

.info-list{
  display:grid;
  gap:0;
}
.info-row{
  display:flex;
  justify-content:space-between;
  gap:16px;
  padding:14px 0;
  border-bottom:1px solid var(--border);
}
.info-row:last-child{
  border-bottom:none;
}
.info-label{
  color:var(--muted);
  font-size:14px;
  font-weight:700;
}
.info-value{
  text-align:right;
  font-size:14px;
  font-weight:900;
  word-break:break-word;
}

.pill{
  display:inline-flex;
  align-items:center;
  gap:6px;
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:999px;
  padding:6px 10px;
  font-size:12px;
  font-weight:900;
}
.pill.muted{
  color:var(--muted);
}

.stack{
  display:grid;
  gap:14px;
}

.notice-box{
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:14px;
  padding:14px;
}
.notice-title{
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:.03em;
  color:var(--muted);
  font-weight:900;
  margin-bottom:6px;
}
.notice-text{
  font-size:14px;
  line-height:1.6;
}

.action-row{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  margin-top:14px;
}

@media (max-width: 900px){
  .settings-grid{
    grid-template-columns:1fr;
  }
}
@media (max-width: 640px){
  .info-row{
    flex-direction:column;
    align-items:flex-start;
  }
  .info-value{
    text-align:left;
  }
}
</style>

<div class="settings-wrap">

  <div class="page-head">
    <div>
      <h1>Settings</h1>
      <p>Basic account preferences and support details for the MVP.</p>
    </div>

    <div class="page-head-actions">
      <a class="btn secondary" href="/trading-journal/dashboard.php">Back to Dashboard</a>
    </div>
  </div>

  <div class="settings-grid">

    <div class="profile-panel">
      <h3 class="panel-title">Account</h3>
      <div class="panel-sub">Your current account details in NXLOG.</div>

      <div class="info-list">
        <div class="info-row">
          <div class="info-label">Name</div>
          <div class="info-value"><?= e($user_name !== '' ? $user_name : '—') ?></div>
        </div>

        <div class="info-row">
          <div class="info-label">Support</div>
          <div class="info-value"><?= e($support_email) ?></div>
        </div>

        <div class="info-row">
          <div class="info-label">Product Stage</div>
          <div class="info-value">
            <span class="pill muted">MVP</span>
          </div>
        </div>
      </div>

      <div class="action-row">
        <a class="btn" href="/trading-journal/logout.php">Logout</a>
      </div>
    </div>

    <div class="stack">
      <div class="preferences-panel">
        <h3 class="panel-title">Preferences</h3>
        <div class="panel-sub">More account and app preferences will be added here later.</div>

        <div class="notice-box">
          <div class="notice-title">Planned</div>
          <div class="notice-text">
            Theme preferences, notification settings, account editing, billing controls, and connected platform settings will be added in future iterations.
          </div>
        </div>
      </div>

      <div class="support-panel">
        <h3 class="panel-title">Support</h3>
        <div class="panel-sub">Need help or want to report an issue?</div>

        <div class="notice-box">
          <div class="notice-title">Contact</div>
          <div class="notice-text">
            Reach support through <strong><?= e($support_email) ?></strong>. This section can later include help links, FAQs, and direct issue reporting.
          </div>
        </div>
      </div>
    </div>

  </div>

</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>