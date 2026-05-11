<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/student_page_transition.php';
requireStudentAuth();

$flash = getFlash();
$student = studentAuth();
$profile = null;
$error = null;

if (is_array($student)) {
    // Schema transition compatibility:
    // Prefer new English columns, keep legacy fields for fallback.
    $lookup = supabaseRequest(
        'GET',
        'pendaftar?select=id,nama_lengkap,email,previous_institution,department_assignment,verification_status,asal_sekolah,jurusan,status_verifikasi,created_at&email=eq.' . rawurlencode((string) $student['email']) . '&order=created_at.desc&limit=1'
    );

    if ($lookup['success'] && is_array($lookup['data']) && count($lookup['data']) > 0) {
        $profile = $lookup['data'][0];
    } else {
        $error = 'Candidate record was not found in the database.';
    }
}

$status = (string) (
    $profile['verification_status']
    ?? $profile['status_verifikasi']
    ?? 'Pending'
);
// Verification gate normalization for portal access logic.
$statusNormalized = strtolower(trim((string) (
    $profile['verification_status']
    ?? $profile['status_verifikasi']
    ?? $status
)));
$isVerified = in_array($statusNormalized, ['verified', 'approved', 'terverifikasi'], true);
$isRejected = in_array($statusNormalized, ['denied', 'rejected', 'ditolak'], true);
$isPending = $statusNormalized === 'pending';
$statusDisplay = $isVerified ? 'CLEARED' : ($isRejected ? 'REJECTED' : 'UNDER REVIEW');
$statusCardClass = $isRejected ? 'bg-[#B22222] text-white' : ($isVerified ? 'bg-[#2E4A31] text-white' : 'bg-[#E5A823] text-black');
$officialLogo = 'assets/ChatGPT_Image_May_8__2026__05_01_58_PM-removebg-preview.jpg';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style><?= uaStudentPageTransitionCss() ?></style>
    <title>Student Command Dashboard - PPDB Oldschool</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&family=Oswald:wght@500;700&display=swap" rel="stylesheet">
    <style><?= bullworthStudentCss() ?></style>
