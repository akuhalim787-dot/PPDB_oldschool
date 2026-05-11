<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/student_page_transition.php';
requireStudentAuth();

// -------------------------------------------------------------------------
// 1) AUTH + BASE CONTEXT
// -------------------------------------------------------------------------
$studentSession = studentAuth();
$officialLogo = 'assets/ChatGPT_Image_May_8__2026__05_01_58_PM-removebg-preview.jpg';
$error = null;

$candidate = null;
$screening = null;
$profile = null;

$sessionId = trim((string) ($studentSession['id'] ?? ''));
$sessionEmail = trim((string) ($studentSession['email'] ?? ''));

// Access flags for portal gating.
$statusRaw = '';
$statusNormalized = '';
$isVerifiedAccess = false;
$isRejectedAccess = false;
$isBlockedAccess = false;

// -------------------------------------------------------------------------
// 2) FETCH STUDENT FROM PENDAFTAR
// -------------------------------------------------------------------------
if ($sessionId !== '') {
    // Schema transition compatibility: prefer new columns with legacy fallback.
    $candidateQuery = 'pendaftar?select=id,nama_lengkap,email,department_assignment,verification_status,jurusan,status_verifikasi,created_at&id=eq.' . rawurlencode($sessionId) . '&limit=1';
    $candidateResult = supabaseRequest('GET', $candidateQuery);
} else {
    $candidateQuery = 'pendaftar?select=id,nama_lengkap,email,department_assignment,verification_status,jurusan,status_verifikasi,created_at&email=eq.' . rawurlencode($sessionEmail) . '&order=created_at.desc&limit=1';
    $candidateResult = supabaseRequest('GET', $candidateQuery);
}

if (!$candidateResult['success'] || !is_array($candidateResult['data']) || count($candidateResult['data']) === 0) {
    $error = 'Candidate record unavailable. Contact Enrollment Office immediately.';
} else {
    $candidate = $candidateResult['data'][0];
}

// -------------------------------------------------------------------------
// 3) ACCESS CONTROL (VERIFIED/APPROVED ONLY)
// -------------------------------------------------------------------------
if ($candidate !== null) {
    $statusRaw = trim((string) ($candidate['verification_status'] ?? $candidate['status_verifikasi'] ?? ''));
    $statusNormalized = mb_strtolower($statusRaw);
    $statusNormalized = str_replace(['_', '-'], ' ', $statusNormalized);
    $statusNormalized = preg_replace('/\s+/', ' ', $statusNormalized) ?? $statusNormalized;

    $isVerifiedAccess = in_array($statusNormalized, ['verified', 'approved', 'terverifikasi'], true);
    $isRejectedAccess = in_array($statusNormalized, ['denied', 'rejected', 'ditolak'], true);
    $isBlockedAccess = !$isVerifiedAccess;
}

