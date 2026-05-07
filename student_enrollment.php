<?php
session_start();

$currentStudentPage = 'enrollment';

$studentData = [
    'student_name' => 'John Doe',
    'student_initials' => 'JD',
    'class_year' => 'Class of 2024'
];


// replace this in the future: enrollment_applications.application_status
if (!isset($_SESSION['mock_application_status'])) {
    $_SESSION['mock_application_status'] = 'application_form_pending';
}
// $_SESSION['mock_application_status'] = 'application_form_pending';
// uncomment the line above and refresh page to reset the enrollment process back to the beginning ^^^
$applicationStatus = $_SESSION['mock_application_status'];

function getEnrollmentStepTitle(string $status): string
{
    return match ($status) {
        'application_form_pending' => 'Application Form',
        'documents_pending' => 'Upload Documents',
        'documents_submitted' => 'Documents Submitted',
        'under_review' => 'Under Review',
        'resubmission_required' => 'Resubmission Required',
        'approved', 'confirmation_pending' => 'Confirm Enrollment',
        'payment_pending' => 'Payment of Fees',
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
        'documents_pending' => 'Your application form is complete. Please proceed to uploading your required documents.',
        'documents_submitted' => 'Your documents have been submitted and are waiting for checking.',
        'under_review' => 'Your application and documents are currently being reviewed by the registrar.',
        'resubmission_required' => 'Some submitted documents need to be corrected or uploaded again.',
        'approved', 'confirmation_pending' => 'Your application has been approved. Please confirm your enrollment.',
        'payment_pending' => 'Your enrollment confirmation has been received. Please proceed to payment.',
        'enrolled' => 'You have been enrolled in the system.',
        'fully_enrolled' => 'Your enrollment process is fully complete.',
        'rejected' => 'Your application was not approved.',
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
        'enrolled', 'fully_enrolled' => 'bi-patch-check-fill',
        'rejected' => 'bi-x-circle-fill',
        default => 'bi-info-circle-fill'
    };

    $buttonText = match ($status) {
        'documents_pending' => 'Proceed to Upload Documents',
        'resubmission_required' => 'Upload Corrected Documents',
        'approved', 'confirmation_pending' => 'Confirm Enrollment',
        'payment_pending' => 'Proceed to Payment',
        default => 'Back to Dashboard'
    };

    $buttonLink = match ($status) {
        'documents_pending', 'resubmission_required' => 'student_enrollment.php',
        'approved', 'confirmation_pending' => '#',
        'payment_pending' => '#',
        default => 'student_dashboard.php'
    };
    ?>

    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5 text-center">
            <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4"
                 style="width: 80px; height: 80px; background-color: #eff6ff;">
                <i class="bi <?= $icon; ?> fs-1" style="color: #0b1f5f;"></i>
            </div>

            <p class="text-uppercase fw-bold mb-2" style="letter-spacing: 3px; color: #64748b;">
                Current Step
            </p>

            <h2 class="fw-bold mb-3" style="color: #0b1f5f;">
                <?= htmlspecialchars($title); ?>
            </h2>

            <p class="fs-5 text-secondary mx-auto mb-4" style="max-width: 720px;">
                <?= htmlspecialchars($description); ?>
            </p>

            <a href="<?= htmlspecialchars($buttonLink); ?>"
               class="btn text-white rounded-3 px-4 py-3 fw-semibold"
               style="background-color: #052c65;">
                <?= htmlspecialchars($buttonText); ?>
                <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>
    </section>

    <?php
}
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

        <main class="col-12 col-lg-10 col-xl-10 ms-auto px-3 px-md-4 px-lg-5 py-4"
              style="min-height: calc(100vh - 140px);">

            <div class="mb-4">
                <p class="text-uppercase fw-bold mb-2" style="letter-spacing: 3px; color: #334155;">
                    Admissions Process
                </p>

                <h1 class="fw-bold mb-3" style="color: #0b1f5f; font-size: clamp(2.2rem, 4vw, 4rem);">
                    Student Enrollment
                </h1>

                <p class="fs-5 text-secondary mb-0" style="max-width: 850px;">
                    Follow your enrollment progress and complete the next required step.
                </p>
            </div>

            <!-- only 2 enrollment pages so far which are application form and documents pages -->
            <?php
            if ($applicationStatus === 'application_form_pending') {
                include __DIR__ . '/student page/enrollment process/student_appform.php';
            } elseif ($applicationStatus === 'documents_pending' || $applicationStatus === 'resubmission_required') {
                include __DIR__ . '/student page/enrollment process/upload_documents.php';
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