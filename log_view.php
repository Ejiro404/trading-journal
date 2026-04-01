<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$user_id  = (int)($_SESSION['user_id'] ?? 0);
$trade_id = (int)($_GET['id'] ?? 0);

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function fmt_price($value, $decimals = 5) {
    if ($value === null || $value === '') return '-';
    return number_format((float)$value, $decimals);
}

function fmt_money($value) {
    if ($value === null || $value === '') return '-';
    return number_format((float)$value, 2);
}

function fmt_dt($value) {
    if (!$value) return '-';
    $ts = strtotime((string)$value);
    return $ts ? date('d M Y, h:i A', $ts) : '-';
}

if ($trade_id <= 0) {
    http_response_code(400);
    die("Invalid trade ID.");
}

$sql = "
    SELECT *
    FROM trades
    WHERE id = ? AND user_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $trade_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$trade = $result ? $result->fetch_assoc() : null;

if (!$trade) {
    http_response_code(404);
    die("Trade not found or access denied.");
}

$direction = strtoupper((string)($trade['direction'] ?? ''));
$directionClass = $direction === 'BUY' ? 'buy' : 'sell';

$source = strtolower((string)($trade['source'] ?? 'manual'));
$sourceClass = $source === 'mt5' ? 'mt5' : 'manual';

$outcome = strtolower((string)($trade['outcome'] ?? 'breakeven'));
$outcomeClass = in_array($outcome, ['win', 'loss', 'breakeven'], true) ? $outcome : 'breakeven';

$pnl = (float)($trade['pnl_amount'] ?? 0);
$pnlClass = $pnl > 0 ? 'value-win' : ($pnl < 0 ? 'value-loss' : 'value-breakeven');

$isClosed = (
    !empty($trade['exit_time']) ||
    $trade['exit_price'] !== null ||
    $trade['pnl_amount'] !== null ||
    $trade['r_multiple'] !== null
);

$tradeState = $isClosed ? 'Closed' : 'Open';
$tradeStateClass = $isClosed ? 'closed' : 'open';

$pageTitle = "Trade Details • NXLOG";
$current   = "trade-history";
require_once __DIR__ . "/partials/app_header.php";
?>

<style>
.log-view-wrap{ display:grid; gap:14px; }

.topbar{
  display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;
}
.topbar-left h1{
  margin:0;
  font-size:28px;
  font-weight:900;
}
.topbar-left p{
  margin:6px 0 0;
  color:var(--muted);
}
.topbar-actions{
  display:flex; gap:10px; flex-wrap:wrap;
}

.hero-card,
.info-card{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:18px;
  box-shadow:var(--shadow);
}

.hero-card{ padding:20px; }
.hero-grid{
  display:grid;
  grid-template-columns:2fr 1fr;
  gap:18px;
  align-items:center;
}
.trade-title{
  display:flex;
  align-items:center;
  gap:10px;
  flex-wrap:wrap;
  margin-bottom:12px;
}
.trade-title h2{
  margin:0;
  font-size:28px;
  font-weight:900;
}
.mini-meta{
  display:flex;
  flex-wrap:wrap;
  gap:12px;
  color:var(--muted);
  font-size:14px;
}

.result-box{
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:16px;
  padding:18px;
  text-align:center;
}
.result-box h3{
  margin:0 0 8px;
  color:var(--muted);
  font-size:13px;
  font-weight:800;
}
.result-box .value{
  font-size:30px;
  font-weight:900;
  line-height:1.1;
  margin-bottom:6px;
}
.result-box .label{
  font-size:14px;
  font-weight:900;
}

.details-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:14px;
}
.info-card{ padding:18px; }
.info-card h3{
  margin:0 0 14px;
  font-size:19px;
  font-weight:900;
}
.info-list{ display:grid; gap:0; }

.info-row{
  display:flex;
  justify-content:space-between;
  gap:16px;
  padding:14px 0;
  border-bottom:1px solid var(--border);
}
.info-row:last-child{ border-bottom:none; }
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

.full-width{ grid-column:1 / -1; }

