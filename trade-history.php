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

/** Table data */
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
$trades = $stmt->get_result();

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
.trade-history-wrap{ display:grid; gap:14px; }

.page-head{
  display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;
}
.page-head h1{ margin:0; font-size:28px; font-weight:900; }
.page-head p{ margin:6px 0 0; color:var(--muted); }

.stats-grid{
  display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px;
}
.stat-card{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:18px;
  padding:16px;
  box-shadow:var(--shadow);
}
.stat-card h3{
  margin:0 0 8px;
  font-size:13px;
  color:var(--muted);
  font-weight:800;
}
.stat-card .value{
  font-size:26px;
  font-weight:900;
  line-height:1.1;
}

.filters-card,
.table-card{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:18px;
  box-shadow:var(--shadow);
}

.filters-card{ padding:16px; }
.filters-grid{
  display:grid;
  grid-template-columns:repeat(6,minmax(0,1fr));
  gap:12px;
}
.filter-field{ display:grid; gap:6px; }
.filter-field label{
  font-size:12px;
  font-weight:800;
  color:var(--muted);
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
}

.history-table-wrap{ overflow:auto; }
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
}
.history-table th{
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:.03em;
  color:var(--muted);
  font-weight:900;
}
.history-table tr:last-child td{ border-bottom:none; }

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

@media (max-width: 1100px){
  .filters-grid,
  .stats-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); }
}
@media (max-width: 720px){
  .filters-grid,
  .stats-grid{ grid-template-columns:1fr; }
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

      <div class="filter-actions" style="margin-top:12px">
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
          <?php if ($trades && $trades->num_rows > 0): ?>
            <?php while ($trade = $trades->fetch_assoc()): ?>
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
                <td>
                  <span class="pill <?= e($directionClass) ?>">
                    <?= e($trade['direction'] ?? '-') ?>
                  </span>
                </td>
                <td><?= e(fmt_price($trade['entry_price'] ?? null)) ?></td>
                <td><?= e(fmt_price($trade['exit_price'] ?? null)) ?></td>
                <td><?= e(fmt_price($trade['position_size'] ?? null, 2)) ?></td>
                <td class="<?= e($pnlClass) ?>"><?= e(fmt_money($trade['pnl_amount'] ?? 0)) ?></td>
                <td>
                  <span class="pill <?= e($outcomeClass) ?>">
                    <?= e(ucfirst($outcomeVal !== '' ? $outcomeVal : 'breakeven')) ?>
                  </span>
                </td>
                <td>
                  <span class="pill <?= e($sourceClass) ?>">
                    <?= e(strtoupper($sourceVal !== '' ? $sourceVal : 'manual')) ?>
                  </span>
                </td>
                <td>
                  <div class="action-group">
                    <a class="btn secondary" href="/trading-journal/log_view.php?id=<?= (int)$trade['id'] ?>">View</a>
                    <a class="btn" href="/trading-journal/log_edit.php?id=<?= (int)$trade['id'] ?>">Edit</a>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="10" class="empty-state">No trades found for the selected filters.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
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