<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "log";

$user_id = (int)($_SESSION['user_id'] ?? 0);

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function fmt_dt($value) {
    if (!$value) return '-';
    $ts = strtotime((string)$value);
    return $ts ? date('d M Y, h:i A', $ts) : '-';
}

function fmt_money($value) {
    if ($value === null || $value === '') return '-';
    return number_format((float)$value, 2);
}

/** Filters */
$q_date     = trim($_GET['date'] ?? '');
$q_symbol   = trim($_GET['symbol'] ?? '');
$q_outcome  = trim($_GET['outcome'] ?? '');
$q_from     = trim($_GET['from'] ?? '');
$q_to       = trim($_GET['to'] ?? '');
$q_strategy = trim($_GET['strategy'] ?? '');

$dateTitle = "";
if ($q_date !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $q_date)) {
    $q_from = $q_date;
    $q_to   = $q_date;

    try {
        $dt = new DateTimeImmutable($q_date);
        $dateTitle = "Trades for " . $dt->format("M d");
    } catch (Throwable $e) {
        $dateTitle = "Trades for " . $q_date;
    }
}

$pageTitle = ($dateTitle !== "")
    ? ($dateTitle . " • Log • NXLOG Analytics")
    : ("Log • NXLOG Analytics");

/** Strategy options */
$opts = [];
$st = $conn->prepare("
    SELECT name
    FROM trade_tags
    WHERE user_id = ? AND tag_type = 'strategy'
    ORDER BY name ASC
");
$st->bind_param("i", $user_id);
$st->execute();
$opts = $st->get_result()->fetch_all(MYSQLI_ASSOC);

/** Query log list */
$sql = "
  SELECT
    t.id,
    t.entry_time,
    t.symbol,
    t.direction,
    t.risk_amount,
    t.r_multiple,
    t.is_reviewed,
    MIN(tt.name) AS strategy
  FROM trades t
  LEFT JOIN trade_tag_map tm
    ON tm.trade_id = t.id
  LEFT JOIN trade_tags tt
    ON tt.id = tm.tag_id
    AND tt.user_id = ?
    AND tt.tag_type = 'strategy'
  WHERE t.user_id = ?
";
$params = [$user_id, $user_id];
$types  = "ii";

if ($q_symbol !== '') {
    $sql .= " AND t.symbol LIKE ? ";
    $params[] = "%$q_symbol%";
    $types .= "s";
}
if ($q_from !== '') {
    $sql .= " AND t.entry_time >= ? ";
    $params[] = $q_from . " 00:00:00";
    $types .= "s";
}
if ($q_to !== '') {
    $sql .= " AND t.entry_time <= ? ";
    $params[] = $q_to . " 23:59:59";
    $types .= "s";
}

if ($q_outcome === 'win')  $sql .= " AND t.r_multiple > 0 ";
if ($q_outcome === 'loss') $sql .= " AND t.r_multiple < 0 ";
if ($q_outcome === 'be')   $sql .= " AND t.r_multiple = 0 ";

if ($q_strategy === '__none__') {
    $sql .= " AND NOT EXISTS (
                SELECT 1
                FROM trade_tag_map tm2
                INNER JOIN trade_tags tt2 ON tt2.id = tm2.tag_id
                WHERE tm2.trade_id = t.id
                  AND tt2.user_id = ?
                  AND tt2.tag_type = 'strategy'
              ) ";
    $params[] = $user_id;
    $types .= "i";
} elseif ($q_strategy !== '') {
    $sql .= " AND EXISTS (
                SELECT 1
                FROM trade_tag_map tm2
                INNER JOIN trade_tags tt2 ON tt2.id = tm2.tag_id
                WHERE tm2.trade_id = t.id
                  AND tt2.user_id = ?
                  AND tt2.tag_type = 'strategy'
                  AND tt2.name = ?
              ) ";
    $params[] = $user_id;
    $params[] = $q_strategy;
    $types .= "is";
}

$sql .= "
  GROUP BY t.id, t.entry_time, t.symbol, t.direction, t.risk_amount, t.r_multiple, t.is_reviewed
  ORDER BY t.entry_time DESC
  LIMIT 200
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . "/partials/app_header.php";
?>

<style>
.log-wrap{ display:grid; gap:14px; }

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

.panel,
.table-panel{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:18px;
  box-shadow:var(--shadow);
}

