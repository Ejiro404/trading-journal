<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "review";
$pageTitle = "Review • NXLOG Analytics";

$user_id = (int)$_SESSION['user_id'];
$trade_id = (int)($_GET['trade_id'] ?? 0);
if ($trade_id <= 0) { http_response_code(400); exit("Invalid trade."); }

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

function replace_trade_tags_by_type(mysqli $conn, int $trade_id, int $user_id, string $type, array $tag_names): void {
  $del = $conn->prepare("
    DELETE tm FROM trade_tag_map tm
    INNER JOIN trade_tags tt ON tt.id = tm.tag_id
    WHERE tm.trade_id=? AND tt.user_id=? AND tt.tag_type=?
  ");
  $del->bind_param("iis", $trade_id, $user_id, $type);
  $del->execute();

  foreach ($tag_names as $name) {
    $name = trim((string)$name);
    if ($name === "") continue;
    $tag_id = get_or_create_tag($conn, $user_id, $type, $name);
    $ins = $conn->prepare("INSERT IGNORE INTO trade_tag_map (trade_id, tag_id) VALUES (?,?)");
    $ins->bind_param("ii", $trade_id, $tag_id);
    $ins->execute();
  }
}

/** Load trade */
$tstmt = $conn->prepare("SELECT id, symbol, direction, entry_time, r_multiple FROM trades WHERE id=? AND user_id=? LIMIT 1");
$tstmt->bind_param("ii", $trade_id, $user_id);
$tstmt->execute();
$trade = $tstmt->get_result()->fetch_assoc();
if (!$trade) { http_response_code(404); exit("Trade not found."); }
if ($trade['r_multiple'] === null) { exit("Close the trade first (R must be calculated) before reviewing."); }

/** Existing review */
$rstmt = $conn->prepare("SELECT * FROM trade_reviews WHERE trade_id=? LIMIT 1");
$rstmt->bind_param("i", $trade_id);
$rstmt->execute();
$review = $rstmt->get_result()->fetch_assoc();

$plan = $review['plan'] ?? '';
$execution_summary = $review['execution_summary'] ?? '';
$rules_score = $review['rules_score'] ?? '';
$mistakes_notes = $review['mistakes_notes'] ?? '';
$lessons = $review['lessons'] ?? '';
$next_time = $review['next_time'] ?? '';

/** Mistake options */
$mistakeOptions = [];
$ms = $conn->prepare("SELECT name FROM trade_tags WHERE user_id=? AND tag_type='mistake' ORDER BY name ASC");
$ms->bind_param("i", $user_id);
$ms->execute();
$mistakeOptions = $ms->get_result()->fetch_all(MYSQLI_ASSOC);

/** Selected mistakes */
$selectedMistakes = [];
$mm = $conn->prepare("
  SELECT tt.name
  FROM trade_tag_map tm
  INNER JOIN trade_tags tt ON tt.id = tm.tag_id
  WHERE tm.trade_id=? AND tt.user_id=? AND tt.tag_type='mistake'
");
$mm->bind_param("ii", $trade_id, $user_id);
$mm->execute();
$selectedMistakes = array_map(fn($x)=>$x['name'], $mm->get_result()->fetch_all(MYSQLI_ASSOC));

$error = $ok = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $plan = trim($_POST['plan'] ?? '');
  $execution_summary = trim($_POST['execution_summary'] ?? '');
  $rules_score_raw = trim($_POST['rules_score'] ?? '');
  $mistakes_notes = trim($_POST['mistakes_notes'] ?? '');
  $lessons = trim($_POST['lessons'] ?? '');
  $next_time = trim($_POST['next_time'] ?? '');

  $rules_score_val = ($rules_score_raw === '') ? null : (int)$rules_score_raw;
  if ($rules_score_val !== null && ($rules_score_val < 0 || $rules_score_val > 100)) {
    $error = "Rule adherence score must be between 0 and 100.";
  } else {
    if ($review) {
      $u = $conn->prepare("
        UPDATE trade_reviews
        SET plan=?, execution_summary=?, rules_score=?, mistakes_notes=?, lessons=?, next_time=?
        WHERE trade_id=?
      ");
      $u->bind_param("ssisssi", $plan, $execution_summary, $rules_score_val, $mistakes_notes, $lessons, $next_time, $trade_id);
      $u->execute();
    } else {
      $i = $conn->prepare("
        INSERT INTO trade_reviews (trade_id, plan, execution_summary, rules_score, mistakes_notes, lessons, next_time)
        VALUES (?,?,?,?,?,?,?)
      ");
      $i->bind_param("ississs", $trade_id, $plan, $execution_summary, $rules_score_val, $mistakes_notes, $lessons, $next_time);
      $i->execute();
    }

    // Mistake chip toggles (checkboxes)
    $picked = $_POST['mistakes'] ?? [];
    if (!is_array($picked)) $picked = [];

    $newMistake = trim($_POST['mistake_new'] ?? '');
    if ($newMistake !== "") $picked[] = $newMistake;

    replace_trade_tags_by_type($conn, $trade_id, $user_id, "mistake", $picked);

    // Mark reviewed
    $m = $conn->prepare("UPDATE trades SET is_reviewed=1 WHERE id=? AND user_id=?");
    $m->bind_param("ii", $trade_id, $user_id);
    $m->execute();

    $ok = "Review saved.";

    // refresh
    $mm->execute();
    $selectedMistakes = array_map(fn($x)=>$x['name'], $mm->get_result()->fetch_all(MYSQLI_ASSOC));
    $rules_score = $rules_score_val === null ? '' : (string)$rules_score_val;
  }
}

require_once __DIR__ . "/partials/app_header.php";
?>

<style>
  .chipgrid{display:flex;gap:10px;flex-wrap:wrap;margin-top:8px}
  .chipbox{position:relative;display:inline-flex;align-items:center}
  .chipbox input{position:absolute;opacity:0;pointer-events:none}
  .chip{
    display:inline-flex;align-items:center;gap:8px;
    padding:8px 12px;border-radius:999px;
    border:1px solid var(--border);
    background:var(--pill);
    color:var(--text);
    font-weight:900;font-size:12px;
    cursor:pointer;
    user-select:none;
  }
  .chip:hover{box-shadow:var(--shadow);transform:translateY(-1px)}
  .chipbox input:checked + .chip{
    border-color:var(--accent);
    box-shadow:var(--shadow);
  }
</style>

<div class="card">
  <h2>Review</h2>
  <div class="small">
    <b><?= e($trade['symbol']) ?></b> • <?= e($trade['direction']) ?> • <?= e($trade['entry_time']) ?> •
    Result: <b><?= number_format((float)$trade['r_multiple'], 2) ?>R</b>
  </div>

  <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="ok"><?= e($ok) ?></p><?php endif; ?>

  <form method="post">
    <label>Plan</label>
    <textarea name="plan"><?= e($plan) ?></textarea>

    <label>Execution</label>
    <textarea name="execution_summary"><?= e($execution_summary) ?></textarea>

    <label>Rule adherence score (0–100)</label>
    <input name="rules_score" type="number" min="0" max="100" step="5" value="<?= e($rules_score) ?>">

    <label>Mistakes (toggle chips)</label>
    <div class="chipgrid">
      <?php foreach ($mistakeOptions as $o): ?>
        <?php $nm = (string)$o['name']; $checked = in_array($nm, $selectedMistakes, true); ?>
        <label class="chipbox">
          <input type="checkbox" name="mistakes[]" value="<?= e($nm) ?>" <?= $checked ? "checked" : "" ?>>
          <span class="chip"><?= e($nm) ?></span>
        </label>
      <?php endforeach; ?>
      <?php if (!$mistakeOptions): ?>
        <span class="small">No mistake tags yet — create one below.</span>
      <?php endif; ?>
    </div>

    <label>Create new mistake tag (optional)</label>
    <input name="mistake_new" placeholder="e.g. early entry, no confirmation">

    <label>Mistakes / confirmations (notes)</label>
    <textarea name="mistakes_notes"><?= e($mistakes_notes) ?></textarea>

    <label>Lessons</label>
    <textarea name="lessons"><?= e($lessons) ?></textarea>

    <label>Next time</label>
    <textarea name="next_time"><?= e($next_time) ?></textarea>

    <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn" type="submit">Save review</button>
      <a class="btn ghost" href="/trading-journal/log_view.php?id=<?= (int)$trade_id ?>">Back to trade</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>
