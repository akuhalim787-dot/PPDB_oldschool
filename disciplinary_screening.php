<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/student_page_transition.php';
requireStudentAuth();

// -------------------------------------------------------------------------
// 1) Core auth/profile loading (real data from existing pendaftar table)
// -------------------------------------------------------------------------
$student = studentAuth();
$error = null;
$profile = null;
$officialLogo = 'assets/ChatGPT_Image_May_8__2026__05_01_58_PM-removebg-preview.jpg';
$statusRaw = '';
$statusNormalized = '';
$isApprovedStatus = false;
$isPendingStatus = false;
$isDeniedStatus = false;
$canAccessScreeningReport = false;
$screeningRecordAvailable = false;
$screeningRecordMessage = 'SCREENING RECORD NOT AVAILABLE';
$screeningData = null;
$behaviorValue = '-';
$attendanceValue = '-';
$psychValue = '-';
$complianceValue = '-';
$administrativeNotesValue = '';
$finalScreeningResult = 'UNDER REVIEW';
$reviewedByValue = '-';
$updatedAtValue = '';
$finalResultClass = 'stamp-review';
$finalResultToneClass = 'tone-yellow';

/**
 * Convert screening status value into visual tone class.
 */
function screeningToneClass(string $status): string
{
    $normalized = strtoupper(trim($status));
    if ($normalized === 'CLEARED') {
        return 'tone-blue';
    }
    if ($normalized === 'UNDER REVIEW') {
        return 'tone-yellow';
    }
    if (in_array($normalized, ['RESTRICTED', 'NON-COMPLIANT'], true)) {
        return 'tone-red';
    }
    return 'tone-yellow';
}

/**
 * Normalize enrollment status text for on-screen English labels.
 */
function enrollmentStatusLabel(string $status): string
{
    $normalized = mb_strtolower(trim($status));
    if (in_array($normalized, ['terverifikasi', 'verified', 'approved'], true)) {
        return 'CLEARED';
    }
    if (in_array($normalized, ['ditolak', 'denied', 'rejected'], true)) {
        return 'REJECTED';
    }
    return 'UNDER REVIEW';
}

$sessionId = trim((string) ($student['id'] ?? ''));
$sessionEmail = trim((string) ($student['email'] ?? ''));

if ($sessionId !== '') {
    // Schema transition compatibility: prefer new columns with legacy fallback.
    $query = 'pendaftar?select=id,nama_lengkap,email,previous_institution,department_assignment,verification_status,asal_sekolah,jurusan,status_verifikasi,created_at&id=eq.' . rawurlencode($sessionId) . '&limit=1';
    $result = supabaseRequest('GET', $query);
} else {
    $query = 'pendaftar?select=id,nama_lengkap,email,previous_institution,department_assignment,verification_status,asal_sekolah,jurusan,status_verifikasi,created_at&email=eq.' . rawurlencode($sessionEmail) . '&order=created_at.desc&limit=1';
    $result = supabaseRequest('GET', $query);
}

if (isset($result) && $result['success'] && is_array($result['data']) && count($result['data']) > 0) {
    $profile = $result['data'][0];
} else {
    $error = 'Candidate dossier unavailable. Contact Behavioral Compliance Division.';
}

// -------------------------------------------------------------------------
// 2) Admission access restriction system for PPDB screening archives
//    Only APPROVED/VERIFIED and PENDING/UNDER REVIEW may open full report.
//    DENIED/REJECTED students get an immersive ACCESS REVOKED page in-place.
// -------------------------------------------------------------------------
if ($profile !== null) {
    $statusRaw = trim((string) ($profile['verification_status'] ?? $profile['status_verifikasi'] ?? ''));
    $statusNormalized = mb_strtolower($statusRaw);
    $statusNormalized = str_replace(['_', '-'], ' ', $statusNormalized);
    $statusNormalized = preg_replace('/\s+/', ' ', $statusNormalized) ?? $statusNormalized;

    $isApprovedStatus = in_array($statusNormalized, ['approved', 'verified', 'terverifikasi'], true);
    $isPendingStatus = in_array($statusNormalized, ['pending', 'under review', 'review', 'menunggu', 'diproses'], true);
    $isDeniedStatus = in_array($statusNormalized, ['ditolak', 'denied', 'rejected'], true);

    // Explicit allow-list as requested: approved/verified/pending/under review only.
    $canAccessScreeningReport = $isApprovedStatus || $isPendingStatus;
}

