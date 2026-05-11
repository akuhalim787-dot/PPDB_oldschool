<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

if (is_array(adminAuth())) {
    redirect('admin_dashboard.php');
}

$flash = getFlash();
$error = null;
$officialLogo = 'assets/ChatGPT_Image_May_8__2026__05_01_58_PM-removebg-preview.jpg';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $adminUser = (string) env('ADMIN_USERNAME', 'admin');
    $adminEmail = (string) env('ADMIN_EMAIL', 'admin@portal.local');
    $adminHash = (string) env('ADMIN_PASSWORD_HASH', '');

    $isIdentifierValid = $identifier === $adminUser || $identifier === $adminEmail;
    $isPasswordValid = false;

    if ($adminHash !== '') {
        $isPasswordValid = str_starts_with($adminHash, '$2y$')
            ? password_verify($password, $adminHash)
            : hash_equals($adminHash, $password);
    }

    if ($isIdentifierValid && $isPasswordValid) {
        $_SESSION['admin_session'] = [
            'username' => $adminUser,
            'email' => $adminEmail,
            'login_at' => gmdate('c'),
        ];
        setFlash('success', 'Access granted. Welcome to the control board.');
        redirect('admin_dashboard.php');
    }

    $error = 'Access denied. Terminal credentials are invalid.';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Terminal - PPDB Oldschool</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&family=Oswald:wght@500;700&display=swap" rel="stylesheet">
    <style><?= retroCss() ?></style>
    <style>
        /* Atmospheric intro — rigid, authoritarian */
        #admin-intro-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #050505;
            transition: opacity 0.95s cubic-bezier(0.35, 0, 0.25, 1);
        }
        #admin-intro-overlay.is-dismissed {
            opacity: 0;
            pointer-events: none;
        }
        .admin-intro-inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.25rem;
            max-width: min(96vw, 52rem);
            padding: 0 0.75rem;
            transition: opacity 0.45s cubic-bezier(0.4, 0, 1, 1);
        }
        .admin-intro-inner.is-text-out {
            opacity: 0;
        }
        .admin-intro-inner.is-text-out .admin-intro-terminal-flicker {
            animation: none;
        }
        .admin-intro-line {
            font-family: 'Oswald', sans-serif;
            font-weight: 600;
            font-size: clamp(0.62rem, 2vw, 0.95rem);
            letter-spacing: 0.22em;
            text-indent: 0.22em;
            color: #f0f4f8;
            text-align: center;
            text-transform: uppercase;
            line-height: 1.55;
            margin: 0;
            min-height: 2.6em;
        }
        .admin-intro-terminal-flicker {
            animation: admin-terminal-flicker-subtle 2.8s linear infinite;
        }
        @keyframes admin-terminal-flicker-subtle {
            0%, 100% { opacity: 1; filter: brightness(1); text-shadow: none; }
            11% { opacity: 0.94; filter: brightness(1.04); }
            12% { opacity: 0.99; }
            31% { opacity: 0.91; filter: brightness(0.97); text-shadow: 0 0 1px rgba(200, 230, 255, 0.35); }
            32% { opacity: 1; }
            52% { opacity: 0.96; text-shadow: 0 0 2px rgba(255, 255, 255, 0.08); }
            71% { opacity: 0.93; filter: brightness(1.06); }
            72% { opacity: 1; }
            89% { opacity: 0.95; filter: brightness(0.98); }
        }
        .admin-intro-caret {
            display: inline-block;
            margin-left: 0.06em;
            color: rgba(180, 220, 200, 0.95);
            font-weight: 400;
            animation: admin-caret-blink 0.85s steps(1, end) infinite;
            vertical-align: baseline;
        }
        .admin-intro-inner.is-typing-done .admin-intro-caret {
            animation: admin-caret-blink 1.1s steps(1, end) infinite;
        }
        @keyframes admin-caret-blink {
            0%, 49% { opacity: 1; }
            50%, 100% { opacity: 0; }
        }
        .admin-intro-log {
            font-family: ui-monospace, 'Cascadia Code', 'Consolas', 'Courier New', monospace;
            font-size: clamp(0.55rem, 1.35vw, 0.68rem);
            letter-spacing: 0.12em;
            color: rgba(160, 205, 175, 0.88);
            text-transform: uppercase;
            margin: 0;
            min-height: 1.4em;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }
        #admin-main-wrap {
            width: 100%;
            max-width: 42rem;
            opacity: 0;
            transform: translateY(2.25rem);
            transition:
                opacity 0.95s cubic-bezier(0.22, 1, 0.36, 1),
                transform 0.95s cubic-bezier(0.22, 1, 0.36, 1);
            will-change: opacity, transform;
        }
        #admin-main-wrap.is-revealed {
            opacity: 1;
            transform: translateY(0);
        }
        .admin-intro-inner.is-syncing {
    opacity: 1 !important;
    filter: drop-shadow(0 0 10px rgba(76, 167, 184, 0.5));
    
}
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 py-8 bg-[#1A1A1A]">
    <div id="admin-intro-overlay" role="status" aria-live="polite" aria-label="Secure uplink initializing">
        <div class="admin-intro-inner" id="admin-intro-inner">
            <p class="admin-intro-line admin-intro-terminal-flicker">
                <span id="admin-intro-typed"></span><span class="admin-intro-caret" aria-hidden="true">▍</span>
            </p>
            <p class="admin-intro-log admin-intro-terminal-flicker" id="admin-intro-log">&nbsp;</p>
        </div>
    </div>

    <div class="w-full max-w-2xl" id="admin-main-wrap">
    <section class="neo-border neo-shadow bg-[#4CA7B8] p-6 rounded-sm text-white relative overflow-hidden">
    <div class="absolute top-0 right-0 w-32 h-full bg-black/10 -skew-x-12 translate-x-16"></div>
    
    <div class="flex items-center gap-4 relative z-10">
        <img src="<?= e($officialLogo) ?>" alt="Universal Academy Crest" class="h-16 w-auto object-contain drop-shadow-[4px_4px_0px_rgba(0,0,0,0.5)]">
        
        <div class="border-l-2 border-white/30 pl-4">
            <p class="font-head text-xs uppercase tracking-[0.4em] opacity-80">Universal Academy • Central Command</p>
            <h1 class="font-head text-5xl md:text-6xl uppercase leading-none mt-1 tracking-tighter text-white">ADMIN TERMINAL</h1>
        </div>
    </div>
    
    <div class="mt-6 flex items-center gap-2">
        <span class="inline-block w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
        <p class="font-head text-[10px] uppercase tracking-[0.2em] text-white/90 italic">Warning: All system activity is logged and monitored.</p>
    </div>
</section>

    <section class="neo-border neo-shadow bg-white p-8 mt-6 rounded-sm border-t-[12px] border-black">
        <?php if (is_array($flash)): ?>
            <div class="neo-border <?= $flash['type'] === 'success' ? 'bg-[#4CA7B8] text-white' : 'bg-[#FFD65A]' ?> p-4 rounded-sm text-sm font-bold mb-6 border-2 border-black">
                SYSTEM MESSAGE: "<?= e($flash['message']) ?>"
            </div>
        <?php endif; ?>

        <?php if ($error !== null): ?>
            <div class="neo-border bg-[#FFD65A] p-4 rounded-sm text-sm font-bold mb-6 uppercase tracking-tighter border-2 border-black flex items-center gap-3">
                <span class="bg-black text-white px-2 py-1 text-xs">ERR_AUTH_FAILED</span>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-6" onsubmit="handleInfiltrate(event)">
            <div class="space-y-1">
                <label class="text-[10px] uppercase font-black tracking-[0.2em] text-gray-400">Security Clearance</label>
                <input type="text" name="identifier" class="neo-input mt-1 rounded-sm w-full bg-gray-50 focus:bg-[#FFF6DE] border-2 font-mono text-sm" placeholder="username_officer_..." required>
            </div>
            <div class="space-y-1">
                <label class="text-[10px] uppercase font-black tracking-[0.2em] text-gray-400">Operational Cipher</label>
                <input type="password" name="password" class="neo-input mt-1 rounded-sm w-full bg-gray-50 focus:bg-[#FFF6DE] border-2 font-mono text-sm" placeholder="••••••••" required>
            </div>
            
            <div class="pt-4">
                <button type="submit" class="neo-btn bg-black text-white w-full py-4 rounded-sm uppercase font-head text-xl tracking-[0.2em] shadow-[8px_8px_0px_rgba(76,167,184,1)] active:shadow-none active:translate-x-[4px] active:translate-y-[4px] transition-all">
                    INFILTRATE SYSTEM
                </button>
            </div>
        </form>

        <div class="mt-8 pt-6 border-t border-dashed border-gray-300 flex justify-between items-center text-[9px] uppercase font-bold tracking-widest text-gray-400">
            <span>Protocol: 06-A / ENROLLMENT</span>
            <a href="index.php" class="hover:text-black underline underline-offset-4 decoration-2">Return to Public View</a>
        </div>
    </section>
</div>

    <script>
        (function () {
            var FULL_TEXT = 'DECRYPTING ENCRYPTED CHANNEL... ACCESS LEVEL: [TOP SECRET]';
            var CHAR_MS = 30;
            var POST_TYPE_PAUSE_MS = 550;
            var TEXT_OUT_MS = 450;
            var OVERLAY_FADE_MS = 950;

            var LOG_MESSAGES = [
    'ESTABLISHING ENCRYPTED TUNNEL...',
    'PINGING BACKDOOR @ 192.168.1.254...',
    'BYPASSING KERBEROS AUTHENTICATION...',
    'SATELLITE UPLINK: HANDSHAKE COMPLETED',
    'DECRYPTING RSA-4096 BIT STREAM...',
    'REMOTE ASSET IDENTIFIED: UA_CORE_SRV',
    'INJECTING EXPLOIT PAYLOAD...',
    'WIPING SYSTEM LOGS... SUCCESS',
    'ACCESSING CENTRAL COMMAND TERMINAL...'
];
            var LOG_ROTATE_MS = 200;

            var overlay = document.getElementById('admin-intro-overlay');
            var inner = document.getElementById('admin-intro-inner');
            var typed = document.getElementById('admin-intro-typed');
            var logEl = document.getElementById('admin-intro-log');
            var main = document.getElementById('admin-main-wrap');
            if (!overlay || !inner || !typed || !logEl || !main) return;

            var logIdx = 0;
logEl.textContent = LOG_MESSAGES[0];
var logTimer = window.setInterval(function () {
    if (logIdx < LOG_MESSAGES.length - 1) {
        logIdx++;
        logEl.textContent = LOG_MESSAGES[logIdx];
    } else {
        window.clearInterval(logTimer);
    }
}, LOG_ROTATE_MS);
            logEl.textContent = LOG_MESSAGES[0];

            var pos = 0;
            function typeNext() {
                if (pos < FULL_TEXT.length) {
                    pos += 1;
                    typed.textContent = FULL_TEXT.slice(0, pos);
                    window.setTimeout(typeNext, CHAR_MS);
                } else {
                    inner.classList.add('is-typing-done');
                    window.setTimeout(beginExit, POST_TYPE_PAUSE_MS);
                }
            }

            function beginExit() {
                window.clearInterval(logTimer);
                inner.classList.add('is-text-out');
                window.setTimeout(function () {
                    overlay.classList.add('is-dismissed');
                    main.classList.add('is-revealed');
                }, TEXT_OUT_MS);
                window.setTimeout(function () {
    overlay.setAttribute('aria-hidden', 'true');
    // Cukup sembunyikan saja, jangan dihapus dari DOM 
    // agar bisa dipanggil lagi oleh handleInfiltrate
}, TEXT_OUT_MS + OVERLAY_FADE_MS + 80);
            }

            typeNext();
            var SYNC_MESSAGES = [
    'ANALYZING BIOMETRIC HASH...',
    'INJECTING CLANDESTINE EXPLOIT...',
    'ESTABLISHING SECURE COMM-LINK...',
    'UPLINK ACTIVE. DATA STREAM SECURED.',
    'PROGRESS COMPLETE. REDIRECTING OPERATIVE...'
];

window.handleInfiltrate = function(event) {
    event.preventDefault();
    var form = event.target;
    
    // Panggil lagi overlay
    overlay.classList.remove('is-dismissed');
    overlay.style.opacity = '1';
    overlay.style.pointerEvents = 'auto';
    inner.classList.remove('is-text-out');
    inner.classList.add('is-syncing');
    
    // Reset state overlay buat animasi syncing
    overlay.classList.remove('is-dismissed');
    overlay.style.opacity = '1';
    overlay.style.pointerEvents = 'auto';
    inner.classList.remove('is-text-out');
    inner.classList.add('is-syncing');
    
    // Teks baru yang lebih sangar
    var CRACK_TEXT = 'UPLINK PROTOCOL: ALPHA-6 INITIATED';
    typed.textContent = ''; // Kosongin dulu biar bisa diketik ulang

    var i = 0;
    function typeCrack() {
        if (i < CRACK_TEXT.length) {
            // Mengambil satu karakter berdasarkan indeks i
            typed.textContent = CRACK_TEXT.slice(0, i + 1); 
            i++;
            setTimeout(typeCrack, 25); // Speed 25ms biar berasa ngetik taktis
        }
    }
    
    typeCrack(); // Mulai ngetik teks "Cracking..."

    var sIdx = 0;
    var syncTimer = setInterval(function() {
        logEl.textContent = '[SYNC] ' + SYNC_MESSAGES[sIdx];
        sIdx++;
        if (sIdx >= SYNC_MESSAGES.length) {
            clearInterval(syncTimer);
            setTimeout(function() {
                form.submit();
            }, 600);
        }
    }, 250);
};
        })();
    </script>
</body>
</html>
