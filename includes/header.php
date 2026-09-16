<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document Request System</title>
  <link rel="stylesheet" href="<?php echo isset($basePath) ? $basePath : ''; ?>assets/css/style.css">
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var nav = document.getElementById('siteNav');
      var indicator = document.getElementById('navIndicator');
      var active = nav ? nav.querySelector('a.active') : null;
      var isMobileNav = window.matchMedia('(max-width: 640px)').matches;

      function boxOf(el) {
        return {
          left: el.offsetLeft,
          width: el.offsetWidth,
          height: el.offsetHeight
        };
      }

      function place(box) {
        indicator.style.left = box.left + 'px';
        indicator.style.width = box.width + 'px';
      }

      function placeInstantly(box) {
        indicator.classList.add('no-anim');
        place(box);
        // Force a reflow so the position lands before the transition re-enables.
        void indicator.offsetWidth;
        indicator.classList.remove('no-anim');
      }

      function initIndicator() {
        if (!(nav && indicator && active && !isMobileNav)) return;

        placeInstantly(boxOf(active));
        requestAnimationFrame(function() {
          indicator.classList.add('is-ready');
        });
      }

      var fontsReady = (document.fonts && document.fonts.ready) ?
        document.fonts.ready :
        Promise.resolve();
      fontsReady.then(initIndicator);

      window.addEventListener('resize', function() {
        if (!(nav && indicator && !isMobileNav)) return;
        var current = nav.querySelector('a.active');
        if (current) placeInstantly(boxOf(current));
      });

      // Nav links: just trigger the page-leaving fade before navigating away.
      if (nav) {
        nav.querySelectorAll('a').forEach(function(link) {
          link.addEventListener('click', function(e) {
            var href = link.getAttribute('href');
            if (!href || e.metaKey || e.ctrlKey || e.shiftKey) return;
            if (link.classList.contains('active')) {
              e.preventDefault();
              return;
            }

            document.body.classList.add('page-leaving');
          });
        });
      }

      var brand = document.querySelector('.brand');
      if (brand) {
        brand.addEventListener('click', function() {
          document.body.classList.add('page-leaving');
        });
      }
    });
  </script>
</head>

<body>
  <?php
  $basePathVal = isset($basePath) ? $basePath : '';
  $currentPage = basename($_SERVER['PHP_SELF'] ?? '');
  $navItems = [
    'index.php'  => 'Home',
    'request.php' => 'Request',
    'track.php'  => 'Track',
  ];
  $navIcons = [
    'index.php' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 11.5L12 4.5L20 11.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 10V19.5H18V10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 19.5V14.5H14V19.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'request.php' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13.5 3H7A1.5 1.5 0 0 0 5.5 4.5V19.5A1.5 1.5 0 0 0 7 21H17A1.5 1.5 0 0 0 18.5 19.5V8L13.5 3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M13.5 3V8H18.5" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M9 13H15M12 10V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
    'track.php' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.6"/><path d="M15.8 15.8L20 20" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
  ];
  ?>
  <?php if ($currentPage !== 'index.php'): ?>
    <a href="<?php echo $basePathVal; ?>index.php" class="mobile-back" aria-label="Back to Home">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M15 5L8 12L15 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
      <span>Back to Home</span>
    </a>
  <?php endif; ?>

  <header class="site-header">
    <div class="container header-inner">
      <a href="<?php echo $basePathVal; ?>index.php" class="brand">
        <img src="<?php echo $basePathVal; ?>assets/images/logo.png" alt="University of Rizal System logo" class="brand-icon">
        <span class="brand-text">Document Request System</span>
      </a>
      <nav class="site-nav" id="siteNav">
        <span class="nav-indicator" id="navIndicator" aria-hidden="true"></span>
        <?php foreach ($navItems as $file => $label): ?>
          <a href="<?php echo $basePathVal . $file; ?>" <?php echo $currentPage === $file ? ' class="active" aria-current="page"' : ''; ?>>
            <span class="nav-icon" aria-hidden="true"><?php echo $navIcons[$file] ?? ''; ?></span>
            <span class="nav-label"><?php echo $label; ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
      <button class="nav-toggle" id="navToggle" aria-label="Menu" aria-expanded="false" aria-controls="siteNav">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>

  <main class="main-content">
</body>