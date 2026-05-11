<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
requireAdminAuth();

// -------------------------------------------------------------------------
// Security/auth context
// -------------------------------------------------------------------------
$admin = adminAuth();
$adminDisplayName = trim((string) (($admin['username'] ?? '') !== '' ? $admin['username'] : ($admin['email'] ?? 'ADMIN_OFFICER')));
$officialLogo = '../assets/ChatGPT_Image_May_8__2026__05_01_58_PM-removebg-preview.jpg';

// -------------------------------------------------------------------------
// Request + state containers
// -------------------------------------------------------------------------
$studentId = trim((string) ($_GET['id'] ?? ''));
$error = null;
$successMessage = null;
$student = null;
$screening = null;
$studentProfile = null;

// Dropdown options requested for every screening dimension.
$statusOptions = ['CLEARED', 'UNDER REVIEW', 'RESTRICTED', 'NON-COMPLIANT'];

// -------------------------------------------------------------------------
// Structured enum states (machine values + UI labels)
// -------------------------------------------------------------------------
$profileEnums = [
    'surveillance_status' => [
        'NONE' => 'NO ACTIVE SURVEILLANCE',
        'PASSIVE_MONITORING' => 'PASSIVE MONITORING',
        'ACTIVE_MONITORING' => 'ACTIVE MONITORING',
        'FACULTY_OBSERVATION_ACTIVE' => 'FACULTY OBSERVATION ACTIVE',
    ],
    'threat_assessment' => [
        'CLEARED' => 'CLEARED',
        'UNDER_REVIEW' => 'UNDER REVIEW',
        'FLAGGED' => 'FLAGGED',
        'RESTRICTED' => 'RESTRICTED',
        'BLACKLISTED' => 'BLACKLISTED',
    ],
    'compliance_condition' => [
        'FULL_COMPLIANCE' => 'FULL COMPLIANCE',
        'CONDITIONAL_CAMPUS_PERMIT' => 'CONDITIONAL CAMPUS PERMIT',
        'PROBATION' => 'PROBATION',
        'RESTRICTED_ACCESS' => 'RESTRICTED ACCESS',
        'SUSPENDED' => 'SUSPENDED',
    ],
    'faculty_intervention' => [
        'NONE' => 'NO FACULTY INTERVENTION',
        'MONTHLY_REVIEW' => 'MONTHLY REVIEW',
        'WEEKLY_HOMEROOM_SUPERVISION' => 'WEEKLY HOMEROOM SUPERVISION',
        'DAILY_CHECK_IN' => 'DAILY CHECK-IN',
        'DIRECT_INTERVENTION' => 'DIRECT INTERVENTION',
    ],
    'campus_access_level' => [
        'FULL_ACCESS' => 'FULL ACCESS',
        'SECTOR_A_GENERAL' => 'SECTOR A - GENERAL',
        'SECTOR_B_CONTROLLED_ACCESS' => 'SECTOR B - CONTROLLED ACCESS',
        'SECTOR_C_RESTRICTED' => 'SECTOR C - RESTRICTED',
        'LOCKDOWN_ACCESS_ONLY' => 'LOCKDOWN ACCESS ONLY',
    ],
    'dormitory_assignment' => [
        'NONE' => 'NO DORMITORY ASSIGNMENT',
        'BLOCK_A_UNIT_01_10' => 'BLOCK A / UNIT 01-10',
        'BLOCK_B_UNIT_11_20' => 'BLOCK B / UNIT 11-20',
        'BLOCK_C_UNIT_12' => 'BLOCK C / UNIT 12',
        'BLOCK_D_RESTRICTED_HOUSING' => 'BLOCK D / RESTRICTED HOUSING',
    ],
];

// -------------------------------------------------------------------------
// Helper: build default screening payload for newly-created records
// -------------------------------------------------------------------------
/**
 * @return array<string, string>
 */
