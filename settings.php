<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "settings";
$pageTitle = "Settings • NXLOG Analytics";

$user_id = (int)($_SESSION['user_id'] ?? 0);
$error = "";
$ok = "";

if (!function_exists('e')) {
  function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
  }
}

$stmt = $conn->prepare("
  SELECT
    id,
    first_name,
    last_name,
    username,
    name,
    email,
    created_at
  FROM users
  WHERE id = ?
  LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
  http_response_code(404);
  exit("User not found.");
}

$first_name = trim((string)($user['first_name'] ?? ''));
$last_name  = trim((string)($user['last_name'] ?? ''));
$username   = trim((string)($user['username'] ?? ''));
$email      = trim((string)($user['email'] ?? ''));

$showEdit = isset($_GET['edit']) && $_GET['edit'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $showEdit = true;

  $first_name = trim($_POST['first_name'] ?? '');
  $last_name  = trim($_POST['last_name'] ?? '');
  $username   = strtolower(trim($_POST['username'] ?? ''));
  $email      = strtolower(trim($_POST['email'] ?? ''));

  $full_name = trim($first_name . " " . $last_name);

  if ($first_name === "" || $last_name === "" || $email === "") {
    $error = "First name, last name, and email are required.";
  }

  elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Enter a valid email address.";
  }

  elseif ($username !== "" && !preg_match('/^[a-z0-9_]{3,20}$/', $username)) {
    $error = "Username must be 3–20 characters using lowercase letters, numbers, or underscore.";
  }

  else {

    $check = $conn->prepare("
      SELECT id
      FROM users
      WHERE email = ?
      AND id != ?
      LIMIT 1
    ");
    $check->bind_param("si", $email, $user_id);
    $check->execute();

    if ($check->get_result()->fetch_assoc()) {
      $error = "That email is already in use.";
    }

    if (!$error && $username !== "") {

      $checkUser = $conn->prepare("
        SELECT id
        FROM users
        WHERE username = ?
        AND id != ?
        LIMIT 1
      ");
      $checkUser->bind_param("si", $username, $user_id);
      $checkUser->execute();

      if ($checkUser->get_result()->fetch_assoc()) {
        $error = "That username is already taken.";
      }
    }

    if (!$error) {

      $usernameDb = $username === "" ? null : $username;

      $upd = $conn->prepare("
        UPDATE users
        SET
          first_name = ?,
          last_name = ?,
          username = ?,
          name = ?,
          email = ?
        WHERE id = ?
      ");

      $upd->bind_param(
        "sssssi",
        $first_name,
        $last_name,
        $usernameDb,
        $full_name,
        $email,
        $user_id
      );

      $upd->execute();

      $_SESSION['user_name']  = $full_name;
      $_SESSION['user_email'] = $email;

      $ok = "Profile updated successfully.";
      $showEdit = false;
    }
  }
}

$displayName = trim($first_name . " " . $last_name);

if ($displayName === "") {
  $displayName = (string)($user['name'] ?? "NXLOG User");
}

$initial = strtoupper(substr($first_name ?: $displayName ?: "N", 0, 1));

require_once __DIR__ . "/partials/app_header.php";
?>

<style>
.settings-wrap{
  display:grid;
  gap:14px;
  width:100%;
}

.page-head{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:12px;
  flex-wrap:wrap;
}

.page-head h1{
  margin:0;
  font-size:30px;
  font-weight:950;
  letter-spacing:-.04em;
}

.page-head p{
  margin:6px 0 0;
  color:var(--muted);
  line-height:1.7;
}

.settings-grid{
  display:grid;
  grid-template-columns:.95fr 1.05fr;
  gap:14px;
}

.profile-card,
.settings-card,
.side-card{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:22px;
  box-shadow:var(--shadow);
  padding:20px;
  min-width:0;
}

.avatar{
  width:78px;
  height:78px;
  border-radius:24px;
  display:grid;
  place-items:center;
  background:
    radial-gradient(circle at top left, rgba(109,94,252,.35), transparent 42%),
    var(--pill);
  border:1px solid rgba(109,94,252,.35);
  font-size:28px;
  font-weight:950;
  margin-bottom:16px;
}

.profile-name{
  font-size:30px;
  font-weight:950;
  letter-spacing:-.04em;
  line-height:1.05;
}

.profile-user{
  margin-top:6px;
  color:var(--muted);
  font-size:14px;
  font-weight:800;
}

.profile-meta{
  display:grid;
  gap:12px;
  margin-top:20px;
}

.meta-box{
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:16px;
  padding:14px;
}

.meta-label{
  font-size:11px;
  text-transform:uppercase;
  letter-spacing:.05em;
  color:var(--muted);
  font-weight:900;
  margin-bottom:6px;
}

.meta-value{
  font-size:15px;
  font-weight:900;
  word-break:break-word;
}

.card-title{
  margin:0 0 5px;
  font-size:22px;
  font-weight:950;
  letter-spacing:-.03em;
}

.card-sub{
  color:var(--muted);
  font-size:13px;
  line-height:1.7;
  margin-bottom:16px;
  font-weight:700;
}

.alert{
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:14px;
  padding:12px 14px;
  font-size:14px;
  font-weight:800;
}

.alert.ok{ color:#16a34a; }
.alert.err{ color:#ef4444; }

.form-grid{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:12px;
}

.field{
  display:grid;
  gap:7px;
}

.field label{
  font-size:11px;
  text-transform:uppercase;
  letter-spacing:.05em;
  color:var(--muted);
  font-weight:900;
}

.field input{
  width:100%;
  min-height:48px;
  border:1px solid var(--border);
  background:var(--bg);
  color:var(--text);
  border-radius:14px;
  padding:12px 14px;
  outline:none;
}

.field-help{
  color:var(--muted);
  font-size:11px;
  line-height:1.6;
  font-weight:700;
}

.span-2{
  grid-column:1 / -1;
}

.actions{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  margin-top:18px;
}

.side-grid{
  display:grid;
  gap:14px;
}

.info-box{
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:16px;
  padding:14px;
}

.info-label{
  font-size:11px;
  text-transform:uppercase;
  letter-spacing:.05em;
  color:var(--muted);
  font-weight:900;
  margin-bottom:6px;
}

.info-text{
  font-size:14px;
  line-height:1.7;
  font-weight:700;
}

.stage-pill{
  display:inline-flex;
  align-items:center;
  gap:6px;
  border-radius:999px;
  padding:7px 12px;
  border:1px solid rgba(109,94,252,.28);
  background:rgba(109,94,252,.10);
  color:#7c6cff;
  font-size:12px;
  font-weight:900;
}

@media (max-width:900px){

  .settings-grid{
    grid-template-columns:1fr;
  }
}

@media (max-width:720px){

  .page-head h1{
    font-size:24px;
  }

  .profile-card,
  .settings-card,
  .side-card{
    padding:14px;
    border-radius:18px;
  }

  .profile-name{
    font-size:24px;
  }

  .avatar{
    width:64px;
    height:64px;
    border-radius:20px;
    font-size:22px;
  }

  .form-grid{
    grid-template-columns:1fr;
  }

  .span-2{
    grid-column:auto;
  }

  .actions{
    display:grid;
    grid-template-columns:1fr;
  }

  .actions .btn{
    width:100%;
  }
}
</style>

<div class="settings-wrap">

  <div class="page-head">
    <div>
      <h1>Settings</h1>
      <p>
        Manage your NXLOG account identity, profile details,
        preferences, and SaaS workspace information.
      </p>
    </div>
  </div>

  <?php if ($ok): ?>
    <div class="alert ok"><?= e($ok) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert err"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="settings-grid">

    <!-- LEFT -->
    <div style="display:grid;gap:14px;">

      <div class="profile-card">

        <div class="avatar"><?= e($initial) ?></div>

        <div class="profile-name"><?= e($displayName) ?></div>

        <div class="profile-user">
          <?= $username ? '@' . e($username) : 'No username set yet' ?>
        </div>

        <div class="profile-meta">

          <div class="meta-box">
            <div class="meta-label">Email</div>
            <div class="meta-value"><?= e($email) ?></div>
          </div>

          <div class="meta-box">
            <div class="meta-label">Joined</div>
            <div class="meta-value"><?= e($user['created_at'] ?? '-') ?></div>
          </div>

          <div class="meta-box">
            <div class="meta-label">Product Stage</div>
            <div class="meta-value">
              <span class="stage-pill">MVP</span>
            </div>
          </div>

        </div>

      </div>

      <?php if ($showEdit): ?>

      <div class="settings-card">

        <h2 class="card-title">Edit Profile</h2>

        <div class="card-sub">
          Update your profile identity and account details.
        </div>

        <form method="post">

          <div class="form-grid">

            <div class="field">
              <label>First Name</label>
              <input
                name="first_name"
                value="<?= e($first_name) ?>"
                required
              >
            </div>

            <div class="field">
              <label>Last Name</label>
              <input
                name="last_name"
                value="<?= e($last_name) ?>"
                required
              >
            </div>

            <div class="field span-2">
              <label>Username</label>
              <input
                name="username"
                value="<?= e($username) ?>"
                placeholder="e.g. nx_trader"
              >

              <div class="field-help">
                Use 3–20 lowercase letters, numbers, or underscore.
              </div>
            </div>

            <div class="field span-2">
              <label>Email</label>
              <input
                type="email"
                name="email"
                value="<?= e($email) ?>"
                required
              >
            </div>

          </div>

          <div class="actions">
            <button class="btn" type="submit">
              Save Changes
            </button>

            <a class="btn secondary" href="/trading-journal/settings.php">
              Cancel
            </a>
          </div>

        </form>

      </div>

      <?php else: ?>

      <div class="settings-card">

        <h2 class="card-title">Account Profile</h2>

        <div class="card-sub">
          Your NXLOG identity profile is active and synced.
        </div>

        <div class="actions">
          <a class="btn" href="/trading-journal/settings.php?edit=1">
            Edit Profile
          </a>

          <a class="btn secondary" href="/trading-journal/dashboard.php">
            Back to Dashboard
          </a>
        </div>

      </div>

      <?php endif; ?>

    </div>

    <!-- RIGHT -->
    <div class="side-grid">

      <div class="side-card">

        <h2 class="card-title">Preferences</h2>

        <div class="card-sub">
          More account and workspace customization options
          will be added in future iterations.
        </div>

        <div class="info-box">
          <div class="info-label">Planned</div>

          <div class="info-text">
            Theme preferences, notifications, account security,
            billing controls, AI coaching preferences, and MT5
            connection management will live here later.
          </div>
        </div>

      </div>

      <div class="side-card">

        <h2 class="card-title">Support</h2>

        <div class="card-sub">
          Need help or want to report an issue?
        </div>

        <div class="info-box">
          <div class="info-label">Contact</div>

          <div class="info-text">
            Support tools, feedback submission,
            help center access, and direct reporting
            will be added during later SaaS stages.
          </div>
        </div>

      </div>

      <div class="side-card">

        <h2 class="card-title">Session</h2>

        <div class="card-sub">
          Manage your active account session.
        </div>

        <div class="actions">
          <a class="btn danger" href="/trading-journal/logout.php">
            Logout
          </a>
        </div>

      </div>

    </div>

  </div>

</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>