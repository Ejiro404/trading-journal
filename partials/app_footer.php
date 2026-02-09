<?php
require_once __DIR__ . "/../config/app.php";
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
?>
    </main>
  </div>

  <div style="height:16px"></div>
  <div class="small" style="text-align:center;color:var(--muted)">
    © <?= date("Y") ?> <?= e($app["name"]) ?> • <?= e($app["version"]) ?>
  </div>
</div>
</body>
</html>
