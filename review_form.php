<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "review";
$pageTitle = "Review • NXLOG Analytics";

$user_id = (int)($_SESSION['user_id'] ?? 0);
$trade_id = (int)($_GET['trade_id'] ?? 0);

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

if ($trade_id <= 0) {
  http_response_code(400);
  exit("Invalid trade.");
}

/** Load trade */
$tstmt = $conn->prepare("
  SELECT id, symbol, direction, entry_time, r_multiple
  FROM trades
  WHERE id=? AND user_id=?
  LIMIT 1
");
$tstmt->bind_param("ii", $trade_id, $user_id);
$tstmt->execute();
$trade = $tstmt->get_result()->fetch_assoc();

if (!$trade) {
  http_response_code(404);
  exit("Trade not found.");
}
if ($trade['r_multiple'] === null) {
  exit("Close the trade first (R must be calculated) before reviewing.");
}

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
$selectedMistakes = array_map(fn($x) => $x['name'], $mm->get_result()->fetch_all(MYSQLI_ASSOC));

$error = "";
$ok = "";

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

    $picked = $_POST['mistakes'] ?? [];
    if (!is_array($picked)) $picked = [];

    $newMistake = trim($_POST['mistake_new'] ?? '');
    if ($newMistake !== "") $picked[] = $newMistake;

    replace_trade_tags_by_type($conn, $trade_id, $user_id, "mistake", $picked);

    $m = $conn->prepare("UPDATE trades SET is_reviewed=1 WHERE id=? AND user_id=?");
    $m->bind_param("ii", $trade_id, $user_id);
    $m->execute();

    $ok = "Review saved.";

    $mm->execute();
    $selectedMistakes = array_map(fn($x) => $x['name'], $mm->get_result()->fetch_all(MYSQLI_ASSOC));
    $rules_score = $rules_score_val === null ? '' : (string)$rules_score_val;
  }
}

require_once __DIR__ . "/partials/app_header.php";
?>

<style>
.reviewform-wrap{ display:grid; gap:14px; }

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

.review-shell{
  display:grid;
  grid-template-columns:1.1fr .9fr;
  gap:14px;
}

.form-panel,
.context-panel{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:18px;
  box-shadow:var(--shadow);
  padding:16px;
}

.panel-title{
  margin:0 0 4px;
  font-size:20px;
  font-weight:900;
}
.panel-sub{
  color:var(--muted);
  font-size:13px;
  font-weight:700;
  margin-bottom:14px;
}

