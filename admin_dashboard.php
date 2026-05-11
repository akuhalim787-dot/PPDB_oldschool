<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
if (!is_array(adminAuth())) {
    redirect('index.php');
}

$flash = getFlash();
$error = null;
$officialLogo = 'assets/ChatGPT_Image_May_8__2026__05_01_58_PM-removebg-preview.jpg';

/**
 * Translate enrollment status labels for consistent on-screen English.
 */
function adminStatusLabel(string $status): string
{
    $normalized = mb_strtolower(trim($status));
    if (in_array($normalized, ['terverifikasi', 'verified', 'approved'], true)) {
        return 'CLEARED';
    }
    if (in_array($normalized, ['ditolak', 'rejected', 'denied'], true)) {
        return 'REJECTED';
    }
    return 'UNDER REVIEW';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $id = trim((string) ($_POST['id'] ?? ''));

    $statusMap = [
        'verify' => 'Terverifikasi',
        'reject' => 'Ditolak',
    ];

    if ($action === 'post_news') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));

        if ($title === '' || $content === '') {
            $error = 'Directive title and content are mandatory.';
        } else {
            $insertNews = supabaseRequest('POST', 'berita', [
                'title' => $title,
                'content' => $content,
                'created_at' => gmdate('c'),
            ]);

            if ($insertNews['success']) {
                setFlash('success', 'New directive successfully published to the bulletin.');
                redirect('admin_dashboard.php');
            }

            $error = 'Failed to archive directive. ' . (string) ($insertNews['error'] ?? 'Inspect Supabase connection integrity.');
        }
    } elseif ($action === 'delete_news') {
        $newsId = trim((string) ($_POST['news_id'] ?? ''));
        if ($newsId === '') {
            $error = 'Directive ID is invalid.';
        } else {
            $deleteNews = supabaseRequest(
                'DELETE',
                'berita?id=eq.' . rawurlencode($newsId)
            );

            if ($deleteNews['success']) {
                setFlash('success', 'Directive successfully purged from archive.');
                redirect('admin_dashboard.php');
            }

            $error = 'Failed to purge directive entry.';
        }
    } elseif ($id === '' || !isset($statusMap[$action])) {
        $error = 'Invalid command request. Retry with proper action protocol.';
    } else {
        $current = supabaseRequest(
            'GET',
            // Schema transition compatibility for verification status.
            'pendaftar?select=id,verification_status,status_verifikasi&id=eq.' . rawurlencode($id) . '&limit=1'
        );

        if (!$current['success'] || !is_array($current['data']) || count($current['data']) !== 1) {
            $error = 'Candidate record not found.';
        } else {
            $currentStatus = mb_strtolower(trim((string) (
                $current['data'][0]['verification_status']
                ?? $current['data'][0]['status_verifikasi']
                ?? 'pending'
            )));
            if ($currentStatus !== 'pending') {
                $error = 'Action locked. Candidate status has already been adjudicated.';
            } else {
                $newStatus = $statusMap[$action];
                $update = supabaseRequest(
                    'PATCH',
                    'pendaftar?id=eq.' . rawurlencode($id),
                    [
                        // Write both during migration window for compatibility.
                        'verification_status' => $newStatus,
                        'status_verifikasi' => $newStatus,
                    ]
                );

                if ($update['success']) {
                    setFlash('success', 'Candidate status successfully updated to ' . adminStatusLabel($newStatus) . '.');
                    redirect('admin_dashboard.php');
                }

                $error = 'Status update failed. Verify that id and verification_status columns are available.';
            }
        }
    }
}

$result = supabaseRequest(
    'GET',
    // Schema transition compatibility: read new columns with legacy fallback.
    'pendaftar?select=id,nama_lengkap,email,previous_institution,department_assignment,verification_status,asal_sekolah,jurusan,status_verifikasi,created_at&order=created_at.desc'
);

$rows = [];
if ($result['success'] && is_array($result['data'])) {
    $rows = $result['data'];
} elseif ($error === null) {
    $error = 'Failed to load candidate records from Supabase.';
}

$newsRows = fetchLatestNews(20);

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PPDB Oldschool</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&family=Oswald:wght@500;700&display=swap" rel="stylesheet">
    <style><?= retroCss() ?></style>
    <style>
    #ua-page-transition {
        position: fixed;
        inset: 0;
        z-index: 99999;
        background: #000000;
        visibility: visible;
        opacity: 1;
        transition: opacity 0.3s cubic-bezier(0.33, 1, 0.68, 1), 
                    visibility 0s linear 0.3s;
        pointer-events: none;
    }
    #ua-page-transition.is-loaded {
        opacity: 0;
        visibility: hidden;
    }
