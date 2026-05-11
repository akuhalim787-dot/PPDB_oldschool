<?php

declare(strict_types=1);

/**
 * Global black veil fade for student-facing pages — reduces white flash between navigations.
 */

function uaStudentPageTransitionCss(): string
{
    return <<<'CSS'
html {
  background-color: #000000;
}
#ua-page-transition {
  position: fixed;
  inset: 0;
  z-index: 2147483000;
  background-color: #000000;
  opacity: 0;
  visibility: hidden;
  pointer-events: none;
  transition: opacity 0.35s cubic-bezier(0.33, 1, 0.68, 1), 
            visibility 0s linear 0.3s;
}
#ua-page-transition.is-covering {
  opacity: 1;
  visibility: visible;
  pointer-events: auto;
  transition: opacity 0.25s cubic-bezier(0.33, 1, 0.68, 1), 
            visibility 0s linear 0s;
}
body#splash-root #ua-page-transition {
  z-index: 24;
}
CSS;
}

function uaStudentPageTransitionVeil(): string
{
    return '<div id="ua-page-transition" class="is-covering" aria-hidden="true"></div>';
}

function uaStudentPageTransitionScript(): string
{
    return <<<'HTML'
<script>
(function () {
  var EXIT_MS = 250;
  var veil = document.getElementById('ua-page-transition');
  if (!veil) return;

  function revealPage() {
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        veil.classList.remove('is-covering');
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', revealPage);
  } else {
    revealPage();
  }

  document.addEventListener(
    'click',
    function (e) {
      var el = e.target;
      if (!el || typeof el.closest !== 'function') return;
      var a = el.closest('a[href]');
      if (!a) return;
      if (a.dataset && String(a.dataset.noPageTransition) === '1') return;

      var href = (a.getAttribute('href') || '').trim();
      if (!href) return;
      if (href.charAt(0) === '#') return;
      if (/^javascript:/i.test(href)) return;
      if (/^mailto:/i.test(href) || /^tel:/i.test(href)) return;
      if (a.target === '_blank' || a.getAttribute('target') === '_blank') return;
      if (a.hasAttribute('download')) return;

      var resolved;
      try {
        resolved = new URL(a.href);
      } catch (err) {
        return;
      }
      if (resolved.origin !== window.location.origin) return;
      if (
        resolved.pathname === window.location.pathname &&
        resolved.search === window.location.search &&
        resolved.hash
      ) {
        return;
      }

      e.preventDefault();
      veil.classList.add('is-covering');
      window.setTimeout(function () {
        window.location.href = resolved.href;
      }, EXIT_MS);
    },
    true
  );
})();
</script>
HTML;
}