function buildDefaultScreeningPayload(string $studentId, string $reviewedBy): array
{
    return [
        // Relation to pendaftar.id via disciplinary_screenings.student_id FK.
        'student_id' => $studentId,
        'behavioral_review' => 'UNDER REVIEW',
        'attendance_history' => 'UNDER REVIEW',
        'psychological_stability' => 'UNDER REVIEW',
        'compliance_rating' => 'UNDER REVIEW',
        'administrative_notes' => 'INITIAL DOSSIER CREATED. AWAITING FORMAL REVIEW.',
        'final_result' => 'UNDER REVIEW',
        'reviewed_by' => $reviewedBy,
        'updated_at' => gmdate('c'),
    ];
}

/**
 * Build default student_profiles payload for threat/compliance control.
 *
 * @return array<string, string>
 */
function buildDefaultStudentProfilePayload(string $studentId): array
{
    return [
        'student_id' => $studentId,
        'surveillance_status'  => 'FACULTY_OBSERVATION_ACTIVE',
        'threat_assessment'     => 'UNDER_REVIEW',
        'compliance_condition'  => 'CONDITIONAL_CAMPUS_PERMIT',
        'faculty_intervention'  => 'WEEKLY_HOMEROOM_SUPERVISION',
        'campus_access_level'   => 'SECTOR_B_CONTROLLED_ACCESS',
        'dormitory_assignment'  => 'BLOCK_C_UNIT_12',
        'disciplinary_notes'    => 'Subject remains enrolled under routine institutional surveillance.',
        'updated_at'            => gmdate('c'),
    ];
}

// -------------------------------------------------------------------------
// 1) Validate required student ID from URL
// -------------------------------------------------------------------------
if ($studentId === '') {
    $error = 'Student ID is required. Open this page with ?id=STUDENT_ID.';
}

// -------------------------------------------------------------------------
// 2) Fetch student profile from pendaftar
// -------------------------------------------------------------------------
if ($error === null) {
    $studentResult = supabaseRequest(
        'GET',
        // Schema transition compatibility: prefer new columns with legacy fallback.
        'pendaftar?select=id,nama_lengkap,email,department_assignment,verification_status,jurusan,status_verifikasi&id=eq.' . rawurlencode($studentId) . '&limit=1'
    );

    if (!$studentResult['success'] || !is_array($studentResult['data']) || count($studentResult['data']) === 0) {
        $error = 'Candidate record not found in pendaftar.';
    } else {
        $student = $studentResult['data'][0];
    }
}

// -------------------------------------------------------------------------
// 3) Fetch existing screening record (if any)
// -------------------------------------------------------------------------
if ($error === null) {
    $screeningResult = supabaseRequest(
        'GET',
        'disciplinary_screenings?select=student_id,behavioral_review,attendance_history,psychological_stability,compliance_rating,administrative_notes,final_result,reviewed_by,updated_at&student_id=eq.' . rawurlencode($studentId) . '&limit=1'
    );

    if (!$screeningResult['success']) {
        $error = 'Failed to access disciplinary_screenings table. ' . (string) ($screeningResult['error'] ?? '');
    } elseif (is_array($screeningResult['data']) && count($screeningResult['data']) > 0) {
        $screening = $screeningResult['data'][0];
    } else {
        // -----------------------------------------------------------------
        // 4) Auto-create default row if screening record does not exist yet
        // -----------------------------------------------------------------
        $insertPayload = buildDefaultScreeningPayload($studentId, $adminDisplayName);
        $insertResult = supabaseRequest('POST', 'disciplinary_screenings', $insertPayload);

        if (!$insertResult['success']) {
            $error = 'Unable to initialize screening dossier. ' . (string) ($insertResult['error'] ?? '');
        } else {
            $screening = $insertPayload;
            $successMessage = 'Default screening dossier created successfully.';
        }
    }
}