.panel{ padding:16px; }

.helper-note{
  margin-top:8px;
  color:var(--muted);
  font-size:12px;
  font-weight:800;
}

.filters-grid{
  display:grid;
  grid-template-columns:repeat(6,minmax(0,1fr));
  gap:12px;
  margin-top:14px;
}
.filter-field{
  display:grid;
  gap:6px;
}
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
  gap:8px;
  flex-wrap:wrap;
  align-items:flex-end;
}

.chips{
  display:flex;
  gap:8px;
  flex-wrap:wrap;
  margin-top:14px;
}
.chip{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:8px 12px;
  border-radius:999px;
  border:1px solid var(--border);
  background:var(--pill);
  color:var(--text);
  font-weight:800;
  font-size:12px;
  text-decoration:none;
  transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease;
}
.chip:hover{
  box-shadow:var(--shadow);
  transform:translateY(-1px);
}
.chip.active{
  border-color:rgba(109,94,252,.45);
  box-shadow:0 0 0 4px rgba(109,94,252,.10);
}

.table-head{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  flex-wrap:wrap;
  padding:16px 16px 0;
}
.table-head h3{
  margin:0;
  font-size:20px;
  font-weight:900;
}
.table-head .sub{
  color:var(--muted);
  font-size:13px;
  font-weight:700;
}

