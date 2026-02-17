<?php
// partials/app_footer.php
?>

    </div> <!-- /.container -->
  </main> <!-- /.main -->
</div> <!-- /.app -->

<script>
(function(){
  // ===== Sidebar collapse =====
  var sbBtn = document.getElementById("sidebarBtn");
  if (sbBtn){
    sbBtn.addEventListener("click", function(){
      document.documentElement.classList.toggle("sb-collapsed");
      localStorage.setItem(
        "nx_sidebar",
        document.documentElement.classList.contains("sb-collapsed") ? "collapsed" : ""
      );
    });

    // restore
    if (localStorage.getItem("nx_sidebar") === "collapsed"){
      document.documentElement.classList.add("sb-collapsed");
    }
  }

  // ===== Theme (auto fallback) =====
  var themeBtn  = document.getElementById("themeToggle");
  var themeIcon = document.getElementById("themeIcon");
  var themeText = document.getElementById("themeText");
  var mq = window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;

  function savedTheme(){
    var v = localStorage.getItem("nx_theme");
    return (v === "dark" || v === "light") ? v : null;
  }

  function setThemeUI(isDark){
    if (themeIcon){
      themeIcon.innerHTML = isDark
        ? '<svg viewBox="0 0 24 24" width="18" height="18"><path d="M21 12.8A8.5 8.5 0 0 1 11.2 3a6.5 6.5 0 1 0 9.8 9.8z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
        : '<svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 18a6 6 0 1 0 0-12 6 6 0 0 0 0 12z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
    }
    if (themeText){
      themeText.textContent = "Theme";
    }
  }

  function applyTheme(){
    var saved = savedTheme(); // dark/light/null(auto)
    var prefersDark = mq ? mq.matches : false;
    var useDark = saved ? (saved === "dark") : prefersDark;
    document.documentElement.classList.toggle("dark", useDark);
    setThemeUI(useDark);
  }

  // follow system changes when on AUTO
  if (mq){
    var onChange = function(){ if (!savedTheme()) applyTheme(); };
    if (mq.addEventListener) mq.addEventListener("change", onChange);
    else if (mq.addListener) mq.addListener(onChange);
  }

  if (themeBtn){
    themeBtn.addEventListener("click", function(){
      var v = savedTheme();
      // simple cycle: AUTO -> DARK -> LIGHT -> AUTO
      if (!v) localStorage.setItem("nx_theme","dark");
      else if (v === "dark") localStorage.setItem("nx_theme","light");
      else localStorage.removeItem("nx_theme");
      applyTheme();
    });
  }

  applyTheme();
})();
</script>

</body>
</html>