// -------------------------------------------------------------------------
// 4) Fetch existing student_profiles record for full admin control
// -------------------------------------------------------------------------
if ($error === null) {
    $profileResult = supabaseRequest(
        'GET',
        'student_profiles?select=student_id,surveillance_status,threat_assessment,compliance_condition,faculty_intervention,campus_access_level,dormitory_assignment,disciplinary_notes,updated_at&student_id=eq.' . rawurlencode($studentId) . '&limit=1'
    );

    if (!$profileResult['success']) {
        $error = 'Failed to access student_profiles table. ' . (string) ($profileResult['error'] ?? '');
    } elseif (is_array($profileResult['data']) && count($profileResult['data']) > 0) {
        $studentProfile = $profileResult['data'][0];
    } else {
        // Auto-create default row so admin can immediately control profile.
        $defaultStudentProfile = buildDefaultStudentProfilePayload($studentId);
        $insertProfile = supabaseRequest('POST', 'student_profiles', $defaultStudentProfile);

        if (!$insertProfile['success']) {
            $error = 'Unable to initialize student profile dossier. ' . (string) ($insertProfile['error'] ?? '');
        } else {
            $studentProfile = $defaultStudentProfile;
            $successMessage = $successMessage === null
                ? 'Default screening dossier and student profile created successfully.'
                : $successMessage . ' Student profile initialized.';
        }
    }
}

// -------------------------------------------------------------------------
// 5) Handle save/update action from admin review form
// -------------------------------------------------------------------------
if ($error === null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sanitizeStatus = static function (string $value, array $allowed): string {
        $normalized = strtoupper(trim($value));
        return in_array($normalized, $allowed, true) ? $normalized : 'UNDER REVIEW';
    };
    $sanitizeEnum = static function (string $value, array $mapping, string $fallback): string {
        $normalized = strtoupper(trim($value));
        return array_key_exists($normalized, $mapping) ? $normalized : $fallback;
    };

    $behavioralReview = $sanitizeStatus((string) ($_POST['behavioral_review'] ?? ''), $statusOptions);
    $attendanceHistory = $sanitizeStatus((string) ($_POST['attendance_history'] ?? ''), $statusOptions);
    $psychologicalStability = $sanitizeStatus((string) ($_POST['psychological_stability'] ?? ''), $statusOptions);
    $complianceRating = $sanitizeStatus((string) ($_POST['compliance_rating'] ?? ''), $statusOptions);
    $finalResult = $sanitizeStatus((string) ($_POST['final_result'] ?? ''), $statusOptions);
    $administrativeNotes = trim((string) ($_POST['administrative_notes'] ?? ''));
    $surveillanceStatusKey = $sanitizeEnum((string) ($_POST['surveillance_status'] ?? ''), $profileEnums['surveillance_status'], 'FACULTY_OBSERVATION_ACTIVE');
    $threatAssessmentKey = $sanitizeEnum((string) ($_POST['threat_assessment'] ?? ''), $profileEnums['threat_assessment'], 'UNDER_REVIEW');
    $complianceConditionKey = $sanitizeEnum((string) ($_POST['compliance_condition'] ?? ''), $profileEnums['compliance_condition'], 'CONDITIONAL_CAMPUS_PERMIT');
    $facultyInterventionKey = $sanitizeEnum((string) ($_POST['faculty_intervention'] ?? ''), $profileEnums['faculty_intervention'], 'WEEKLY_HOMEROOM_SUPERVISION');
    $campusAccessLevelKey = $sanitizeEnum((string) ($_POST['campus_access_level'] ?? ''), $profileEnums['campus_access_level'], 'SECTOR_B_CONTROLLED_ACCESS');
    $dormitoryAssignmentKey = $sanitizeEnum((string) ($_POST['dormitory_assignment'] ?? ''), $profileEnums['dormitory_assignment'], 'BLOCK_C_UNIT_12');
    $disciplinaryProfileNotes = trim((string) ($_POST['disciplinary_notes'] ?? ''));

    if ($administrativeNotes === '') {
        $administrativeNotes = 'NO ADDITIONAL NOTES PROVIDED.';
    }
    if ($disciplinaryProfileNotes === '') {
        $disciplinaryProfileNotes = 'NO PROFILE NOTES PROVIDED.';
    }

    $updatePayload = [
        'behavioral_review'       => $behavioralReview,
        'attendance_history'      => $attendanceHistory,
        'psychological_stability' => $psychologicalStability,
        'compliance_rating'       => $complianceRating,
        'final_result'            => $finalResult,
        'administrative_notes'    => $administrativeNotes,
        'updated_at'              => gmdate('c'),
    ];

    $profileUpdatePayload = [
        'surveillance_status'  => $surveillanceStatusKey,
        'threat_assessment'    => $threatAssessmentKey,
        'compliance_condition' => $complianceConditionKey,
        'faculty_intervention' => $facultyInterventionKey,
        'campus_access_level'  => $campusAccessLevelKey,
        'dormitory_assignment' => $dormitoryAssignmentKey,
        'disciplinary_notes'   => $disciplinaryProfileNotes,
        'updated_at'           => gmdate('c'),
    ];

    // Update existing row linked by student_id.
    $updateResult = supabaseRequest(
        'PATCH',
        'disciplinary_screenings?student_id=eq.' . rawurlencode($studentId),
        $updatePayload
    );

    if (!$updateResult['success']) {
        $error = 'Failed to save screening dossier changes. ' . (string) ($updateResult['error'] ?? '');
    } else {
        $profileUpdate = supabaseRequest(
            'PATCH',
            'student_profiles?student_id=eq.' . rawurlencode($studentId),
            $profileUpdatePayload
        );

        if (!$profileUpdate['success']) {
            $error = 'Screening saved, but student profile update failed. ' . (string) ($profileUpdate['error'] ?? '');
        } else {
            $successMessage = 'Screening dossier and threat/compliance profile updated successfully.';
            $screening = array_merge((array) $screening, $updatePayload);
            $studentProfile = array_merge((array) $studentProfile, $profileUpdatePayload);
        }
    }
}

