<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/student_page_transition.php';

$flash = getFlash();
$featuredTotal = 0;
$featuredVerified = 0;
$jurusanLabels = [];
$jurusanValues = [];
$statusLabels = ['Cleared', 'Rejected', 'Under Review'];
$statusValues = [0, 0, 0];
$statusPercentages = [0, 0, 0];
$latestNews = fetchLatestNews(5);
$officialLogo = 'assets/ChatGPT_Image_May_8__2026__05_01_58_PM-removebg-preview.jpg';

// --- DATA FETCHING LOGIC ---
$statsResponse = supabaseRequest(
    'GET',
    // Schema transition compatibility: use new columns with legacy fallback.
    'pendaftar?select=department_assignment,verification_status,jurusan,status_verifikasi'
);

if ($statsResponse['success'] && is_array($statsResponse['data'])) {
    $featuredTotal = count($statsResponse['data']);
    $jurusanCounts = [];
    $statusCounts = [
        'Cleared' => 0,
        'Rejected' => 0,
        'Under Review' => 0,
    ];

    foreach ($statsResponse['data'] as $item) {
        $status = trim((string) ($item['verification_status'] ?? $item['status_verifikasi'] ?? ''));
        $jurusan = trim((string) ($item['department_assignment'] ?? $item['jurusan'] ?? 'Unassigned'));
        $jurusan = $jurusan !== '' ? $jurusan : 'Unassigned';

        if (mb_strtolower($status) === 'terverifikasi') {
            $featuredVerified++;
            $statusCounts['Cleared']++;
        } elseif (mb_strtolower($status) === 'ditolak') {
            $statusCounts['Rejected']++;
        } else {
            $statusCounts['Under Review']++;
        }

        $jurusanCounts[$jurusan] = ($jurusanCounts[$jurusan] ?? 0) + 1;
    }

    $jurusanLabels = array_keys($jurusanCounts);
    $jurusanValues = array_values($jurusanCounts);
    $statusValues = array_values($statusCounts);
    $statusPercentages = array_map(
        static fn(int $count): float => $featuredTotal > 0 ? round(($count / $featuredTotal) * 100, 1) : 0,
        $statusValues
    );
}

$studentSession = $_SESSION['student_auth'] ?? null;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style><?= uaStudentPageTransitionCss() ?></style>
    <title>Universal Academy - Enrollment Command</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&family=Oswald:wght@500;700&display=swap" rel="stylesheet">
    <style>
        <?= bullworthStudentCss() ?>
    </style>
</head>
<body class="bullworth-student min-h-screen pb-10">
<?= uaStudentPageTransitionVeil() ?>

