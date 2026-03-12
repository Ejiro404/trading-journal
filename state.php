<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "state";
$pageTitle = "State • NXLOG Analytics";

$user_id = (int)($_SESSION['user_id'] ?? 0);
$today = date("Y-m-d");

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$error = "";
$ok = "";

/** Load existing entry */
$st = $conn->prepare("SELECT * FROM daily_state WHERE user_id=? AND date=? LIMIT 1");
$st->bind_param("is", $user_id, $today);
$st->execute();
$row = $st->get_result()->fetch_assoc();

$energy = $row['energy'] ?? '';
$mood = $row['mood'] ?? '';
$focus = $row['focus'] ?? '';
$confidence = $row['confidence'] ?? '';
$tilt = $row['tilt_risk'] ?? '';
$notes = $row['notes'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $energy = (int)($_POST['energy'] ?? 0);
  $mood = (int)($_POST['mood'] ?? 0);
  $focus = (int)($_POST['focus'] ?? 0);
  $confidence = (int)($_POST['confidence'] ?? 0);
  $tilt = (int)($_POST['tilt_risk'] ?? 0);
  $notes = trim($_POST['notes'] ?? '');

  $vals = [$energy, $mood, $focus, $confidence, $tilt];
  foreach ($vals as $v) {
    if ($v < 1 || $v > 5) {
      $error = "Scores must be between 1 and 5.";
      break;
    }
  }

  if (!$error) {
    if ($row) {
      $u = $conn->prepare("
        UPDATE daily_state
        SET energy=?, mood=?, focus=?, confidence=?, tilt_risk=?, notes=?
        WHERE user_id=? AND date=?
      ");
      $u->bind_param("iiiiisis", $energy, $mood, $focus, $confidence, $tilt, $notes, $user_id, $today);
      $u->execute();
    } else {
      $i = $conn->prepare("
        INSERT INTO daily_state (user_id, date, energy, mood, focus, confidence, tilt_risk, notes)
        VALUES (?,?,?,?,?,?,?,?)
      ");
      $i->bind_param("isiiiiis", $user_id, $today, $energy, $mood, $focus, $confidence, $tilt, $notes);
      $i->execute();
    }

    $ok = "State saved for today.";

    // refresh current row snapshot after save
    $st = $conn->prepare("SELECT * FROM daily_state WHERE user_id=? AND date=? LIMIT 1");
    $st->bind_param("is", $user_id, $today);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
  }
}

require_once __DIR__ . "/partials/app_header.php";
?>

<style>
.state-wrap{ display:grid; gap:14px; }

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

.state-panel,
.helper-panel{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:18px;
  box-shadow:var(--shadow);
}
.state-panel{ padding:16px; }
.helper-panel{ padding:16px; }

.meta-row{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  flex-wrap:wrap;
  margin-bottom:12px;
}
.meta-row .sub{
  color:var(--muted);
  font-size:13px;
  font-weight:700;
}

.alert{
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:14px;
  padding:12px 14px;
  font-size:14px;
  font-weight:800;
  margin-bottom:12px;
}
.alert.ok{ color:#16a34a; }
.alert.err{ color:#ef4444; }

.form-grid{
  display:grid;
  grid-template-columns:repeat(5,minmax(0,1fr));
  gap:12px;
}
.field{
  display:grid;
  gap:6px;
}
.field label{
  font-size:12px;
  font-weight:800;
  color:var(--muted);
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
  min-height:120px;
  resize:vertical;
}

.full-width{
  grid-column:1 / -1;
}

.score-hint{
  margin-top:4px;
  color:var(--muted);
  font-size:11px;
  font-weight:700;
}

.actions{
  display:flex;
  gap:10px;
  align-items:center;
  flex-wrap:wrap;
  margin-top:14px;
}

.guide-grid{
  display:grid;
  grid-template-columns:repeat(5,minmax(0,1fr));
  gap:12px;
}
.guide-box{
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:14px;
  padding:12px;
}
.guide-title{
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:.03em;
  color:var(--muted);
  font-weight:900;
  margin-bottom:6px;
}
.guide-value{
  font-size:22px;
  font-weight:900;
  line-height:1.1;
}
.guide-sub{
  margin-top:6px;
  color:var(--muted);
  font-size:12px;
  font-weight:700;
}

@media (max-width: 1100px){
  .form-grid,
  .guide-grid{
    grid-template-columns:repeat(2,minmax(0,1fr));
  }
}
@media (max-width: 720px){
  .form-grid,
  .guide-grid{
    grid-template-columns:1fr;
  }
}
</style>

<div class="state-wrap">

  <div class="page-head">
    <div>
      <h1>State</h1>
      <p>A quick daily check-in. Consistency starts before execution.</p>
    </div>
  </div>

  <div class="helper-panel">
    <div class="meta-row">
      <div>
        <strong>Today’s Check-In</strong>
        <div class="sub">Date: <?= e($today) ?></div>
      </div>
      <div class="sub">
        Use a 1–5 scale for each metric.
      </div>
    </div>

    <div class="guide-grid">
      <div class="guide-box">
        <div class="guide-title">Energy</div>
        <div class="guide-value"><?= $energy !== '' ? (int)$energy : '—' ?></div>
        <div class="guide-sub">Physical readiness to trade.</div>
      </div>
      <div class="guide-box">
        <div class="guide-title">Mood</div>
        <div class="guide-value"><?= $mood !== '' ? (int)$mood : '—' ?></div>
        <div class="guide-sub">Emotional baseline for the day.</div>
      </div>
      <div class="guide-box">
        <div class="guide-title">Focus</div>
        <div class="guide-value"><?= $focus !== '' ? (int)$focus : '—' ?></div>
        <div class="guide-sub">Attention quality and mental clarity.</div>
      </div>
      <div class="guide-box">
        <div class="guide-title">Confidence</div>
        <div class="guide-value"><?= $confidence !== '' ? (int)$confidence : '—' ?></div>
        <div class="guide-sub">Execution confidence without forcing trades.</div>
      </div>
      <div class="guide-box">
        <div class="guide-title">Tilt Risk</div>
        <div class="guide-value"><?= $tilt !== '' ? (int)$tilt : '—' ?></div>
        <div class="guide-sub">Higher score means more emotional risk.</div>
      </div>
    </div>
  </div>

  <div class="state-panel">
    <?php if ($error): ?>
      <div class="alert err"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($ok): ?>
      <div class="alert ok"><?= e($ok) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-grid">
        <div class="field">
          <label for="energy">Energy (1–5)</label>
          <input id="energy" type="number" name="energy" min="1" max="5" value="<?= e($energy) ?>" required>
          <div class="score-hint">1 = drained, 5 = fully energized</div>
        </div>

        <div class="field">
          <label for="mood">Mood (1–5)</label>
          <input id="mood" type="number" name="mood" min="1" max="5" value="<?= e($mood) ?>" required>
          <div class="score-hint">1 = unstable, 5 = calm and steady</div>
        </div>

        <div class="field">
          <label for="focus">Focus (1–5)</label>
          <input id="focus" type="number" name="focus" min="1" max="5" value="<?= e($focus) ?>" required>
          <div class="score-hint">1 = scattered, 5 = locked in</div>
        </div>

        <div class="field">
          <label for="confidence">Confidence (1–5)</label>
          <input id="confidence" type="number" name="confidence" min="1" max="5" value="<?= e($confidence) ?>" required>
          <div class="score-hint">1 = doubtful, 5 = composed and ready</div>
        </div>

        <div class="field">
          <label for="tilt_risk">Tilt Risk (1–5)</label>
          <input id="tilt_risk" type="number" name="tilt_risk" min="1" max="5" value="<?= e($tilt) ?>" required>
          <div class="score-hint">1 = low risk, 5 = high emotional risk</div>
        </div>

        <div class="field full-width">
          <label for="notes">Notes (optional)</label>
          <textarea id="notes" name="notes" placeholder="Any context that might affect execution today..."><?= e($notes) ?></textarea>
        </div>
      </div>

      <div class="actions">
        <button class="btn" type="submit">Save State</button>
        <a class="btn secondary" href="/trading-journal/dashboard.php">Back to Dashboard</a>
      </div>
    </form>
  </div>

</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>