// Pre-fill safe values for form fields.
$behavioralReviewValue = (string) ($screening['behavioral_review'] ?? 'UNDER REVIEW');
$attendanceHistoryValue = (string) ($screening['attendance_history'] ?? 'UNDER REVIEW');
$psychologicalStabilityValue = (string) ($screening['psychological_stability'] ?? 'UNDER REVIEW');
$complianceRatingValue = (string) ($screening['compliance_rating'] ?? 'UNDER REVIEW');
$finalResultValue = (string) ($screening['final_result'] ?? 'UNDER REVIEW');
$administrativeNotesValue = (string) ($screening['administrative_notes'] ?? '');
$reviewedByValue = (string) ($screening['reviewed_by'] ?? $adminDisplayName);
$updatedAtValue = (string) ($screening['updated_at'] ?? '');
$surveillanceStatusValue = strtoupper(trim((string) ($studentProfile['surveillance_status'] ?? 'FACULTY_OBSERVATION_ACTIVE')));
$threatAssessmentValue = strtoupper(trim((string) ($studentProfile['threat_assessment'] ?? 'UNDER_REVIEW')));
$complianceConditionValue = strtoupper(trim((string) ($studentProfile['compliance_condition'] ?? 'CONDITIONAL_CAMPUS_PERMIT')));
$facultyInterventionValue = strtoupper(trim((string) ($studentProfile['faculty_intervention'] ?? 'WEEKLY_HOMEROOM_SUPERVISION')));
$campusAccessLevelValue = strtoupper(trim((string) ($studentProfile['campus_access_level'] ?? 'SECTOR_B_CONTROLLED_ACCESS')));
$dormitoryAssignmentValue = strtoupper(trim((string) ($studentProfile['dormitory_assignment'] ?? 'BLOCK_C_UNIT_12')));
$disciplinaryProfileNotesValue = (string) ($studentProfile['disciplinary_notes'] ?? '');