</style>
</head>
<body class="min-h-screen bg-[#121212] text-gray-200"> <div class="max-w-7xl mx-auto px-4 py-8">
    <body class="min-h-screen bg-[#121212] text-gray-200">
    <div id="ua-page-transition" aria-hidden="true"></div>
    <div class="flex flex-wrap gap-4 justify-between items-start">
        <div class="neo-border-white neo-shadow-cyan bg-[#1A1A1A] p-6 rounded-sm text-white flex-1 min-w-[320px] border-2 border-white shadow-[6px_6px_0px_rgba(76,167,184,1)]">
            <div class="flex items-center gap-4">
                <img src="<?= e($officialLogo) ?>" alt="Universal Academy Crest" class="h-16 w-auto object-contain drop-shadow-[4px_4px_0px_rgba(0,0,0,0.8)]">
                <div class="border-l-2 border-white/20 pl-4">
                    <p class="font-head text-xs uppercase tracking-[0.4em] text-[#4CA7B8] font-bold">Universal Academy • Shadow Command</p>
                    <h1 class="font-head text-5xl md:text-6xl uppercase leading-none mt-1 tracking-tighter">CONTROL BOARD</h1>
                </div>
            </div>
        </div>
        
        <div class="flex flex-col gap-2">
            <a href="logout.php?role=admin" class="neo-btn bg-[#F05D4B] text-white px-6 py-3 rounded-sm text-sm font-bold uppercase tracking-widest text-center shadow-[4px_4px_0px_rgba(255,255,255,0.2)] hover:shadow-none hover:translate-x-[4px] hover:translate-y-[4px] transition-all">
                EXIT TERMINAL
            </a>
            <div class="border-2 border-white bg-black p-2 text-[10px] font-black uppercase text-center text-[#FFD65A] tracking-widest">
                Access Level: SUPERVISOR
            </div>
        </div>
    </div>

    <?php if (is_array($flash)): ?>
        <div class="mt-8 border-2 border-white bg-[#1A1A1A] p-4 rounded-sm text-sm font-bold italic shadow-[4px_4px_0px_rgba(255,214,90,1)]">
            <span class="text-[#FFD65A]">>>> SYSTEM_MSG:</span> "<?= e($flash['message']) ?>"
        </div>
    <?php endif; ?>

    <div class="mt-8 grid grid-cols-1 xl:grid-cols-4 gap-8">
        
        <aside class="xl:col-span-1 space-y-6">
            <section class="border-2 border-white bg-[#1A1A1A] p-6 rounded-sm shadow-[6px_6px_0px_rgba(0,0,0,1)]">
                <h2 class="font-head text-2xl uppercase tracking-tighter text-[#FFD65A]">Issue Decree</h2>
                <form method="post" class="mt-6 space-y-4">
                    <input type="hidden" name="action" value="post_news">
                    <div>
                        <label class="text-[9px] uppercase font-black tracking-widest text-gray-500">Subject</label>
                        <input type="text" name="title" class="w-full bg-black border-2 border-white/20 p-2 text-sm text-white focus:border-[#4CA7B8] outline-none" required>
                    </div>
                    <div>
                        <label class="text-[9px] uppercase font-black tracking-widest text-gray-500">Directive</label>
                        <textarea name="content" rows="3" class="w-full bg-black border-2 border-white/20 p-2 text-sm text-white focus:border-[#4CA7B8] outline-none" required></textarea>
                    </div>
                    <button type="submit" class="bg-[#4CA7B8] text-white w-full py-3 rounded-sm text-xs font-bold uppercase tracking-widest border-2 border-white shadow-[4px_4px_0px_rgba(0,0,0,1)]">Execute Post</button>
                </form>
            </section>

            <section class="border-2 border-white bg-[#1A1A1A] p-6 rounded-sm shadow-[6px_6px_0px_rgba(0,0,0,1)]">
                <h3 class="font-head text-xl uppercase text-[#4CA7B8] border-b border-white/10 pb-2">Archived Logs</h3>
                <div class="mt-4 space-y-3 max-h-[300px] overflow-y-auto pr-2">
                    <?php foreach ($newsRows as $news): ?>
                        <div class="border border-white/10 bg-black/40 p-3 text-xs">
                            <p class="font-bold uppercase text-white"><?= e($news['title']) ?></p>
                            <form method="post" class="mt-2 text-right">
                                <input type="hidden" name="action" value="delete_news">
                                <input type="hidden" name="news_id" value="<?= e($news['id']) ?>">
                                <button type="submit" class="text-red-500 font-black uppercase text-[8px] hover:underline">[ PURGE ]</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </aside>

        <main class="xl:col-span-3">
            <section class="border-2 border-white bg-[#1A1A1A] p-6 rounded-sm shadow-[8px_8px_0px_rgba(0,0,0,1)]">
                <div class="flex justify-between items-center mb-6 border-b-2 border-white/10 pb-4">
                    <h2 class="font-head text-3xl uppercase tracking-tighter text-white">Recruitment Ledger</h2>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        <span class="text-[10px] font-black tracking-widest text-gray-500">ENCRYPTED_DATA_STREAM</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b-2 border-white">
                                <th class="p-3 text-[10px] font-black uppercase tracking-widest text-[#FFD65A]">Identity</th>
                                <th class="p-3 text-[10px] font-black uppercase tracking-widest text-gray-400">Institution</th>
                                <th class="p-3 text-[10px] font-black uppercase tracking-widest text-gray-400">Dept.</th>
                                <th class="p-3 text-[10px] font-black uppercase tracking-widest text-gray-400">Security</th>
                                <th class="p-3 text-center text-[10px] font-black uppercase tracking-widest text-white">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y border-white/5">
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $verificationStatus = (string) ($row['verification_status'] ?? $row['status_verifikasi'] ?? 'pending');
                                $isPending = mb_strtolower($verificationStatus) === 'pending';
                                ?>
                                <tr class="hover:bg-white/5 transition-all">
                                    <td class="p-4">
                                        <p class="font-bold text-white text-sm uppercase"><?= e($row['nama_lengkap']) ?></p>
                                        <p class="text-[10px] text-[#4CA7B8] font-mono"><?= e($row['email']) ?></p>
                                    </td>
                                    <td class="p-4 text-xs font-bold text-gray-400 uppercase"><?= e((string) ($row['previous_institution'] ?? $row['asal_sekolah'] ?? '-')) ?></td>
                                    <td class="p-4">
                                        <span class="border border-[#4CA7B8] text-[#4CA7B8] px-2 py-0.5 text-[9px] font-black uppercase">
                                            <?= e((string) ($row['department_assignment'] ?? $row['jurusan'] ?? '-')) ?>
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <?php $verificationTone = mb_strtolower($verificationStatus); ?>
                                        <span class="text-xs font-black uppercase <?= $isPending ? 'text-[#FFD65A]' : (in_array($verificationTone, ['ditolak', 'rejected', 'denied'], true) ? 'text-red-500' : 'text-green-500') ?>">
                                            _<?= e(adminStatusLabel($verificationStatus)) ?>
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <?php
                                        // Disciplinary screening action button:
                                        // Pass current candidate ID to the dedicated screening management page.
                                        $screeningUrl = 'admin/manage_screening.php?id=' . rawurlencode((string) ($row['id'] ?? ''));
                                        ?>
                                        <?php if ($isPending): ?>
                                            <div class="flex justify-center gap-2">
                                                <a href="<?= e($screeningUrl) ?>" class="bg-[#FFD65A] text-black px-3 py-1 text-[9px] font-black uppercase border border-black hover:bg-white hover:text-black transition-all">SCREENING</a>
                                                <form method="post">
                                                    <input type="hidden" name="action" value="verify">
                                                    <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                                    <button class="bg-[#4CA7B8] text-white px-3 py-1 text-[9px] font-black uppercase border border-white hover:bg-white hover:text-black transition-all">PASS</button>
                                                </form>
                                                <form method="post">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                                    <button class="bg-transparent text-red-500 px-3 py-1 text-[9px] font-black uppercase border border-red-500 hover:bg-red-500 hover:text-white transition-all">DENY</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <div class="flex justify-center gap-2 items-center">
                                                <a href="<?= e($screeningUrl) ?>" class="bg-[#FFD65A] text-black px-3 py-1 text-[9px] font-black uppercase border border-black hover:bg-white hover:text-black transition-all">SCREENING</a>
                                                <div class="text-center opacity-20 italic text-[9px] tracking-widest font-bold">_HIGHLY_CLASSIFIED</div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <div class="mt-12 text-center">
        <p class="font-head text-[9px] uppercase tracking-[0.5em] text-white/20">
            System Overseer: Universal Academy Administrative Board // No Trespassing
        </p>
    </div>
</div>
<script>
        window.addEventListener('DOMContentLoaded', function() {
            // Beri jeda sangat singkat (50ms) agar browser sempat render background hitam
            setTimeout(function() {
                document.getElementById('ua-page-transition').classList.add('is-loaded');
            }, 50);
        });
    </script>
</body>
</body>