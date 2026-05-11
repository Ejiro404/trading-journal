<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$user_id = (int)($_SESSION['user_id'] ?? 0);

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

function build_query(array $overrides = []) {
    $query = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) {
            unset($query[$k]);
        } else {
            $query[$k] = $v;
        }
    }
    return http_build_query($query);
}

$search     = trim($_GET['search'] ?? '');
$direction  = trim($_GET['direction'] ?? '');
$outcome    = trim($_GET['outcome'] ?? '');
$source     = trim($_GET['source'] ?? '');
$date_from  = trim($_GET['date_from'] ?? '');
$date_to    = trim($_GET['date_to'] ?? '');

$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$where = ["user_id = ?"];
$params = [$user_id];
$types = "i";

if ($search !== '') {
    $where[] = "symbol LIKE ?";
    $params[] = "%{$search}%";
    $types .= "s";
}

if ($direction !== '' && in_array($direction, ['BUY', 'SELL'], true)) {
    $where[] = "direction = ?";
    $params[] = $direction;
    $types .= "s";
}

if ($outcome !== '' && in_array($outcome, ['win', 'loss', 'breakeven'], true)) {
    $where[] = "outcome = ?";
    $params[] = $outcome;
    $types .= "s";
}

if ($source !== '') {
    $where[] = "source = ?";
    $params[] = $source;
    $types .= "s";
}

if ($date_from !== '') {
    $where[] = "DATE(entry_time) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if ($date_to !== '') {
    $where[] = "DATE(entry_time) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$whereSql = implode(" AND ", $where);

/** Count */
$countSql = "SELECT COUNT(*) AS total FROM trades WHERE $whereSql";
$countStmt = $conn->prepare($countSql);
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalTrades = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalTrades / $perPage));

/** Trade data */
$sql = "
    SELECT
        id,
        symbol,
        direction,
        entry_time,
        exit_time,
        entry_price,
        exit_price,
        position_size,
        pnl_amount,
        outcome,
        source,
        created_at
    FROM trades
    WHERE $whereSql
    ORDER BY entry_time DESC, id DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
$fullTypes = $types . "ii";
$fullParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($fullTypes, ...$fullParams);
$stmt->execute();
$trades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/** Top stats */
$statsSql = "
    SELECT
        COUNT(*) AS total_trades,
        SUM(CASE WHEN outcome = 'win' THEN 1 ELSE 0 END) AS wins,
        SUM(CASE WHEN outcome = 'loss' THEN 1 ELSE 0 END) AS losses,
        SUM(CASE WHEN outcome = 'breakeven' THEN 1 ELSE 0 END) AS breakeven_count,
        SUM(COALESCE(pnl_amount, 0)) AS net_pl
    FROM trades
    WHERE user_id = ?
";
$statsStmt = $conn->prepare($statsSql);
$statsStmt->bind_param("i", $user_id);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

$pageTitle = "Trade History • NXLOG";
$current   = "trade-history";
require_once __DIR__ . "/partials/app_header.php";
?>

