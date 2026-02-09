<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "log";
$pageTitle = "Log • NXLOG Analytics";

$user_id = (int)$_SESSION['user_id'];

/** Filters */
$q_symbol   = trim($_GET['symbol'] ?? '');
$q_outcome  = trim($_GET['outcome'] ?? ''); // win/loss/be
$q_from     = trim($_GET['from'] ?? '');
$q_to       = trim($_GET['to'] ?? '');
$q_strategy = trim($_GET['strategy'] ?? ''); // ''=all, '__none__'=unassigned, else strategy name

/** Strategy options */
$opts = [];
$st = $conn->prepare("SELECT name FROM trade_tags WHERE user_id=? AND tag_type='strategy' ORDER BY name ASC");
$st->bind_param("i", $user_id);
$st->execute();
$opts = $st->get_result()->fetch_all(MYSQLI_ASSOC);

/**
 * Query log list (strategy via LEFT JOIN)
 * If multiple strategies exist for a trade, MIN picks one (we treat it as 1-strategy-per-trade).
 */
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

if ($q_symbol !== '') { $sql .= " AND t.symbol LIKE ? "; $params[] = "%$q_symbol%"; $types .= "s"; }
if ($q_from !== '')   { $sql .= " AND t.entry_time >= ? "; $params[] = $q_from." 00:00:00"; $types .= "s"; }
if ($q_to !== '')     { $sql .= " AND t.entry_time <= ? "; $params[] = $q_to." 23:59:59"; $types .= "s"; }

if ($q_outcome === 'win')  { $sql .= " AND t.r_multiple > 0 "; }
if ($q_outcome === 'loss') { $sql .= " AND t.r_multiple < 0 "; }
if ($q_outcome === 'be')   { $sql .= " AND t.r_multiple = 0 "; }

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
  .chips{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
  .chip{
    display:inline-flex;align-items:center;gap:8px;
    padding:8px 12px;border-radius:999px;
    border:1px solid var(--border);
    background:var(--pill);
    color:var(--text);
    font-weight:800;font-size:12px;
    text-decoration:none;
  }
  .chip:hover{box-shadow:var(--shadow);transform:translateY(-1px)}
  .chip.active{border-color:var(--accent)}
  .chip small{font-weight:800;color:var(--muted)}
</style>

<div class="card">
  <h2>Log</h2>
  <p class="small">A structured record of execution and outcomes.</p>

  <form method="get" class="row">
    <div class="col">
      <label>Symbol</label>
      <input name="symbol" value="<?= e($q_symbol) ?>" placeholder="EURUSD, BTCUSD...">
    </div>

    <div class="col">
      <label>Outcome</label>
      <select name="outcome">
        <option value="" <?= $q_outcome===''?'selected':'' ?>>All</option>
        <option value="win" <?= $q_outcome==='win'?'selected':'' ?>>Win</option>
        <option value="loss" <?= $q_outcome==='loss'?'selected':'' ?>>Loss</option>
        <option value="be" <?= $q_outcome==='be'?'selected':'' ?>>Break-even</option>
      </select>
    </div>

    <div class="col">
      <label>Strategy</label>
      <select name="strategy">
        <option value="" <?= $q_strategy===''?'selected':'' ?>>All</option>
        <option value="__none__" <?= $q_strategy==='__none__'?'selected':'' ?>>Unassigned</option>
        <?php foreach ($opts as $o): ?>
          <?php $nm = (string)$o['name']; ?>
          <option value="<?= e($nm) ?>" <?= $q_strategy===$nm?'selected':'' ?>><?= e($nm) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col">
      <label>From</label>
      <input name="from" type="date" value="<?= e($q_from) ?>">
    </div>

    <div class="col">
      <label>To</label>
      <input name="to" type="date" value="<?= e($q_to) ?>">
    </div>

    <div class="col" style="align-self:end;display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn" type="submit">Filter</button>
      <a class="btn ghost" href="/trading-journal/log.php">Reset</a>
    </div>
  </form>

  <!-- Strategy chips -->
  <div class="chips">
    <?php
      // Build base URL preserving other filters except strategy
      $base = [
        'symbol' => $q_symbol,
        'outcome'=> $q_outcome,
        'from'   => $q_from,
        'to'     => $q_to,
      ];
      $linkAll = "/trading-journal/log.php?" . http_build_query($base + ['strategy' => '']);
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

<div class="card">
  <?php if (!$rows): ?>
    <p class="small">No trades match your filters.</p>
    <a class="btn" href="/trading-journal/log_new.php">Log a trade</a>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Symbol</th>
          <th>Side</th>
          <th>Strategy</th>
          <th>Risk (1R)</th>
          <th>R</th>
          <th>Review</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <?php
          $rm = $r['r_multiple'];
          $badgeClass = ($rm === null) ? "" : ((float)$rm >= 0 ? "good" : "bad");
          $str = $r['strategy'] ? (string)$r['strategy'] : "—";
          $rev = ((int)$r['is_reviewed'] === 1) ? "Reviewed" : "Pending";

          // chip link for strategy in table
          $linkStr = ($str === "—")
            ? ("/trading-journal/log.php?" . http_build_query($base + ['strategy' => '__none__']))
            : ("/trading-journal/log.php?" . http_build_query($base + ['strategy' => $str]));
        ?>
        <tr>
          <td><?= e($r['entry_time']) ?></td>
          <td><?= e($r['symbol']) ?></td>
          <td><?= e($r['direction']) ?></td>
          <td class="small">
            <a class="chip" style="padding:6px 10px" href="<?= e($linkStr) ?>"><?= e($str) ?></a>
          </td>
          <td><?= number_format((float)$r['risk_amount'], 2) ?></td>
          <td>
            <?php if ($rm === null): ?>
              <span class="badge">open</span>
            <?php else: ?>
              <span class="badge <?= $badgeClass ?>"><?= number_format((float)$rm, 2) ?>R</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ((int)$r['is_reviewed'] === 1): ?>
              <span class="badge good"><?= e($rev) ?></span>
            <?php else: ?>
              <span class="badge"><?= e($rev) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <a class="btn secondary" href="/trading-journal/log_view.php?id=<?= (int)$r['id'] ?>">Open</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>
