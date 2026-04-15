<?php
$currentStudentPage = 'dashboard';

$studentData = [
    'student_name' => 'John Doe',
    'student_initials' => 'JD',
    'class_year' => 'Class of 2026',
    'completion_percentage' => 67,
    'application_status' => '',
];

$enrollmentSteps = [];
$notifications = [
];

// enrollmentSteps example in the future:
// $enrollmentSteps = [
//     [ basically each index counts as a step
//         'title' => 'Biographical Info', title of the step
//         'status' => 'Complete', or 'Pending'
//         'status_class' => 'text-success fw-semibold', or 'text-warning fw-semibold'
//         'icon' => 'bi bi-check-circle-fill' or 'bi bi-exclamation-circle-fill' just check on bootstrap icons catalog
//     ]
// ];

// notifications example in the future:
// $notifications = [
//     [
//         'title' => 'Official Transcript Received', - self explanatory
//         'description' => 'University of Science and Arts has successfully transmitted your records.', - basically details
//         'time' => '2H AGO' - must be worked on in the future
//     ]
// ];
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

            <div class="d-block">
                <div class="mb-4">
                    <h1 class="fw-bold mb-2" style="color: #111827; font-size: clamp(2rem, 4vw, 4rem);">
                        Welcome back, <?= htmlspecialchars($studentData['student_name']); ?>.
                    </h1>
                    <p class="mb-0 fs-4 text-secondary">
                        Your academic journey is <?= $studentData['completion_percentage']; ?>% complete. Review your next steps below.
                    </p>
                </div>

                <!-- progress component -->
                <div class="row g-4 mb-4">
                    <div class="col-12 col-xl-8">
                        <div class="card border-0 rounded-4 shadow-sm h-100">
                            <div class="card-body p-4 p-lg-5">
                                <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
                                    <h2 class="fw-bold mb-0" style="color: #1f2937;">Fall 2024 Enrollment</h2>
                                    <div class="text-end">
                                        <div class="text-secondary text-uppercase small fw-semibold mb-1">Progress</div>
                                        <!-- percentage -->
                                        <div class="fw-bold" style="font-size: 3rem; color: #0b1f5f;">
                                            <?= $studentData['completion_percentage']; ?>%
                                        </div>
                                    </div>
                                </div>

                                <!-- application status section -->
                                <div class="mb-3 text-uppercase fw-medium" style="letter-spacing: 2px; color: #374151;">
                                    Application Status:
                                    <span class="fw-semibold">
                                        <?= !empty($studentData['application_status']) ? htmlspecialchars($studentData['application_status']) : 'No status yet'; ?>
                                    </span>
                                </div>

                                <!-- progress bar -->
                                <div class="progress mb-5" role="progressbar" aria-valuenow="<?= $studentData['completion_percentage']; ?>" aria-valuemin="0" aria-valuemax="100" style="height: 12x;">
                                    <div class="progress-bar rounded-pill progress-bar-striped progress-bar-animated" style="width: <?= $studentData['completion_percentage']; ?>%; background-color: #052c65;"></div>
                                </div>

                                <!-- steps section -->
                                <div class="row g-4">
                                    <?php if (!empty($enrollmentSteps)): ?>
                                        <?php foreach ($enrollmentSteps as $index => $step): ?>
                                            <div class="col-12 col-md-4">
                                                <div class="small text-uppercase fw-semibold text-secondary mb-2">Step <?= $index + 1; ?></div>
                                                <div class="fw-semibold fs-5 mb-2"><?= htmlspecialchars($step['title']); ?></div>
                                                <div class="small <?= $step['status_class']; ?>">
                                                    <i class="<?= $step['icon']; ?> me-1"></i><?= htmlspecialchars($step['status']); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-12 col-md-4">
                                            <div class="small text-uppercase fw-semibold text-secondary mb-2">Step 1</div>
                                            <div class="fw-semibold fs-5 mb-2">Biographical Info</div>
                                            <div class="small text-secondary">
                                                <i class="bi bi-dash-circle me-1"></i>No data yet
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="small text-uppercase fw-semibold text-secondary mb-2">Step 2</div>
                                            <div class="fw-semibold fs-5 mb-2">Transcript Upload</div>
                                            <div class="small text-secondary">
                                                <i class="bi bi-dash-circle me-1"></i>No data yet
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="small text-uppercase fw-semibold text-secondary mb-2">Step 3</div>
                                            <div class="fw-semibold fs-5 mb-2">Tuition Deposit</div>
                                            <div class="small text-secondary">
                                                <i class="bi bi-dash-circle me-1"></i>No data yet
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- buttons and connect with counselor section -->
                    <div class="col-12 col-xl-4">
                        <div class="d-grid gap-3">
                            <!-- start enrollment button -->
                            <button class="btn text-white fw-semibold rounded-4 py-3 text-start px-4 d-flex justify-content-between align-items-center" style="background-color: #052c65;">
                                <span>Start Enrollment</span>
                                <i class="bi bi-arrow-right fs-4"></i>
                            </button>
                            
                            <!-- upload documents button -->
                            <button class="btn btn-light fw-semibold rounded-4 py-3 text-start px-4 d-flex justify-content-between align-items-center border">
                                <span>Upload Documents</span>
                                <i class="bi bi-cloud-upload fs-4"></i>
                            </button>
                            
                            <!-- contact admissions card -->
                            <div class="card border-0 rounded-4 shadow-sm">
                                <div class="card-body p-4">
                                    <div class="small text-uppercase fw-semibold mb-3 text-secondary" style="letter-spacing: 2px;">Admissions Office</div>
                                    <p class="text-secondary fs-5 mb-4">
                                        Need assistance with your documents? Our registrar is available Mon-Fri, 9AM-5PM.
                                    </p>
                                    <a href="#" class="fw-semibold text-decoration-none" style="color: #111827;"><i class="bi bi-telephone-fill me-2"></i>Connect with Counselor</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- profile, documents, app status cards -->
                <div class="row g-4 mb-4">
                    <!-- profile card -->
                    <div class="col-12 col-md-4">
                        <div class="card border-0 rounded-4 shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="rounded-4 d-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px; background-color: #eff6ff;">
                                    <i class="bi bi-person-fill fs-4" style="color: #0b1f5f;"></i>
                                </div>
                                <h3 class="fw-bold mb-3">Profile</h3>
                                <p class="text-secondary mb-3">No profile updates yet. Student information will appear here once connected to the database.</p>
                                <a href="#" class="text-decoration-none fw-semibold" style="color: #0b1f5f;">Manage Profile <i class="bi bi-chevron-right small"></i></a>
                            </div>
                        </div>
                    </div>

                    <!-- documents card -->
                    <div class="col-12 col-md-4">
                        <div class="card border-0 rounded-4 shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="rounded-4 d-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px; background-color: #eff6ff;">
                                    <i class="bi bi-file-earmark-text-fill fs-4" style="color: #0b1f5f;"></i>
                                </div>
                                <h3 class="fw-bold mb-3">Documents</h3>
                                <p class="text-secondary mb-3">No document records available yet. Verified files will be shown here in the future.</p>
                                <a href="#" class="text-decoration-none fw-semibold" style="color: #0b1f5f;">View Vault <i class="bi bi-chevron-right small"></i></a>
                            </div>
                        </div>
                    </div>

                    <!-- application status card -->
                    <div class="col-12 col-md-4">
                        <div class="card border-0 rounded-4 shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="rounded-4 d-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px; background-color: #eff6ff;">
                                    <i class="bi bi-clipboard-check-fill fs-4" style="color: #0b1f5f;"></i>
                                </div>
                                <h3 class="fw-bold mb-3">Application Status</h3>
                                <p class="text-secondary mb-3">No application activity yet. Status updates and review history will appear here later.</p>
                                <a href="#" class="text-decoration-none fw-semibold" style="color: #0b1f5f;">Track History <i class="bi bi-chevron-right small"></i></a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- notifications section -->
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

                    <div class="card-footer bg-transparent border-0 text-center py-3">
                        <a href="#" class="text-decoration-none fw-bold text-uppercase" style="letter-spacing: 2px; color: #111827;">View Notification Archive</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'student page/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>