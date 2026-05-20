<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['role'] ?? '') == 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

require_once __DIR__ . '/config.php';
require_once 'utilities/UIFormatter.php'; // this is for format helper and colors based on enrollment status

$currentStudentPage = 'enrollment';
$userId = (int) $_SESSION['user_id'];

function fetchStudent(PDO $pdo, int $userId): ?array
{
    $statement = $pdo->prepare("
                                SELECT sp.student_id, sp.user_id, sp.student_number, u.full_name, u.email
                                FROM student_profiles sp
                                INNER JOIN users u ON sp.user_id = u.user_id
                                WHERE sp.user_id = :user_id
                                LIMIT 1
                            ");
    $statement->execute([':user_id' => $userId]);
    $student = $statement->fetch(PDO::FETCH_ASSOC);

    return $student ?: null;
}

function fetchLatestApplication(PDO $pdo, int $studentId): ?array
{
    $stmt = $pdo->prepare("
                            SELECT ea.*, p.program_code, p.program_name, p.slots_available 
                            FROM enrollment_applications ea 
                            INNER JOIN programs p ON ea.program_id = p.program_id 
                            WHERE ea.student_id = :student_id 
                            ORDER BY ea.updated_at DESC, ea.application_id DESC 
                            LIMIT 1
                        ");
    $stmt->execute([':student_id' => $studentId]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    return $application ?: null;
}

function fetchActivePrograms(PDO $pdo): array
{
    $stmt = $pdo->query("
                            SELECT program_id, program_code, program_name, slots_available 
                            FROM programs WHERE is_active = 1 
                            ORDER BY program_name ASC
                        ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function insertStatusHistory(PDO $pdo, int $applicationId, ?string $oldStatus, string $newStatus, int $changedBy, string $notes): void
{
    $stmt = $pdo->prepare("
                            INSERT INTO status_history (application_id, old_status, new_status, changed_by, change_notes) 
                            VALUES (:application_id, :old_status, :new_status, :changed_by, :change_notes)
                        ");
    $stmt->execute([
        ':application_id' => $applicationId,
        ':old_status' => $oldStatus,
        ':new_status' => $newStatus,
        ':changed_by' => $changedBy,
        ':change_notes' => $notes
    ]);
}

function getUploadedDocumentCounts(PDO $pdo, int $applicationId): array
{
    $stmt = $pdo->prepare("
                            SELECT COUNT(*) AS total_uploaded, SUM(CASE WHEN dt.is_required = 1 THEN 1 ELSE 0 END) AS required_uploaded 
                            FROM uploaded_documents ud 
                            INNER JOIN document_types dt ON ud.document_type_id = dt.document_type_id 
                            WHERE ud.application_id = :application_id
                        ");
    $stmt->execute([':application_id' => $applicationId]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_uploaded' => 0, 'required_uploaded' => 0];

    return [
        'total_uploaded' => (int) ($counts['total_uploaded'] ?? 0),
        'required_uploaded' => (int) ($counts['required_uploaded'] ?? 0)
    ];
}

function getEnrollmentStepTitle(string $status): string
{
    return match ($status) {
        'application_form_pending' => 'Application Form',
        'documents_pending' => 'Upload Documents',
        'documents_submitted' => 'Documents Submitted',
        'under_review' => 'Under Review',
        'resubmission_required' => 'Resubmission Required',
        'approved', 'confirmation_pending' => 'Reservation Payment',
        'payment_pending' => 'Payment of Fees',
        'payment_submitted' => 'Payment Submitted',
        'enrolled' => 'Enrolled',
        'fully_enrolled' => 'Fully Enrolled',
        'rejected' => 'Application Rejected',
        default => 'Enrollment Status'
    };
}

function getEnrollmentStepDescription(string $status): string
{
    return match ($status) {
        'application_form_pending' => 'Complete your application form to begin the enrollment process.',
        'documents_pending' => 'Your application form is complete. Please upload your required documents.',
        'documents_submitted' => 'Your documents have been submitted and are waiting for checking.',
        'under_review' => 'Your application and documents are currently being reviewed by the registrar.',
        'resubmission_required' => 'Some submitted documents need to be corrected or uploaded again.',
        'approved', 'confirmation_pending', 'payment_pending' => 'Your application has been approved. Please submit the reservation payment to confirm your intention to enroll.',
        'payment_submitted' => 'Your proof of payment has been submitted. Please wait while the registrar verifies your payment.',
        'enrolled' => 'You have been enrolled in the system.',
        'fully_enrolled' => 'Your enrollment process is fully complete.',
        'rejected' => 'Your application was not approved. Please contact the registrar for more information.',
        default => 'Please check your enrollment status.'
    };
}

function renderStatusCard(string $status): void
{
    $title = getEnrollmentStepTitle($status);
    $description = getEnrollmentStepDescription($status);

    $icon = match ($status) {
        'documents_pending' => 'bi-cloud-upload-fill',
        'documents_submitted', 'under_review' => 'bi-hourglass-split',
        'resubmission_required' => 'bi-exclamation-triangle-fill',
        'approved', 'confirmation_pending' => 'bi-check-circle-fill',
        'payment_pending' => 'bi-credit-card-fill',
        'payment_submitted' => 'bi-receipt-cutoff',
        'enrolled', 'fully_enrolled' => 'bi-patch-check-fill',
        'rejected' => 'bi-x-circle-fill',
        default => 'bi-info-circle-fill'
    };

    $buttonText = match ($status) {
        'documents_pending' => 'Proceed to Upload Documents',
        'resubmission_required' => 'Upload Corrected Documents',
        'approved', 'confirmation_pending', 'payment_pending' => 'Proceed to Payment',
        default => 'Back to Dashboard'
    };

    $buttonLink = match ($status) {
        'documents_pending', 'resubmission_required', 'approved', 'confirmation_pending', 'payment_pending' => 'student_enrollment.php',
        default => 'student_dashboard.php'
    };
    ?>
    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5 text-center">
            <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4" style="width: 80px; height: 80px; background-color: #eff6ff;">
                <i class="bi <?= $icon; ?> fs-1" style="<?= $status === 'rejected' ? 'color: #da3030' : 'color: #0b1f5f' ?>"></i>
            </div>
            <p class="text-uppercase fw-bold mb-2" style="letter-spacing: 3px; color: #64748b;">Current Step</p>
            <h2 class="fw-bold mb-3" style="<?= $status === 'rejected' ? 'color: #da3030' : 'color: #0b1f5f' ?>"><?= htmlspecialchars($title); ?></h2>
            <p class="fs-5 text-secondary mx-auto mb-4" style="max-width: 720px;"><?= htmlspecialchars($description); ?></p>
            <a href="<?= htmlspecialchars($buttonLink); ?>" class="btn text-white rounded-3 px-4 py-3 fw-semibold" style="background-color: #052c65;">
                <?= htmlspecialchars($buttonText); ?>
                <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>
    </section>
    <?php
}

$student = fetchStudent($pdo, $userId);

if (!$student || !isset($student['student_id'])) {
    die('Student profile was not found for this account. Please contact the administrator.');
}

$studentId = (int) $student['student_id'];

$currentApplication = fetchLatestApplication($pdo, $studentId);
$applicationStatus = $currentApplication['application_status'] ?? 'application_form_pending';
$programs = fetchActivePrograms($pdo);

$studentData = [
    'student_name' => $student['full_name'],
    'student_initials' => UIFormatter::initialsFromName($student['full_name']),
    'class_year' => 'Student Portal'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Enrollment</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light-subtle">

<?php include 'student page/navbar.php'; ?>

<div class="container-fluid">
    <div class="row g-0">
        <?php include 'student page/sidebar.php'; ?>

        <main class="col-12 col-lg-10 col-xl-10 ms-auto px-3 px-md-4 px-lg-5 py-4" style="min-height: calc(100vh - 140px);">
            <div class="mb-4">
                <p class="text-uppercase fw-bold mb-2" style="letter-spacing: 3px; color: #334155;">Admissions Process</p>
                <h1 class="fw-bold mb-3" style="color: #0b1f5f; font-size: clamp(2.2rem, 4vw, 4rem);">Student Enrollment</h1>
                <p class="fs-5 text-secondary mb-0" style="max-width: 850px;">Follow your enrollment progress and complete the next required step.</p>
            </div>

            <?php
            if ($applicationStatus === 'application_form_pending') {
                include __DIR__ . '/student page/enrollment process/student_appform.php';
            } elseif ($applicationStatus === 'documents_pending' || $applicationStatus === 'resubmission_required') {
                include __DIR__ . '/student page/enrollment process/upload_documents.php';
            } elseif ($applicationStatus === 'approved' || $applicationStatus === 'confirmation_pending' || $applicationStatus === 'payment_pending') {
                include __DIR__ . '/student page/enrollment process/payment.php';
            } else {
                renderStatusCard($applicationStatus);
            }
            ?>
        </main>
    </div>
</div>

<?php include 'student page/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
