<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/student_page_transition.php';
requireStudentAuth();

$student = studentAuth();
$error = null;
$profile = null;

$sessionId = trim((string) ($student['id'] ?? ''));
$requestedId = trim((string) ($_GET['id'] ?? ''));
$lookupId = $sessionId !== '' ? $sessionId : $requestedId;

if ($lookupId !== '') {
    // Schema transition compatibility: prefer new columns with legacy fallback.
    $query = 'pendaftar?select=id,nama_lengkap,email,previous_institution,department_assignment,verification_status,asal_sekolah,jurusan,status_verifikasi,created_at&id=eq.' . rawurlencode($lookupId) . '&limit=1';
    $result = supabaseRequest('GET', $query);
} else {
    $email = trim((string) ($student['email'] ?? ''));
    $query = 'pendaftar?select=id,nama_lengkap,email,previous_institution,department_assignment,verification_status,asal_sekolah,jurusan,status_verifikasi,created_at&email=eq.' . rawurlencode($email) . '&order=created_at.desc&limit=1';
    $result = supabaseRequest('GET', $query);
}

if (isset($result) && $result['success'] && is_array($result['data']) && count($result['data']) > 0) {
    $profile = $result['data'][0];
} else {
    $error = 'Candidate record not found.';
}

$status = mb_strtolower(trim((string) ($profile['verification_status'] ?? $profile['status_verifikasi'] ?? 'pending')));

$isVerified = in_array($status, ['terverifikasi', 'verified']);
$isRejected = in_array($status, ['ditolak', 'rejected']);
$isPending  = !$isVerified && !$isRejected;
$officialLogo = 'assets/ChatGPT_Image_May_8__2026__05_01_58_PM-removebg-preview.jpg';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style><?= uaStudentPageTransitionCss() ?></style>
    <title>OFFICIAL FILE: <?= e((string)($profile['nama_lengkap'] ?? 'Unknown')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:ital,wght@0,400;0,700;1,400&family=Montserrat:ital,wght@0,700;1,400;1,900&family=Oswald:wght@500;700&family=Special+Elite&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 40px 20px;
            background: #1A1C1E;
            font-family: 'Montserrat', sans-serif;
            color: #F4F1E1;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .font-head { font-family: 'Oswald', sans-serif; }
        
        /* A4 Sheet Styling */
        .sheet {
            width: 210mm;
            min-height: 285mm;
            margin: 0 auto;
            background: #F4F1E1;
            border: 15px solid #0B1E3B;
            padding: 40px;
            position: relative;
            overflow: hidden;
            box-shadow: 8px 8px 0px #000000;
            color: #0B1E3B;
        }

        /* Watermark Background */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 150px;
            font-weight: 900;
            color: rgba(0,0,0,0.03);
            pointer-events: none;
            text-transform: uppercase;
            z-index: 0;
            white-space: nowrap;
        }

        .header-grid {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 20px;
            border-bottom: 8px solid #0B1E3B;
            padding-bottom: 20px;
            align-items: center;
        }

        .logo-frame {
            border: 5px solid #0B1E3B;
            padding: 10px;
            background: #E5A823;
        }

        .title-area h1 {
            margin: 0;
            font-size: 50px;
            line-height: 0.9;
            text-transform: uppercase;
            font-family: 'Oswald', sans-serif;
            letter-spacing: -2px;
            color: #0B1E3B;
        }

        .file-info {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-weight: 900;
            text-transform: uppercase;
            font-size: 12px;
            background: #0B1E3B;
            color: #F4F1E1;
            padding: 5px 10px;
        }

        .section-title {
            background: #0B1E3B;
            color: #F4F1E1;
            padding: 8px 15px;
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            font-size: 24px;
            margin: 30px 0 15px 0;
            display: inline-block;
            transform: skewX(-10deg);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            z-index: 1;
            position: relative;
        }

        td {
            border: 2px solid #0B1E3B;
            padding: 12px 15px;
            font-size: 14px;
            font-weight: 700;
        }

        td.label {
            background: #e8e4d4;
            width: 30%;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 1px;
        }

        .photo-area {
            width: 160px;
            height: 200px;
            border: 5px dashed #0B1E3B;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-weight: 900;
            font-size: 20px;
            background: #ebe6d4;
            margin-left: 20px;
            position: relative;
        }

        /* The Stamp Effect */
        .stamp {
            position: absolute;
            top: 150px;
            right: 40px;
            width: 250px;
            height: 100px;
            border: 8px double #0B1E3B;
            border-radius: 0 !important;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Oswald', sans-serif;
            font-size: 40px;
            font-weight: 900;
            text-transform: uppercase;
            transform: rotate(15deg);
            opacity: 0.92;
            z-index: 10;
            background: rgba(244, 241, 225, 0.88);
            pointer-events: none;
            box-shadow: none;
        }

