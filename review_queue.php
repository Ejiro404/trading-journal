<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
require_login();

$current = "review";
$pageTitle = "Review Queue • NXLOG Analytics";

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

$stmt = $conn->prepare("
  SELECT
    id,
    entry_time,
    symbol,
    direction,
    r_multiple
  FROM trades
  WHERE user_id = ?
    AND r_multiple IS NOT NULL
    AND (is_reviewed = 0 OR is_reviewed IS NULL)
  ORDER BY entry_time ASC
  LIMIT 200
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . "/partials/app_header.php";
?>

<style>
.review-wrap{
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

.table-panel{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:18px;
  box-shadow:var(--shadow);
  min-width:0;
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
  line-height:1.1;
  font-weight:900;
}

.table-head .sub{
  color:var(--muted);
  font-size:13px;
  font-weight:700;
  line-height:1.5;
}

.table-wrap{
  width:100%;
  overflow-x:auto;
  -webkit-overflow-scrolling:touch;
  padding-top:12px;
}

.review-table{
  width:100%;
  border-collapse:collapse;
  min-width:760px;
}

.review-table th,
.review-table td{
  padding:14px 16px;
  text-align:left;
  border-bottom:1px solid var(--border);
  vertical-align:middle;
  white-space:nowrap;
}

.review-table th{
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:.03em;
  color:var(--muted);
  font-weight:900;
}

.review-table tr:last-child td{
  border-bottom:none;
}

.pill{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:6px;
  border:1px solid var(--border);
  background:var(--pill);
  border-radius:999px;
  padding:6px 10px;
  font-size:12px;
  font-weight:900;
  white-space:nowrap;
}

.pill.buy{ color:#16a34a; }
.pill.sell{ color:#ef4444; }
.pill.good{ color:#16a34a; }
.pill.bad{ color:#ef4444; }

.empty-state{
  padding:28px 18px;
  text-align:center;
}

.empty-state p{
  margin:0 0 14px;
  color:var(--muted);
  line-height:1.6;
}

.mobile-review-list{
  display:none;
}

@media (max-width:720px){
  .review-wrap{
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

  .table-panel{
    background:transparent;
    border:0;
    box-shadow:none;
  }

  .table-head{
    padding:0;
  }

  .table-head h3{
    font-size:18px;
  }

  .table-head .sub{
    font-size:11px;
  }

  .table-wrap{
    display:none;
  }

  .mobile-review-list{
    display:grid;
    gap:10px;
    margin-top:10px;
  }

  .mobile-review-card{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:18px;
    box-shadow:var(--shadow);
    padding:13px;
  }

  .mobile-review-top{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
  }

  .mobile-symbol{
    font-size:17px;
    line-height:1.05;
    font-weight:950;
    letter-spacing:-.03em;
  }

  .mobile-time{
    margin-top:4px;
    font-size:10px;
    color:var(--muted);
    font-weight:800;
  }

  .mobile-review-r{
    text-align:right;
    white-space:nowrap;
  }

  .mobile-pill-row{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
    margin-top:10px;
  }

  .mobile-review-actions{
    display:grid;
    grid-template-columns:1fr;
    gap:8px;
    margin-top:12px;
  }

  .mobile-review-actions .btn{
    width:100%;
    min-height:36px;
    padding:8px 10px;
    font-size:12px;
  }

  .empty-state{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:18px;
    box-shadow:var(--shadow);
    padding:22px 14px;
  }

  .empty-state .btn{
    width:100%;
    min-height:36px;
    padding:8px 10px;
    font-size:12px;
  }
}
</style>

<div class="review-wrap">

  <div class="page-head">
    <div>
      <h1>Review Queue</h1>
      <p>Unreviewed closed trades. Review is where growth happens.</p>
    </div>
  </div>

  <div class="table-panel">
    <div class="table-head">
      <div>
        <h3>Pending Reviews</h3>
        <div class="sub"><?= count($rows) ?> trade<?= count($rows) === 1 ? '' : 's' ?> waiting for review</div>
      </div>
    </div>

    <?php if (!$rows): ?>
      <div class="empty-state">
        <p>You’re clear. No pending reviews.</p>
        <a class="btn secondary" href="/trading-journal/trade-history.php">Open Trade History</a>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="review-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Symbol</th>
              <th>Side</th>
              <th>R</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $t): ?>
              <?php
                $dir = strtoupper((string)$t['direction']);
                $dirClass = $dir === 'BUY' ? 'buy' : 'sell';
                $rVal = (float)$t['r_multiple'];
                $rClass = $rVal >= 0 ? 'good' : 'bad';
              ?>
              <tr>
                <td><?= e(fmt_dt($t['entry_time'])) ?></td>
                <td><strong><?= e($t['symbol']) ?></strong></td>
                <td>
                  <span class="pill <?= e($dirClass) ?>"><?= e($dir) ?></span>
                </td>
                <td>
                  <span class="pill <?= e($rClass) ?>"><?= e(number_format($rVal, 2)) ?>R</span>
                </td>
                <td>
                  <a class="btn" href="/trading-journal/review_form.php?trade_id=<?= (int)$t['id'] ?>">Review</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="mobile-review-list">
        <?php foreach ($rows as $t): ?>
          <?php
            $dir = strtoupper((string)$t['direction']);
            $dirClass = $dir === 'BUY' ? 'buy' : 'sell';
            $rVal = (float)$t['r_multiple'];
            $rClass = $rVal >= 0 ? 'good' : 'bad';
          ?>
          <article class="mobile-review-card">
            <div class="mobile-review-top">
              <div>
                <div class="mobile-symbol"><?= e($t['symbol']) ?></div>
                <div class="mobile-time"><?= e(fmt_dt($t['entry_time'])) ?></div>
              </div>

              <div class="mobile-review-r">
                <span class="pill <?= e($rClass) ?>"><?= e(number_format($rVal, 2)) ?>R</span>
              </div>
            </div>

            <div class="mobile-pill-row">
              <span class="pill <?= e($dirClass) ?>"><?= e($dir) ?></span>
            </div>

            <div class="mobile-review-actions">
              <a class="btn" href="/trading-journal/review_form.php?trade_id=<?= (int)$t['id'] ?>">Review Trade</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . "/partials/app_footer.php"; ?>