if (!array_key_exists($surveillanceStatusValue, $profileEnums['surveillance_status'])) {
    $surveillanceStatusValue = 'FACULTY_OBSERVATION_ACTIVE';
}
if (!array_key_exists($threatAssessmentValue, $profileEnums['threat_assessment'])) {
    $threatAssessmentValue = 'UNDER_REVIEW';
}
if (!array_key_exists($complianceConditionValue, $profileEnums['compliance_condition'])) {
    $complianceConditionValue = 'CONDITIONAL_CAMPUS_PERMIT';
}
if (!array_key_exists($facultyInterventionValue, $profileEnums['faculty_intervention'])) {
    $facultyInterventionValue = 'WEEKLY_HOMEROOM_SUPERVISION';
}
if (!array_key_exists($campusAccessLevelValue, $profileEnums['campus_access_level'])) {
    $campusAccessLevelValue = 'SECTOR_B_CONTROLLED_ACCESS';
}
if (!array_key_exists($dormitoryAssignmentValue, $profileEnums['dormitory_assignment'])) {
    $dormitoryAssignmentValue = 'BLOCK_C_UNIT_12';
}

/**
 * Convert status value to tone class.
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
    return 'tone-red';
}

/**
 * Normalize enrollment verification labels for English UI output.
 */
