<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/student_page_transition.php';

if (is_array(studentAuth())) {
    redirect('dashboard.php');
}

$error = null;
$officialLogo = 'assets/ChatGPT_Image_May_8__2026__05_01_58_PM-removebg-preview.jpg';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $asalSekolah = trim($_POST['asal_sekolah'] ?? '');
    $jurusan = trim($_POST['jurusan'] ?? '');

    if ($nama === '' || $email === '' || $password === '' || $asalSekolah === '' || $jurusan === '') {
        $error = 'All fields are mandatory, recruit!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format. Try again.';
    } elseif (strlen($password) < 6) {
        $error = 'Password too weak! Minimum 6 characters.';
    } else {
        $check = supabaseRequest('GET', 'pendaftar?select=id,email&email=eq.' . rawurlencode($email) . '&limit=1');
        if ($check['success'] && is_array($check['data']) && count($check['data']) > 0) {
            $error = 'This email is already in our files.';
        } else {
            $payload = [
                'nama_lengkap' => $nama,
                'email' => $email,
                'password' => $password,
                // Schema transition compatibility: write both new and legacy columns.
                'previous_institution' => $asalSekolah,
                'department_assignment' => $jurusan,
                'verification_status' => 'Pending',
                'asal_sekolah' => $asalSekolah,
                'jurusan' => $jurusan,
                'status_verifikasi' => 'Pending',
            ];
            $result = supabaseRequest('POST', 'pendaftar', $payload);

            if ($result['success']) {
                setFlash('success', 'Enlistment successful. You may now login.');
                redirect('login.php');
            }
            $error = 'System failure. Report to the admin immediately.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style><?= uaStudentPageTransitionCss() ?></style>
    <title>ENLIST NOW - Universal Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,700;1,900&family=Oswald:wght@500;700&display=swap" rel="stylesheet">
    <style>
        <?= bullworthStudentCss() ?>
        body.bullworth-student.register-shell {
            background-color: #1A1C1E;
            background-image: url("https://www.transparenttextures.com/patterns/dark-leather.png");
            color: #F4F1E1;
        }
        .register-shell .bullworth-card {
            border: 6px solid #0B1E3B;
            box-shadow: 8px 8px 0px #000000;
            border-radius: 0 !important;
        }
        .register-shell .bullworth-input {
            border: 5px solid #0B1E3B;
            background: #F4F1E1;
            color: #0B1E3B;
            border-radius: 0 !important;
            transition: box-shadow 0.15s ease, transform 0.15s ease;
        }
        .register-shell .bullworth-input:focus {
            outline: none;
            box-shadow: 8px 8px 0px #000000;
            transform: translate(-2px, -2px);
        }
        .register-shell .label-style {
            font-family: 'Oswald', sans-serif;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #0B1E3B;
        }
        .register-shell .recruit-motto {
            font-family: 'Special Elite', 'Courier New', monospace;
        }
    </style>
</head>
<body class="bullworth-student register-shell min-h-screen flex items-center justify-center py-12 px-4">
<div class="max-w-xl w-full">
    <div class="bullworth-card bg-[#E5A823] p-6 mb-0 relative overflow-hidden border-[6px] border-[#0B1E3B]">
        <div class="relative z-10">
            <div class="flex items-center gap-4">
                <img src="<?= e($officialLogo) ?>" alt="Logo" class="h-16 w-auto border-[5px] border-[#0B1E3B] bg-[#F4F1E1] p-1">
                <div>
                    <p class="font-head text-2xl uppercase leading-none text-[#0B1E3B]">Universal Academy</p>
                    <p class="font-head text-xs uppercase tracking-[0.3em] text-[#0B1E3B] opacity-80">Enrollment Office</p>
                </div>
            </div>
            <h1 class="font-head text-5xl uppercase leading-none mt-6 text-[#0B1E3B] italic">NEW RECRUIT</h1>
            <p class="recruit-motto bw-oath mt-2 text-sm font-bold uppercase border-t-[4px] border-[#0B1E3B] pt-2">"Fortis Fortuna Adiuvat" — Fortune favors the brave.</p>
        </div>
        <div class="absolute -right-4 -bottom-4 opacity-10 font-head text-8xl rotate-12 select-none">UA2026</div>
    </div>

    <div class="bullworth-card bg-[#F4F1E1] p-8 mt-4 border-[6px] border-[#0B1E3B]">
        <?php if ($error !== null): ?>
            <div class="border-[5px] border-[#0B1E3B] bg-[#B22222] p-3 text-[#F4F1E1] font-bold uppercase text-sm mb-6">
                ⚠ ERROR: <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-5">
            <div class="grid grid-cols-1 gap-5">
                <div>
                    <label class="label-style text-xs">Legal Full Name</label>
                    <input type="text" name="nama_lengkap" placeholder="e.g. JIMMY HOPKINS" 
                           class="bullworth-input w-full p-3 font-bold uppercase placeholder:opacity-30" 
                           value="<?= e($_POST['nama_lengkap'] ?? '') ?>" required>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label-style text-xs">Academy Email</label>
                        <input type="email" name="email" placeholder="recruit@ua.edu"
                               class="bullworth-input w-full p-3 font-bold uppercase placeholder:opacity-30" 
                               value="<?= e($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label class="label-style text-xs">Secure Password</label>
                        <input type="password" name="password" placeholder="******"
                               class="bullworth-input w-full p-3 font-bold uppercase placeholder:opacity-30" required>
                    </div>
                </div>

                <div>
                    <label class="label-style text-xs">Former Institution</label>
                    <input type="text" name="asal_sekolah" placeholder="Where did you fail before?"
                           class="bullworth-input w-full p-3 font-bold uppercase placeholder:opacity-30" 
                           value="<?= e($_POST['asal_sekolah'] ?? '') ?>" required>
                </div>

                <div>
    <label class="label-style text-xs">Specialization / Division</label>
    <input type="text" name="jurusan" 
           placeholder="Choose your destiny"
           class="bullworth-input w-full p-3 font-bold uppercase placeholder:opacity-30" 
           value="<?= e($_POST['jurusan'] ?? '') ?>" required>
    <p class="text-[10px] mt-1 font-bold opacity-50 uppercase italic">Write your true path. won't hoping you're survive</p>
</div>
            </div>

            <button type="submit" class="neo-btn bg-[#4CA7B8] w-full py-4 rounded-none uppercase font-head text-xl transition-all border-[5px] border-[#0B1E3B]">
                Submit Enrollment File
            </button>
        </form>

        <div class="mt-8 pt-6 border-t-[5px] border-[#0B1E3B] flex flex-col sm:flex-row gap-4 justify-between items-center">
            <a href="index.php" class="text-xs font-black uppercase text-[#0B1E3B] hover:underline">← Abandon Mission</a>
            <a href="login.php" class="bw-hard-shadow bg-[#E5A823] border-[5px] border-[#0B1E3B] px-4 py-2 text-xs font-black uppercase">
                PREVIOUSLY ENLISTED? AUTHORIZE ACCESS
            </a>
        </div>
    </div>

    <p class="bw-fine-print text-center text-[##0B1E3B] font-head uppercase text-[10px] mt-6 tracking-widest opacity-70">
        Property of Universal Academy • Disciplinary Committee 2026
    </p>
</div>
<?= uaStudentPageTransitionScript() ?>
</body>
</html>