.note-box,
.tag-box,
.screenshot-box{
  border:1px dashed var(--border);
  background:var(--pill);
  border-radius:14px;
  padding:14px;
  line-height:1.6;
}
.note-section-title{
  font-size:12px;
  color:var(--muted);
  font-weight:900;
  text-transform:uppercase;
  letter-spacing:.03em;
  margin-bottom:6px;
}
.note-block + .note-block{
  margin-top:14px;
  padding-top:14px;
  border-top:1px solid var(--border);
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
.pill.buy{ color:#16a34a; }
.pill.sell{ color:#ef4444; }
.pill.manual{ color:#a855f7; }
.pill.mt5{ color:#2563eb; }
.pill.open{ color:#eab308; }
.pill.closed{ color:#16a34a; }
.pill.win{ color:#16a34a; }
.pill.loss{ color:#ef4444; }
.pill.breakeven{ color:#eab308; }

.value-win{ color:#16a34a; font-weight:900; }
.value-loss{ color:#ef4444; font-weight:900; }
.value-breakeven{ color:#eab308; font-weight:900; }

.muted{ color:var(--muted); }

.screenshot-image{
  width:100%;
  max-width:100%;
  display:block;
  border-radius:16px;
  border:1px solid var(--border);
}

@media (max-width: 900px){
  .hero-grid,
  .details-grid{ grid-template-columns:1fr; }
}
@media (max-width: 640px){
  .info-row{
    flex-direction:column;
    align-items:flex-start;
  }
  .info-value{ text-align:left; }
}
</style>

<div class="log-view-wrap">

  <div class="topbar">
    <div class="topbar-left">
      <h1>Trade Details</h1>
      <p>Review the full record for this trade.</p>
    </div>

    <div class="topbar-actions">
      <a href="/trading-journal/trade-history.php" class="btn secondary">← Back to History</a>
      <a href="/trading-journal/log_edit.php?id=<?= (int)$trade['id'] ?>" class="btn">Edit Trade</a>
      <?php if (!$isClosed): ?>
        <a href="/trading-journal/close_trade.php?id=<?= (int)$trade['id'] ?>" class="btn">Close Trade</a>
      <?php else: ?>
        <a href="/trading-journal/review_form.php?trade_id=<?= (int)$trade['id'] ?>" class="btn">Review Trade</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="hero-card">
    <div class="hero-grid">
      <div>
        <div class="trade-title">
          <h2><?= e($trade['symbol'] ?? '-') ?></h2>

          <span class="pill <?= e($directionClass) ?>">
            <?= e($direction !== '' ? $direction : '-') ?>
          </span>

          <span class="pill <?= e($sourceClass) ?>">
            <?= e(strtoupper($source !== '' ? $source : 'manual')) ?>
          </span>

          <span class="pill <?= e($tradeStateClass) ?>">
            <?= e($tradeState) ?>
          </span>
        </div>

        <div class="mini-meta">
          <span>Trade ID: #<?= (int)$trade['id'] ?></span>
          <span>Opened: <?= e(fmt_dt($trade['entry_time'] ?? null)) ?></span>
          <span>Closed: <?= e(fmt_dt($trade['exit_time'] ?? null)) ?></span>
        </div>
      </div>

      <div class="result-box">
        <h3>Trade Result</h3>
        <div class="value <?= e($pnlClass) ?>"><?= e(fmt_money($trade['pnl_amount'] ?? 0)) ?></div>
        <div class="label">
          <span class="pill <?= e($outcomeClass) ?>">
            <?= e(ucfirst($outcome)) ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="details-grid">

    <div class="info-card">
      <h3>Execution Details</h3>
      <div class="info-list">
        <div class="info-row">
          <div class="info-label">Symbol</div>
          <div class="info-value"><?= e($trade['symbol'] ?? '-') ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Market</div>
          <div class="info-value"><?= e($trade['market'] ?? '-') ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Direction</div>
          <div class="info-value"><?= e($direction !== '' ? $direction : '-') ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Session</div>
          <div class="info-value"><?= e($trade['session'] ?? '-') ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Entry Time</div>
          <div class="info-value"><?= e(fmt_dt($trade['entry_time'] ?? null)) ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Exit Time</div>
          <div class="info-value"><?= e(fmt_dt($trade['exit_time'] ?? null)) ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Entry Price</div>
          <div class="info-value"><?= e(fmt_price($trade['entry_price'] ?? null)) ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Stop Loss</div>
          <div class="info-value"><?= e(fmt_price($trade['stop_loss'] ?? null)) ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Take Profit</div>
          <div class="info-value"><?= e(fmt_price($trade['take_profit'] ?? null)) ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Exit Price</div>
          <div class="info-value"><?= e(fmt_price($trade['exit_price'] ?? null)) ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Position Size</div>
          <div class="info-value"><?= e(fmt_price($trade['position_size'] ?? null, 2)) ?></div>
        </div>
      </div>
    </div>

    <div class="info-card">
      <h3>Journal Record</h3>
      <div class="info-list">
        <div class="info-row">
          <div class="info-label">P/L</div>
          <div class="info-value <?= e($pnlClass) ?>"><?= e(fmt_money($trade['pnl_amount'] ?? 0)) ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Outcome</div>
          <div class="info-value"><?= e(ucfirst($outcome)) ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">R Multiple</div>
          <div class="info-value"><?= e($trade['r_multiple'] !== null && $trade['r_multiple'] !== '' ? number_format((float)$trade['r_multiple'], 2) . 'R' : '-') ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Risk Amount</div>
          <div class="info-value"><?= e(fmt_money($trade['risk_amount'] ?? null)) ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Exit Reason</div>
          <div class="info-value"><?= e($trade['exit_reason'] ?? '-') ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Setup</div>
          <div class="info-value"><?= e($trade['setup'] ?? '-') ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Reviewed</div>
          <div class="info-value"><?= !empty($trade['is_reviewed']) ? 'Yes' : 'No' ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Source</div>
          <div class="info-value"><?= e(strtoupper($source !== '' ? $source : 'manual')) ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">External ID</div>
          <div class="info-value"><?= e($trade['external_id'] ?? '-') ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">MT5 Ticket</div>
          <div class="info-value"><?= e($trade['mt5_ticket'] ?? '-') ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">MT5 Login</div>
          <div class="info-value"><?= e($trade['mt5_login'] ?? '-') ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">MT5 Magic</div>
          <div class="info-value"><?= e($trade['mt5_magic'] ?? '-') ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Created At</div>
          <div class="info-value"><?= e(fmt_dt($trade['created_at'] ?? null)) ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Updated At</div>
          <div class="info-value"><?= e(fmt_dt($trade['updated_at'] ?? null)) ?></div>
        </div>
      </div>
    </div>

    <div class="info-card full-width">
      <h3>Trade Notes</h3>
      <div class="note-box">
        <div class="note-block">
          <div class="note-section-title">Pre-Trade Notes</div>
          <div>
            <?php if (!empty($trade['notes_pre'])): ?>
              <?= nl2br(e($trade['notes_pre'])) ?>
            <?php else: ?>
              <span class="muted">No pre-trade notes added yet.</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="note-block">
          <div class="note-section-title">Post-Trade Notes</div>
          <div>
            <?php if (!empty($trade['notes_post'])): ?>
              <?= nl2br(e($trade['notes_post'])) ?>
            <?php else: ?>
              <span class="muted">No post-trade notes added yet.</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="info-card">
      <h3>Tags</h3>
      <div class="tag-box">
        <?php if (!empty($trade['tags'])): ?>
          <?= e($trade['tags']) ?>
        <?php else: ?>
          <span class="muted">No tags added yet.</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="info-card">
      <h3>Trade Screenshot</h3>
      <div class="screenshot-box">
        <?php if (!empty($trade['screenshot_path'])): ?>
          <img class="screenshot-image" src="/trading-journal/<?= e($trade['screenshot_path']) ?>" alt="Trade screenshot">
        <?php else: ?>
          <span class="muted">No screenshot attached yet.</span>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>