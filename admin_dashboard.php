<?php
$currentAdminPage = 'dashboard';

$stats = [
    'total_students' => 0,
    'pending_applications' => 0,
    'approved_this_month' => 0,
    'rejected_applications' => 0,
];

$recentApplications = [];
$recentActivities = [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light-subtle">

<?php include 'admin page/navbar.php'; ?>

<div class="container-fluid">
    <div class="row g-0">

        <?php include 'admin page/sidebar.php'; ?>

        <main class="col-12 col-lg-10 col-xl-10 ms-auto px-3 px-md-4 px-lg-4 py-4" style="min-height: calc(100vh - 140px);">

            <!-- DESKTOP VIEW -->
            <div class="d-block">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h1 class="fw-bold mb-1" style="color: #0f172a;">Registrations Overview</h1>
                        <p class="mb-0 fs-5 text-secondary">Hello, admin. Here's what's happening with admissions today.</p>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card border-0 rounded-4 shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #dbeafe;">
                                        <i class="bi bi-people-fill fs-4" style="color: #2563eb;"></i>
                                    </div>
                                    <span class="badge rounded-pill text-success-emphasis" style="background-color: #dcfce7;">0%</span>
                                </div>
                                <h2 class="fw-bold mb-1"><?= number_format($stats['total_students']); ?></h2>
                                <p class="mb-0 fs-5 text-secondary">Total Active Students</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card border-0 rounded-4 shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #fef3c7;">
                                        <i class="bi bi-clipboard2-check-fill fs-4" style="color: #d97706;"></i>
                                    </div>
                                    <span class="badge rounded-pill text-warning-emphasis" style="background-color: #fef3c7;">0 Urgent</span>
                                </div>
                                <h2 class="fw-bold mb-1"><?= number_format($stats['pending_applications']); ?></h2>
                                <p class="mb-0 fs-5 text-secondary">Pending Applications</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card border-0 rounded-4 shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #dcfce7;">
                                        <i class="bi bi-shield-check fs-4" style="color: #16a34a;"></i>
                                    </div>
                                    <span class="badge rounded-pill text-success-emphasis" style="background-color: #dcfce7;">0%</span>
                                </div>
                                <h2 class="fw-bold mb-1"><?= number_format($stats['approved_this_month']); ?></h2>
                                <p class="mb-0 fs-5 text-secondary">Approved This Month</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card border-0 rounded-4 shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #fee2e2;">
                                        <i class="bi bi-x-circle-fill fs-4" style="color: #dc2626;"></i>
                                    </div>
                                    <span class="badge rounded-pill text-danger-emphasis" style="background-color: #fee2e2;">0%</span>
                                </div>
                                <h2 class="fw-bold mb-1"><?= number_format($stats['rejected_applications']); ?></h2>
                                <p class="mb-0 fs-5 text-secondary">Rejected Applications</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-12 col-xl-8">
                        <div class="card border-0 rounded-4 shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div>
                                        <h3 class="fw-bold mb-1" style="color: #111827;">Recent Applications</h3>
                                        <p class="mb-0 text-secondary">Managing latest admissions stream</p>
                                    </div>
                                    <button class="btn btn-sm text-primary-emphasis" style="background-color: #eff6ff;">View Full List</button>
                                </div>

                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead>
                                            <tr class="text-secondary small text-uppercase">
                                                <th class="border-0">Applicant</th>
                                                <th class="border-0">Course</th>
                                                <th class="border-0">Applied</th>
                                                <th class="border-0">Status</th>
                                                <th class="border-0 text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($recentApplications)): ?>
                                                <?php foreach ($recentApplications as $application): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($application['name']); ?></td>
                                                        <td><?= htmlspecialchars($application['course']); ?></td>
                                                        <td><?= htmlspecialchars($application['applied_at']); ?></td>
                                                        <td><?= htmlspecialchars($application['status']); ?></td>
                                                        <td class="text-end">
                                                            <button class="btn btn-sm btn-light"><i class="bi bi-three-dots-vertical"></i></button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-5 text-secondary">
                                                        <div class="mb-2">
                                                            <i class="bi bi-inbox fs-1"></i>
                                                        </div>
                                                        No applications available yet.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-between align-items-center pt-2">
                                    <small class="text-secondary">Showing 0 of 0 applicants</small>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i></button>
                                        <button class="btn text-white" style="background-color: #1e3a8a;">1</button>
                                        <button class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-4">
                        <div class="card border-0 rounded-4 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <h3 class="fw-bold mb-4" style="color: #111827;">
                                    <i class="bi bi-clock-history me-2 text-secondary"></i>Recent Activity
                                </h3>

                                <?php if (!empty($recentActivities)): ?>
                                    <?php foreach ($recentActivities as $activity): ?>
                                        <div class="d-flex gap-3 mb-4">
                                            <div class="mt-1">
                                                <span class="rounded-circle d-inline-block" style="width: 12px; height: 12px; background-color: #2563eb;"></span>
                                            </div>
                                            <div>
                                                <div class="fw-semibold"><?= htmlspecialchars($activity['title']); ?></div>
                                                <div class="text-secondary small"><?= htmlspecialchars($activity['description']); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4 text-secondary">
                                        <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
                                        No recent activity yet.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>


    </div>
</div>

<?php include 'admin page/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>