<style>
.trade-history-wrap{
  display:grid;
  gap:14px;
  width:100%;
  max-width:100%;
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

.stats-grid{
  display:grid;
  grid-template-columns:repeat(4,minmax(0,1fr));
  gap:14px;
}

.stat-card{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:18px;
  padding:16px;
  box-shadow:var(--shadow);
  min-width:0;
}

.stat-card h3{
  margin:0 0 8px;
  font-size:12px;
  color:var(--muted);
  font-weight:900;
  text-transform:uppercase;
  letter-spacing:.04em;
}

.stat-card .value{
  font-size:26px;
  font-weight:900;
  line-height:1.1;
  word-break:break-word;
}

.filters-card,
.table-card{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:18px;
  box-shadow:var(--shadow);
  min-width:0;
}

.filters-card{
  padding:16px;
}

.filters-grid{
  display:grid;
  grid-template-columns:repeat(6,minmax(0,1fr));
  gap:12px;
}

.filter-field{
  display:grid;
  gap:6px;
  min-width:0;
}

.filter-field label{
  font-size:12px;
  font-weight:800;
  color:var(--muted);
  margin:0;
}

.filter-field input,
.filter-field select{
  width:100%;
  min-height:42px;
  border:1px solid var(--border);
  background:var(--bg);
  color:var(--text);
  border-radius:12px;
  padding:10px 12px;
  outline:none;
}

.filter-actions{
  display:flex;
  gap:10px;
  align-items:flex-end;
  flex-wrap:wrap;
  margin-top:12px;
}

.history-table-wrap{
  width:100%;
  overflow-x:auto;
  -webkit-overflow-scrolling:touch;
}

.history-table{
  width:100%;
  border-collapse:collapse;
  min-width:1080px;
}

.history-table th,
.history-table td{
  padding:14px 16px;
  border-bottom:1px solid var(--border);
  text-align:left;
  vertical-align:middle;
  white-space:nowrap;
}

.history-table th{
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:.03em;
  color:var(--muted);
  font-weight:900;
}

.history-table tr:last-child td{
  border-bottom:none;
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
  white-space:nowrap;
}

.pill.buy{ color:#16a34a; }
.pill.sell{ color:#ef4444; }
.pill.win{ color:#16a34a; }
.pill.loss{ color:#ef4444; }
.pill.breakeven{ color:#eab308; }
.pill.manual{ color:#a855f7; }
.pill.mt5{ color:#2563eb; }

.value-win{ color:#16a34a; font-weight:900; }
.value-loss{ color:#ef4444; font-weight:900; }
.value-breakeven{ color:#eab308; font-weight:900; }

.empty-state{
  padding:28px 18px;
  text-align:center;
  color:var(--muted);
}

.action-group{
  display:flex;
  gap:8px;
  flex-wrap:wrap;
}

.mobile-trade-list{
  display:none;
}

.pagination{
  display:flex;
  justify-content:flex-end;
  gap:8px;
  flex-wrap:wrap;
  padding:14px 16px 16px;
}

.page-link{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width:40px;
  height:40px;
  border-radius:12px;
  border:1px solid var(--border);
  background:var(--pill);
  color:var(--text);
  text-decoration:none;
  font-weight:900;
}

.page-link.active{
  border-color:transparent;
}

@media (max-width:1100px){
  .filters-grid,
  .stats-grid{
    grid-template-columns:repeat(2,minmax(0,1fr));
  }
}

@media (max-width:720px){
  .trade-history-wrap{
    gap:12px;
  }

  .page-head h1{
    font-size:22px;
  }

  .page-head p{
    font-size:12px;
  }

  .stats-grid{
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
  }

  .stat-card{
    padding:13px;
    border-radius:18px;
  }

  .stat-card h3{
    font-size:10px;
    margin-bottom:6px;
  }

  .stat-card .value{
    font-size:22px;
  }

  .filters-card{
    padding:13px;
    border-radius:18px;
  }

  .filters-grid{
    grid-template-columns:1fr 1fr;
    gap:10px;
  }

  .filter-field label{
    font-size:10px;
  }

  .filter-field input,
  .filter-field select{
    min-height:38px;
    padding:8px 10px;
    font-size:16px;
    border-radius:12px;
  }

  .filter-actions{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:8px;
  }

  .filter-actions .btn{
    width:100%;
    min-height:36px;
    font-size:12px;
    padding:8px 10px;
  }

  .history-table-wrap{
    display:none;
  }

  .table-card{
    background:transparent;
    border:0;
    box-shadow:none;
  }

  .mobile-trade-list{
    display:grid;
    gap:10px;
  }

  .mobile-trade-card{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:18px;
    box-shadow:var(--shadow);
    padding:13px;
  }

  .mobile-trade-top{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
  }

  .mobile-symbol{
    font-size:17px;
    line-height:1.05;
    font-weight:950;
    letter-spacing:-.03em;
  }

  .mobile-time{
    margin-top:4px;
    font-size:10px;
    color:var(--muted);
    font-weight:800;
  }

  .mobile-pnl{
    text-align:right;
    font-size:16px;
    font-weight:950;
    white-space:nowrap;
  }

  .mobile-pill-row{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
    margin-top:10px;
  }

  .mobile-trade-meta{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:8px;
    margin-top:12px;
  }

  .mobile-meta-box{
    border:1px solid var(--border);
    background:var(--pill);
    border-radius:14px;
    padding:9px;
    min-width:0;
  }

  .mobile-meta-label{
    font-size:10px;
    color:var(--muted);
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.04em;
  }

  .mobile-meta-value{
    margin-top:4px;
    font-size:12px;
    font-weight:900;
    word-break:break-word;
  }

  .mobile-card-actions{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:8px;
    margin-top:12px;
  }

  .mobile-card-actions .btn{
    width:100%;
    min-height:36px;
    font-size:12px;
    padding:8px 10px;
  }

  .empty-state{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:18px;
    box-shadow:var(--shadow);
  }

  .pagination{
    justify-content:center;
    padding:12px 0 0;
  }

  .page-link{
    min-width:36px;
    height:36px;
    border-radius:11px;
    font-size:12px;
  }
}

@media (max-width:390px){
  .filters-grid{
    grid-template-columns:1fr;
  }

  .stats-grid{
    grid-template-columns:1fr 1fr;
  }

  .mobile-trade-meta{
    grid-template-columns:1fr;
  }
}
</style>

<div class="trade-history-wrap">

  <div class="page-head">
    <div>
      <h1>Trade History</h1>
      <p>Review all your recorded trades in one place.</p>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <h3>Total Trades</h3>
      <div class="value"><?= (int)($stats['total_trades'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
      <h3>Wins</h3>
      <div class="value"><?= (int)($stats['wins'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
      <h3>Losses</h3>
      <div class="value"><?= (int)($stats['losses'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
      <h3>Net P/L</h3>
      <div class="value"><?= fmt_money($stats['net_pl'] ?? 0) ?></div>
    </div>
  </div>

  <div class="filters-card">
    <form method="GET">
      <div class="filters-grid">
        <div class="filter-field">
          <label for="search">Search Symbol</label>
          <input id="search" type="text" name="search" placeholder="e.g. EURUSD" value="<?= e($search) ?>">
        </div>

        <div class="filter-field">
          <label for="direction">Direction</label>
          <select id="direction" name="direction">
            <option value="">All Directions</option>
            <option value="BUY" <?= $direction === 'BUY' ? 'selected' : '' ?>>BUY</option>
            <option value="SELL" <?= $direction === 'SELL' ? 'selected' : '' ?>>SELL</option>
          </select>
        </div>

        <div class="filter-field">
          <label for="outcome">Outcome</label>
          <select id="outcome" name="outcome">
            <option value="">All Outcomes</option>
            <option value="win" <?= $outcome === 'win' ? 'selected' : '' ?>>Win</option>
            <option value="loss" <?= $outcome === 'loss' ? 'selected' : '' ?>>Loss</option>
            <option value="breakeven" <?= $outcome === 'breakeven' ? 'selected' : '' ?>>Breakeven</option>
          </select>
        </div>

        <div class="filter-field">
          <label for="source">Source</label>
          <select id="source" name="source">
            <option value="">All Sources</option>
            <option value="manual" <?= $source === 'manual' ? 'selected' : '' ?>>Manual</option>
            <option value="mt5" <?= $source === 'mt5' ? 'selected' : '' ?>>MT5</option>
          </select>
        </div>

        <div class="filter-field">
          <label for="date_from">From</label>
          <input id="date_from" type="date" name="date_from" value="<?= e($date_from) ?>">
        </div>

        <div class="filter-field">
          <label for="date_to">To</label>
          <input id="date_to" type="date" name="date_to" value="<?= e($date_to) ?>">
        </div>
      </div>

      <div class="filter-actions">
        <button class="btn" type="submit">Apply Filters</button>
        <a class="btn secondary" href="/trading-journal/trade-history.php">Reset</a>
      </div>
    </form>
  </div>

  <div class="table-card">
    <div class="history-table-wrap">
      <table class="history-table">
        <thead>
          <tr>
            <th>Entry Time</th>
            <th>Symbol</th>
            <th>Direction</th>
            <th>Entry</th>
            <th>Exit</th>
            <th>Position Size</th>
            <th>P/L</th>
            <th>Outcome</th>
            <th>Source</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($trades)): ?>
            <?php foreach ($trades as $trade): ?>
              <?php
                $directionClass = strtoupper((string)$trade['direction']) === 'BUY' ? 'buy' : 'sell';
                $outcomeVal = strtolower((string)($trade['outcome'] ?? ''));
                $outcomeClass = in_array($outcomeVal, ['win', 'loss', 'breakeven'], true) ? $outcomeVal : 'breakeven';
                $sourceVal = strtolower((string)($trade['source'] ?? 'manual'));
                $sourceClass = $sourceVal === 'mt5' ? 'mt5' : 'manual';
                $pnl = (float)($trade['pnl_amount'] ?? 0);
                $pnlClass = $pnl > 0 ? 'value-win' : ($pnl < 0 ? 'value-loss' : 'value-breakeven');
              ?>
              <tr>
                <td><?= e(fmt_dt($trade['entry_time'] ?? null)) ?></td>
                <td><strong><?= e($trade['symbol'] ?? '-') ?></strong></td>
                <td><span class="pill <?= e($directionClass) ?>"><?= e($trade['direction'] ?? '-') ?></span></td>
                <td><?= e(fmt_price($trade['entry_price'] ?? null)) ?></td>
                <td><?= e(fmt_price($trade['exit_price'] ?? null)) ?></td>
                <td><?= e(fmt_price($trade['position_size'] ?? null, 2)) ?></td>
                <td class="<?= e($pnlClass) ?>"><?= e(fmt_money($trade['pnl_amount'] ?? 0)) ?></td>
                <td><span class="pill <?= e($outcomeClass) ?>"><?= e(ucfirst($outcomeVal !== '' ? $outcomeVal : 'breakeven')) ?></span></td>
                <td><span class="pill <?= e($sourceClass) ?>"><?= e(strtoupper($sourceVal !== '' ? $sourceVal : 'manual')) ?></span></td>
                <td>
                  <div class="action-group">
                    <a class="btn secondary" href="/trading-journal/log_view.php?id=<?= (int)$trade['id'] ?>">View</a>
                    <a class="btn" href="/trading-journal/log_edit.php?id=<?= (int)$trade['id'] ?>">Edit</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="10" class="empty-state">No trades found for the selected filters.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="mobile-trade-list">
      <?php if (!empty($trades)): ?>
        <?php foreach ($trades as $trade): ?>
          <?php
            $directionClass = strtoupper((string)$trade['direction']) === 'BUY' ? 'buy' : 'sell';
            $outcomeVal = strtolower((string)($trade['outcome'] ?? ''));
            $outcomeClass = in_array($outcomeVal, ['win', 'loss', 'breakeven'], true) ? $outcomeVal : 'breakeven';
            $sourceVal = strtolower((string)($trade['source'] ?? 'manual'));
            $sourceClass = $sourceVal === 'mt5' ? 'mt5' : 'manual';
            $pnl = (float)($trade['pnl_amount'] ?? 0);
            $pnlClass = $pnl > 0 ? 'value-win' : ($pnl < 0 ? 'value-loss' : 'value-breakeven');
          ?>
          <article class="mobile-trade-card">
            <div class="mobile-trade-top">
              <div>
                <div class="mobile-symbol"><?= e($trade['symbol'] ?? '-') ?></div>
                <div class="mobile-time"><?= e(fmt_dt($trade['entry_time'] ?? null)) ?></div>
              </div>
              <div class="mobile-pnl <?= e($pnlClass) ?>">
                <?= e(fmt_money($trade['pnl_amount'] ?? 0)) ?>
              </div>
            </div>

            <div class="mobile-pill-row">
              <span class="pill <?= e($directionClass) ?>"><?= e($trade['direction'] ?? '-') ?></span>
              <span class="pill <?= e($outcomeClass) ?>"><?= e(ucfirst($outcomeVal !== '' ? $outcomeVal : 'breakeven')) ?></span>
              <span class="pill <?= e($sourceClass) ?>"><?= e(strtoupper($sourceVal !== '' ? $sourceVal : 'manual')) ?></span>
            </div>

            <div class="mobile-trade-meta">
              <div class="mobile-meta-box">
                <div class="mobile-meta-label">Entry</div>
                <div class="mobile-meta-value"><?= e(fmt_price($trade['entry_price'] ?? null)) ?></div>
              </div>

              <div class="mobile-meta-box">
                <div class="mobile-meta-label">Exit</div>
                <div class="mobile-meta-value"><?= e(fmt_price($trade['exit_price'] ?? null)) ?></div>
              </div>

              <div class="mobile-meta-box">
                <div class="mobile-meta-label">Position</div>
                <div class="mobile-meta-value"><?= e(fmt_price($trade['position_size'] ?? null, 2)) ?></div>
              </div>

              <div class="mobile-meta-box">
                <div class="mobile-meta-label">Created</div>
                <div class="mobile-meta-value"><?= e(fmt_dt($trade['created_at'] ?? null)) ?></div>
              </div>
            </div>

            <div class="mobile-card-actions">
              <a class="btn secondary" href="/trading-journal/log_view.php?id=<?= (int)$trade['id'] ?>">View</a>
              <a class="btn" href="/trading-journal/log_edit.php?id=<?= (int)$trade['id'] ?>">Edit</a>
            </div>
          </article>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state">No trades found for the selected filters.</div>
      <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a
            class="page-link <?= $i === $page ? 'active btn' : '' ?>"
            href="/trading-journal/trade-history.php?<?= e(build_query(['page' => $i])) ?>"
          >
            <?= $i ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>