</main>

<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-grid">

      <div class="footer-col">
        <a href="<?php echo isset($basePath) ? $basePath : ''; ?>index.php" class="footer-brand">
          <span class="footer-brand-text">Document Request System</span>
        </a>
        <p>Request registrar documents online — Certificates, Transcript of Records, Grades,
          and more — without waiting in line at the office.</p>

      </div>

      <div class="footer-col">
        <h4>Quick Links</h4>
        <ul>
          <li><a href="<?php echo isset($basePath) ? $basePath : ''; ?>index.php">Home</a></li>
          <li><a href="<?php echo isset($basePath) ? $basePath : ''; ?>request.php">Request</a></li>
          <li><a href="<?php echo isset($basePath) ? $basePath : ''; ?>track.php">Track</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Links</h4>
        <ul>
          <li><a href="https://www.facebook.com/URSMorongCampus/" target="_blank" rel="noopener">URS - Morong</a></li>
          <li><a href="https://www.facebook.com/profile.php?id=100093695203421" target="_blank" rel="noopener">Registrar Office</a></li>
          <li><a href="https://olesappone.univrs.edu.ph/StudentEnrollment" target="_blank" rel="noopener">Student Portal</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Registrar Office Hours</h4>
        <ul class="footer-hours">
          <li>
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="3" y="4.5" width="18" height="16" rx="2.5" stroke="currentColor" stroke-width="1.5" />
              <path d="M3 9.5H21" stroke="currentColor" stroke-width="1.5" />
              <path d="M8 2.5V6.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
              <path d="M16 2.5V6.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
            </svg>
            <span>Office Hours: 7:00am &ndash; 6:00pm</span>
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="3" y="4.5" width="18" height="16" rx="2.5" stroke="currentColor" stroke-width="1.5" />
              <path d="M3 9.5H21" stroke="currentColor" stroke-width="1.5" />
              <path d="M8 2.5V6.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
              <path d="M16 2.5V6.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
            </svg>
            <span>Business Days: Monday &ndash; Thursday</span>
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 21.5s7-6.1 7-11.7A7 7 0 0 0 5 9.8c0 5.6 7 11.7 7 11.7Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
              <circle cx="12" cy="9.8" r="2.4" stroke="currentColor" stroke-width="1.5" />
            </svg>
            <span>Registrar's Office, Morong Campus</span>
          </li>
        </ul>
      </div>

    </div>

    <div class="footer-bottom">
      <p>&copy; <?php echo date('Y'); ?> Document Request System &mdash; Registrar's Office</p>
    </div>
  </div>
</footer>

<script src="<?php echo isset($basePath) ? $basePath : ''; ?>assets/js/script.js"></script>
<script>
  (function() {
    var btn = document.getElementById('navToggle');
    var nav = document.getElementById('siteNav');
    if (!btn || !nav) return;
    btn.addEventListener('click', function() {
      var open = nav.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  })();
</script>
</body>

</html>