<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$currentAdminPage = 'dashboard';

$basePath = '';

require_once 'config.php';

if (!isset($pdo) && isset($conn) && $conn instanceof PDO) {
    $pdo = $conn;
}


class AdminDashboard {

    private PDO $conn;

    public function __construct(PDO $pdo) {
        $this->conn = $pdo;
    }

    public function getStats(): array {

        $stats = [
            'total_students' => 0,
            'pending_applications' => 0,
            'approved_this_month' => 0,
            'rejected_applications' => 0,
        ];

        $query = "
            SELECT
                SUM(CASE WHEN application_status IN ('enrolled', 'fully_enrolled') THEN 1 ELSE 0 END) AS total_students,
                SUM(CASE WHEN application_status IN ('documents_pending', 'documents_submitted', 'under_review', 'payment_pending', 'payment_submitted', 'resubmission_required') THEN 1 ELSE 0 END) AS pending_applications,
                SUM(CASE WHEN application_status IN ('payment_pending', 'payment_submitted', 'enrolled', 'fully_enrolled') AND MONTH(updated_at) = MONTH(CURRENT_DATE()) AND YEAR(updated_at) = YEAR(CURRENT_DATE()) THEN 1 ELSE 0 END) AS approved_this_month,
                SUM(CASE WHEN application_status = 'rejected' THEN 1 ELSE 0 END) AS rejected_applications
            FROM enrollment_applications
        ";

        $stmt = $this->conn->prepare($query);

        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $stats['total_students'] = (int) $row['total_students'];
            $stats['pending_applications'] = (int) $row['pending_applications'];
            $stats['approved_this_month'] = (int) $row['approved_this_month'];
            $stats['rejected_applications'] = (int) $row['rejected_applications'];
        }

        return $stats;
    }

    public function getRecentApplications(): PDOStatement {

        $query = "
            SELECT
                ea.application_id,
                ea.application_status,
                ea.submitted_at,
                ea.updated_at,
                u.full_name,
                u.email,
                p.program_name,
                p.program_code
            FROM enrollment_applications ea
            INNER JOIN student_profiles sp ON ea.student_id = sp.student_id
            INNER JOIN users u ON sp.user_id = u.user_id
            INNER JOIN programs p ON ea.program_id = p.program_id
            ORDER BY ea.updated_at DESC
            LIMIT 5
        ";

        $stmt = $this->conn->prepare($query);

        $stmt->execute();

        return $stmt;
    }

    public function getRecentActivities(): PDOStatement {

        $query = "
            SELECT
                sh.new_status,
                sh.change_notes,
                sh.changed_at,
                u.full_name
            FROM status_history sh
            LEFT JOIN users u ON sh.changed_by = u.user_id
            ORDER BY sh.changed_at DESC
            LIMIT 5
        ";

        $stmt = $this->conn->prepare($query);

        $stmt->execute();

        return $stmt;
    }
}

function getStatusBadge(string $status): string {

    $label = ucwords(str_replace('_', ' ', $status));

    if(in_array($status, ['enrolled', 'fully_enrolled', 'payment_pending'])) {
        return '<span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">' . htmlspecialchars($label) . '</span>';
    }

    if(in_array($status, ['rejected'])) {
        return '<span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">' . htmlspecialchars($label) . '</span>';
    }

    if(in_array($status, ['resubmission_required'])) {
        return '<span class="badge rounded-pill bg-warning-subtle text-warning px-3 py-2">' . htmlspecialchars($label) . '</span>';
    }

    return '<span class="badge rounded-pill bg-primary-subtle text-primary px-3 py-2">' . htmlspecialchars($label) . '</span>';
}

$dashboard = new AdminDashboard($pdo);

$stats = $dashboard->getStats();

$recentApplications = $dashboard->getRecentApplications();

$recentActivities = $dashboard->getRecentActivities();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>
<body class="bg-light-subtle">

<?php include $basePath . 'admin page/navbar.php'; ?>

<div class="container-fluid">
    <div class="row g-0">

        <?php include $basePath . 'admin page/sidebar.php'; ?>

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
                                    <span class="badge rounded-pill text-success-emphasis" style="background-color: #dcfce7;">Live</span>
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
                                    <span class="badge rounded-pill text-warning-emphasis" style="background-color: #fef3c7;">Open</span>
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
                                    <span class="badge rounded-pill text-success-emphasis" style="background-color: #dcfce7;">Month</span>
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
                                    <span class="badge rounded-pill text-danger-emphasis" style="background-color: #fee2e2;">Total</span>
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
                                            <?php if($recentApplications->rowCount() > 0): ?>
                                                <?php while($row = $recentApplications->fetch(PDO::FETCH_ASSOC)) : ?>
                                                    <tr>
                                                        <td>
                                                            <div class="fw-semibold"><?= htmlspecialchars($row['full_name']); ?></div>
                                                            <div class="small text-secondary"><?= htmlspecialchars($row['email']); ?></div>
                                                        </td>
                                                        <td>
                                                            <div><?= htmlspecialchars($row['program_name']); ?></div>
                                                            <div class="small text-secondary"><?= htmlspecialchars($row['program_code']); ?></div>
                                                        </td>
                                                        <td class="text-secondary small">
                                                            <?= !empty($row['submitted_at']) ? htmlspecialchars(date('M d, Y', strtotime($row['submitted_at']))) : 'Not submitted'; ?>
                                                        </td>
                                                        <td>
                                                            <?= getStatusBadge($row['application_status']); ?>
                                                        </td>
                                                        <td class="text-end">
                                                            <a href="admin_applications.php?search=<?= urlencode($row['full_name']); ?>" class="btn btn-sm btn-light border">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
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
                                    <small class="text-secondary">Showing latest applications</small>
                                    <a href="admin_applications.php" class="btn btn-sm text-white" style="background-color: #1e3a8a;">Open Applications</a>
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

                                <?php if($recentActivities->rowCount() > 0): ?>
                                    <?php while($activity = $recentActivities->fetch(PDO::FETCH_ASSOC)) : ?>
                                        <div class="d-flex gap-3 mb-4">
                                            <div class="mt-1">
                                                <span class="rounded-circle d-inline-block" style="width: 12px; height: 12px; background-color: #2563eb;"></span>
                                            </div>
                                            <div>
                                                <div class="fw-semibold"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $activity['new_status']))); ?></div>
                                                <div class="text-secondary small">
                                                    <?= htmlspecialchars($activity['change_notes'] ?: 'Status was updated.'); ?>
                                                </div>
                                                <div class="text-secondary small">
                                                    <?= htmlspecialchars(date('M d, Y h:i A', strtotime($activity['changed_at']))); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
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

<?php include $basePath . 'admin page/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