<div class="max-w-[1300px] mx-auto w-full px-3 py-5 sm:px-4 md:py-8">
    
    <nav class="neo-border neo-shadow bg-[#4CA7B8] p-4 rounded-sm w-full mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="neo-border bg-[#FFD65A] px-4 py-2 rounded-sm inline-flex items-center gap-4 w-full md:w-auto">
                <img src="<?= e($officialLogo) ?>" alt="Crest" class="bw-drop-logo h-10 md:h-12 w-auto object-contain">
                <div class="border-l-2 border-black pl-3">
                    <p class="font-head text-xl md:text-2xl uppercase leading-none tracking-tighter">Universal</p>
                    <p class="font-head text-sm md:text-1sm uppercase leading-none opacity-100 font-bold">Academy</p>
                </div>
            </div>
            <div class="flex gap-3">
                <a href="register.php" class="neo-btn bg-[#FFD65A] px-6 py-3 rounded-sm text-xs font-bold uppercase border-2 border-black hover:shadow-none transition-all">Join The Academy</a>
                <a href="login.php" class="neo-btn bg-white px-6 py-3 rounded-sm text-xs font-bold uppercase border-2 border-black hover:shadow-none transition-all">Personnel Portal</a>
            </div>
        </div>
    </nav>

    <header class="neo-border neo-shadow bg-[#FFD65A] p-8 md:p-12 rounded-sm w-full text-center relative overflow-hidden">
        <div class="absolute -top-10 -left-10 text-9xl font-black opacity-5 rotate-12 select-none">UA_2026</div>
        <p class="font-head text-sm tracking-[0.4em] uppercase opacity-80 mb-6">Est. MMXXIV • Official Enrollment Portal</p>
        
        <div class="flex justify-center mb-8">
            <img src="<?= e($officialLogo) ?>" alt="Crest" class="bw-drop-logo h-32 md:h-48 w-auto object-contain">
        </div>

        <h1 class="font-head text-5xl md:text-8xl leading-[0.85] uppercase tracking-tighter">
            UNIVERSAL <br class="hidden md:block">
            <span class="inline-block bg-white neo-border px-4 py-1 md:px-8 md:py-3 rounded-sm -rotate-1 mx-2">ACADEMY</span><br>
            PRESTIGE & HONOR
        </h1>

        <div class="mt-8 flex justify-center">
            <p class="bw-oath max-w-2xl text-lg md:text-2xl font-bold uppercase italic border-y-4 border-black py-3">
                "EDUCATION IS THE ROD, AND YOU ARE THE IRON. WE FORGE GREATNESS."
            </p>
        </div>
    </header>

    <main class="mt-10 grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <section class="lg:col-span-4 space-y-6">
            <div class="neo-border neo-shadow bg-[#4CA7B8] p-5 text-white relative">
                <div class="absolute top-2 right-2 text-[10px] bg-black px-2 py-0.5 uppercase font-bold">Live_Auth</div>
                <h3 class="font-head text-xl uppercase mb-4 tracking-wider">Active Terminal</h3>
                <?php if ($studentSession): ?>
                    <div class="bg-black/20 p-4 neo-border border-white/50">
                        <p class="font-head text-2xl uppercase leading-none break-words"><?= e($studentSession['nama_lengkap']) ?></p>
                        <p class="text-[10px] font-mono mt-2 uppercase opacity-70 italic">REF_ID: <?= e((string)($studentSession['nisn'] ?? 'UNSET')) ?></p>
                        <a href="dashboard.php" class="mt-4 neo-btn bg-[#FFD65A] text-black w-full py-2 text-center block font-head text-xs uppercase">Enter Dashboard</a>
                    </div>
                <?php else: ?>
                    <div class="border-2 border-dashed border-white/40 p-6 text-center">
                        <p class="font-head text-lg uppercase opacity-60 italic">Guest Access Only</p>
                        <p class="text-[9px] uppercase mt-1">Please login to access student records</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="neo-border neo-shadow bg-[#FFF6DE] p-6">
                <h4 class="font-head text-xl uppercase mb-4 border-b-2 border-black pb-1">Quick Protocols</h4>
                <div class="grid grid-cols-1 gap-3">
                    <a href="register.php" class="neo-btn bg-white p-3 text-xs font-bold uppercase text-center border-2 border-black hover:bg-[#4CA7B8] hover:text-white transition-colors">Start Registration</a>
                    <a href="login.php" class="neo-btn bg-white p-3 text-xs font-bold uppercase text-center border-2 border-black hover:bg-black hover:text-white transition-colors">Sign In</a>
                    <a href="disciplinary_screening.php" class="neo-btn bg-white p-3 text-xs font-bold uppercase text-center border-2 border-black hover:bg-[#FFD65A] transition-colors">Disciplinary Screening</a>
                </div>
            </div>
        </section>

        <section class="lg:col-span-8 space-y-6">
            
            <div class="neo-border neo-shadow bg-[#4CA7B8] p-6 text-white relative">
                <div class="flex items-end justify-between mb-8 border-b-2 border-white/30 pb-2">
                    <h2 class="font-head text-5xl uppercase italic tracking-tighter leading-none">Live Monitor</h2>
                    <div class="text-right">
                        <span class="inline-block w-2 h-2 bg-green-400 rounded-full animate-pulse mr-1"></span>
                        <span class="text-[10px] font-black uppercase">Syncing_Realtime</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <article class="bg-black/20 p-4 border-l-4 border-white">
                        <p class="text-[10px] font-black uppercase opacity-70">Total_Capacity</p>
                        <p class="font-head text-6xl leading-none mt-1">360</p>
                    </article>
                    <article class="bg-black/20 p-4 border-l-4 border-[#FFD65A]">
                        <p class="text-[10px] font-black uppercase text-[#FFD65A]">Total_Recruits</p>
                        <p class="font-head text-6xl leading-none mt-1 text-[#FFD65A]"><?= e((string)$featuredTotal) ?></p>
                    </article>
                    <article class="bg-black/20 p-4 border-l-4 border-[#FFD65A]">
                        <p class="text-[10px] font-black uppercase text-[#FFD65A]">Verified_Clearance</p>
                        <p class="font-head text-6xl leading-none mt-1 text-[#FFD65A]"><?= e((string)$featuredVerified) ?></p>
                    </article>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="neo-border neo-shadow bg-white p-6">
                    <h4 class="font-head text-2xl uppercase mb-4 italic">Ratio Analysis</h4>
                    <div class="h-[250px] w-full">
                        <canvas id="statusDoughnutChart"></canvas>
                    </div>
                </div>
                <div class="neo-border neo-shadow bg-white p-6">
                    <h4 class="font-head text-2xl uppercase mb-4 italic">Department Quota</h4>
                    <div class="h-[250px] w-full">
                        <canvas id="jurusanQuotaChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="neo-border neo-shadow bg-white p-8 border-l-[15px] border-black">
                <div class="flex items-center gap-4 mb-8">
                    <h2 class="font-head text-4xl uppercase tracking-tighter italic underline decoration-4 decoration-[#E5A823]">Latest Decrees</h2>
                    <div class="flex-1 border-b-2 border-black border-dashed"></div>
                </div>
                
                <div class="space-y-8">
                    <?php if (empty($latestNews)): ?>
                        <p class="text-xs uppercase font-bold italic opacity-40">No incoming transmissions...</p>
                    <?php else: ?>
                        <?php foreach ($latestNews as $news): ?>
                            <article class="relative group border-b border-gray-100 pb-6 last:border-0">
                                <time class="bg-black text-white px-2 py-0.5 text-[9px] font-black uppercase italic"><?= e(formatNewsDate($news['created_at'])) ?></time>
                                <h3 class="font-head text-2xl uppercase mt-3 group-hover:text-[#E5A823] transition-colors leading-tight"><?= e($news['title']) ?></h3>
                                <p class="text-sm mt-2 text-gray-600 leading-relaxed font-medium italic"><?= e($news['content']) ?></p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <footer class="mt-12 text-center">
        <div class="inline-block neo-border bg-black text-white px-10 py-4 -rotate-1">
            <p class="bw-oath font-head text-xl uppercase tracking-[0.2em]">"Exterminate All the brutes"</p>
        </div>
        <p class="mt-6 text-[10px] text-[#ffffff] font-head uppercase opacity-100 tracking-widest">Universal Academy System v2.0 // Enrollment Command Center</p>
    </footer>
</div>

<script>
const statusLabels = <?= json_encode($statusLabels) ?>;
const statusValues = <?= json_encode($statusValues) ?>;
const statusPercentages = <?= json_encode($statusPercentages) ?>;
const publicJurusanLabels = <?= json_encode($jurusanLabels) ?>;
const publicJurusanValues = <?= json_encode($jurusanValues) ?>;

Chart.defaults.font.family = 'Oswald, sans-serif';
Chart.defaults.color = '#0B1E3B';

// 1. Status Doughnut
new Chart(document.getElementById('statusDoughnutChart'), {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{
            data: statusValues,
            backgroundColor: ['#2E4A31', '#B22222', '#E5A823'],
            borderColor: '#0B1E3B',
            borderWidth: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 15,
                    font: { size: 12, weight: 'bold' },
                    generateLabels: (chart) => {
                        const original = Chart.defaults.plugins.legend.labels.generateLabels(chart);
                        return original.map((label, i) => ({
                            ...label,
                            text: `${statusLabels[i]} (${statusPercentages[i]}%)`
                        }));
                    }
                }
            }
        }
    }
});

// 2. Jurusan Bar
new Chart(document.getElementById('jurusanQuotaChart'), {
    type: 'bar',
    data: {
        labels: publicJurusanLabels.length > 0 ? publicJurusanLabels : ['No Data'],
        datasets: [{
            label: 'Students',
            data: publicJurusanValues.length > 0 ? publicJurusanValues : [0],
            backgroundColor: '#E5A823',
            borderColor: '#0B1E3B',
            borderWidth: 4
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: { max: 100, grid: { color: '#E5E5E5' }, ticks: { font: { weight: 'bold' } } },
            y: { grid: { display: false }, ticks: { font: { weight: 'bold' } } }
        },
        plugins: {
            legend: { display: false }
        }
    }
});
</script>
<?= uaStudentPageTransitionScript() ?>
</body>
</html>