</head>
<body class="bullworth-student min-h-screen bg-[#FDF6E3]">
<?= uaStudentPageTransitionVeil() ?>
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex flex-wrap gap-4 justify-between items-stretch">
        <div class="neo-border neo-shadow bg-[#FFD65A] p-6 rounded-sm flex-1 min-w-[300px]">
            <div class="flex items-center gap-4">
                <img src="<?= e($officialLogo) ?>" alt="Universal Academy Crest" class="bw-drop-logo h-16 w-auto object-contain">
                <div class="border-l-2 border-black pl-4">
                    <p class="font-head text-xs uppercase tracking-[0.2em] opacity-80">Academy Personnel Record</p>
                    <h1 class="font-head text-4xl md:text-5xl uppercase leading-none mt-1">
                        Welcome, <span class="bg-white px-2 border-2 border-black inline-block rotate-[-1deg]"><?= e((string) ($student['nama_lengkap'] ?? 'Recruit')) ?></span>
                    </h1>
                </div>
            </div>
        </div>
        
        <div class="flex flex-col gap-2 justify-center">
            <a href="print_formulir.php" target="_blank" class="neo-btn bg-[#4CA7B8] text-white px-6 py-3 rounded-sm text-sm font-bold uppercase tracking-widest text-center">
                🖨️ Obtain Identity Card
            </a>
            <a href="logout.php?role=student" class="neo-btn bg-white px-6 py-2 rounded-sm text-xs font-bold uppercase tracking-widest text-center border-2 border-black">
                Secure Logout
            </a>
        </div>
    </div>

    <?php if (is_array($flash)): ?>
        <div class="mt-8 neo-border neo-shadow <?= $flash['type'] === 'success' ? 'bg-[#4CA7B8] text-white' : 'bg-[#FFD65A]' ?> p-4 rounded-sm text-sm font-bold italic">
             "<?= e($flash['message']) ?>"
        </div>
    <?php endif; ?>

    <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
        <section class="lg:col-span-2 neo-border neo-shadow bg-white p-8 rounded-sm relative">
            <div class="absolute top-0 right-0 bg-black text-white px-4 py-1 font-head text-xs uppercase">File #<?= substr(md5($student['email'] ?? '0'), 0, 8) ?></div>
            <h2 class="font-head text-4xl uppercase tracking-tighter border-b-4 border-black pb-2 inline-block">Registrant Profile</h2>
            
            <div class="mt-8 grid md:grid-cols-2 gap-6 text-sm">
                <div class="space-y-1">
                    <label class="text-[10px] font-black uppercase text-gray-400 tracking-widest">Full Name</label>
                    <div class="neo-border bg-[#FFF6DE] p-4 rounded-sm font-bold text-lg uppercase"><?= e((string) ($profile['nama_lengkap'] ?? '-')) ?></div>
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-black uppercase text-gray-400 tracking-widest">Enrollment Email</label>
                    <div class="neo-border bg-[#FFF6DE] p-4 rounded-sm font-bold text-lg"><?= e((string) ($profile['email'] ?? '-')) ?></div>
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-black uppercase text-gray-400 tracking-widest">Previous Institution</label>
                    <div class="neo-border bg-[#FFF6DE] p-4 rounded-sm font-bold text-lg uppercase"><?= e((string) ($profile['previous_institution'] ?? $profile['asal_sekolah'] ?? '-')) ?></div>
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-black uppercase text-gray-400 tracking-widest">Selected division</label>
                    <div class="neo-border bg-[#4CA7B8] text-white p-4 rounded-sm font-bold text-lg uppercase"><?= e((string) ($profile['department_assignment'] ?? $profile['jurusan'] ?? '-')) ?></div>
                </div>
            </div>
        </section>

        <section class="neo-border neo-shadow <?= $statusCardClass ?> p-8 rounded-sm flex flex-col justify-center items-center text-center">
            <p class="font-head text-xl uppercase tracking-widest opacity-80 border-b-2 border-black w-full pb-2">Entry Status</p>
            <div class="my-6">
                <h3 class="bw-text-hard-lite font-head text-6xl md:text-7xl uppercase leading-none tracking-tighter">
                    <?= e($statusDisplay) ?>
                </h3>
            </div>
            
            <div class="neo-border bg-white/50 p-4 rounded-sm">
                <p class="text-sm font-bold uppercase tracking-tight">
                    <?php if ($isRejected): ?>
                        <span class="text-red-600">Application Terminated. You do not meet the Academy's standards.</span>
                    <?php elseif ($isVerified): ?>
                        <span class="text-green-700 italic">Clearance Granted. Welcome to the elite. Report to the administration immediately.</span>
                    <?php else: ?>
                        <span class="italic font-medium">Verification in progress. Do not contact us; we will contact you.</span>
                    <?php endif; ?>
                </p>
            </div>

            <div class="mt-8 opacity-20 transform rotate-12">
                <img src="<?= e($officialLogo) ?>" alt="Seal" class="h-20 w-auto grayscale">
            </div>
        </section>
    </div>

    <!-- Portal access logic block: render verified access button or restricted notice -->
    <section class="mt-8 neo-border neo-shadow bg-white p-6 rounded-sm">
        <p class="font-head text-xl uppercase tracking-tighter border-b-2 border-black pb-2">Internal Dossier Access</p>
        <p class="text-[10px] uppercase font-black tracking-[0.2em] opacity-60 mt-2">Campus Surveillance Network // Behavioral Compliance Division // Internal Dossier Access</p>

        <?php if ($isVerified): ?>
            <div class="mt-4 neo-border bg-[#4CA7B8] text-white p-5 rounded-sm">
                <p class="font-head text-2xl uppercase">Internal Student Portal</p>
                <p class="text-xs font-bold uppercase mt-1 opacity-90">Access Institutional Monitoring Records</p>
                <a href="student_portal.php" class="inline-block mt-4 neo-btn bg-[#FFD65A] text-black px-5 py-2 rounded-sm text-xs font-bold uppercase tracking-widest border-2 border-black">Enter Portal</a>
            </div>
        <?php elseif ($isRejected): ?>
            <!-- Restricted access rendering for denied/rejected candidates -->
            <div class="mt-4 neo-border bg-[#F05D4B] text-white p-5 rounded-sm">
                <p class="font-head text-2xl uppercase">Authorization Revoked</p>
                <p class="text-xs font-bold uppercase mt-2">This subject no longer retains academy access privileges.</p>
            </div>
        <?php elseif ($isPending): ?>
            <div class="mt-4 neo-border bg-[#FFD65A] text-black p-5 rounded-sm">
                <p class="font-head text-2xl uppercase">Portal Access Locked</p>
                <p class="text-xs font-bold uppercase mt-2">Student clearance has not yet been granted.</p>
            </div>
        <?php else: ?>
            <div class="mt-4 neo-border bg-[#FFD65A] text-black p-5 rounded-sm">
                <p class="font-head text-2xl uppercase">Portal Access Locked</p>
                <p class="text-xs font-bold uppercase mt-2">Internal dossier access is unavailable for current status.</p>
            </div>
        <?php endif; ?>
    </section>

    <p class="bw-oath mt-12 text-center font-head text-[10px] uppercase tracking-[0.4em] opacity-30 italic">
        "Work hard, play by the rules, and you might survive." — Universal Academy Records Office
    </p>
</div>
<?= uaStudentPageTransitionScript() ?>
</body>