.alert{
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:14px;
  padding:12px 14px;
  font-size:14px;
  font-weight:800;
  margin-bottom:14px;
}
.alert.err{ color:#ef4444; }
.alert.ok{ color:#16a34a; }

.trade-summary{
  display:grid;
  grid-template-columns:repeat(4,minmax(0,1fr));
  gap:12px;
}
.summary-box{
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:14px;
  padding:12px 14px;
}
.summary-label{
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:.03em;
  color:var(--muted);
  font-weight:900;
  margin-bottom:6px;
}
.summary-value{
  font-size:16px;
  font-weight:900;
  line-height:1.3;
}

.form-grid{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:12px;
}
.field{
  display:grid;
  gap:6px;
}
.field label{
  font-size:12px;
  font-weight:900;
  color:var(--muted);
  text-transform:uppercase;
  letter-spacing:.03em;
}
.field input,
.field textarea{
  width:100%;
  border:1px solid var(--border);
  background:var(--bg);
  color:var(--text);
  border-radius:12px;
  padding:10px 12px;
  outline:none;
}
.field input{
  min-height:44px;
}
.field textarea{
  min-height:140px;
  resize:vertical;
}
.span-2{ grid-column:1 / -1; }

.chipgrid{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  margin-top:8px;
}
.chipbox{
  position:relative;
  display:inline-flex;
  align-items:center;
}
.chipbox input{
  position:absolute;
  opacity:0;
  pointer-events:none;
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
  font-weight:900;
  font-size:12px;
  cursor:pointer;
  user-select:none;
  transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease;
}
.chip:hover{
  box-shadow:var(--shadow);
  transform:translateY(-1px);
}
.chipbox input:checked + .chip{
  border-color:rgba(109,94,252,.45);
  box-shadow:0 0 0 4px rgba(109,94,252,.10);
}

.helper-list{
  display:grid;
  gap:10px;
}
.helper-item{
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:14px;
  padding:12px 14px;
}
.helper-item-title{
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:.03em;
  color:var(--muted);
  font-weight:900;
  margin-bottom:6px;
}
.helper-item-text{
  font-size:13px;
  line-height:1.6;
  font-weight:700;
}

.actions{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  margin-top:16px;
}

@media (max-width: 1100px){
  .review-shell{ grid-template-columns:1fr; }
}
@media (max-width: 820px){
  .trade-summary{ grid-template-columns:repeat(2,minmax(0,1fr)); }
  .form-grid{ grid-template-columns:1fr; }
}
@media (max-width: 560px){
  .trade-summary{ grid-template-columns:1fr; }
}
</style>

<div class="reviewform-wrap">

  <div class="page-head">
    <div>
      <h1>Review Trade</h1>
      <p>Turn this closed trade into feedback, lessons, and cleaner execution going forward.</p>
    </div>

    <div class="page-head-actions">
      <a class="btn secondary" href="/trading-journal/log_view.php?id=<?= (int)$trade_id ?>">Back to Trade</a>
    </div>
  </div>

  <div class="review-shell">

    <div class="form-panel">
      <h3 class="panel-title">Review Form</h3>
      <div class="panel-sub">Capture the plan, execution quality, mistakes, lessons, and next-step adjustments.</div>

      <div class="trade-summary" style="margin-bottom:14px;">
        <div class="summary-box">
          <div class="summary-label">Symbol</div>
          <div class="summary-value"><?= e($trade['symbol']) ?></div>
        </div>
        <div class="summary-box">
          <div class="summary-label">Direction</div>
          <div class="summary-value"><?= e($trade['direction']) ?></div>
        </div>
        <div class="summary-box">
          <div class="summary-label">Entry Time</div>
          <div class="summary-value"><?= e(fmt_dt($trade['entry_time'])) ?></div>
        </div>
        <div class="summary-box">
          <div class="summary-label">Result</div>
          <div class="summary-value"><?= number_format((float)$trade['r_multiple'], 2) ?>R</div>
        </div>
      </div>

      <?php if ($error): ?>
        <div class="alert err"><?= e($error) ?></div>
      <?php endif; ?>

      <?php if ($ok): ?>
        <div class="alert ok"><?= e($ok) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="form-grid">

          <div class="field span-2">
            <label for="plan">Plan</label>
            <textarea id="plan" name="plan"><?= e($plan) ?></textarea>
          </div>

          <div class="field span-2">
            <label for="execution_summary">Execution</label>
            <textarea id="execution_summary" name="execution_summary"><?= e($execution_summary) ?></textarea>
          </div>

          <div class="field span-2">
            <label for="rules_score">Rule Adherence Score (0–100)</label>
            <input id="rules_score" name="rules_score" type="number" min="0" max="100" step="5" value="<?= e($rules_score) ?>">
          </div>

          <div class="field span-2">
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
                <span class="panel-sub" style="margin:0">No mistake tags yet — create one below.</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="field span-2">
            <label for="mistake_new">Create New Mistake Tag</label>
            <input id="mistake_new" name="mistake_new" placeholder="e.g. early entry, no confirmation">
          </div>

          <div class="field span-2">
            <label for="mistakes_notes">Mistakes / Confirmations (notes)</label>
            <textarea id="mistakes_notes" name="mistakes_notes"><?= e($mistakes_notes) ?></textarea>
          </div>

          <div class="field span-2">
            <label for="lessons">Lessons</label>
            <textarea id="lessons" name="lessons"><?= e($lessons) ?></textarea>
          </div>

          <div class="field span-2">
            <label for="next_time">Next Time</label>
            <textarea id="next_time" name="next_time"><?= e($next_time) ?></textarea>
          </div>

        </div>

        <div class="actions">
          <button class="btn" type="submit">Save Review</button>
          <a class="btn secondary" href="/trading-journal/log_view.php?id=<?= (int)$trade_id ?>">Cancel</a>
        </div>
      </form>
    </div>

    <div class="context-panel">
      <h3 class="panel-title">Review Guide</h3>
      <div class="panel-sub">Good reviews are specific, honest, and focused on repeatable improvement.</div>

      <div class="helper-list">
        <div class="helper-item">
          <div class="helper-item-title">Plan</div>
          <div class="helper-item-text">State what the setup was supposed to do and what conditions you expected before entry.</div>
        </div>

        <div class="helper-item">
          <div class="helper-item-title">Execution</div>
          <div class="helper-item-text">Explain whether you followed your process cleanly or introduced emotional or technical mistakes.</div>
        </div>

        <div class="helper-item">
          <div class="helper-item-title">Lessons</div>
          <div class="helper-item-text">Write what this trade teaches you. The lesson should be usable on future trades.</div>
        </div>

        <div class="helper-item">
          <div class="helper-item-title">Next Time</div>
          <div class="helper-item-text">Translate the lesson into one specific behavioral improvement or execution rule.</div>
        </div>
      </div>
    </div>

  </div>

</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>