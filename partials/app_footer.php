<?php
// partials/app_footer.php
?>
    </div> <!-- /.container -->
  </main> <!-- /.main -->
</div> <!-- /.app -->

<!-- ===== MOBILE BOTTOM NAV ===== -->
<nav class="mobile-bottom-nav">

  <a class="mobile-tab <?= $current==='dashboard'?'active':'' ?>"
     href="/trading-journal/dashboard.php">
    <span class="mobile-tab-ico">
      <svg viewBox="0 0 24 24" fill="none">
        <path d="M3 11.5L12 4l9 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M5 10.5V20h14v-9.5" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
      </svg>
    </span>
    <span class="mobile-tab-text">Home</span>
  </a>

  <a class="mobile-tab <?= $current==='trade-history'?'active':'' ?>"
     href="/trading-journal/trade-history.php">
    <span class="mobile-tab-ico">
      <svg viewBox="0 0 24 24" fill="none">
        <path d="M4 19V5M4 19h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <path d="M8 15V9M12 19V7M16 12V9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </span>
    <span class="mobile-tab-text">Trades</span>
  </a>

  <a class="mobile-tab <?= $current==='log'?'active':'' ?>"
     href="/trading-journal/log.php">
    <span class="mobile-tab-ico">
      <svg viewBox="0 0 24 24" fill="none">
        <path d="M7 4h11a2 2 0 0 1 2 2v14a2 2 0 0 0-2-2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"
          stroke="currentColor"
          stroke-width="2"
          stroke-linejoin="round"/>
        <path d="M7 8h9M7 12h9M7 16h6"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"/>
      </svg>
    </span>
    <span class="mobile-tab-text">Journal</span>
  </a>

  <a class="mobile-tab <?= $current==='analytics'?'active':'' ?>"
     href="/trading-journal/analytics.php">
    <span class="mobile-tab-ico">
      <svg viewBox="0 0 24 24" fill="none">
        <path d="M4 19V5M4 19h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <path d="M8 15V9M12 19V7M16 12V9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </span>
    <span class="mobile-tab-text">Analysis</span>
  </a>

  <button class="mobile-tab more-tab"
          id="mobileMoreBtn"
          type="button">
    <span class="mobile-tab-ico">
      <svg viewBox="0 0 24 24" fill="none">
        <path d="M12 5h.01M12 12h.01M12 19h.01"
          stroke="currentColor"
          stroke-width="3"
          stroke-linecap="round"/>
      </svg>
    </span>
    <span class="mobile-tab-text">More</span>
  </button>

</nav>

<style>
/* ===== MOBILE APP NAV ===== */
.mobile-bottom-nav{
  display:none;
}

@media (max-width:900px){

  body{
    padding-bottom:92px;
  }

  .sidebar{
    display:none !important;
  }

  body.nav-open .sidebar{
    display:flex !important;
  }

  .topbar{
    margin:10px 10px 0;
    border-radius:24px;
    min-height:74px;
    padding:14px 16px;
    box-shadow:
      0 12px 30px rgba(15,23,42,.06),
      inset 0 1px 0 rgba(255,255,255,.55);
  }

  .topbar-title{
    font-size:15px !important;
    font-weight:900;
    letter-spacing:-.02em;
  }

  .container{
    padding-top:10px !important;
    padding-bottom:20px !important;
  }

  .mobile-bottom-nav{
    position:fixed;
    left:0;
    right:0;
    bottom:0;
    z-index:9999;

    height:82px;
    padding:10px 8px calc(10px + env(safe-area-inset-bottom));

    display:flex;
    align-items:flex-start;
    justify-content:space-around;
    gap:4px;

    background:rgba(255,255,255,.88);
    backdrop-filter:blur(20px);

    border-top:1px solid rgba(15,23,42,.06);

    box-shadow:
      0 -10px 40px rgba(15,23,42,.06);
  }

  html.dark .mobile-bottom-nav{
    background:rgba(11,18,32,.92);
    border-top:1px solid rgba(255,255,255,.06);
  }

  .mobile-tab{
    flex:1;
    min-width:0;

    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:6px;

    color:var(--muted);
    text-decoration:none;

    border:none;
    background:none;

    padding:8px 4px;
    border-radius:18px;

    transition:
      background .16s ease,
      transform .16s ease,
      color .16s ease;
  }

  .mobile-tab:active{
    transform:scale(.97);
  }

  .mobile-tab.active{
    color:var(--accent);
  }

  .mobile-tab.active .mobile-tab-ico{
    background:
      radial-gradient(circle at top left,
      rgba(109,94,252,.18),
      transparent 55%),
      rgba(109,94,252,.10);

    border-color:rgba(109,94,252,.20);
  }

  .mobile-tab-ico{
    width:44px;
    height:44px;
    border-radius:16px;

    display:grid;
    place-items:center;

    border:1px solid transparent;

    transition:
      background .16s ease,
      border-color .16s ease,
      transform .16s ease;
  }

  .mobile-tab svg{
    width:22px;
    height:22px;
  }

  .mobile-tab-text{
    font-size:11px;
    font-weight:900;
    letter-spacing:.02em;
    white-space:nowrap;
  }

  .more-tab{
    cursor:pointer;
  }
}
</style>

<script>
(function(){

  var sidebarToggleTop = document.getElementById("sidebarToggleTop");
  var themeToggle = document.getElementById("themeToggle");
  var themeText = document.getElementById("themeText");
  var mobileMenuBtn = document.getElementById("mobileMenuBtn");
  var sidebarBackdrop = document.getElementById("sidebarBackdrop");
  var mobileMoreBtn = document.getElementById("mobileMoreBtn");

  function isDark(){
    return document.documentElement.classList.contains("dark");
  }

  function syncThemeText(){
    if (!themeText) return;
    themeText.textContent = isDark() ? "Light mode" : "Dark mode";
  }

  function closeMobileNav(){
    document.body.classList.remove("nav-open");
  }

  function openMobileNav(){
    document.body.classList.add("nav-open");
  }

  if (sidebarToggleTop){
    sidebarToggleTop.addEventListener("click", function(){

      if (window.innerWidth <= 900){
        openMobileNav();
        return;
      }

      document.documentElement.classList.toggle("sb-collapsed");

      localStorage.setItem(
        "nx_sidebar",
        document.documentElement.classList.contains("sb-collapsed")
          ? "collapsed"
          : "expanded"
      );
    });
  }

  if (themeToggle){

    syncThemeText();

    themeToggle.addEventListener("click", function(){

      var dark = document.documentElement.classList.toggle("dark");

      localStorage.setItem("tj_theme", dark ? "dark" : "light");
      localStorage.setItem("nx_theme", dark ? "dark" : "light");

      syncThemeText();
    });
  }

  if (mobileMenuBtn){
    mobileMenuBtn.addEventListener("click", openMobileNav);
  }

  if (mobileMoreBtn){
    mobileMoreBtn.addEventListener("click", openMobileNav);
  }

  if (sidebarBackdrop){
    sidebarBackdrop.addEventListener("click", closeMobileNav);
  }

  document.querySelectorAll(".sidebar a").forEach(function(link){
    link.addEventListener("click", closeMobileNav);
  });

  window.addEventListener("keydown", function(e){
    if (e.key === "Escape"){
      closeMobileNav();
    }
  });

})();
</script>

</body>
</html>