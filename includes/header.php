<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : ''; ?>Document Request System</title>
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
        indicator.style.height = box.height + 'px';
      }

      if (nav && indicator && active && !isMobileNav) {
        var target = boxOf(active);
        var from = sessionStorage.getItem('navFrom');

        if (from) {
          sessionStorage.removeItem('navFrom');
          try {
            // Start where the pill was on the previous page, then slide.
            indicator.classList.remove('is-ready');
            place(JSON.parse(from));
            requestAnimationFrame(function() {
              indicator.classList.add('is-ready');
              requestAnimationFrame(function() {
                place(target);
              });
            });
          } catch (err) {
            place(target);
            requestAnimationFrame(function() {
              indicator.classList.add('is-ready');
            });
          }
        } else {
          place(target);
          requestAnimationFrame(function() {
            indicator.classList.add('is-ready');
          });
        }

        window.addEventListener('resize', function() {
          var current = nav.querySelector('a.active');
          if (current) place(boxOf(current));
        });
      }

      // Nav links: remember where the pill is now, then navigate immediately.
      // The slide itself happens on the next page, so nothing is delayed.
      if (nav) {
        nav.querySelectorAll('a').forEach(function(link) {
          link.addEventListener('click', function(e) {
            var href = link.getAttribute('href');
            if (!href || e.metaKey || e.ctrlKey || e.shiftKey) return;
            if (link.classList.contains('active')) {
              e.preventDefault();
              return;
            }

            if (indicator && active && !isMobileNav) {
              sessionStorage.setItem('navFrom', JSON.stringify(boxOf(active)));
            }

            document.body.classList.add('page-leaving');
          });
        });
      }

      var brand = document.querySelector('.brand');
      if (brand) {
        brand.addEventListener('click', function() {
          if (indicator && active && !isMobileNav) {
            sessionStorage.setItem('navFrom', JSON.stringify(boxOf(active)));
          }
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
    'request.php' => 'New Request',
    'track.php'  => 'Track Request',
  ];
  ?>
  <header class="site-header">
    <div class="container header-inner">
      <a href="<?php echo $basePathVal; ?>index.php" class="brand">
        <span class="brand-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M6 2.5H14.5L19 7V21.5H6V2.5Z" stroke="#1a1207" stroke-width="1.4" stroke-linejoin="round" fill="none" />
            <path d="M14.5 2.5V7H19" stroke="#1a1207" stroke-width="1.4" stroke-linejoin="round" fill="none" />
            <path d="M9 12H16" stroke="#1a1207" stroke-width="1.3" stroke-linecap="round" />
            <path d="M9 15.3H16" stroke="#1a1207" stroke-width="1.3" stroke-linecap="round" />
            <path d="M9 18.6H13" stroke="#1a1207" stroke-width="1.3" stroke-linecap="round" />
          </svg>
        </span>
        <span class="brand-text">Document Request System</span>
      </a>
      <nav class="site-nav" id="siteNav">
        <span class="nav-indicator" id="navIndicator" aria-hidden="true"></span>
        <?php foreach ($navItems as $file => $label): ?>
          <a href="<?php echo $basePathVal . $file; ?>" <?php echo $currentPage === $file ? ' class="active" aria-current="page"' : ''; ?>><?php echo $label; ?></a>
        <?php endforeach; ?>
      </nav>
      <button class="nav-toggle" id="navToggle" aria-label="Menu" aria-expanded="false" aria-controls="siteNav">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>

  <main class="main-content">
</body>