// -------------------------------------------------------------------------
// 4) FETCH SCREENING + STUDENT PROFILE (ONLY WHEN ACCESS ALLOWED)
// -------------------------------------------------------------------------
if ($error === null && $candidate !== null && !$isBlockedAccess) {
    $studentId = trim((string) ($candidate['id'] ?? ''));

    // Relationship note:
    // - disciplinary_screenings.student_id references pendaftar.id
    // - student_profiles.student_id references pendaftar.id

    // Fetch disciplinary screening summary.
    $screeningQuery = 'disciplinary_screenings?select=behavioral_review,attendance_history,psychological_stability,compliance_rating,administrative_notes,final_result,reviewed_by,updated_at&student_id=eq.' . rawurlencode($studentId) . '&limit=1';
    $screeningResult = supabaseRequest('GET', $screeningQuery);
    if ($screeningResult['success'] && is_array($screeningResult['data']) && count($screeningResult['data']) > 0) {
        $screening = $screeningResult['data'][0];
    }

    // Fetch institutional profile row.
    $profileQuery = 'student_profiles?select=student_id,surveillance_status,threat_assessment,compliance_condition,faculty_intervention,campus_access_level,dormitory_assignment,disciplinary_notes,updated_at&student_id=eq.' . rawurlencode($studentId) . '&limit=1';
    $profileResult = supabaseRequest('GET', $profileQuery);

    if ($profileResult['success'] && is_array($profileResult['data']) && count($profileResult['data']) > 0) {
        $profile = $profileResult['data'][0];
    } else {
        // Auto-create default institutional profile if missing.
        $defaultProfile = [
            'student_id' => $studentId,
            'surveillance_status' => 'FACULTY_OBSERVATION_ACTIVE',
            'threat_assessment' => 'UNDER_REVIEW',
            'compliance_condition' => 'CONDITIONAL_CAMPUS_PERMIT',
            'faculty_intervention' => 'WEEKLY_HOMEROOM_SUPERVISION',
            'campus_access_level' => 'SECTOR_B_CONTROLLED_ACCESS',
            'dormitory_assignment' => 'BLOCK_C_UNIT_12',
            'disciplinary_notes' => 'Subject remains enrolled under routine institutional surveillance.',
            'updated_at' => gmdate('c'),
        ];

        $profileInsert = supabaseRequest('POST', 'student_profiles', $defaultProfile);
        if ($profileInsert['success']) {
            $profile = $defaultProfile;
        }
    }
}

/**
 * Normalize a status value into UPPER_SNAKE_CASE for CSS class binding.
 */
function statusKey(string $value): string
{
    $normalized = strtoupper(trim($value));
    $normalized = str_replace(['-', '/', '\\'], '_', $normalized);
    $normalized = preg_replace('/\s+/', '_', $normalized) ?? $normalized;
    $normalized = preg_replace('/[^A-Z0-9_]/', '', $normalized) ?? $normalized;
    return trim($normalized, '_');
}

/**
 * Human-readable status text from enum/machine value.
 */
function statusDisplayText(string $value): string
{
    return str_replace('_', ' ', statusKey($value));
}

/**
 * Normalize enrollment status text for consistent display labels.
 */
