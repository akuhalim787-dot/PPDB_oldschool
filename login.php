<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/student_page_transition.php';

if (is_array(studentAuth())) {
    redirect('dashboard.php');
}

$error = null;
$flash = getFlash();
$officialLogo = 'assets/ChatGPT_Image_May_8__2026__05_01_58_PM-removebg-preview.jpg';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email and password credentials are mandatory.';
    } else {
        $lookup = supabaseRequest(
            'GET',
            // Schema transition compatibility for enrollment fields.
            'pendaftar?select=id,nama_lengkap,email,password,previous_institution,department_assignment,verification_status,asal_sekolah,jurusan,status_verifikasi,created_at&email=eq.' . rawurlencode($email) . '&limit=1'
        );

        if ($lookup['success'] && is_array($lookup['data']) && count($lookup['data']) === 1) {
            $student = $lookup['data'][0];
            $storedPassword = (string) ($student['password'] ?? '');

            if ($storedPassword !== '' && hash_equals($storedPassword, $password)) {
                $_SESSION['student_auth'] = [
                    'id' => $student['id'] ?? null,
                    'nama_lengkap' => $student['nama_lengkap'] ?? '',
                    'email' => $student['email'] ?? '',
                ];
                setFlash('success', 'Access granted. Welcome back to the academy network.');
                redirect('dashboard.php');
            }
        }

        $error = 'Authorization failed. Re-check email and password credentials.';
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style><?= uaStudentPageTransitionCss() ?></style>
    <title>Student Authorization Terminal - PPDB Oldschool</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&family=Oswald:wght@500;700&display=swap" rel="stylesheet">
    <style><?= bullworthStudentCss() ?></style>
</head>
<body class="bullworth-student min-h-screen bg-[#FDF6E3]">
<?= uaStudentPageTransitionVeil() ?>
 <div class="max-w-xl mx-auto px-4 py-12">
    <div class="neo-border neo-shadow bg-[#4CA7B8] p-6 rounded-sm text-white">
        <div class="flex items-center gap-4">
            <img src="<?= e($officialLogo) ?>" alt="Universal Academy Logo" class="bw-drop-logo h-14 w-auto object-contain">
            <div>
                <p class="font-head text-2xl md:text-3xl uppercase leading-none tracking-tighter">Universal Academy</p>
                <p class="font-head text-xs uppercase tracking-[0.2em] opacity-90 mt-1">Institutional Access</p>
            </div>
        </div>
        <h1 class="font-head text-5xl md:text-6xl uppercase leading-none mt-6 tracking-tighter">
            PERSONNEL<span class="bg-white text-black px-2 mx-1">LOGIN</span>
        </h1>
    </div>

    <div class="neo-border neo-shadow bg-white p-6 mt-8 rounded-sm relative overflow-hidden">
        <div class="absolute top-0 left-0 w-2 h-full bg-[#FFD65A]"></div>
        
        <?php if (is_array($flash)): ?>
            <div class="neo-border <?= $flash['type'] === 'success' ? 'bg-[#4CA7B8] text-white' : 'bg-[#FFD65A]' ?> p-4 rounded-sm text-sm font-bold mb-6 italic">
                "<?= e($flash['message']) ?>"
            </div>
        <?php endif; ?>

        <?php if ($error !== null): ?>
            <div class="neo-border bg-[#FFD65A] p-4 rounded-sm text-sm font-bold mb-6 uppercase tracking-tighter border-2 border-black">
                ⚠️ Verification Error: <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-6 pl-2">
            <div>
                <label class="text-xs uppercase font-black tracking-[0.15em] text-gray-500">Academic Email</label>
                <input type="email" name="email" placeholder="student@universal.edu" class="neo-input mt-2 rounded-sm w-full bg-gray-50" required>
            </div>
            <div>
                <label class="text-xs uppercase font-black tracking-[0.15em] text-gray-500">Security Cipher</label>
                <input type="password" name="password" placeholder="••••••••" class="neo-input mt-2 rounded-sm w-full bg-gray-50" required>
            </div>
            
            <button type="submit" class="neo-btn bg-[#FFD65A] w-full py-4 rounded-sm uppercase font-head text-lg tracking-widest active:shadow-none active:translate-x-[6px] active:translate-y-[6px] transition-all">
                Authorize Entrance
            </button>
        </form>

        <div class="mt-8 flex flex-wrap gap-3 pl-2">
            <a href="register.php" class="neo-btn bg-white px-5 py-2 rounded-sm text-[10px] md:text-xs font-bold uppercase tracking-widest border-2 border-black">New Recruit?</a>
            <a href="index.php" class="neo-btn bg-[#4CA7B8] text-white px-5 py-2 rounded-sm text-[10px] md:text-xs font-bold uppercase tracking-widest border-2 border-black">Abandon Access</a>
        </div>
    </div>

    <p class="bw-oath text-center mt-8 font-head text-[12px] text-[#ffffff] uppercase tracking-[0.3em] opacity-30">
        "Non Ducor, Duco" — I am not led, I lead.
    </p>
</div>
<?= uaStudentPageTransitionScript() ?>
</body>
