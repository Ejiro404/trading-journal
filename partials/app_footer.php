<?php
// partials/app_footer.php
?>
    </div> <!-- /.container -->
  </main> <!-- /.main -->
</div> <!-- /.app -->

<script>
(function(){
  // ===== Sidebar collapse toggle (TOP button in sidebar) =====
  var btn = document.getElementById("sidebarToggleTop");
  if (btn){
    btn.addEventListener("click", function(){
      document.documentElement.classList.toggle("sb-collapsed");
      localStorage.setItem(
        "nx_sidebar",
        document.documentElement.classList.contains("sb-collapsed") ? "collapsed" : ""
      );
    });
  }

  // ===== Theme toggle with TRUE AUTO fallback =====
  var themeBtn  = document.getElementById("themeToggle");
  var themeText = document.getElementById("themeText");
  var themeIcon = document.getElementById("themeIcon");
  var mq = window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;

  function getSavedTheme(){
    var v = localStorage.getItem("nx_theme");
    return (v === "dark" || v === "light") ? v : null; // null = AUTO
  }

  function setThemeUI(){
    var saved = getSavedTheme();
    var mode = saved ? (saved === "dark" ? "Dark" : "Light") : "Auto";
    if (themeText) themeText.textContent = "Theme (" + mode + ")";

    // icon based on applied theme
    var useDark = document.documentElement.classList.contains("dark");
    if (themeIcon){
      themeIcon.innerHTML = useDark
        ? '<svg viewBox="0 0 24 24" width="18" height="18"><path d="M21 12.8A8.5 8.5 0 0 1 11.2 3a6.5 6.5 0 1 0 9.8 9.8z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
        : '<svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 18a6 6 0 1 0 0-12 6 6 0 0 0 0 12z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
    }
  }

  function applyTheme(){
    var saved = getSavedTheme();
    var prefersDark = mq ? mq.matches : false;
    var useDark = saved ? (saved === "dark") : prefersDark;
    document.documentElement.classList.toggle("dark", useDark);
    setThemeUI();
  }

  // follow system changes when on AUTO
  if (mq){
    var onChange = function(){ if (!getSavedTheme()) applyTheme(); };
    if (mq.addEventListener) mq.addEventListener("change", onChange);
    else if (mq.addListener) mq.addListener(onChange);
  }

  if (themeBtn){
    themeBtn.addEventListener("click", function(){
      // cycle: AUTO -> DARK -> LIGHT -> AUTO
      var saved = getSavedTheme();
      if (!saved) localStorage.setItem("nx_theme", "dark");
      else if (saved === "dark") localStorage.setItem("nx_theme", "light");
      else localStorage.removeItem("nx_theme");
      applyTheme();
    });
  }

  applyTheme();
})();
</script>

</body>
</html>
