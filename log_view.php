<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "log";
$pageTitle = "Trade • NXLOG Analytics";

$user_id = (int)($_SESSION['user_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM trades WHERE id=? AND user_id=? LIMIT 1");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$trade = $stmt->get_result()->fetch_assoc();
if (!$trade) { http_response_code(404); exit("Trade not found."); }

$is_closed = !empty($trade['exit_time']);

require_once __DIR__ . "/partials/app_header.php";
?>

<style>
.trade-hero{
  padding:24px;
  border-radius:18px;
  border:1px solid var(--border);
  background:var(--card);
  box-shadow:var(--shadow);
}
.trade-title{
  font-size:28px;
  font-weight:950;
  letter-spacing:-.02em;
}
.trade-meta{
  margin-top:6px;
  font-size:13px;
  color:var(--muted);
  font-weight:700;
}

.info-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
  gap:18px;
  margin-top:18px;
}

.info-card{
  padding:18px;
  border-radius:16px;
  border:1px solid var(--border);
  background:var(--card);
  box-shadow:var(--shadow);
}

.info-card h3{
  font-size:14px;
  font-weight:900;
  letter-spacing:.05em;
  text-transform:uppercase;
  color:var(--muted);
  margin-bottom:14px;
}

.kv{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:10px;
  font-size:14px;
}
.kv-label{
  color:var(--muted);
  font-weight:700;
}
.kv-value{
  font-weight:900;
}

.badge-pill{
  padding:6px 10px;
  border-radius:999px;
  font-size:12px;
  font-weight:900;
  border:1px solid var(--border);
  background:var(--pill);
}

.badge-good{ color:#16a34a; }
.badge-bad{ color:#ef4444; }

.notes-block{
  margin-top:20px;
  padding:18px;
  border-radius:16px;
  border:1px solid var(--border);
  background:var(--card);
  box-shadow:var(--shadow);
}
.notes-block h3{
  font-size:14px;
  font-weight:900;
  text-transform:uppercase;
  color:var(--muted);
  margin-bottom:12px;
}
.notes-text{
  font-size:14px;
  line-height:1.6;
  color:var(--text);
}
</style>

<div class="trade-hero">
  <div class="trade-title">
    <?= e($trade['symbol']) ?> • <?= e($trade['direction']) ?>
  </div>
  <div class="trade-meta">
    <?= e($trade['market']) ?>
    <?= $trade['session'] ? " • " . e($trade['session']) : "" ?>
    • <?= e($trade['entry_time']) ?>
  </div>
</div>

<div class="info-grid">

  <!-- CONTEXT -->
  <div class="info-card">
    <h3>Context</h3>

    <div class="kv">
      <div class="kv-label">Strategy</div>
      <div class="kv-value">
        <?= $trade['setup'] ? '<span class="badge-pill">'.e($trade['setup']).'</span>' : '—' ?>
      </div>
    </div>

    <div class="kv">
      <div class="kv-label">Risk (1R)</div>
      <div class="kv-value"><?= number_format((float)$trade['risk_amount'],2) ?></div>
    </div>

    <div class="kv">
      <div class="kv-label">Legacy tags</div>
      <div class="kv-value"><?= e($trade['tags'] ?? '—') ?></div>
    </div>
  </div>

  <!-- RESULT -->
  <div class="info-card">
    <h3>Result</h3>

    <div class="kv">
      <div class="kv-label">Exit time</div>
      <div class="kv-value">
        <?= $trade['exit_time'] ? e($trade['exit_time']) : 'Open' ?>
      </div>
    </div>

    <div class="kv">
      <div class="kv-label">P/L</div>
      <div class="kv-value <?= (float)$trade['pnl_amount'] >= 0 ? 'badge-good' : 'badge-bad' ?>">
        <?= $trade['pnl_amount']===null ? "-" : number_format((float)$trade['pnl_amount'],2) ?>
      </div>
    </div>

    <div class="kv">
      <div class="kv-label">R Multiple</div>
      <div class="kv-value <?= (float)$trade['r_multiple'] >= 0 ? 'badge-good' : 'badge-bad' ?>">
        <?= $trade['r_multiple']===null ? "-" : number_format((float)$trade['r_multiple'],2) . "R" ?>
      </div>
    </div>

    <div class="kv">
      <div class="kv-label">Outcome</div>
      <div class="kv-value"><?= e($trade['outcome'] ?? '—') ?></div>
    </div>

    <div class="kv">
      <div class="kv-label">Review</div>
      <div class="kv-value">
        <?= (int)$trade['is_reviewed']===1 
          ? '<span class="badge-pill badge-good">Reviewed</span>' 
          : '<span class="badge-pill">Pending</span>' ?>
      </div>
    </div>

  </div>

</div>

<!-- NOTES -->
<div class="notes-block">
  <h3>Pre-trade Notes</h3>
  <div class="notes-text">
    <?= nl2br(e($trade['notes_pre'] ?? '—')) ?>
  </div>
</div>

<div class="notes-block">
  <h3>Post-trade Notes</h3>
  <div class="notes-text">
    <?= nl2br(e($trade['notes_post'] ?? '—')) ?>
  </div>
</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>