function enrollmentDisplayLabel(string $status): string
{
    $normalized = mb_strtolower(trim($status));
    if (in_array($normalized, ['verified', 'approved', 'terverifikasi'], true)) {
        return 'CLEARED';
    }
    if (in_array($normalized, ['denied', 'rejected', 'ditolak'], true)) {
        return 'REJECTED';
    }
    return 'UNDER REVIEW';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style><?= uaStudentPageTransitionCss() ?></style>
    <title>Student Internal Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&family=Montserrat:wght@400;500;700;900&family=Oswald:wght@500;700&family=Special+Elite&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 28px 14px;
            background: #1A1C1E;
            color: #F4F1E1;
            font-family: 'Montserrat', sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .font-head { font-family: 'Oswald', sans-serif; }
        .no-print {
            max-width: 220mm;
            margin: 0 auto 14px auto;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            border: 5px solid #0B1E3B;
            background: #E5A823;
            color: #0B1E3B;
            padding: 10px 16px;
            text-decoration: none;
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            font-weight: 700;
            box-shadow: 8px 8px 0px #000000;
            letter-spacing: 0.5px;
            border-radius: 0 !important;
        }
        .btn:hover { transform: translate(2px, 2px); box-shadow: 8px 8px 0px #000000; }
        .btn-white { background: #F4F1E1; color: #0B1E3B; }

        .sheet {
            width: 220mm;
            max-width: 100%;
            min-height: 290mm;
            margin: 0 auto;
            border: 14px solid #0B1E3B;
            background: #F4F1E1;
            box-shadow: 8px 8px 0px #000000;
            position: relative;
            overflow: hidden;
            padding: 24px;
        }
        .watermark {
            position: absolute;
            inset: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            pointer-events: none;
            text-transform: uppercase;
            font-size: clamp(50px, 9vw, 126px);
            color: rgba(0,0,0,0.045);
            font-weight: 900;
            transform: rotate(-26deg);
            text-align: center;
            line-height: 1.1;
            z-index: 0;
        }
        .content { position: relative; z-index: 1; color: #0B1E3B; }
        .header {
            display: grid;
            grid-template-columns: 110px 1fr;
            gap: 16px;
            align-items: center;
            border-bottom: 7px solid #0B1E3B;
            padding-bottom: 14px;
        }
        .logo-box {
            border: 5px solid #0B1E3B;
            background: #E5A823;
            padding: 8px;
        }
        .title {
            margin: 0;
            text-transform: uppercase;
            line-height: 0.95;
            letter-spacing: -1px;
            font-size: clamp(28px, 4.2vw, 48px);
            color: #0B1E3B;
            font-family: 'Oswald', sans-serif;
        }
        .tags {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .tag {
            background: #0B1E3B;
            color: #F4F1E1;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .section-title {
            margin: 18px 0 10px 0;
            background: #0B1E3B;
            color: #F4F1E1;
            display: inline-block;
            padding: 8px 12px;
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            transform: skewX(-8deg);
            font-size: 24px;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .card {
            border: 5px solid #0B1E3B;
            background: #ebe6d4;
            padding: 10px;
        }
        .label {
            display: block;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
            opacity: 0.7;
        }
        .value {
            margin-top: 4px;
            font-weight: 800;
            text-transform: uppercase;
            word-break: break-word;
        }
        /* Global enum badge system for student portal */
        .status-badge {
            border: 3px solid #0B1E3B;
            padding: 3px 8px;
            font-weight: 900;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .status-banner {
            border: 5px solid #0B1E3B;
            padding: 15px 55px;
            font-weight: 1500;
            text-transform: uppercase;
            font-size: 30px;
            line-height: 1;
            margin-top: 9px;
            margin-bottom: 20px;
            box-shadow: none;      
        }
       .status-banner.status-badge { 
    display: block; 
    width: fit-content; 
}

        /* SAFE (cleared) */
        .status-CLEARED,
        .status-FULL_ACCESS,
        .status-NONE,
        .status-FULL_COMPLIANCE { background: #2E4A31; color: #F4F1E1; }

        /* MONITORING (navy panel) */
        .status-PASSIVE_MONITORING,
        .status-ACTIVE_MONITORING,
        .status-UNDER_REVIEW,
        .status-SECTOR_A_GENERAL,
        .status-MONTHLY_REVIEW { background: #0B1E3B; color: #F4F1E1; }

        /* WARNING (accent) */
        .status-FACULTY_OBSERVATION_ACTIVE,
        .status-FLAGGED,
        .status-PROBATION,
        .status-WEEKLY_HOMEROOM_SUPERVISION,
        .status-CONDITIONAL_CAMPUS_PERMIT,
        .status-SECTOR_B_CONTROLLED_ACCESS { background: #E5A823; color: #0B1E3B; }

        /* RESTRICTED */
        .status-RESTRICTED,
        .status-RESTRICTED_ACCESS,
        .status-DAILY_CHECK_IN,
        .status-SECTOR_C_RESTRICTED,
        .status-BLOCK_D_RESTRICTED_HOUSING { background: #E5A823; color: #0B1E3B; border: 3px solid #0B1E3B; }

        /* DANGER */
        .status-BLACKLISTED,
        .status-SUSPENDED,
        .status-NON_COMPLIANT,
        .status-LOCKDOWN_ACCESS_ONLY,
        .status-DIRECT_INTERVENTION {
            background: #B22222;
            color: #F4F1E1;
        }
        .stamp {
            margin: 14px 0;
            border: 6px double #0B1E3B;
            border-radius: 0 !important;
            text-align: center;
            font-family: 'Oswald', sans-serif;
            font-size: 32px;
            font-weight: 900;
            text-transform: uppercase;
            padding: 10px;
            transform: rotate(-2deg);
            background: rgba(244, 241, 225, 0.92);
            box-shadow: 8px 8px 0px #000000;
        }
        .stamp-blue { color: #2E4A31; border-color: #2E4A31; box-shadow: 8px 8px 0px #000000; }
        .stamp-yellow { color: #E5A823; border-color: #E5A823; box-shadow: 8px 8px 0px #000000; }
        .stamp-red { color: #B22222; border-color: #B22222; box-shadow: 8px 8px 0px #000000; }

        table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            border: 2px solid #0B1E3B;
            padding: 10px 12px;
            font-size: 13px;
            vertical-align: top;
        }
        td.label-cell {
            width: 42%;
            background: #e8e4d4;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.3px;
        }
        .notes-box {
            border: 5px solid #0B1E3B;
            background: #F4F1E1;
            color: #0B1E3B;
            padding: 12px;
            font-size: 13px;
            font-style: italic;
            line-height: 1.6;
            min-height: 78px;
        }
        .footer-note {
            margin-top: 20px;
            border-top: 3px dashed #0B1E3B;
            font-family: 'Special Elite', 'Courier Prime', 'Courier New', monospace;
            padding-top: 10px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 800;
            opacity: 0.75;
        }
        @media (max-width: 940px) {
            .sheet {
                width: 100%;
                min-height: 0;
                border-width: 10px;
                padding: 16px;
            }
            .header,
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
        @media print {
            body { background: #F4F1E1; padding: 0; }
            .no-print { display: none !important; }
            .sheet {
                width: 100%;
                border-width: 9px;
                box-shadow: none;
                margin: 0;
            }
        }
    </style>
</head>
<body>
<?= uaStudentPageTransitionVeil() ?>

<div class="no-print">
    <a href="dashboard.php" class="btn btn-white">Back to Dashboard</a>
    <?php if (!$isBlockedAccess && $error === null): ?>
        <button class="btn" onclick="window.print();" type="button">Print Internal Dossier</button>
    <?php endif; ?>
</div>

<?php if ($error !== null): ?>
    <div class="sheet">
        <div class="content">
            <h1 class="font-head title">Access Restricted</h1>
            <p class="value"><?= e($error) ?></p>
        </div>
    </div>
<?php elseif ($isBlockedAccess): ?>
    <div class="sheet">
        <div class="watermark">Authorization Revoked</div>
        <div class="content">
            <header class="header">
                <div class="logo-box">
                    <img src="<?= e($officialLogo) ?>" alt="Academy Logo" style="width:100%;display:block;">
                </div>
                <div>
                    <h1 class="title font-head">Authorization Revoked</h1>
                    <div class="tags">
                        <span class="tag">Behavioral Compliance Division</span>
                        <span class="tag">Campus Surveillance Network</span>
                        <span class="tag">Authorized Student Access: Denied</span>
                    </div>
                </div>
            </header>

            <div class="stamp stamp-red"><?= e($isRejectedAccess ? 'ACCESS DENIED' : 'ACCESS RESTRICTED') ?></div>

            <div class="section-title">Restriction Notice</div>
            <table>
                <tr>
                    <td class="label-cell">Candidate Name</td>
                    <td><?= e((string) ($candidate['nama_lengkap'] ?? '-')) ?></td>
                </tr>
                <tr>
                    <td class="label-cell">Verification Status</td>
                    <?php $revokeStatusKey = statusKey($statusRaw !== '' ? $statusRaw : 'RESTRICTED'); ?>
                    <td><span class="status-badge status-<?= e($revokeStatusKey) ?>"><?= e($statusRaw !== '' ? enrollmentDisplayLabel($statusRaw) : 'RESTRICTED') ?></span></td>
                </tr>
                <tr>
                    <td class="label-cell">Archive Clearance</td>
                    <td>AUTHORIZATION REVOKED. STUDENT INTERNAL DOSSIER ACCESS HAS BEEN BLOCKED BY INSTITUTIONAL REVIEW.</td>
                </tr>
            </table>

            <div class="footer-note">
                Candidate failed access policy // Behavioral Compliance Division // Do Not Appeal Through Student Terminal
            </div>
        </div>
    </div>
<?php else: ?>
    <?php
    // Prepare display values from dynamic rows.
    $surveillanceStatus = strtoupper(trim((string) ($profile['surveillance_status'] ?? 'FACULTY_OBSERVATION_ACTIVE')));
    $threatAssessment = strtoupper(trim((string) ($profile['threat_assessment'] ?? 'UNDER_REVIEW')));
    $complianceCondition = strtoupper(trim((string) ($profile['compliance_condition'] ?? 'CONDITIONAL_CAMPUS_PERMIT')));
    $facultyIntervention = strtoupper(trim((string) ($profile['faculty_intervention'] ?? 'WEEKLY_HOMEROOM_SUPERVISION')));
    $campusAccessLevel = strtoupper(trim((string) ($profile['campus_access_level'] ?? 'SECTOR_B_CONTROLLED_ACCESS')));
    $dormitoryAssignment = strtoupper(trim((string) ($profile['dormitory_assignment'] ?? 'NONE')));
    $disciplinaryNotes = trim((string) ($profile['disciplinary_notes'] ?? 'No administrative observations recorded.'));

    $behavioralReview = strtoupper(trim((string) ($screening['behavioral_review'] ?? 'UNDER REVIEW')));
    $attendanceHistory = strtoupper(trim((string) ($screening['attendance_history'] ?? 'UNDER REVIEW')));
    $psychologicalStability = strtoupper(trim((string) ($screening['psychological_stability'] ?? 'UNDER REVIEW')));
    $complianceRating = strtoupper(trim((string) ($screening['compliance_rating'] ?? 'UNDER REVIEW')));
    $finalResult = strtoupper(trim((string) ($screening['final_result'] ?? 'UNDER REVIEW')));

    $finalStampClass = in_array(statusKey($finalResult), ['CLEARED', 'FULL_ACCESS', 'NONE', 'FULL_COMPLIANCE'], true)
        ? 'stamp-blue'
        : (in_array(statusKey($finalResult), ['BLACKLISTED', 'SUSPENDED', 'NON_COMPLIANT', 'LOCKDOWN_ACCESS_ONLY', 'DIRECT_INTERVENTION'], true) ? 'stamp-red' : 'stamp-yellow');
    ?>
    <div class="sheet">
        <div class="watermark">Monitored Subject</div>
        <div class="content">
            <header class="header">
                <div class="logo-box">
                    <img src="<?= e($officialLogo) ?>" alt="Academy Logo" style="width:100%;display:block;">
                </div>
                <div>
                    <h1 class="title font-head">Internal Student Dossier</h1>
                    <div class="tags">
                        <span class="tag">Behavioral Compliance Division</span>
                        <span class="tag">Authorized Student Access</span>
                        <span class="tag">Campus Surveillance Network</span>
                    </div>
                </div>
            </header>

            <div class="section-title">Personnel DOSSIER</div>
            <section class="grid-2">
                <article class="card">
                    <span class="label">Full Name</span>
                    <div class="value"><?= e((string) ($candidate['nama_lengkap'] ?? '-')) ?></div>
                </article>
                <article class="card">
                    <span class="label">Student ID</span>
                    <div class="value">#<?= e((string) ($candidate['id'] ?? '-')) ?></div>
                </article>
                <article class="card">
                    <span class="label">division</span>
                    <div class="value"><?= e((string) ($candidate['department_assignment'] ?? $candidate['jurusan'] ?? '-')) ?></div>
                </article>
                <article class="card">
                    <span class="label">Verification Status</span>
                    <?php $verificationKey = statusKey($statusRaw); ?>
                    <div class="value"><span class="status-badge status-<?= e($verificationKey) ?>"><?= e(enrollmentDisplayLabel($statusRaw)) ?></span></div>
                </article>
            </section>

            <div class="section-title">Disciplinary Surveillance Status</div>
            <?php $surveillanceKey = statusKey($surveillanceStatus); ?>
            <div class="status-banner status-badge status-<?= e($surveillanceKey) ?>">
                <?= e(statusDisplayText($surveillanceStatus)) ?>
            </div>

            <div class="section-title">Threat / Compliance Profile</div>
            <table>
                <tr>
                    <td class="label-cell">Threat Assessment</td>
                    <?php $threatKey = statusKey($threatAssessment); ?>
                    <td><span class="status-badge status-<?= e($threatKey) ?>"><?= e(statusDisplayText($threatAssessment)) ?></span></td>
                </tr>
                <tr>
                    <td class="label-cell">Compliance Condition</td>
                    <?php $complianceConditionKey = statusKey($complianceCondition); ?>
                    <td><span class="status-badge status-<?= e($complianceConditionKey) ?>"><?= e(statusDisplayText($complianceCondition)) ?></span></td>
                </tr>
                <tr>
                    <td class="label-cell">Faculty Intervention</td>
                    <?php $facultyKey = statusKey($facultyIntervention); ?>
                    <td><span class="status-badge status-<?= e($facultyKey) ?>"><?= e(statusDisplayText($facultyIntervention)) ?></span></td>
                </tr>
                <tr>
                    <td class="label-cell">Campus Access Level</td>
                    <?php $accessKey = statusKey($campusAccessLevel); ?>
                    <td><span class="status-badge status-<?= e($accessKey) ?>"><?= e(statusDisplayText($campusAccessLevel)) ?></span></td>
                </tr>
                <tr>
                    <td class="label-cell">Dormitory Assignment</td>
                    <?php $dormitoryKey = statusKey($dormitoryAssignment); ?>
                    <td><span class="status-badge status-<?= e($dormitoryKey) ?>"><?= e(statusDisplayText($dormitoryAssignment)) ?></span></td>
                </tr>
            </table>

            <div class="section-title">Administrative Observations</div>
            <div class="notes-box"><?= e($disciplinaryNotes) ?></div>

            <div class="section-title">Screening Summary</div>
            <div class="stamp <?= e($finalStampClass) ?>">Final Result: <?= e($finalResult) ?></div>
            <table>
                <tr>
                    <td class="label-cell">Behavioral Review</td>
                    <?php $behavioralKey = statusKey($behavioralReview); ?>
                    <td><span class="status-badge status-<?= e($behavioralKey) ?>"><?= e(statusDisplayText($behavioralReview)) ?></span></td>
                </tr>
                <tr>
                    <td class="label-cell">Attendance History</td>
                    <?php $attendanceKey = statusKey($attendanceHistory); ?>
                    <td><span class="status-badge status-<?= e($attendanceKey) ?>"><?= e(statusDisplayText($attendanceHistory)) ?></span></td>
                </tr>
                <tr>
                    <td class="label-cell">Psychological Stability</td>
                    <?php $psychKey = statusKey($psychologicalStability); ?>
                    <td><span class="status-badge status-<?= e($psychKey) ?>"><?= e(statusDisplayText($psychologicalStability)) ?></span></td>
                </tr>
                <tr>
                    <td class="label-cell">Compliance Rating</td>
                    <?php $complianceKey = statusKey($complianceRating); ?>
                    <td><span class="status-badge status-<?= e($complianceKey) ?>"><?= e(statusDisplayText($complianceRating)) ?></span></td>
                </tr>
                <tr>
                    <td class="label-cell">Reviewed By</td>
                    <td><?= e((string) ($screening['reviewed_by'] ?? '-')) ?></td>
                </tr>
                <tr>
                    <td class="label-cell">Last Screening Update</td>
                    <td><?= e((string) (!empty($screening['updated_at']) ? formatNewsDate((string) $screening['updated_at']) : '-')) ?></td>
                </tr>
            </table>

            <div class="footer-note">
                Internal Student Monitoring Dossier // Universal Academy Oversight Bureau // Obedientia Supra Omnia
            </div>
        </div>
    </div>
<?php endif; ?>

<?= uaStudentPageTransitionScript() ?>
</body>
</html>