.stamp-approved {
    border-color: #2E4A31;
    color: #2E4A31;
}

.stamp-rejected {
    border-color: #B22222;
    color: #B22222;
}

.stamp-pending {
    border-color: #E5A823;
    color: #E5A823;
}

        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 100px;
            margin-top: 10px;
        }

        .sig-box {
            border-top: 5px solid #0B1E3B;
            padding-top: 50px;
            text-align: center;
            font-weight: 900;
            text-transform: uppercase;
            font-size: 12px;
        }

        .print-oath {
            font-family: 'Special Elite', 'Courier Prime', 'Courier New', monospace;
            font-size: 13px;
            font-style: normal;
            line-height: 1.65;
            font-weight: 400;
            color: #0B1E3B;
        }

        .footer-creed {
            margin-top: 50px;
            border-top: 4px solid #0B1E3B;
            padding-top: 20px;
            text-align: center;
        }

        .motto {
            font-family: 'Special Elite', 'Courier Prime', serif;
            font-size: 26px;
            font-weight: 400;
            font-style: normal;
            text-transform: uppercase;
            color: #0B1E3B;
        }

        .footer-fine-print {
            font-family: 'Courier Prime', 'Courier New', monospace;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 10px;
            letter-spacing: 2px;
            color: #0B1E3B;
        }

        /* Controls */
        .no-print {
            max-width: 210mm;
            margin: 0 auto 20px auto;
            display: flex;
            gap: 15px;
        }

        .btn {
            background: #E5A823;
            border: 5px solid #0B1E3B;
            padding: 12px 25px;
            font-family: 'Oswald', sans-serif;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
            text-decoration: none;
            color: #0B1E3B;
            box-shadow: 8px 8px 0px #000000;
            border-radius: 0 !important;
        }

        .btn:hover {
            transform: translate(2px, 2px);
            box-shadow: 8px 8px 0px #000000;
        }

        @media print {
            body { padding: 0; background: #F4F1E1; }
            .no-print { display: none !important; }
            .sheet { 
                margin: 0; 
                box-shadow: none; 
                border-width: 10px; 
                width: 100%;
            }
        }
    </style>
</head>
<body>
<?= uaStudentPageTransitionVeil() ?>

<?php if ($error !== null): ?>
    <div class="no-print">
        <a href="dashboard.php" class="btn">Return to Base</a>
    </div>
    <div class="sheet">
        <h1 class="font-head">ACCESS DENIED</h1>
        <p><?= e($error) ?></p>
    </div>
<?php else: ?>
    <div class="no-print">
        <a href="dashboard.php" class="btn" style="background:#F4F1E1;">← Dashboard</a>
        <button onclick="window.print();" class="btn">Print Official File</button>
    </div>

    <div class="sheet">
        <div class="watermark">CONFIDENTIAL</div>

        <header class="header-grid">
            <div class="logo-frame">
                <img src="<?= e($officialLogo) ?>" alt="Logo" style="width:100%; display:block;">
            </div>
            <div class="title-area">
                <h1 class="font-head">Universal Academy</h1>
                <div class="file-info">
                    <span>ENROLLMENT FILE: 2026-ALPHA</span>
                    <span>TYPE: ADMISSION_CARD</span>
                </div>
            </div>
        </header>

        <div style="display: flex; align-items: flex-start; margin-top: 20px;">
            <div style="flex: 1;">
                <div class="section-title">Personnel Data</div>
                <table>
                    <tr>
                        <td class="label">Registrant ID</td>
                        <td>#<?= e((string) ($profile['id'] ?? '-')) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Legal Full Name</td>
                        <td style="font-size: 20px; font-family: 'Oswald';"><?= e((string) ($profile['nama_lengkap'] ?? '-')) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Contact/Email</td>
                        <td><?= e((string) ($profile['email'] ?? '-')) ?></td>
                    </tr>
                    <tr>
                        <td class="label">division</td>
                        <td><span style="background:#0B1E3B; color:#E5A823; padding: 2px 8px; border: 3px solid #0B1E3B;"><?= e((string) ($profile['department_assignment'] ?? $profile['jurusan'] ?? '-')) ?></span></td>
                    </tr>
                    <tr>
                        <td class="label">Previous School</td>
                        <td><?= e((string) ($profile['previous_institution'] ?? $profile['asal_sekolah'] ?? '-')) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Entry Date</td>
                        <td><?= e(formatNewsDate((string) ($profile['created_at'] ?? ''))) ?></td>
                    </tr>
                </table>
            </div>

            <div class="photo-area">
                PLACE<br>RECRUIT<br>PHOTO<br>HERE
                <div style="position:absolute; bottom:5px; font-size:8px; opacity:0.5;">SIZE: 3x4</div>
            </div>
        </div>

        <?php if ($isVerified): ?>
    <div class="stamp stamp-approved">APPROVED</div>

<?php elseif ($isRejected): ?>
    <div class="stamp stamp-rejected">DENIED</div>

<?php else: ?>
    <div class="stamp stamp-pending">PENDING</div>
<?php endif; ?>

        <div class="section-title">Official Oath</div>
        <p class="print-oath">
            "Applicants and members of Universal Academy are held accountable for the entire jurisdiction of the academy’s regulation. Adherence to the academy's policies is compulsory throughout the time frame, including the behavior code, the academic code, and administration orders.

Universal Academy strictly enforces its policies on any form of dishonesty, misconduct, and non-compliance. In the event of non-compliance, whether major or minor, it will be subjected to the academy’s disciplinary policy and may lead to instant expulsion from the academy without prior warning.

In joining Universal Academy, one agrees to become a member of Universal Academy contingent upon one's ability to conform to the academy’s policies."
        </p>

        <div class="signature-grid">
            <div class="sig-box">
                <div style="height: 60px;"></div>
                Parent / Guardian
            </div>
            <div class="sig-box">
                <div style="height: 60px;"></div>
                The Candidate (Sign Above)
            </div>
        </div>

        <div class="footer-creed">
            <div class="motto">"Obedientia supra omnia"</div>
            <p class="footer-fine-print">
                Universal Academy Official Document • Do Not Duplicate
            </p>
        </div>

        <div style="position:absolute; bottom:20px; right:40px; font-family: 'Courier Prime', 'Courier New', monospace; font-size: 10px; opacity:0.35; color:#0B1E3B;">
            ||||||||||||||||||||||||| UA-2026-<?= e((string)$profile['id']) ?>
        </div>
    </div>
<?php endif; ?>

<script>
    // Auto print kalo data aman
    <?php if ($profile): ?>
    window.onload = function () { 
        // Kasih delay dikit biar user sempet liat kerennya layout lo
        setTimeout(() => { window.print(); }, 1000); 
    };
    <?php endif; ?>
</script>
<?= uaStudentPageTransitionScript() ?>
</body>
</html>