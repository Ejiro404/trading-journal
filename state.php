<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "state";
$pageTitle = "State • NXLOG Analytics";

$user_id = (int)$_SESSION['user_id'];
$today = date("Y-m-d");

$error = $ok = "";

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

  $vals = [$energy,$mood,$focus,$confidence,$tilt];
  foreach ($vals as $v) {
    if ($v < 1 || $v > 5) { $error = "Scores must be between 1 and 5."; break; }
  }

  if (!$error) {
    if ($row) {
      $u = $conn->prepare("
        UPDATE daily_state SET energy=?, mood=?, focus=?, confidence=?, tilt_risk=?, notes=?
        WHERE user_id=? AND date=?
      ");
      $u->bind_param("iiiiisis", $energy,$mood,$focus,$confidence,$tilt,$notes,$user_id,$today);
      $u->execute();
    } else {
      $i = $conn->prepare("
        INSERT INTO daily_state (user_id, date, energy, mood, focus, confidence, tilt_risk, notes)
        VALUES (?,?,?,?,?,?,?,?)
      ");
      $i->bind_param("isiiiiis", $user_id,$today,$energy,$mood,$focus,$confidence,$tilt,$notes);
      $i->execute();
    }
    $ok = "State saved for today.";
  }
}

require_once __DIR__ . "/partials/app_header.php";
?>

<div class="card">
  <h2>State</h2>
  <p class="small">A quick daily check-in. Consistency starts before execution.</p>

  <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="ok"><?= e($ok) ?></p><?php endif; ?>

  <form method="post" class="row">
    <div class="col">
      <label>Energy (1–5)</label>
      <input type="number" name="energy" min="1" max="5" value="<?= e($energy) ?>" required>
    </div>
    <div class="col">
      <label>Mood (1–5)</label>
      <input type="number" name="mood" min="1" max="5" value="<?= e($mood) ?>" required>
    </div>
    <div class="col">
      <label>Focus (1–5)</label>
      <input type="number" name="focus" min="1" max="5" value="<?= e($focus) ?>" required>
    </div>
    <div class="col">
      <label>Confidence (1–5)</label>
      <input type="number" name="confidence" min="1" max="5" value="<?= e($confidence) ?>" required>
    </div>
    <div class="col">
      <label>Tilt risk (1–5)</label>
      <input type="number" name="tilt_risk" min="1" max="5" value="<?= e($tilt) ?>" required>
    </div>

    <div class="col" style="flex:1 1 100%">
      <label>Notes (optional)</label>
      <textarea name="notes" placeholder="Any context that might affect execution today..."><?= e($notes) ?></textarea>
    </div>

    <div class="col" style="align-self:end">
      <button class="btn" type="submit">Save</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>