// -------------------------------------------------------------------------
// 3) Fetch screening dossier from disciplinary_screenings (dynamic data)
//    Relation: pendaftar.id (candidate) -> disciplinary_screenings.student_id
// -------------------------------------------------------------------------
if ($profile !== null && $canAccessScreeningReport) {
    $currentStudentId = trim((string) ($profile['id'] ?? ''));
    if ($currentStudentId !== '') {
        $screeningQuery = 'disciplinary_screenings?select=behavioral_review,attendance_history,psychological_stability,compliance_rating,administrative_notes,final_result,reviewed_by,updated_at&student_id=eq.' . rawurlencode($currentStudentId) . '&limit=1';
        $screeningResult = supabaseRequest('GET', $screeningQuery);

        if ($screeningResult['success'] && is_array($screeningResult['data']) && count($screeningResult['data']) > 0) {
            $screeningRecordAvailable = true;
            $screeningData = $screeningResult['data'][0];

            $behaviorValue = strtoupper(trim((string) ($screeningData['behavioral_review'] ?? '-')));
            $attendanceValue = strtoupper(trim((string) ($screeningData['attendance_history'] ?? '-')));
            $psychValue = strtoupper(trim((string) ($screeningData['psychological_stability'] ?? '-')));
            $complianceValue = strtoupper(trim((string) ($screeningData['compliance_rating'] ?? '-')));
            $administrativeNotesValue = trim((string) ($screeningData['administrative_notes'] ?? ''));
            $reviewedByValue = trim((string) ($screeningData['reviewed_by'] ?? '-'));
            $updatedAtValue = trim((string) ($screeningData['updated_at'] ?? ''));

            $finalScreeningResult = strtoupper(trim((string) ($screeningData['final_result'] ?? 'UNDER REVIEW')));
            $finalResultToneClass = screeningToneClass($finalScreeningResult);
            if ($finalScreeningResult === 'CLEARED') {
                $finalResultClass = 'stamp-cleared';
            } elseif ($finalScreeningResult === 'UNDER REVIEW') {
                $finalResultClass = 'stamp-review';
            } else {
                $finalResultClass = 'stamp-restricted';
            }
        } else {
            $screeningRecordMessage = 'SCREENING RECORD NOT AVAILABLE';
        }
    } else {
        $screeningRecordMessage = 'SCREENING RECORD NOT AVAILABLE';
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disciplinary Screening Report</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&family=Montserrat:wght@400;500;700;900&family=Oswald:wght@500;700&family=Special+Elite&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 28px 16px;
            font-family: 'Montserrat', sans-serif;
            color: #F4F1E1;
            background: #0B1E3B;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .font-head { font-family: 'Oswald', sans-serif; }

        /* Top controls hidden on print. */
        .no-print {
            max-width: 210mm;
            margin: 0 auto 16px auto;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn {
            border: 5px solid #0B1E3B;
            background: #E5A823;
            box-shadow: 8px 8px 0 #0B1E3B;
            color: #0B1E3B;
            border-radius: 0 !important;
            text-decoration: none;
            text-transform: uppercase;
            font-family: 'Oswald', sans-serif;
            font-weight: 700;
            padding: 10px 18px;
            cursor: pointer;
            letter-spacing: 0.5px;
        }
        .btn-white { background: #F4F1E1; color: #0B1E3B; }
        .btn:hover { transform: translate(2px, 2px); box-shadow: 8px 8px 0 #0B1E3B; }

        /* Main dossier sheet style (paper + thick border). */
        .sheet {
            width: 210mm;
            min-height: 290mm;
            margin: 0 auto;
            background: #F4F1E1;
            border: 15px solid #0B1E3B;
            box-shadow: 8px 8px 0 #0B1E3B;
            position: relative;
            overflow: hidden;
            padding: 26px;
        }
        .watermark {
            position: absolute;
            inset: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            pointer-events: none;
            font-size: clamp(52px, 9vw, 128px);
            font-weight: 900;
            color: rgba(0, 0, 0, 0.045);
            transform: rotate(-28deg);
            text-transform: uppercase;
            letter-spacing: 5px;
            z-index: 0;
            text-align: center;
            line-height: 1.1;
        }
        .content {
            position: relative;
            z-index: 1;
            color: #0B1E3B;
        }

        /* Header branding section. */
        .header-grid {
            display: grid;
            grid-template-columns: 110px 1fr;
            gap: 16px;
            align-items: center;
            border-bottom: 7px solid #0B1E3B;
            padding-bottom: 16px;
        }
        .logo-box {
            border: 5px solid #0B1E3B;
            background: #E5A823;
            padding: 8px;
        }
        .dossier-title {
            margin: 0;
            font-size: clamp(28px, 4.1vw, 48px);
            line-height: 0.95;
            text-transform: uppercase;
            letter-spacing: -1px;
            color: #0B1E3B;
            font-family: 'Oswald', sans-serif;
        }
        .subline {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .tag {
            background: #0B1E3B;
            color: #F4F1E1;
            padding: 4px 8px;
        }

        /* Institutional metadata block. */
        .meta-grid {
            margin-top: 16px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .meta-item {
            border: 5px solid #0B1E3B;
            padding: 10px;
            background: #ebe6d4;
        }
        .meta-label {
            display: block;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.7;
        }
        .meta-value {
            margin-top: 4px;
            font-weight: 800;
            text-transform: uppercase;
        }

        /* Section list styling. */
        .section-title {
            display: inline-block;
            margin: 18px 0 10px 0;
            background: #0B1E3B;
            color: #F4F1E1;
            padding: 7px 12px;
            font-family: 'Oswald', sans-serif;
            font-size: 24px;
            text-transform: uppercase;
            transform: skewX(-8deg);
        }
        .screening-table {
            width: 100%;
            border-collapse: collapse;
        }
        .screening-table td {
            border: 2px solid #0B1E3B;
            padding: 10px 12px;
            vertical-align: top;
            font-size: 13px;
        }
        .screening-table td.label {
            width: 42%;
            background: #e8e4d4;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        /* Color system requested by user. */
        .tone-blue {
            background: #2E4A31;
            color: #F4F1E1;
            border: 3px solid #0B1E3B;
            font-weight: 900;
            padding: 3px 8px;
            display: inline-block;
            text-transform: uppercase;
        }
        .tone-yellow {
            background: #E5A823;
            color: #0B1E3B;
            border: 3px solid #0B1E3B;
            font-weight: 900;
            padding: 3px 8px;
            display: inline-block;
            text-transform: uppercase;
        }
        .tone-red {
            background: #B22222;
            color: #F4F1E1;
            border: 3px solid #0B1E3B;
            font-weight: 900;
            padding: 3px 8px;
            display: inline-block;
            text-transform: uppercase;
        }

        /* Official academy stamp style. */
        .academy-stamp {
            position: absolute;
            top: 210px;
            right: 20px;
            border: 6px double #0B1E3B;
            border-radius: 0 !important;
            width: 235px;
            min-height: 95px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-family: 'Oswald', sans-serif;
            font-size: 34px;
            font-weight: 900;
            text-transform: uppercase;
            transform: rotate(12deg);
            background: rgba(244, 241, 225, 0.92);
            z-index: 2;
            letter-spacing: 1px;
        }
        .stamp-cleared { color: #2E4A31; border-color: #2E4A31; box-shadow: 8px 8px 0 #0B1E3B; }
        .stamp-review { color: #E5A823; border-color: #E5A823; box-shadow: 8px 8px 0 #0B1E3B; }
        .stamp-restricted { color: #B22222; border-color: #B22222; box-shadow: 8px 8px 0 #0B1E3B; }
        .stamp-access-denied { color: #B22222; border-color: #B22222; box-shadow: 8px 8px 0 #0B1E3B; }

        /* Signature boxes section. */
        .signature-grid {
            margin-top: 28px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }
        .signature-box {
            border-top: 5px solid #0B1E3B;
            padding-top: 8px;
            text-align: center;
            text-transform: uppercase;
            font-size: 11px;
            font-weight: 900;
        }
        .signature-space {
            height: 56px;
        }

        .footer-note {
            margin-top: 24px;
            border-top: 3px dashed #0B1E3B;
            padding-top: 10px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 800;
            opacity: 0.85;
            font-family: 'Special Elite', 'Courier Prime', 'Courier New', monospace;
        }

        .screening-alert-missing {
            margin-top: 16px;
            padding: 12px;
            border: 5px solid #0B1E3B;
            background: #F4F1E1;
            color: #B22222;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        /* Responsive tweaks. */
        @media (max-width: 900px) {
            .sheet {
                width: 100%;
                min-height: 0;
                border-width: 10px;
                padding: 18px;
            }
            .academy-stamp {
                position: static;
                transform: none;
                margin: 12px 0;
                width: 100%;
            }
            .meta-grid,
            .signature-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Print mode rules. */
        @media print {
            body { background: #F4F1E1; padding: 0; }
            .no-print { display: none !important; }
            .sheet {
                width: 100%;
                margin: 0;
                border-width: 10px;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
<?= uaStudentPageTransitionVeil() ?>

<div class="no-print">
    <a href="dashboard.php" class="btn btn-white">Return to Dashboard</a>
    <?php if ($canAccessScreeningReport): ?>
        <button type="button" onclick="window.print();" class="btn">Print Screening Report</button>
    <?php endif; ?>
</div>

<?php if ($error !== null): ?>
    <div class="sheet">
        <div class="content">
            <h1 class="font-head" style="font-size: 46px; margin: 0; text-transform: uppercase;">Access Restricted</h1>
            <p style="font-weight: 700; text-transform: uppercase;"><?= e($error) ?></p>
            <p style="font-size: 12px; text-transform: uppercase; opacity: 0.65;">Authorized Personnel Only</p>
        </div>
    </div>
<?php elseif (!$canAccessScreeningReport): ?>
    <div class="sheet">
        <div class="watermark">Authorization Revoked</div>
        <div class="content">
            <header class="header-grid">
                <div class="logo-box">
                    <img src="<?= e($officialLogo) ?>" alt="Academy Logo" style="width:100%; display:block;">
                </div>
                <div>
                    <h1 class="font-head dossier-title">Access Revoked</h1>
                    <div class="subline">
                        <span class="tag">Authorization Revoked</span>
                        <span class="tag">Behavioral Compliance Division</span>
                        <span class="tag">Classified Restriction Notice</span>
                    </div>
                </div>
            </header>

            <div class="academy-stamp stamp-access-denied">
                Access Denied
            </div>

            <div class="section-title">Institutional Access Decision</div>
            <table class="screening-table">
                <tr>
                    <td class="label">Candidate Name</td>
                    <td><?= e((string) ($profile['nama_lengkap'] ?? '-')) ?></td>
                </tr>
                <tr>
                    <td class="label">Candidate ID</td>
                    <td>#<?= e((string) ($profile['id'] ?? '-')) ?></td>
                </tr>
                <tr>
                    <td class="label">Admission Status</td>
                    <td>
                        <span class="tone-red"><?= e($statusRaw !== '' ? enrollmentStatusLabel($statusRaw) : 'RESTRICTED') ?></span>
                    </td>
                </tr>
                <tr>
                    <td class="label">Archive Clearance</td>
                    <td><span class="tone-red">ACCESS TO SCREENING ARCHIVES DENIED</span></td>
                </tr>
                <tr>
                    <td class="label">Compliance Notice</td>
                    <td style="font-style: italic; font-weight: 700;">
                        CANDIDATE FAILED INSTITUTIONAL REVIEW. AUTHORIZATION REVOKED BY BEHAVIORAL COMPLIANCE DIVISION.
                    </td>
                </tr>
            </table>

            <div class="signature-grid">
                <div class="signature-box">
                    <div class="signature-space"></div>
                    Restrictions Officer
                </div>
                <div class="signature-box">
                    <div class="signature-space"></div>
                    Behavioral Compliance Division
                </div>
            </div>

            <div class="footer-note">
                Access to Screening Archives Denied // Authorized Personnel Only // Universal Academy Classified File
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="sheet">
        <div class="watermark">Candidate Screening Archive</div>
        <div class="content">
            <header class="header-grid">
                <div class="logo-box">
                    <img src="<?= e($officialLogo) ?>" alt="Academy Logo" style="width:100%; display:block;">
                </div>
                <div>
                    <h1 class="font-head dossier-title">Disciplinary Screening Report</h1>
                    <div class="subline">
                        <span class="tag">Institutional Conduct Evaluation</span>
                        <span class="tag">Behavioral Compliance Division</span>
                        <span class="tag">Authorized Personnel Only</span>
                    </div>
                </div>
            </header>

            <div class="academy-stamp <?= e($finalResultClass) ?>">
                <?= e($finalScreeningResult) ?>
            </div>

            <section class="meta-grid">
                <article class="meta-item">
                    <span class="meta-label">Candidate Name</span>
                    <div class="meta-value"><?= e((string) ($profile['nama_lengkap'] ?? '-')) ?></div>
                </article>
                <article class="meta-item">
                    <span class="meta-label">Candidate ID</span>
                    <div class="meta-value">#<?= e((string) ($profile['id'] ?? '-')) ?></div>
                </article>
                <article class="meta-item">
                    <span class="meta-label">Enrollment Email</span>
                    <div class="meta-value" style="text-transform:none;"><?= e((string) ($profile['email'] ?? '-')) ?></div>
                </article>
                <article class="meta-item">
                    <span class="meta-label">Department Assignment</span>
                    <div class="meta-value"><?= e((string) ($profile['department_assignment'] ?? $profile['jurusan'] ?? '-')) ?></div>
                </article>
            </section>

            <div class="section-title">Candidate Screening Matrix</div>

            <table class="screening-table">
                <tr>
                    <td class="label">A. Behavioral Review</td>
                    <td>
                        <?php $behaviorClass = screeningToneClass($behaviorValue); ?>
                        <span class="<?= e($behaviorClass) ?>"><?= e($behaviorValue) ?></span>
                    </td>
                </tr>
                <tr>
                    <td class="label">B. Attendance History</td>
                    <td>
                        <?php $attendanceClass = screeningToneClass($attendanceValue); ?>
                        <span class="<?= e($attendanceClass) ?>"><?= e($attendanceValue) ?></span>
                    </td>
                </tr>
                <tr>
                    <td class="label">C. Psychological Stability</td>
                    <td>
                        <?php $psychClass = screeningToneClass($psychValue); ?>
                        <span class="<?= e($psychClass) ?>"><?= e($psychValue) ?></span>
                    </td>
                </tr>
                <tr>
                    <td class="label">D. Compliance Rating</td>
                    <td>
                        <?php $complianceClass = screeningToneClass($complianceValue); ?>
                        <span class="<?= e($complianceClass) ?>"><?= e($complianceValue) ?></span>
                    </td>
                </tr>
                <tr>
                    <td class="label">E. Administrative Notes</td>
                    <td style="font-style: italic; font-weight: 600;">
                        <?= e($administrativeNotesValue !== '' ? $administrativeNotesValue : $screeningRecordMessage) ?>
                    </td>
                </tr>
                <tr>
                    <td class="label">Final Screening Result</td>
                    <td>
                        <span class="<?= e($finalResultToneClass) ?>"><?= e($finalScreeningResult) ?></span>
                    </td>
                </tr>
                <tr>
                    <td class="label">Reviewed By</td>
                    <td><?= e($screeningRecordAvailable ? $reviewedByValue : '-') ?></td>
                </tr>
                <tr>
                    <td class="label">Last Updated</td>
                    <td><?= e($screeningRecordAvailable ? ($updatedAtValue !== '' ? formatNewsDate($updatedAtValue) : '-') : '-') ?></td>
                </tr>
            </table>

            <?php if (!$screeningRecordAvailable): ?>
                <div class="screening-alert-missing">
                    <?= e($screeningRecordMessage) ?>
                </div>
            <?php endif; ?>

            <div class="signature-grid">
                <div class="signature-box">
                    <div class="signature-space"></div>
                    Discipline Officer Signature
                </div>
                <div class="signature-box">
                    <div class="signature-space"></div>
                    Behavioral Compliance Division
                </div>
            </div>

            <div class="footer-note">
                Candidate Screening Archive // Confidential Dossier // Universal Academy Internal Bureaucratic File
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
// Optional auto-focus behavior for print flow can be added here later.
</script>
<?= uaStudentPageTransitionScript() ?>
</body>
</html>
