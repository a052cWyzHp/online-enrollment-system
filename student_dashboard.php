<?php
session_start();

$currentStudentPage = 'dashboard';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config.php';

function ensureStudentNumber(PDO $pdo, int $userId): string
{
    try {
        $pdo->beginTransaction();

        // Get the student's profile and lock it while generating the number
        $studentQuery = $pdo->prepare("
            SELECT student_id, student_number
            FROM student_profiles
            WHERE user_id = :user_id
            LIMIT 1
            FOR UPDATE
        ");

        $studentQuery->execute([
            ':user_id' => $userId
        ]);

        $student = $studentQuery->fetch(PDO::FETCH_ASSOC);

        // If profile does not exist, create it
        if (!$student) {
            $createProfile = $pdo->prepare("
                INSERT INTO student_profiles (user_id)
                VALUES (:user_id)
            ");

            $createProfile->execute([
                ':user_id' => $userId
            ]);

            $studentId = (int) $pdo->lastInsertId();
            $currentStudentNumber = null;
        } else {
            $studentId = (int) $student['student_id'];
            $currentStudentNumber = $student['student_number'];
        }

        // If student already has a number, return it
        if (!empty($currentStudentNumber)) {
            $pdo->commit();
            return $currentStudentNumber;
        }

        // Example: 2026 becomes 26
        $yearPrefix = date('y');

        // Find the latest student number for the current year
        $latestQuery = $pdo->prepare("
            SELECT student_number
            FROM student_profiles
            WHERE student_number LIKE :year_prefix
            ORDER BY student_number DESC
            LIMIT 1
        ");

        $latestQuery->execute([
            ':year_prefix' => $yearPrefix . '-%'
        ]);

        $latest = $latestQuery->fetch(PDO::FETCH_ASSOC);

        if ($latest && !empty($latest['student_number'])) {
            // Example: 26-0007 becomes 7
            $lastNumber = (int) substr($latest['student_number'], 3);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        // Example: 26-0001
        $newStudentNumber = $yearPrefix . '-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

        $updateQuery = $pdo->prepare("
            UPDATE student_profiles
            SET student_number = :student_number
            WHERE student_id = :student_id
        ");

        $updateQuery->execute([
            ':student_number' => $newStudentNumber,
            ':student_id' => $studentId
        ]);

        $pdo->commit();

        return $newStudentNumber;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        die('Unable to generate student number. Please contact the administrator.');
    }
}
$userId = (int) $_SESSION['user_id'];
$studentNumber = ensureStudentNumber($pdo, $userId);

// converts name to initials like Hello World -> H W
function getInitials(string $name): string
{
    // removes spaces before and after the name then splits each word into an item in the $words array
    $words = preg_split('/\s+/', trim($name));
    $initials = '';

    // for every item (or word) in the $words array...
    foreach ($words as $word) {
        if ($word !== '') { // if the item is not empty...
            $initials .= strtoupper(substr($word, 0, 1)); // take the first letter of the string in this item then make the letter uppercase, then put it in $initials variable
        }

        // and only stop if the length of the string in $initials is 2
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'S'; // output the initials if $initials is not empty, if it is however, output S as initial for student instead
}

// properly formats enrollment status of the user
function formatStatusLabel(string $status): string
{
    return match ($status) {
        'application_form_pending' => 'Application Form Pending',
        'documents_pending' => 'Documents Pending',
        'documents_submitted' => 'Documents Submitted',
        'under_review' => 'Under Review',
        'resubmission_required' => 'Resubmission Required',
        'approved' => 'Approved',
        'confirmation_pending' => 'Confirmation Pending',
        'payment_pending' => 'Payment Pending',
        'payment_submitted' => 'Payment Submitted',
        'enrolled' => 'Enrolled',
        'fully_enrolled' => 'Fully Enrolled',
        'rejected' => 'Rejected',
        default => 'Not Started'
    };
}

// provides description of the enrollment status
function getStatusDescription(string $status): string
{
    return match ($status) {
        'application_form_pending' => 'Complete your application form to start the enrollment process.',
        'documents_pending' => 'Your application form is complete. Please upload your required documents.',
        'documents_submitted' => 'Your documents were submitted and are ready for checking.',
        'under_review' => 'Your application and documents are currently being reviewed by the registrar.',
        'resubmission_required' => 'Some requirements need correction. Please review and resubmit the requested documents.',
        'approved' => 'Your application has been approved. Please proceed to the reservation payment step.',
        'confirmation_pending' => 'Please confirm your intention to enroll by completing the next step.',
        'payment_pending' => 'Please pay the ₱3,000 reservation fee and upload your proof of payment.',
        'payment_submitted' => 'Your proof of payment has been submitted and is waiting for admin verification.',
        'enrolled' => 'Your payment has been verified and you are now enrolled in the system.',
        'fully_enrolled' => 'Your enrollment process is fully complete.',
        'rejected' => 'Your application was not approved. Please contact the registrar for assistance.',
        default => 'You have not started the enrollment process yet.'
    };
}

// this is for the progress bar
function getProgressPercent(string $status): int
{
    return match ($status) {
        'application_form_pending' => 0,
        'documents_pending' => 25,
        'documents_submitted' => 40,
        'under_review' => 50,
        'resubmission_required' => 45,
        'approved', 'confirmation_pending' => 70,
        'payment_pending' => 75,
        'payment_submitted' => 85,
        'enrolled' => 95,
        'fully_enrolled' => 100,
        'rejected' => 40,
        default => 0
    };
}

// this is for the application status part of the page, it basically dictates the color of the enrollment status
function getStatusBadgeClass(string $status): string
{
    return match ($status) {
        'fully_enrolled', 'enrolled', 'approved' => 'text-success-emphasis bg-success-subtle',
        'payment_pending', 'documents_pending', 'under_review', 'documents_submitted', 'payment_submitted', 'confirmation_pending' => 'text-primary-emphasis bg-primary-subtle',
        'resubmission_required' => 'text-warning-emphasis bg-warning-subtle',
        'rejected' => 'text-danger-emphasis bg-danger-subtle',
        default => 'text-secondary-emphasis bg-secondary-subtle'
    };
}

// for the text inside enrollment button depending on the enrollment status
function getEnrollmentButtonText(string $status): string
{
    return match ($status) {
        'application_form_pending' => 'Start Application Form',
        'documents_pending', 'resubmission_required' => 'Upload Documents',
        'approved', 'confirmation_pending', 'payment_pending' => 'Proceed to Payment',
        'payment_submitted', 'documents_submitted', 'under_review' => 'View Review Status',
        'enrolled', 'fully_enrolled' => 'View Enrollment Details',
        'rejected' => 'View Application Status',
        default => 'Start Enrollment'
    };
}

// this is for the steps below the progress bar
function getStepStatus(string $currentStatus, string $step): array
{
    $stepOrder = [
        'application' => 1,
        'documents' => 2,
        'review' => 3,
        'payment' => 4,
        'enrolled' => 5
    ];

    $currentPosition = match ($currentStatus) {
        'application_form_pending' => 1,
        'documents_pending', 'resubmission_required' => 2,
        'documents_submitted', 'under_review' => 3,
        'approved', 'confirmation_pending', 'payment_pending', 'payment_submitted' => 4,
        'enrolled', 'fully_enrolled' => 5,
        'rejected' => 3,
        default => 1
    };

    $stepPosition = $stepOrder[$step];

    if ($currentStatus === 'rejected' && $step === 'review') {
        return ['Rejected', 'text-danger fw-semibold', 'bi bi-x-circle-fill'];
    }

    if ($stepPosition < $currentPosition || ($currentStatus === 'fully_enrolled' && $stepPosition <= 5)) {
        return ['Complete', 'text-success fw-semibold', 'bi bi-check-circle-fill'];
    }

    if ($stepPosition === $currentPosition) {
        if ($currentStatus === 'resubmission_required') {
            return ['Needs Action', 'text-warning fw-semibold', 'bi bi-exclamation-circle-fill'];
        }

        if (in_array($currentStatus, ['documents_submitted', 'under_review', 'payment_submitted'], true)) {
            return ['In Review', 'text-primary fw-semibold', 'bi bi-hourglass-split'];
        }

        return ['Current Step', 'text-primary fw-semibold', 'bi bi-arrow-right-circle-fill'];
    }

    return ['Not Started', 'text-secondary fw-semibold', 'bi bi-dash-circle-fill'];
}

$userId = (int) $_SESSION['user_id'];
// this $userId variable is to be used for SQL queries
$studentProfile = null;
// this $studentProfile variable becomes an object whose data can be accessed to display data
$currentApplication = null;
$documentsCount = 0;
$requiredDocumentsUploaded = 0;
$notifications = [];

try {
    // this is to fetch student_profiles table record linked to the userId logged into this page
    $studentQuery = $pdo->prepare("
                                    SELECT sp.student_id, sp.student_number, u.full_name, u.email 
                                    FROM student_profiles sp 
                                    INNER JOIN users u ON sp.user_id = u.user_id 
                                    WHERE sp.user_id = :user_id 
                                    LIMIT 1
                                ");
    $studentQuery->execute([':user_id' => $userId]);
    $studentProfile = $studentQuery->fetch(PDO::FETCH_ASSOC);

    if ($studentProfile) {
        // this is to fetch enrollment_applications table row linked to the student_id of the user to gather enrollment information of the student
        $applicationQuery = $pdo->prepare("
                                            SELECT ea.application_id, ea.application_status, ea.school_year, ea.submitted_at, ea.updated_at, p.program_name 
                                            FROM enrollment_applications ea 
                                            INNER JOIN programs p ON ea.program_id = p.program_id 
                                            WHERE ea.student_id = :student_id 
                                            ORDER BY ea.updated_at DESC 
                                            LIMIT 1
                                        ");
        $applicationQuery->execute([':student_id' => $studentProfile['student_id']]);
        $currentApplication = $applicationQuery->fetch(PDO::FETCH_ASSOC);

        if ($currentApplication) {
            // this is to fetch uploaded_documents and document_types table linked to the application_id just to display information of uploaded documents
            $documentsQuery = $pdo->prepare("
                                                SELECT COUNT(*) AS total_uploaded, SUM(CASE WHEN dt.is_required = 1 THEN 1 ELSE 0 END) AS required_uploaded 
                                                FROM uploaded_documents ud 
                                                INNER JOIN document_types dt ON ud.document_type_id = dt.document_type_id 
                                                WHERE ud.application_id = :application_id
                                            ");
            $documentsQuery->execute([':application_id' => $currentApplication['application_id']]);
            $documentSummary = $documentsQuery->fetch(PDO::FETCH_ASSOC);

            $documentsCount = (int) ($documentSummary['total_uploaded'] ?? 0);
            $requiredDocumentsUploaded = (int) ($documentSummary['required_uploaded'] ?? 0);

            // this is to fetch status_history table linked to application_id for notification part of the page
            $historyQuery = $pdo->prepare("
                                            SELECT new_status, change_notes, changed_at 
                                            FROM status_history 
                                            WHERE application_id = :application_id 
                                            ORDER BY changed_at DESC 
                                            LIMIT 3
                                        ");
            $historyQuery->execute([':application_id' => $currentApplication['application_id']]);
            $historyRows = $historyQuery->fetchAll(PDO::FETCH_ASSOC);

            // puts results from SQL query above to an associative array $notifications
            foreach ($historyRows as $history) {
                $notifications[] = [
                    'title' => formatStatusLabel($history['new_status']),
                    'description' => $history['change_notes'] ?: getStatusDescription($history['new_status']),
                    'time' => date('M d, Y', strtotime($history['changed_at']))
                ];
            }
        }
    }
} catch (PDOException $e) {
}

// ENROLLMENT OVERVIEW parts
$studentName = $studentProfile['full_name'] ?? ($_SESSION['full_name'] ?? 'Student');
$applicationStatus = $currentApplication['application_status'] ?? 'application_form_pending';
$progressPercent = getProgressPercent($applicationStatus);
$programName = $currentApplication['program_name'] ?? 'No program selected yet';
$schoolYear = $currentApplication['school_year'] ?? 'Not set';
$submittedAt = !empty($currentApplication['submitted_at']) ? date('M d, Y', strtotime($currentApplication['submitted_at'])) : 'Not submitted yet';

$studentData = [
    'student_name' => $studentName,
    'student_initials' => getInitials($studentName),
    'class_year' => $schoolYear,
    'completion_percentage' => $progressPercent,
    'application_status' => formatStatusLabel($applicationStatus),
];


// APPLICATION STEP PARTS
$applicationStep = getStepStatus($applicationStatus, 'application');
$documentsStep = getStepStatus($applicationStatus, 'documents');
$reviewStep = getStepStatus($applicationStatus, 'review');
$paymentStep = getStepStatus($applicationStatus, 'payment');
$enrolledStep = getStepStatus($applicationStatus, 'enrolled');

$enrollmentSteps = [
    [
        'title' => 'Application Form',
        'status' => $applicationStep[0],
        'status_class' => $applicationStep[1],
        'icon' => $applicationStep[2]
    ],
    [
        'title' => 'Documents Upload',
        'status' => $documentsStep[0],
        'status_class' => $documentsStep[1],
        'icon' => $documentsStep[2]
    ],
    [
        'title' => 'Admin Review',
        'status' => $reviewStep[0],
        'status_class' => $reviewStep[1],
        'icon' => $reviewStep[2]
    ],
    [
        'title' => 'Reservation Payment',
        'status' => $paymentStep[0],
        'status_class' => $paymentStep[1],
        'icon' => $paymentStep[2]
    ],
    [
        'title' => 'Final Enrollment',
        'status' => $enrolledStep[0],
        'status_class' => $enrolledStep[1],
        'icon' => $enrolledStep[2]
    ],
];

// notification system at bottom of the page
if (empty($notifications)) {
    $notifications[] = [
        'title' => formatStatusLabel($applicationStatus),
        'description' => getStatusDescription($applicationStatus),
        'time' => 'Current'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light-subtle">

<?php include 'student page/navbar.php'; ?>

<div class="container-fluid">
    <div class="row g-0">

        <?php include 'student page/sidebar.php'; ?>

        <main class="col-12 col-lg-10 col-xl-10 ms-auto px-3 px-md-4 px-lg-4 py-4" style="min-height: calc(100vh - 140px);">

            <div class="mb-4">
                <h1 class="fw-bold mb-2" style="color: #111827; font-size: clamp(2rem, 4vw, 4rem);">
                    Welcome back, <?= htmlspecialchars($studentData['student_name']); ?>.
                </h1>
                <p class="mb-0 fs-4 text-secondary">
                    Your enrollment process is <?= $studentData['completion_percentage']; ?>% complete. <?= $studentData['completion_percentage'] === 100 ? 'Congratulations and welcome!' : 'Review your next step below.' ?>
                </p>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-12 col-xl-8">
                    <div class="card border-0 rounded-4 shadow-sm h-100">
                        <div class="card-body p-4 p-lg-5">
                            <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
                                <div>
                                    <h2 class="fw-bold mb-2" style="color: #1f2937;">Enrollment Overview</h2>
                                    <div class="text-secondary">
                                        Program: <span class="fw-semibold text-dark"><?= htmlspecialchars($programName); ?></span>
                                    </div>
                                    <div class="text-secondary">
                                        School Year: <span class="fw-semibold text-dark"><?= htmlspecialchars($schoolYear); ?></span>
                                    </div>
                                </div>

                                <div class="text-end">
                                    <div class="text-secondary text-uppercase small fw-semibold mb-1">Progress</div>
                                    <div class="fw-bold" style="font-size: 3rem; color: #0b1f5f;">
                                        <?= $studentData['completion_percentage']; ?>%
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                <div class="text-uppercase fw-medium" style="letter-spacing: 2px; color: #374151;">
                                    Application Status:
                                </div>
                                <span class="badge rounded-pill px-3 py-2 <?= getStatusBadgeClass($applicationStatus); ?>">
                                    <?= htmlspecialchars($studentData['application_status']); ?>
                                </span>
                            </div>

                            <p class="text-secondary fs-5 mb-4">
                                <?= htmlspecialchars(getStatusDescription($applicationStatus)); ?>
                            </p>

                            <div class="progress mb-5" role="progressbar" aria-valuenow="<?= $studentData['completion_percentage']; ?>" aria-valuemin="0" aria-valuemax="100" style="height: 12px;">
                                <div class="progress-bar rounded-pill progress-bar-striped progress-bar-animated" style="width: <?= $studentData['completion_percentage']; ?>%; background-color: #052c65;"></div>
                            </div>

                            <div class="row g-4">
                                <?php foreach ($enrollmentSteps as $index => $step): ?>
                                    <div class="col-12 col-md-6 col-xl">
                                        <div class="small text-uppercase fw-semibold text-secondary mb-2">Step <?= $index + 1; ?></div>
                                        <div class="fw-semibold fs-5 mb-2"><?= htmlspecialchars($step['title']); ?></div>
                                        <div class="small <?= $step['status_class']; ?>">
                                            <i class="<?= $step['icon']; ?> me-1"></i><?= htmlspecialchars($step['status']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-4">
                    <div class="d-grid gap-3">
                        <a href="student_enrollment.php" class="btn text-white fw-semibold rounded-4 py-3 text-start px-4 d-flex justify-content-between align-items-center" style="background-color: #052c65;">
                            <span><?= htmlspecialchars(getEnrollmentButtonText($applicationStatus)); ?></span>
                            <i class="bi bi-arrow-right fs-4"></i>
                        </a>

                        <div class="card border-0 rounded-4 shadow-sm">
                            <div class="card-body p-4">
                                <div class="small text-uppercase fw-semibold mb-3 text-secondary" style="letter-spacing: 2px;">Application Details</div>
                                <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                                    <span class="text-secondary">Submitted</span>
                                    <span class="fw-semibold"><?= htmlspecialchars($submittedAt); ?></span>
                                </div>
                                <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                                    <span class="text-secondary">Documents</span>
                                    <span class="fw-semibold"><?= $documentsCount; ?> uploaded</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-secondary">Required Docs</span>
                                    <span class="fw-semibold"><?= $requiredDocumentsUploaded; ?>/3</span>
                                </div>
                            </div>
                        </div>

                        <!-- <div class="card border-0 rounded-4 shadow-sm">
                            <div class="card-body p-4">
                                <div class="small text-uppercase fw-semibold mb-3 text-secondary" style="letter-spacing: 2px;">Admissions Office</div>
                                <p class="text-secondary fs-5 mb-4">
                                    Need assistance with your enrollment requirements? Our registrar is available Mon-Fri, 9AM-5PM.
                                </p>
                                <a href="#" class="fw-semibold text-decoration-none" style="color: #111827;"><i class="bi bi-telephone-fill me-2"></i>Connect with Counselor</a>
                            </div>
                        </div> -->
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-12 col-md-4">
                    <div class="card border-0 rounded-4 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="rounded-4 d-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px; background-color: #eff6ff;">
                                <i class="bi bi-person-fill fs-4" style="color: #0b1f5f;"></i>
                            </div>
                            <h3 class="fw-bold mb-3">Profile</h3>
                            <p class="text-secondary mb-3">Manage your student information and keep your contact details updated.</p>
                            <a href="#" class="text-decoration-none fw-semibold" style="color: #0b1f5f;">Manage Profile <i class="bi bi-chevron-right small"></i></a>
                        </div>
                    </div>
                </div>

                <!-- <div class="col-12 col-md-4">
                    <div class="card border-0 rounded-4 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="rounded-4 d-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px; background-color: #eff6ff;">
                                <i class="bi bi-file-earmark-text-fill fs-4" style="color: #0b1f5f;"></i>
                            </div>
                            <h3 class="fw-bold mb-3">Documents</h3>
                            <p class="text-secondary mb-3">You have uploaded <?= $documentsCount; ?> document<?= $documentsCount === 1 ? '' : 's'; ?> for your current application.</p>
                            <a href="student_enrollment.php" class="text-decoration-none fw-semibold" style="color: #0b1f5f;">View Requirements <i class="bi bi-chevron-right small"></i></a>
                        </div>
                    </div>
                </div> -->

                <div class="col-12 col-md-4">
                    <div class="card border-0 rounded-4 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="rounded-4 d-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px; background-color: #eff6ff;">
                                <i class="bi bi-clipboard-check-fill fs-4" style="color: #0b1f5f;"></i>
                            </div>
                            <h3 class="fw-bold mb-3">Application Status</h3>
                            <p class="text-secondary mb-3"><?= htmlspecialchars(getStatusDescription($applicationStatus)); ?></p>
                            <a href="student_enrollment.php" class="text-decoration-none fw-semibold" style="color: #0b1f5f;">View Enrollment Page <i class="bi bi-chevron-right small"></i></a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 rounded-4 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 p-4 pb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="fw-bold text-uppercase" style="letter-spacing: 3px; color: #111827;">Recent Notifications</div>
                </div>

                <div class="card-body p-4 pt-0">
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="d-flex justify-content-between gap-3 py-3 border-bottom">
                                <div class="d-flex gap-3">
                                    <div class="pt-1">
                                        <span class="rounded-circle d-inline-block" style="width: 10px; height: 10px; background-color: #052c65;"></span>
                                    </div>
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($notification['title']); ?></div>
                                        <div class="text-secondary"><?= htmlspecialchars($notification['description']); ?></div>
                                    </div>
                                </div>
                                <div class="small text-secondary text-nowrap"><?= htmlspecialchars($notification['time']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-secondary">
                            <i class="bi bi-bell-slash fs-1 d-block mb-3"></i>
                            No notifications yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'student page/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
