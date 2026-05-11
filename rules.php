<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "settings";
$pageTitle = "Rules • NXLOG Analytics";

$user_id = (int)$_SESSION['user_id'];
$error = $ok = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
  $name = trim($_POST['name'] ?? '');

  if ($name === '') {
    $error = "Rule name cannot be empty.";
  } else {
    try {
      $stmt = $conn->prepare("INSERT INTO trade_rules (user_id, name, is_active) VALUES (?, ?, 1)");
      $stmt->bind_param("is", $user_id, $name);
      $stmt->execute();
      $ok = "Rule added.";
    } catch (Throwable $e) {
      $error = "Could not add rule. It may already exist.";
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
  $rule_id = (int)($_POST['rule_id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  $is_active = isset($_POST['is_active']) ? 1 : 0;

  if ($rule_id <= 0) {
    $error = "Invalid rule.";
  } elseif ($name === '') {
    $error = "Rule name cannot be empty.";
  } else {
    $stmt = $conn->prepare("UPDATE trade_rules SET name=?, is_active=? WHERE id=? AND user_id=?");
    $stmt->bind_param("siii", $name, $is_active, $rule_id, $user_id);
    $stmt->execute();
    $ok = "Rule updated.";
  }
}

$stmt = $conn->prepare("SELECT id, name, is_active, created_at FROM trade_rules WHERE user_id=? ORDER BY is_active DESC, name ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . "/partials/app_header.php";
?>

<style>
.rules-wrap{
  display:grid;
  gap:14px;
  width:100%;
  max-width:100%;
}

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
  line-height:1.05;
  font-weight:900;
  letter-spacing:-.03em;
}

.page-head p{
  margin:6px 0 0;
  color:var(--muted);
  line-height:1.6;
}

.rules-card{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:18px;
  box-shadow:var(--shadow);
  padding:16px;
  min-width:0;
}

.card-title{
  margin:0 0 4px;
  font-size:20px;
  line-height:1.1;
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
  margin-bottom:14px;
}

.alert.err{ color:#ef4444; }
.alert.ok{ color:#16a34a; }

.add-grid{
  display:grid;
  grid-template-columns:1fr auto;
  gap:12px;
  align-items:end;
}

.field{
  display:grid;
  gap:6px;
  min-width:0;
}

.field label{
  margin:0;
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
  font-size:16px;
}

.table-wrap{
  width:100%;
  overflow-x:auto;
  -webkit-overflow-scrolling:touch;
}

.rules-table{
  width:100%;
  border-collapse:collapse;
  min-width:760px;
}

.rules-table th,
.rules-table td{
  padding:14px 16px;
  border-bottom:1px solid var(--border);
  text-align:left;
  vertical-align:middle;
}

.rules-table th{
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:.03em;
  color:var(--muted);
  font-weight:900;
}

.rules-table tr:last-child td{
  border-bottom:none;
}

.rule-form{
  display:contents;
}

.rule-name-input{
  width:100%;
  min-height:40px;
  border:1px solid var(--border);
  background:var(--bg);
  color:var(--text);
  border-radius:12px;
  padding:9px 11px;
  outline:none;
  font-size:16px;
}

.active-row{
  display:inline-flex;
  align-items:center;
  gap:8px;
  margin:0;
  color:var(--muted);
  font-size:12px;
  font-weight:800;
  white-space:nowrap;
}

.active-row input{
  width:auto;
}

.status-pill{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:999px;
  padding:6px 10px;
  font-size:12px;
  font-weight:900;
  white-space:nowrap;
}

.status-pill.active{ color:#16a34a; }
.status-pill.inactive{ color:#ef4444; }

.mobile-rule-list{
  display:none;
}

@media (max-width:720px){
  .rules-wrap{
    gap:12px;
  }

  .page-head{
    display:grid;
    gap:10px;
  }

  .page-head h1{
    font-size:22px;
  }

  .page-head p{
    font-size:12px;
  }

  .rules-card{
    padding:13px;
    border-radius:18px;
  }

  .card-title{
    font-size:17px;
  }

  .card-sub{
    font-size:11px;
    margin-bottom:12px;
  }

  .alert{
    font-size:12px;
    padding:10px 12px;
  }

  .add-grid{
    grid-template-columns:1fr;
    gap:10px;
  }

  .add-grid .btn{
    width:100%;
    min-height:36px;
    padding:8px 10px;
    font-size:12px;
  }

  .field label{
    font-size:10px;
  }

  .field input{
    min-height:38px;
    padding:8px 10px;
    font-size:16px;
  }

  .table-wrap{
    display:none;
  }

  .mobile-rule-list{
    display:grid;
    gap:10px;
  }

  .mobile-rule-card{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:18px;
    box-shadow:var(--shadow);
    padding:13px;
  }

  .mobile-rule-card form{
    display:grid;
    gap:10px;
  }

  .mobile-rule-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:10px;
  }

  .mobile-rule-title{
    font-size:12px;
    color:var(--muted);
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.03em;
  }

  .mobile-rule-date{
    font-size:10px;
    color:var(--muted);
    font-weight:800;
    margin-top:4px;
  }

  .rule-name-input{
    min-height:38px;
    padding:8px 10px;
    font-size:16px;
  }

  .mobile-rule-actions{
    display:grid;
    grid-template-columns:1fr;
    gap:8px;
  }

  .mobile-rule-actions .btn{
    width:100%;
    min-height:36px;
    padding:8px 10px;
    font-size:12px;
  }
}
</style>

<div class="rules-wrap">

  <div class="page-head">
    <div>
      <h1>Rules</h1>
      <p>Manage the execution rules used in reviews and discipline tracking.</p>
    </div>
  </div>

  <div class="rules-card">
    <h2 class="card-title">Rule Breaks Manager</h2>
    <div class="card-sub">Add, rename, or disable rules. Disabled rules will not show in the trade checklist.</div>

    <?php if ($error): ?>
      <div class="alert err"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($ok): ?>
      <div class="alert ok"><?= e($ok) ?></div>
    <?php endif; ?>

    <form method="post" class="add-grid">
      <input type="hidden" name="action" value="add">

      <div class="field">
        <label for="name">New Rule Name</label>
        <input id="name" name="name" placeholder="e.g. Traded against HTF bias" required>
      </div>

      <button class="btn" type="submit">Add Rule</button>
    </form>
  </div>

  <div class="rules-card">
    <h2 class="card-title">Your Rules</h2>
    <div class="card-sub"><?= count($rules) ?> rule<?= count($rules) === 1 ? '' : 's' ?> in your rule library.</div>

    <div class="table-wrap">
      <table class="rules-table">
        <thead>
          <tr>
            <th>Rule</th>
            <th>Status</th>
            <th>Created</th>
            <th>Save</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rules as $r): ?>
            <tr>
              <form method="post" class="rule-form">
                <td style="width:55%">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="rule_id" value="<?= (int)$r['id'] ?>">
                  <input class="rule-name-input" name="name" value="<?= e($r['name']) ?>">
                </td>
                <td>
                  <label class="active-row">
                    <input type="checkbox" name="is_active" <?= ((int)$r['is_active'] === 1) ? "checked" : "" ?>>
                    <span class="status-pill <?= ((int)$r['is_active'] === 1) ? 'active' : 'inactive' ?>">
                      <?= ((int)$r['is_active'] === 1) ? 'Active' : 'Inactive' ?>
                    </span>
                  </label>
                </td>
                <td class="small"><?= e($r['created_at']) ?></td>
                <td>
                  <button class="btn" type="submit">Save</button>
                </td>
              </form>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="mobile-rule-list">
      <?php foreach ($rules as $r): ?>
        <article class="mobile-rule-card">
          <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="rule_id" value="<?= (int)$r['id'] ?>">

            <div class="mobile-rule-top">
              <div>
                <div class="mobile-rule-title">Rule</div>
                <div class="mobile-rule-date">Created: <?= e($r['created_at']) ?></div>
              </div>

              <span class="status-pill <?= ((int)$r['is_active'] === 1) ? 'active' : 'inactive' ?>">
                <?= ((int)$r['is_active'] === 1) ? 'Active' : 'Inactive' ?>
              </span>
            </div>

            <input class="rule-name-input" name="name" value="<?= e($r['name']) ?>">

            <label class="active-row">
              <input type="checkbox" name="is_active" <?= ((int)$r['is_active'] === 1) ? "checked" : "" ?>>
              <span>Keep rule active</span>
            </label>

            <div class="mobile-rule-actions">
              <button class="btn" type="submit">Save Rule</button>
            </div>
          </form>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>