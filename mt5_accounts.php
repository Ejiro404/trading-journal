<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "mt5";
$pageTitle = "MT5 Accounts • NXLOG Analytics";

$user_id = (int)($_SESSION['user_id'] ?? 0);
$error = "";
$ok = "";

if (!function_exists('e')) {
  function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
  }
}

function make_sync_token(): string {
  return bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === "add") {
    $account_login  = trim($_POST['account_login'] ?? '');
    $account_server = trim($_POST['account_server'] ?? '');
    $account_name   = trim($_POST['account_name'] ?? '');
    $broker_name    = trim($_POST['broker_name'] ?? '');

    if ($account_login === "" || !ctype_digit($account_login)) {
      $error = "Enter a valid MT5 account login number.";
    } else {
      $sync_token = make_sync_token();

      try {
        $stmt = $conn->prepare("
          INSERT INTO mt5_accounts
            (user_id, account_login, account_server, account_name, broker_name, sync_token, is_active)
          VALUES
            (?, ?, ?, ?, ?, ?, 1)
        ");
        $loginNum = (int)$account_login;
        $stmt->bind_param("iissss", $user_id, $loginNum, $account_server, $account_name, $broker_name, $sync_token);
        $stmt->execute();

        $ok = "MT5 account linked successfully.";
      } catch (Throwable $e) {
        $error = "Could not add this MT5 account. It may already exist.";
      }
    }
  }

  if ($action === "toggle") {
    $account_id = (int)($_POST['account_id'] ?? 0);

    $stmt = $conn->prepare("
      UPDATE mt5_accounts
      SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END
      WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $account_id, $user_id);
    $stmt->execute();

    $ok = "MT5 account status updated.";
  }

  if ($action === "regenerate") {
    $account_id = (int)($_POST['account_id'] ?? 0);
    $new_token = make_sync_token();

    $stmt = $conn->prepare("
      UPDATE mt5_accounts
      SET sync_token = ?
      WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param("sii", $new_token, $account_id, $user_id);
    $stmt->execute();

    $ok = "Sync token regenerated. Update your EA with the new token.";
  }
}

$stmt = $conn->prepare("
  SELECT *
  FROM mt5_accounts
  WHERE user_id = ?
  ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . "/partials/app_header.php";
?>

<style>
.mt5-wrap{
  display:grid;
  gap:14px;
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

.mt5-grid{
  display:grid;
  grid-template-columns:.9fr 1.1fr;
  gap:14px;
}

.mt5-card{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:18px;
  box-shadow:var(--shadow);
  padding:18px;
  min-width:0;
}

.card-title{
  margin:0 0 4px;
  font-size:20px;
  font-weight:900;
}

.card-sub{
  color:var(--muted);
  font-size:13px;
  font-weight:700;
  line-height:1.6;
  margin-bottom:14px;
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

.field input{
  width:100%;
  min-height:44px;
  border:1px solid var(--border);
  background:var(--bg);
  color:var(--text);
  border-radius:12px;
  padding:10px 12px;
  outline:none;
}

.actions{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  margin-top:14px;
}

.account-list{
  display:grid;
  gap:12px;
}

.account-box{
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:16px;
  padding:14px;
}

.account-head{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:10px;
  flex-wrap:wrap;
}

.account-title{
  font-size:17px;
  font-weight:950;
}

.account-sub{
  margin-top:4px;
  color:var(--muted);
  font-size:12px;
  font-weight:700;
  line-height:1.5;
}

.status-pill{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  border:1px solid var(--border);
  background:var(--card);
  border-radius:999px;
  padding:6px 10px;
  font-size:12px;
  font-weight:900;
}

.status-pill.active{ color:#16a34a; }
.status-pill.inactive{ color:#ef4444; }

.token-box{
  margin-top:12px;
  border:1px dashed var(--border);
  background:var(--card);
  border-radius:14px;
  padding:12px;
}

.token-label{
  font-size:11px;
  text-transform:uppercase;
  letter-spacing:.04em;
  color:var(--muted);
  font-weight:900;
  margin-bottom:6px;
}

.token-value{
  font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size:12px;
  line-height:1.6;
  word-break:break-all;
}

.help-list{
  display:grid;
  gap:10px;
}

.help-item{
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:14px;
  padding:12px 14px;
}

.help-title{
  font-size:12px;
  font-weight:900;
  color:var(--muted);
  text-transform:uppercase;
  letter-spacing:.03em;
  margin-bottom:6px;
}

.help-text{
  font-size:13px;
  font-weight:700;
  line-height:1.6;
}

@media (max-width:900px){
  .mt5-grid{
    grid-template-columns:1fr;
  }
}

@media (max-width:720px){
  .page-head h1{
    font-size:22px;
  }

  .page-head p{
    font-size:12px;
  }

  .mt5-card{
    padding:13px;
    border-radius:16px;
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

<div class="mt5-wrap">

  <div class="page-head">
    <div>
      <h1>MT5 Accounts</h1>
      <p>Link MT5 accounts securely so your EA can sync trades into NXLOG automatically.</p>
    </div>

    <div>
      <a class="btn secondary" href="/trading-journal/settings.php">Back to Settings</a>
    </div>
  </div>

  <?php if ($ok): ?>
    <div class="alert ok"><?= e($ok) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert err"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="mt5-grid">

    <div class="mt5-card">
      <h2 class="card-title">Link MT5 Account</h2>
      <div class="card-sub">
        Add each MT5 account separately. NXLOG will generate a private sync token for your EA.
      </div>

      <form method="post">
        <input type="hidden" name="action" value="add">

        <div class="form-grid">
          <div class="field">
            <label for="account_login">MT5 Login</label>
            <input id="account_login" name="account_login" placeholder="e.g. 12345678" required>
          </div>

          <div class="field">
            <label for="account_server">Server</label>
            <input id="account_server" name="account_server" placeholder="e.g. ICMarketsSC-Demo">
          </div>

          <div class="field">
            <label for="account_name">Account Name</label>
            <input id="account_name" name="account_name" placeholder="e.g. FTMO Challenge">
          </div>

          <div class="field">
            <label for="broker_name">Broker</label>
            <input id="broker_name" name="broker_name" placeholder="e.g. IC Markets">
          </div>
        </div>

        <div class="actions">
          <button class="btn" type="submit">Generate Sync Token</button>
        </div>
      </form>
    </div>

    <div class="mt5-card">
      <h2 class="card-title">Connected Accounts</h2>
      <div class="card-sub">
        Use the sync token inside your NXLOG MT5 EA. Never share this token publicly.
      </div>

      <?php if (!$accounts): ?>
        <div class="help-item">
          <div class="help-title">No MT5 Account Yet</div>
          <div class="help-text">Create one on the left, then paste the generated token into your EA settings.</div>
        </div>
      <?php else: ?>
        <div class="account-list">
          <?php foreach ($accounts as $a): ?>
            <div class="account-box">
              <div class="account-head">
                <div>
                  <div class="account-title">
                    <?= e($a['account_name'] ?: "MT5 Account") ?>
                  </div>
                  <div class="account-sub">
                    Login: <?= e($a['account_login']) ?>
                    <?php if (!empty($a['account_server'])): ?>
                      • Server: <?= e($a['account_server']) ?>
                    <?php endif; ?>
                    <?php if (!empty($a['broker_name'])): ?>
                      • Broker: <?= e($a['broker_name']) ?>
                    <?php endif; ?>
                  </div>
                </div>

                <span class="status-pill <?= (int)$a['is_active'] === 1 ? 'active' : 'inactive' ?>">
                  <?= (int)$a['is_active'] === 1 ? "Active" : "Disabled" ?>
                </span>
              </div>

              <div class="token-box">
                <div class="token-label">Sync Token</div>
                <div class="token-value"><?= e($a['sync_token']) ?></div>
              </div>

              <div class="account-sub" style="margin-top:10px">
                Last sync:
                <?= !empty($a['last_sync_at']) ? e($a['last_sync_at']) : "Never synced yet" ?>
              </div>

              <div class="actions">
                <form method="post">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="account_id" value="<?= (int)$a['id'] ?>">
                  <button class="btn secondary" type="submit">
                    <?= (int)$a['is_active'] === 1 ? "Disable" : "Enable" ?>
                  </button>
                </form>

                <form method="post" onsubmit="return confirm('Regenerate token? Your current EA token will stop working.');">
                  <input type="hidden" name="action" value="regenerate">
                  <input type="hidden" name="account_id" value="<?= (int)$a['id'] ?>">
                  <button class="btn danger" type="submit">Regenerate Token</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <div class="mt5-card">
    <h2 class="card-title">EA Setup Notes</h2>
    <div class="help-list">
      <div class="help-item">
        <div class="help-title">Webhook URL</div>
        <div class="help-text">
          Your EA should post trade events to:
          <strong>/trading-journal/api/mt5/trade-hook.php</strong>
        </div>
      </div>

      <div class="help-item">
        <div class="help-title">Required Security</div>
        <div class="help-text">
          The EA should send the account sync token in the JSON payload. The webhook will use it to identify the correct user and MT5 account.
        </div>
      </div>

      <div class="help-item">
        <div class="help-title">Supported Events</div>
        <div class="help-text">
          OPEN, UPDATE, and CLOSE events will be supported by the hardened webhook.
        </div>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>