function enrollmentLabel(string $status): string
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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Screening Dossier</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700;900&family=Oswald:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #1b1b1b;
            color: #1A1A1A;
            font-family: 'Montserrat', sans-serif;
            padding: 28px 14px;
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
            border: 4px solid #1A1A1A;
            background: #FFD65A;
            color: #1A1A1A;
            text-decoration: none;
            padding: 10px 16px;
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: 6px 6px 0 #000;
            cursor: pointer;
        }
        .btn:hover { transform: translate(2px, 2px); box-shadow: 4px 4px 0 #000; }
        .btn-dark { background: #fff; }
        .btn-save { background: #4CA7B8; color: #fff; }

        .sheet {
            width: 220mm;
            max-width: 100%;
            margin: 0 auto;
            min-height: 290mm;
            background: #fff;
            border: 14px solid #1A1A1A;
            box-shadow: 18px 18px 0 rgba(0, 0, 0, 0.5);
            position: relative;
            padding: 24px;
            overflow: hidden;
        }
        .watermark {
            position: absolute;
            inset: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            pointer-events: none;
            font-size: clamp(44px, 8vw, 118px);
            font-weight: 900;
            text-transform: uppercase;
            color: rgba(0, 0, 0, 0.05);
            transform: rotate(-24deg);
            text-align: center;
            line-height: 1.2;
            z-index: 0;
        }
        .content { position: relative; z-index: 1; }

        .header {
            display: grid;
            grid-template-columns: 110px 1fr;
            gap: 14px;
            align-items: center;
            border-bottom: 7px solid #1A1A1A;
            padding-bottom: 14px;
        }
        .logo-box {
            border: 4px solid #1A1A1A;
            background: #FFD65A;
            padding: 8px;
        }
        .title {
            margin: 0;
            text-transform: uppercase;
            font-size: clamp(26px, 4.2vw, 46px);
            line-height: 0.95;
            letter-spacing: -1px;
        }
        .tags {
            margin-top: 8px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .tag {
            background: #1A1A1A;
            color: #fff;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .notice {
            margin-top: 14px;
            border: 3px solid #1A1A1A;
            padding: 10px 12px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
        }
        .notice-success { background: #d8f2f7; }
        .notice-error { background: #ffe0dc; }

        .section-title {
            margin: 18px 0 10px 0;
            background: #1A1A1A;
            color: #fff;
            display: inline-block;
            padding: 8px 12px;
            text-transform: uppercase;
            font-family: 'Oswald', sans-serif;
            transform: skewX(-8deg);
            font-size: 23px;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .card {
            border: 3px solid #1A1A1A;
            background: #f6f6f6;
            padding: 10px;
        }
        .label {
            display: block;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 900;
            opacity: 0.7;
        }
        .value {
            margin-top: 4px;
            font-weight: 800;
            text-transform: uppercase;
            word-break: break-word;
        }

        .stamp {
            margin: 12px 0;
            border: 7px double #1A1A1A;
            padding: 10px 12px;
            text-align: center;
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            font-size: 28px;
            font-weight: 900;
            transform: rotate(-2deg);
            background: rgba(255, 255, 255, 0.85);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .field {
            border: 2px solid #1A1A1A;
            background: #fff;
            padding: 10px;
        }
        .field-label {
            display: block;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }
        select,
        textarea,
        input[type="text"] {
            width: 100%;
            border: 3px solid #1A1A1A;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 13px;
            padding: 10px;
            background: #fff;
            color: #1A1A1A;
        }
        textarea { min-height: 180px; resize: vertical; }
        .full { grid-column: 1 / -1; }
        .readonly-box {
            background: #efefef;
            border: 3px solid #1A1A1A;
            padding: 10px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .tone-blue { background: #4CA7B8; color: #fff; padding: 3px 8px; border: 2px solid #1A1A1A; font-weight: 900; display: inline-block; }
        .tone-yellow { background: #FFD65A; color: #1A1A1A; padding: 3px 8px; border: 2px solid #1A1A1A; font-weight: 900; display: inline-block; }
        .tone-red { background: #F05D4B; color: #fff; padding: 3px 8px; border: 2px solid #1A1A1A; font-weight: 900; display: inline-block; }

        .footer-note {
            margin-top: 16px;
            border-top: 2px dashed #1A1A1A;
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
                border-width: 10px;
                min-height: 0;
                padding: 16px;
            }
            .header,
            .profile-grid,
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .sheet {
                width: 100%;
                margin: 0;
                border-width: 9px;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <a href="../admin_dashboard.php" class="btn btn-dark">Back to Admin Dashboard</a>
    <button type="button" class="btn" onclick="window.print();">Print Dossier</button>
</div>

<div class="sheet">
    <div class="watermark">INTERNAL EVALUATION DOSSIER</div>
    <div class="content">
        <!-- Header block -->
        <header class="header">
            <div class="logo-box">
                <img src="<?= e($officialLogo) ?>" alt="Academy Logo" style="width:100%;display:block;">
            </div>
            <div>
                <h1 class="title font-head">Manage Screening Record</h1>
                <div class="tags">
                    <span class="tag">Behavioral Compliance Division</span>
                    <span class="tag">Internal Evaluation Dossier</span>
                    <span class="tag">Authorized Administrative Personnel Only</span>
                </div>
            </div>
        </header>

        <?php if ($successMessage !== null): ?>
            <div class="notice notice-success"><?= e($successMessage) ?></div>
        <?php endif; ?>
        <?php if ($error !== null): ?>
            <div class="notice notice-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($student !== null): ?>
            <!-- Student profile summary -->
            <div class="section-title">Candidate Profile Snapshot</div>
            <section class="profile-grid">
                <article class="card">
                    <span class="label">Full Name</span>
                    <div class="value"><?= e((string) ($student['nama_lengkap'] ?? '-')) ?></div>
                </article>
                <article class="card">
                    <span class="label">Email</span>
                    <div class="value" style="text-transform:none;"><?= e((string) ($student['email'] ?? '-')) ?></div>
                </article>
                <article class="card">
                    <span class="label">division</span>
                    <div class="value"><?= e((string) ($student['department_assignment'] ?? $student['jurusan'] ?? '-')) ?></div>
                </article>
                <article class="card">
                    <span class="label">Verification Status</span>
                    <?php
                    $verificationStatus = (string) ($student['verification_status'] ?? $student['status_verifikasi'] ?? 'pending');
                    $verificationLabel = enrollmentLabel($verificationStatus === '' ? 'UNDER REVIEW' : $verificationStatus);
                    $verificationTone = screeningToneClass($verificationLabel);
                    ?>
                    <div class="value"><span class="<?= e($verificationTone) ?>"><?= e($verificationLabel) ?></span></div>
                </article>
            </section>

            <!-- Main screening review form -->
            <div class="section-title">Screening Control Panel</div>
            <div class="stamp <?= e(screeningToneClass($finalResultValue)) ?>">
                FINAL DOSSIER STATUS: <?= e($finalResultValue) ?>
            </div>

            <form method="post">
                <section class="form-grid">
                    <article class="field">
                        <label class="field-label">A. Behavioral Review</label>
                        <select name="behavioral_review" required>
                            <?php foreach ($statusOptions as $option): ?>
                                <option value="<?= e($option) ?>" <?= strtoupper($behavioralReviewValue) === $option ? 'selected' : '' ?>>
                                    <?= e($option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </article>

                    <article class="field">
                        <label class="field-label">B. Attendance History</label>
                        <select name="attendance_history" required>
                            <?php foreach ($statusOptions as $option): ?>
                                <option value="<?= e($option) ?>" <?= strtoupper($attendanceHistoryValue) === $option ? 'selected' : '' ?>>
                                    <?= e($option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </article>

                    <article class="field">
                        <label class="field-label">C. Psychological Stability</label>
                        <select name="psychological_stability" required>
                            <?php foreach ($statusOptions as $option): ?>
                                <option value="<?= e($option) ?>" <?= strtoupper($psychologicalStabilityValue) === $option ? 'selected' : '' ?>>
                                    <?= e($option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </article>

                    <article class="field">
                        <label class="field-label">D. Compliance Rating</label>
                        <select name="compliance_rating" required>
                            <?php foreach ($statusOptions as $option): ?>
                                <option value="<?= e($option) ?>" <?= strtoupper($complianceRatingValue) === $option ? 'selected' : '' ?>>
                                    <?= e($option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </article>

                    <article class="field">
                        <label class="field-label">F. Final Screening Result</label>
                        <select name="final_result" required>
                            <?php foreach ($statusOptions as $option): ?>
                                <option value="<?= e($option) ?>" <?= strtoupper($finalResultValue) === $option ? 'selected' : '' ?>>
                                    <?= e($option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </article>

                    <article class="field">
                        <label class="field-label">Reviewed By</label>
                        <div class="readonly-box"><?= e($adminDisplayName) ?></div>
                    </article>

                    <article class="field full">
                        <label class="field-label">E. Administrative Notes</label>
                        <textarea name="administrative_notes" placeholder="Enter institutional notes..." required><?= e($administrativeNotesValue) ?></textarea>
                    </article>

                    <article class="field full">
                        <label class="field-label">Threat / Compliance Profile Control</label>
                        <div class="form-grid">
                            <div>
                                <label class="field-label">Surveillance Status</label>
                                <select name="surveillance_status" required>
                                    <?php foreach ($profileEnums['surveillance_status'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= strtoupper($surveillanceStatusValue) === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Threat Assessment</label>
                                <select name="threat_assessment" required>
                                    <?php foreach ($profileEnums['threat_assessment'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= strtoupper($threatAssessmentValue) === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Compliance Condition</label>
                                <select name="compliance_condition" required>
                                    <?php foreach ($profileEnums['compliance_condition'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= strtoupper($complianceConditionValue) === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Faculty Intervention</label>
                                <select name="faculty_intervention" required>
                                    <?php foreach ($profileEnums['faculty_intervention'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= strtoupper($facultyInterventionValue) === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Campus Access Level</label>
                                <select name="campus_access_level" required>
                                    <?php foreach ($profileEnums['campus_access_level'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= strtoupper($campusAccessLevelValue) === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Dormitory Assignment</label>
                                <select name="dormitory_assignment" required>
                                    <?php foreach ($profileEnums['dormitory_assignment'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= strtoupper($dormitoryAssignmentValue) === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </article>

                    <article class="field full">
                        <label class="field-label">Disciplinary Profile Notes</label>
                        <textarea name="disciplinary_notes" placeholder="Enter threat and compliance observations..." required><?= e($disciplinaryProfileNotesValue) ?></textarea>
                    </article>

                    <article class="field full">
                        <label class="field-label">Last Updated Timestamp</label>
                        <input type="text" value="<?= e($updatedAtValue !== '' ? $updatedAtValue : 'NOT SAVED YET') ?>" readonly>
                    </article>
                </section>

                <div class="no-print" style="margin:14px 0 0 0; max-width:none;">
                    <button type="submit" class="btn btn-save">Save Screening Dossier</button>
                </div>
            </form>

            <div class="footer-note">
                Record Officer: <?= e($reviewedByValue) ?> // Bureau: Behavioral Compliance Division // Document Class: Internal
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
