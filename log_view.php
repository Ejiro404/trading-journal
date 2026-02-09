<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "log";
$pageTitle = "Trade • NXLOG Analytics";

$user_id = (int)$_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);

function dtlocal_to_mysql($v) {
  $v = trim((string)$v);
  if ($v === "") return "";
  return str_replace("T", " ", $v) . ":00";
}

function get_or_create_tag(mysqli $conn, int $user_id, string $type, string $name): int {
  $name = trim($name);
  if ($name === "") return 0;

  $sel = $conn->prepare("SELECT id FROM trade_tags WHERE user_id=? AND tag_type=? AND name=? LIMIT 1");
  $sel->bind_param("iss", $user_id, $type, $name);
  $sel->execute();
  $row = $sel->get_result()->fetch_assoc();
  if ($row) return (int)$row['id'];

  $ins = $conn->prepare("INSERT INTO trade_tags (user_id, tag_type, name) VALUES (?,?,?)");
  $ins->bind_param("iss", $user_id, $type, $name);
  $ins->execute();
  return (int)$conn->insert_id;
}

function set_trade_strategy(mysqli $conn, int $trade_id, int $user_id, string $strategy_name): void {
  // delete existing strategy mappings
  $del = $conn->prepare("
    DELETE tm FROM trade_tag_map tm
    INNER JOIN trade_tags tt ON tt.id = tm.tag_id
    WHERE tm.trade_id=? AND tt.user_id=? AND tt.tag_type='strategy'
  ");
  $del->bind_param("ii", $trade_id, $user_id);
  $del->execute();

  if (trim($strategy_name) === "") return;

  $tag_id = get_or_create_tag($conn, $user_id, "strategy", $strategy_name);
  $ins = $conn->prepare("INSERT IGNORE INTO trade_tag_map (trade_id, tag_id) VALUES (?,?)");
  $ins->bind_param("ii", $trade_id, $tag_id);
  $ins->execute();
}

$stmt = $conn->prepare("SELECT * FROM trades WHERE id=? AND user_id=? LIMIT 1");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$trade = $stmt->get_result()->fetch_assoc();
if (!$trade) { http_response_code(404); exit("Trade not found."); }

$error = $ok = "";

/** Strategy options */
$opts = [];
$st = $conn->prepare("SELECT name FROM trade_tags WHERE user_id=? AND tag_type='strategy' ORDER BY name ASC");
$st->bind_param("i", $user_id);
$st->execute();
$opts = $st->get_result()->fetch_all(MYSQLI_ASSOC);

/** Current strategy */
$strategy = "";
$sq = $conn->prepare("
  SELECT tt.name
  FROM trade_tag_map tm
  INNER JOIN trade_tags tt ON tt.id = tm.tag_id
  WHERE tm.trade_id=? AND tt.user_id=? AND tt.tag_type='strategy'
  LIMIT 1
");
$sq->bind_param("ii", $id, $user_id);
$sq->execute();
$sr = $sq->get_result()->fetch_assoc();
if ($sr) $strategy = (string)$sr['name'];

/** Mistake tags */
$mistakeNames = [];
$mq = $conn->prepare("
  SELECT tt.name
  FROM trade_tag_map tm
  INNER JOIN trade_tags tt ON tt.id = tm.tag_id
  WHERE tm.trade_id=? AND tt.user_id=? AND tt.tag_type='mistake'
  ORDER BY tt.name ASC
");
$mq->bind_param("ii", $id, $user_id);
$mq->execute();
$mistakeNames = array_map(fn($x)=>$x['name'], $mq->get_result()->fetch_all(MYSQLI_ASSOC));

/** Review snippet */
$rv = $conn->prepare("SELECT id, rules_score FROM trade_reviews WHERE trade_id=? LIMIT 1");
$rv->bind_param("i", $id);
$rv->execute();
$review = $rv->get_result()->fetch_assoc();

/** QUICK ASSIGN STRATEGY */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_strategy') {
  $existing = trim($_POST['strategy_existing'] ?? '');
  $new = trim($_POST['strategy_new'] ?? '');
  $chosen = ($new !== "") ? $new : $existing; // empty means unassigned

  set_trade_strategy($conn, $id, $user_id, $chosen);

  // refresh current strategy + options
  $st->execute();
  $opts = $st->get_result()->fetch_all(MYSQLI_ASSOC);

  $sq->execute();
  $sr = $sq->get_result()->fetch_assoc();
  $strategy = $sr ? (string)$sr['name'] : "";

  $ok = "Strategy updated.";
}

/** CLOSE TRADE (R-first) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close') {
  $exit_time = dtlocal_to_mysql($_POST['exit_time'] ?? '');
  $exit_price_raw = trim($_POST['exit_price'] ?? '');
  $exit_price = ($exit_price_raw === '') ? null : (float)$exit_price_raw;

  $pnl_raw = trim($_POST['pnl_amount'] ?? '');
  $pnl_amount = ($pnl_raw === '') ? null : (float)$pnl_raw;

  $notes_post = trim($_POST['notes_post'] ?? '');

  if ($exit_time === "" || $pnl_amount === null) {
    $error = "Exit time and P/L (money) are required to close a trade (R-first).";
  } else {
    $risk = (float)$trade['risk_amount'];
    if ($risk <= 0) {
      $error = "Invalid risk amount (must be > 0).";
    } else {
      $r_multiple = $pnl_amount / $risk;
      $outcome = ($r_multiple > 0) ? "win" : (($r_multiple < 0) ? "loss" : "breakeven");

      if ($exit_price === null) {
        $upd = $conn->prepare("
          UPDATE trades
          SET exit_time=?, exit_price=NULL, pnl_amount=?, r_multiple=?, outcome=?, notes_post=?
          WHERE id=? AND user_id=?
        ");
        $upd->bind_param("sddssii", $exit_time, $pnl_amount, $r_multiple, $outcome, $notes_post, $id, $user_id);
      } else {
        $upd = $conn->prepare("
          UPDATE trades
          SET exit_time=?, exit_price=?, pnl_amount=?, r_multiple=?, outcome=?, notes_post=?
          WHERE id=? AND user_id=?
        ");
        $upd->bind_param("sdddssii", $exit_time, $exit_price, $pnl_amount, $r_multiple, $outcome, $notes_post, $id, $user_id);
      }

      $upd->execute();
      $ok = "Trade closed. R and outcome calculated.";

      // refresh trade
      $stmt = $conn->prepare("SELECT * FROM trades WHERE id=? AND user_id=? LIMIT 1");
      $stmt->bind_param("ii", $id, $user_id);
      $stmt->execute();
      $trade = $stmt->get_result()->fetch_assoc();
    }
  }
}

$is_closed = !empty($trade['exit_time']);

require_once __DIR__ . "/partials/app_header.php";
?>

<style>
  .chip{
    display:inline-flex;align-items:center;gap:8px;
    padding:6px 10px;border-radius:999px;
    border:1px solid var(--border);
    background:var(--pill);
    color:var(--text);
    font-weight:800;font-size:12px;
    text-decoration:none;
  }
  .chip:hover{box-shadow:var(--shadow);transform:translateY(-1px)}
  .chip strong{font-weight:900}
</style>

<div class="card">
  <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div>
      <h2 style="margin:0"><?= e($trade['symbol']) ?> • <?= e($trade['direction']) ?></h2>
      <div class="small">
        <?= e($trade['market']) ?><?= $trade['session'] ? " • " . e($trade['session']) : "" ?> • <?= e($trade['entry_time']) ?>
      </div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn ghost" href="/trading-journal/log_edit.php?id=<?= (int)$trade['id'] ?>">Edit</a>
      <?php if ($is_closed): ?>
        <a class="btn" href="/trading-journal/review_form.php?trade_id=<?= (int)$trade['id'] ?>">
          <?= (int)$trade['is_reviewed']===1 ? "Edit Review" : "Add Review" ?>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="ok"><?= e($ok) ?></p><?php endif; ?>

  <div class="row">
    <div class="col card">
      <h3>Context</h3>
      <div class="small">
        <b>Strategy:</b>
        <?= $strategy ? '<span class="chip"><strong>'.e($strategy).'</strong></span>' : '—' ?>
      </div>
      <div class="small"><b>Setup:</b> <?= e($trade['setup'] ?? '') ?></div>
      <div class="small"><b>Legacy tags:</b> <?= e($trade['tags'] ?? '') ?></div>
      <div class="small"><b>Mistakes:</b> <?= $mistakeNames ? e(implode(", ", $mistakeNames)) : "—" ?></div>
    </div>

    <div class="col card">
      <h3>Result</h3>
      <div class="small">Exit time: <?= $trade['exit_time'] ? e($trade['exit_time']) : "open" ?></div>
      <div class="small">P/L: <?= $trade['pnl_amount']===null ? "-" : number_format((float)$trade['pnl_amount'], 2) ?></div>
      <div class="small">R: <?= $trade['r_multiple']===null ? "-" : number_format((float)$trade['r_multiple'], 2) . "R" ?></div>
      <div class="small">Outcome: <?= $trade['outcome'] ? e($trade['outcome']) : "-" ?></div>
      <div class="small">Review: <?= (int)$trade['is_reviewed']===1 ? "Reviewed" : "Pending" ?></div>
      <?php if ($review && $review['rules_score'] !== null): ?>
        <div class="small">Rules score: <?= (int)$review['rules_score'] ?>/100</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- QUICK ASSIGN STRATEGY -->
  <div class="card">
    <h3>Quick Assign Strategy</h3>
    <form method="post" class="row" style="align-items:end">
      <input type="hidden" name="action" value="set_strategy">

      <div class="col">
        <label>Select</label>
        <select name="strategy_existing">
          <option value="" <?= $strategy===''?'selected':'' ?>>(Unassigned)</option>
          <?php foreach ($opts as $o): ?>
            <?php $nm = (string)$o['name']; ?>
            <option value="<?= e($nm) ?>" <?= ($strategy===$nm)?'selected':'' ?>><?= e($nm) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="small">Choose a strategy for this trade.</div>
      </div>

      <div class="col">
        <label>Or create new</label>
        <input name="strategy_new" placeholder="e.g. Sweep + MSS">
        <div class="small">If filled, it creates and assigns.</div>
      </div>

      <div class="col" style="align-self:end">
        <button class="btn" type="submit">Save</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h3>Notes</h3>
    <div class="small" style="margin-top:8px"><b>Pre-trade:</b><br><?= nl2br(e($trade['notes_pre'] ?? '')) ?></div>
    <div class="small" style="margin-top:8px"><b>Post-trade:</b><br><?= nl2br(e($trade['notes_post'] ?? '')) ?></div>
  </div>
</div>

<?php if (!$is_closed): ?>
<div class="card">
  <h2>Close Trade (compute R)</h2>
  <form method="post">
    <input type="hidden" name="action" value="close">

    <div class="row">
      <div class="col">
        <label>Exit time</label>
        <input name="exit_time" type="datetime-local" required>
      </div>
      <div class="col">
        <label>Exit price (optional)</label>
        <input name="exit_price" type="number" step="0.00000001">
      </div>
      <div class="col">
        <label>P/L in money (required)</label>
        <input name="pnl_amount" type="number" step="0.01" required>
        <div class="small">R = P/L ÷ Risk(1R)</div>
      </div>
    </div>

    <label>Post-trade review (optional)</label>
    <textarea name="notes_post"></textarea>

    <div style="margin-top:12px">
      <button class="btn" type="submit">Close trade</button>
    </div>
  </form>
</div>
<?php endif; ?>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>