.table-wrap{
  overflow:auto;
  padding:12px 0 0;
}
.log-table{
  width:100%;
  border-collapse:collapse;
  min-width:920px;
}
.log-table th,
.log-table td{
  padding:14px 16px;
  text-align:left;
  border-bottom:1px solid var(--border);
  vertical-align:middle;
}
.log-table th{
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:.03em;
  color:var(--muted);
  font-weight:900;
}
.log-table tr:last-child td{
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
}
.pill.buy{ color:#16a34a; }
.pill.sell{ color:#ef4444; }
.pill.good{ color:#16a34a; }
.pill.bad{ color:#ef4444; }
.pill.pending{ color:#eab308; }

.strategy-chip{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:6px 10px;
  border-radius:999px;
  border:1px solid var(--border);
  background:var(--pill);
  color:var(--text);
  font-size:12px;
  font-weight:800;
  text-decoration:none;
}
.strategy-chip:hover{
  box-shadow:var(--shadow);
}

.empty-state{
  padding:26px 18px 22px;
  text-align:center;
}
.empty-state p{
  margin:0 0 14px;
  color:var(--muted);
}

@media (max-width: 1100px){
  .filters-grid{ grid-template-columns:repeat(3,minmax(0,1fr)); }
}
@media (max-width: 720px){
  .filters-grid{ grid-template-columns:1fr; }
}
</style>

<div class="log-wrap">

  <div class="page-head">
    <div>
      <h1><?= e($dateTitle !== "" ? $dateTitle : "Log") ?></h1>
      <p><?= $dateTitle !== "" ? "Filtered view for a single day." : "A structured record of execution and outcomes." ?></p>
    </div>
    <div>
      <a class="btn" href="/trading-journal/log_new.php">+ Log Trade</a>
    </div>
  </div>

  <div class="panel">
    <?php if ($q_date !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $q_date)): ?>
      <div class="helper-note">Tip: clear the date filter to view all trades.</div>
    <?php endif; ?>

    <form method="get">
      <?php if ($q_date !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $q_date)): ?>
        <input type="hidden" name="date" value="<?= e($q_date) ?>">
      <?php endif; ?>

      <div class="filters-grid">
        <div class="filter-field">
          <label for="symbol">Symbol</label>
          <input id="symbol" name="symbol" value="<?= e($q_symbol) ?>" placeholder="EURUSD, BTCUSD...">
        </div>

        <div class="filter-field">
          <label for="outcome">Outcome</label>
          <select id="outcome" name="outcome">
            <option value="" <?= $q_outcome===''?'selected':'' ?>>All</option>
            <option value="win" <?= $q_outcome==='win'?'selected':'' ?>>Win</option>
            <option value="loss" <?= $q_outcome==='loss'?'selected':'' ?>>Loss</option>
            <option value="be" <?= $q_outcome==='be'?'selected':'' ?>>Break-even</option>
          </select>
        </div>

        <div class="filter-field">
          <label for="strategy">Strategy</label>
          <select id="strategy" name="strategy">
            <option value="" <?= $q_strategy===''?'selected':'' ?>>All</option>
            <option value="__none__" <?= $q_strategy==='__none__'?'selected':'' ?>>Unassigned</option>
            <?php foreach ($opts as $o): ?>
              <?php $nm = (string)$o['name']; ?>
              <option value="<?= e($nm) ?>" <?= $q_strategy===$nm?'selected':'' ?>><?= e($nm) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-field">
          <label for="from">From</label>
          <input id="from" name="from" type="date" value="<?= e($q_from) ?>" <?= ($q_date !== '' ? 'disabled' : '') ?>>
        </div>

        <div class="filter-field">
          <label for="to">To</label>
          <input id="to" name="to" type="date" value="<?= e($q_to) ?>" <?= ($q_date !== '' ? 'disabled' : '') ?>>
        </div>

        <div class="filter-actions">
          <button class="btn" type="submit">Apply Filters</button>
          <a class="btn secondary" href="/trading-journal/log.php">Reset</a>
        </div>
      </div>
    </form>

    <div class="chips">
      <?php
        $base = [
          'symbol' => $q_symbol,
          'outcome'=> $q_outcome,
          'from'   => $q_from,
          'to'     => $q_to,
        ];

        if ($q_date !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $q_date)) {
          $base['date'] = $q_date;
          unset($base['from'], $base['to']);
        }

        $linkAll  = "/trading-journal/log.php?" . http_build_query($base + ['strategy' => '']);
        $linkNone = "/trading-journal/log.php?" . http_build_query($base + ['strategy' => '__none__']);
      ?>

      <a class="chip <?= $q_strategy===''?'active':'' ?>" href="<?= e($linkAll) ?>">All</a>
      <a class="chip <?= $q_strategy==='__none__'?'active':'' ?>" href="<?= e($linkNone) ?>">Unassigned</a>

      <?php foreach ($opts as $o): ?>
        <?php
          $nm = (string)$o['name'];
          $link = "/trading-journal/log.php?" . http_build_query($base + ['strategy' => $nm]);
        ?>
        <a class="chip <?= ($q_strategy===$nm)?'active':'' ?>" href="<?= e($link) ?>">
          <?= e($nm) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="table-panel">
    <div class="table-head">
      <div>
        <h3>Trade Log</h3>
        <div class="sub"><?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?> shown</div>
      </div>
    </div>

    <?php if (!$rows): ?>
      <div class="empty-state">
        <p>No trades match your filters.</p>
        <a class="btn" href="/trading-journal/log_new.php">Log a trade</a>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="log-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Symbol</th>
              <th>Side</th>
              <th>Strategy</th>
              <th>Risk (1R)</th>
              <th>R</th>
              <th>Review</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <?php
                $rm = $r['r_multiple'];
                $strategy = $r['strategy'] ? (string)$r['strategy'] : "—";
                $linkStr = ($strategy === "—")
                  ? ("/trading-journal/log.php?" . http_build_query($base + ['strategy' => '__none__']))
                  : ("/trading-journal/log.php?" . http_build_query($base + ['strategy' => $strategy]));

                $dir = strtoupper((string)$r['direction']);
                $dirClass = $dir === 'BUY' ? 'buy' : 'sell';
              ?>
              <tr>
                <td><?= e(fmt_dt($r['entry_time'])) ?></td>
                <td><strong><?= e($r['symbol']) ?></strong></td>
                <td>
                  <span class="pill <?= e($dirClass) ?>"><?= e($dir) ?></span>
                </td>
                <td>
                  <a class="strategy-chip" href="<?= e($linkStr) ?>"><?= e($strategy) ?></a>
                </td>
                <td><?= e(fmt_money($r['risk_amount'])) ?></td>
                <td>
                  <?php if ($rm === null): ?>
                    <span class="pill pending">Open</span>
                  <?php else: ?>
                    <span class="pill <?= ((float)$rm >= 0 ? 'good' : 'bad') ?>">
                      <?= e(number_format((float)$rm, 2)) ?>R
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ((int)$r['is_reviewed'] === 1): ?>
                    <span class="pill good">Reviewed</span>
                  <?php else: ?>
                    <span class="pill pending">Pending</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a class="btn secondary" href="/trading-journal/log_view.php?id=<?= (int)$r['id'] ?>">Open</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>