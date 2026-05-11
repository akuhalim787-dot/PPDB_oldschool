<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/student_page_transition.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style><?= uaStudentPageTransitionCss() ?></style>
    <title>Universal Academy — Entry</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'Oswald', sans-serif;
            background: #050505;
            color: #FFD65A;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            overflow: hidden;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
            cursor: wait;
            pointer-events: none;
        }

        body.splash-ready {
            cursor: pointer;
            pointer-events: auto;
        }

        /* Dense veil — all content concealed until entrance runs */
        .splash-entrance-veil {
            position: fixed;
            inset: 0;
            z-index: 50;
            background: #020202;
            opacity: 1;
            transition: opacity 2s cubic-bezier(0.22, 0.85, 0.32, 1);
            pointer-events: none;
        }

        body.splash-entrance-run .splash-entrance-veil {
            opacity: 0;
        }

        body.splash-entrance-run .splash-entrance-veil.is-gone {
            visibility: hidden;
        }

        .splash-exit-overlay {
            position: fixed;
            inset: 0;
            z-index: 100;
            background: #000000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 1s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body.is-exiting .splash-exit-overlay {
            opacity: 1;
            pointer-events: auto;
        }

        .splash-inner {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
            max-width: 100%;
            transition:
                transform 1s cubic-bezier(0.45, 0.05, 0.35, 1),
                opacity 1s cubic-bezier(0.45, 0.05, 0.35, 1);
            will-change: transform, opacity;
        }

        body.is-exiting .splash-inner {
            transform: scale(1.14);
            opacity: 0;
        }

        body.is-exiting .splash-logo {
            filter: grayscale(0%);
        }

        body.is-exiting .splash-hint {
            animation: none;
        }

        .splash-logo {
            height: 20rem;
            width: auto;
            max-width: min(90vw, 28rem);
            object-fit: contain;
            filter: grayscale(100%);
            transform: scale(0.9);
            opacity: 0;
            transform-origin: center center;
            transition:
                filter 0.7s ease,
                opacity 2s cubic-bezier(0.18, 0.85, 0.35, 1),
                transform 2s cubic-bezier(0.18, 0.85, 0.35, 1);
        }

        body.splash-entrance-run .splash-logo {
            opacity: 1;
            transform: scale(1);
        }

        .splash-logo:hover {
            filter: grayscale(0%);
        }

        .splash-title {
            margin-top: 2rem;
            font-size: clamp(1.5rem, 5vw, 2.75rem);
            font-weight: 700;
            letter-spacing: 0.65em;
            text-indent: 0.65em;
            color: #FFD65A;
            text-transform: uppercase;
            line-height: 1.2;
            opacity: 0;
            transition: opacity 1.55s cubic-bezier(0.2, 0.75, 0.25, 1);
        }

        body.splash-title-in .splash-title {
            opacity: 1;
        }

        .splash-hint {
            margin-top: 2.5rem;
            font-size: 0.65rem;
            font-weight: 500;
            letter-spacing: 0.35em;
            color: rgba(255, 214, 90, 0.55);
            text-transform: uppercase;
            opacity: 0;
            transition: opacity 1.35s cubic-bezier(0.25, 0.7, 0.3, 1);
        }

        body.splash-hint-in .splash-hint {
            opacity: 1;
        }

        body.splash-ready .splash-hint {
            animation: splash-pulse 2.8s ease-in-out infinite;
        }

        @keyframes splash-pulse {
            0%, 100% { opacity: 0.62; }
            50% { opacity: 1; }
        }

        .splash-rule {
            margin-top: 3rem;
            width: min(12rem, 40vw);
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 214, 90, 0.25), transparent);
            opacity: 0;
            transition: opacity 1.35s cubic-bezier(0.25, 0.7, 0.3, 1);
        }

        body.splash-hint-in .splash-rule {
            opacity: 1;
        }
    </style>
</head>
<body id="splash-root" role="button" aria-label="Please wait — gateway opening" aria-disabled="true">
    <?= uaStudentPageTransitionVeil() ?>
    <div id="splash-entrance-veil" class="splash-entrance-veil" aria-hidden="true"></div>
    <div class="splash-exit-overlay" aria-hidden="true"></div>
    <div class="splash-inner">
        <img
            src="assets/ChatGPT_Image_May_8__2026__05_01_58_PM-removebg-preview.jpg"
            alt="Universal Academy crest"
            class="splash-logo"
            width="800"
            height="800"
            decoding="async"
        >
        <h1 class="splash-title">Universal Academy</h1>
        <p class="splash-hint">SYSTEM INITIALIZING... CLICK ANYWHERE TO PROCEED</p>
        <div class="splash-rule" aria-hidden="true"></div>
    </div>
    <script>
        (function () {
            var EXIT_MS = 1000;
            var navigated = false;

            var DELAY_LOGO_MS = 600;
            var DELAY_TITLE_MS = 1200;
            var DELAY_HINT_MS = 2000;
            var VEIL_REMOVE_MS = 2600;
            var ENTRANCE_COMPLETE_MS = 3800;

            var body = document.body;
            var veil = document.getElementById('splash-entrance-veil');

            window.setTimeout(function () {
                body.classList.add('splash-entrance-run');
            }, DELAY_LOGO_MS);

            window.setTimeout(function () {
                body.classList.add('splash-title-in');
            }, DELAY_TITLE_MS);

            window.setTimeout(function () {
                body.classList.add('splash-hint-in');
            }, DELAY_HINT_MS);

            window.setTimeout(function () {
                if (veil) {
                    veil.classList.add('is-gone');
                }
            }, VEIL_REMOVE_MS);

            window.setTimeout(function () {
                body.classList.add('splash-ready');
                body.setAttribute('aria-label', 'Click anywhere to enter');
                body.removeAttribute('aria-disabled');
                if (veil && veil.parentNode) {
                    veil.parentNode.removeChild(veil);
                }
            }, ENTRANCE_COMPLETE_MS);

            function proceedToCommand() {
                if (!body.classList.contains('splash-ready') || navigated) return;
                navigated = true;
                body.classList.add('is-exiting');
                body.style.cursor = 'wait';
                window.setTimeout(function () {
                    window.location.href = 'index.php';
                }, EXIT_MS);
            }

            document.body.addEventListener('click', proceedToCommand);
        })();
    </script>
    <?= uaStudentPageTransitionScript() ?>
</body>
</html>
