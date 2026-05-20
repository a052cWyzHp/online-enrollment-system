<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['role'] ?? '') == 'student') {
    header('Location: student_dashboard.php');
    exit;
}

$currentAdminPage = 'applications';
$basePath = '';

require_once 'config.php';

if (!isset($pdo) && isset($conn) && $conn instanceof PDO) {
    $pdo = $conn;
}


class ApplicationManager {
    private PDO $conn;

    public function __construct(PDO $pdo) {
        $this->conn = $pdo;
    }

    public function getPrograms(): array {
        $query = "
            SELECT
                program_id,
                program_code,
                program_name
            FROM programs
            WHERE is_active = 1
            ORDER BY program_name ASC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getApplicationStatuses(): array {
        return [
            'application_form_pending',
            'documents_pending',
            'documents_submitted',
            'under_review',
            'resubmission_required',
            'approved',
            'confirmation_pending',
            'payment_pending',
            'payment_submitted',
            'enrolled',
            'fully_enrolled',
            'rejected'
        ];
    }

    private function buildWhere(string $search, string $programId, string $status, array &$params): string {
        $where = [
            'u.full_name LIKE :search'
        ];

        $params = [
            ':search' => '%' . $search . '%'
        ];

        if($programId !== '') {
            $where[] = 'ea.program_id = :program_id';
            $params[':program_id'] = (int) $programId;
        }

        if($status !== '') {
            $where[] = 'ea.application_status = :status';
            $params[':status'] = $status;
        }

        return implode(' AND ', $where);
    }

    public function countApplications(string $search, string $programId, string $status): int {
        $params = [];
        $whereSql = $this->buildWhere($search, $programId, $status, $params);

        $query = "
            SELECT COUNT(*)
            FROM enrollment_applications ea
            INNER JOIN student_profiles sp ON ea.student_id = sp.student_id
            INNER JOIN users u ON sp.user_id = u.user_id
            INNER JOIN programs p ON ea.program_id = p.program_id
            WHERE $whereSql
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function getAllApplications(string $search, string $sortBy, string $sortOrder, string $programId, string $status, int $limit, int $offset): array {
        $allowedSort = [
            'application_id' => 'ea.application_id',
            'name' => 'u.full_name',
            'program' => 'p.program_name',
            'status' => 'ea.application_status',
            'date_submitted' => 'ea.submitted_at'
        ];

        $sortColumn = $allowedSort[$sortBy] ?? 'ea.application_id';
        $orderDirection = strtolower($sortOrder) === 'asc' ? 'ASC' : 'DESC';

        $params = [];
        $whereSql = $this->buildWhere($search, $programId, $status, $params);

        $query = "
            SELECT
                ea.application_id,
                ea.student_id,
                ea.program_id,
                ea.school_year,
                ea.previous_school,
                ea.previous_school_address,
                ea.year_graduated,
                ea.entry_type,
                ea.application_status,
                ea.submitted_at,
                ea.updated_at,
                sp.student_number,
                sp.birth_date,
                sp.gender,
                sp.phone,
                sp.nationality,
                sp.street_address,
                sp.city,
                sp.province,
                sp.zip_code,
                sp.guardian_name,
                sp.guardian_relationship,
                sp.guardian_phone,
                u.full_name,
                u.email,
                p.program_name,
                p.program_code
            FROM enrollment_applications ea
            INNER JOIN student_profiles sp ON ea.student_id = sp.student_id
            INNER JOIN users u ON sp.user_id = u.user_id
            INNER JOIN programs p ON ea.program_id = p.program_id
            WHERE $whereSql
            ORDER BY $sortColumn $orderDirection
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->conn->prepare($query);

        foreach($params as $key => $value) {
            if($key === ':program_id') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDocumentsByApplication(int $applicationId): array {
        $query = "
            SELECT
                ud.document_id,
                ud.file_name,
                ud.file_path,
                ud.uploaded_at,
                dt.document_name,
                dt.is_required
            FROM uploaded_documents ud
            INNER JOIN document_types dt ON ud.document_type_id = dt.document_type_id
            WHERE ud.application_id = :application_id
            ORDER BY dt.is_required DESC, dt.document_name ASC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':application_id' => $applicationId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPaymentByApplication(int $applicationId): ?array {
        $query = "
            SELECT
                payment_id,
                amount,
                payment_status,
                proof_of_payment,
                submitted_at,
                verified_at
            FROM payments
            WHERE application_id = :application_id
            ORDER BY created_at DESC
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':application_id' => $applicationId
        ]);

        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        return $payment ?: null;
    }

    public function updateStatus(int $applicationId, string $action, int $adminUserId): string {
        $this->conn->beginTransaction();

        try {
            $query = "
                SELECT
                    ea.application_id,
                    ea.program_id,
                    ea.application_status,
                    u.full_name
                FROM enrollment_applications ea
                INNER JOIN student_profiles sp ON ea.student_id = sp.student_id
                INNER JOIN users u ON sp.user_id = u.user_id
                WHERE ea.application_id = :application_id
                LIMIT 1
                FOR UPDATE
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':application_id' => $applicationId
            ]);

            $application = $stmt->fetch(PDO::FETCH_ASSOC);

            if(!$application) {
                throw new Exception('Application was not found.');
            }

            $oldStatus = $application['application_status'];
            $newStatus = $this->getAllowedNextStatus($oldStatus, $action);

            if(!$newStatus) {
                throw new Exception('This action is not available for the current application status.');
            }

            if($newStatus === 'fully_enrolled') {
                $slot = $this->conn->prepare("
                    UPDATE programs
                    SET slots_available = slots_available - 1
                    WHERE program_id = :program_id
                    AND slots_available > 0
                ");

                $slot->execute([
                    ':program_id' => $application['program_id']
                ]);

                if($slot->rowCount() === 0) {
                    throw new Exception('No slots are available for this program.');
                }

                $paymentUpdate = $this->conn->prepare("
                    UPDATE payments
                    SET payment_status = 'verified',
                        verified_at = NOW()
                    WHERE application_id = :application_id
                    ORDER BY created_at DESC
                    LIMIT 1
                ");

                $paymentUpdate->execute([
                    ':application_id' => $applicationId
                ]);
            }

            if($action === 'request_payment_reupload') {
                $paymentUpdate = $this->conn->prepare("
                    UPDATE payments
                    SET payment_status = 'rejected',
                        review_notes = 'Admin requested reupload of proof of payment.'
                    WHERE application_id = :application_id
                    ORDER BY created_at DESC
                    LIMIT 1
                ");

                $paymentUpdate->execute([
                    ':application_id' => $applicationId
                ]);
            }

            $update = $this->conn->prepare("
                UPDATE enrollment_applications
                SET application_status = :new_status
                WHERE application_id = :application_id
            ");

            $update->execute([
                ':new_status' => $newStatus,
                ':application_id' => $applicationId
            ]);

            $reviewDecision = $this->getReviewDecision($action, $newStatus);

            $review = $this->conn->prepare("
                INSERT INTO application_reviews (
                    application_id,
                    admin_user_id,
                    remarks,
                    decision
                ) VALUES (
                    :application_id,
                    :admin_user_id,
                    :remarks,
                    :decision
                )
            ");

            $review->execute([
                ':application_id' => $applicationId,
                ':admin_user_id' => $adminUserId,
                ':remarks' => 'Admin updated the application from ' . $oldStatus . ' to ' . $newStatus . '.',
                ':decision' => $reviewDecision
            ]);

            $history = $this->conn->prepare("
                INSERT INTO status_history (
                    application_id,
                    old_status,
                    new_status,
                    changed_by,
                    change_notes
                ) VALUES (
                    :application_id,
                    :old_status,
                    :new_status,
                    :changed_by,
                    :change_notes
                )
            ");

            $history->execute([
                ':application_id' => $applicationId,
                ':old_status' => $oldStatus,
                ':new_status' => $newStatus,
                ':changed_by' => $adminUserId,
                ':change_notes' => $this->getActionNote($action)
            ]);

            $log = $this->conn->prepare("
                INSERT INTO admin_logs (
                    user_id,
                    action_type,
                    target_table,
                    target_id,
                    description,
                    ip_address
                ) VALUES (
                    :user_id,
                    :action_type,
                    'enrollment_applications',
                    :target_id,
                    :description,
                    :ip_address
                )
            ");

            $log->execute([
                ':user_id' => $adminUserId,
                ':action_type' => 'application_status_update',
                ':target_id' => $applicationId,
                ':description' => 'Updated application of ' . $application['full_name'] . ' from ' . $oldStatus . ' to ' . $newStatus . '.',
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $this->conn->commit();

            return 'Application status updated successfully.';

        } catch(Exception $e) {
            if($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return $e->getMessage();
        }
    }

    private function getAllowedNextStatus(string $oldStatus, string $action): ?string {
        if(in_array($oldStatus, ['documents_submitted', 'under_review'])) {
            if($action === 'approve_documents') {
                return 'payment_pending';
            }

            if($action === 'reject_application') {
                return 'rejected';
            }

            if($action === 'request_resubmission') {
                return 'resubmission_required';
            }
        }

        if($oldStatus === 'payment_submitted') {
            if($action === 'approve_payment') {
                return 'fully_enrolled';
            }

            if($action === 'request_payment_reupload') {
                return 'payment_pending';
            }
        }

        return null;
    }

    private function getReviewDecision(string $action, string $newStatus): string {
        if($action === 'reject_application') {
            return 'rejected';
        }

        if($action === 'request_resubmission' || $action === 'request_payment_reupload') {
            return 'resubmit';
        }

        if($newStatus === 'payment_pending') {
            return 'pending';
        }

        return 'approved';
    }

    private function getActionNote(string $action): string {
        return match($action) {
            'approve_documents' => 'Admin accepted submitted documents and moved application to payment step.',
            'reject_application' => 'Admin rejected the application.',
            'request_resubmission' => 'Admin requested document resubmission.',
            'approve_payment' => 'Admin approved payment and finalized enrollment.',
            'request_payment_reupload' => 'Admin requested payment proof reupload.',
            default => 'Admin changed application status.'
        };
    }
}

function formatStatusLabel(string $status): string {
    return ucwords(str_replace('_', ' ', $status));
}

function safeDate(?string $date): string {
    return !empty($date) ? date('M d, Y', strtotime($date)) : 'Not submitted';
}

function safeDateTime(?string $date): string {
    return !empty($date) ? date('M d, Y h:i A', strtotime($date)) : 'Not submitted';
}

function initialsFromName(string $name): string {
    $parts = preg_split('/\s+/', trim($name));

    if(count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }

    return strtoupper(substr($name, 0, 1));
}

function statusBadgeClass(string $status): string {
    return match($status) {
        'fully_enrolled', 'enrolled' => 'bg-success-subtle text-success',
        'payment_pending', 'payment_submitted', 'under_review', 'documents_submitted' => 'bg-warning-subtle text-warning',
        'resubmission_required' => 'bg-info-subtle text-info',
        'rejected' => 'bg-danger-subtle text-danger',
        default => 'bg-secondary-subtle text-secondary'
    };
}

function getApplicationActions(string $status): array {
    if(in_array($status, ['documents_submitted', 'under_review'])) {
        return [
            [
                'action' => 'approve_documents',
                'label' => 'Accept Documents',
                'class' => 'btn-success',
                'icon' => 'bi-check-lg',
                'confirm' => 'accept the submitted documents and move this applicant to the payment step'
            ],
            [
                'action' => 'request_resubmission',
                'label' => 'Request Resubmission',
                'class' => 'btn-warning',
                'icon' => 'bi-arrow-repeat',
                'confirm' => 'request the applicant to resubmit their documents'
            ],
            [
                'action' => 'reject_application',
                'label' => 'Reject Application',
                'class' => 'btn-danger',
                'icon' => 'bi-x-lg',
                'confirm' => 'reject this application'
            ]
        ];
    }

    if($status === 'payment_submitted') {
        return [
            [
                'action' => 'approve_payment',
                'label' => 'Approve Payment',
                'class' => 'btn-success',
                'icon' => 'bi-check-lg',
                'confirm' => 'approve this payment and mark the student as fully enrolled'
            ],
            [
                'action' => 'request_payment_reupload',
                'label' => 'Request Payment Reupload',
                'class' => 'btn-warning',
                'icon' => 'bi-arrow-repeat',
                'confirm' => 'request the student to upload a new proof of payment'
            ]
        ];
    }

    return [];
}

function pageLink(int $pageNumber): string {
    $query = $_GET;
    $query['page'] = $pageNumber;

    return '?' . http_build_query($query);
}

$manager = new ApplicationManager($pdo);

$message = $_SESSION['admin_application_message'] ?? '';
unset($_SESSION['admin_application_message']);

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_action'], $_POST['application_id'])) {
    $applicationId = (int) $_POST['application_id'];
    $action = $_POST['application_action'];
    $adminUserId = (int) $_SESSION['user_id'];

    $_SESSION['admin_application_message'] = $manager->updateStatus($applicationId, $action, $adminUserId);

    header('Location: admin_applications.php?' . http_build_query($_GET));
    exit;
}

$search = trim($_GET['search'] ?? '');
$sortBy = $_GET['sort_by'] ?? 'application_id';
$sortOrder = $_GET['sort_order'] ?? 'desc';
$programId = $_GET['program_id'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 2;
$offset = ($page - 1) * $limit;

$programs = $manager->getPrograms();
$statuses = $manager->getApplicationStatuses();
$totalApplications = $manager->countApplications($search, $programId, $statusFilter);
$totalPages = max(1, (int) ceil($totalApplications / $limit));

if($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

$applications = $manager->getAllApplications($search, $sortBy, $sortOrder, $programId, $statusFilter, $limit, $offset);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>Applications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>

<body class="bg-light-subtle">

<!-- NAVBAR -->
<?php include 'admin page/navbar.php'; ?>

<div class="container-fluid">
    <div class="row g-0">

        <!-- SIDEBAR -->
        <?php include 'admin page/sidebar.php'; ?>

        <!-- MAIN -->
        <main class="col-12 col-lg-10 col-xl-10 ms-auto px-3 px-md-4 py-4"
              style="min-height: calc(100vh - 120px);">

            <!-- HEADER -->
            <div class="d-flex flex-column flex-md-row
                        justify-content-between
                        align-items-md-center
                        gap-3
                        mb-4">
                <div>
                    <h1 class="fw-bold mb-1"
                        style="color:#0f172a;">
                        Enrollment Applications
                    </h1>
                    <p class="text-secondary mb-0">
                        Review submitted applications, uploaded documents, and payment proofs.
                    </p>
                </div>

                <a href="admin_applications.php" class="btn btn-primary rounded-pill px-4">
                    <i class="bi bi-arrow-clockwise me-2"></i>
                    Refresh
                </a>
            </div>

            <?php if($message !== ''): ?>
                <div class="alert alert-info border-0 rounded-4 shadow-sm">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- FILTERS -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <form method="get" class="row g-3 align-items-end">
                        <div class="col-12 col-lg-3">
                            <label class="form-label small fw-bold text-uppercase">Search Applicant Name</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" class="form-control rounded-3" placeholder="Search by name">
                        </div>

                        <div class="col-12 col-sm-6 col-lg-2">
                            <label class="form-label small fw-bold text-uppercase">Sort By</label>
                            <select name="sort_by" class="form-select rounded-3">
                                <option value="application_id" <?= $sortBy === 'application_id' ? 'selected' : ''; ?>>Application ID</option>
                                <option value="name" <?= $sortBy === 'name' ? 'selected' : ''; ?>>Name</option>
                                <option value="program" <?= $sortBy === 'program' ? 'selected' : ''; ?>>Program</option>
                                <option value="status" <?= $sortBy === 'status' ? 'selected' : ''; ?>>Status</option>
                                <option value="date_submitted" <?= $sortBy === 'date_submitted' ? 'selected' : ''; ?>>Date Submitted</option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-lg-2">
                            <label class="form-label small fw-bold text-uppercase">Order</label>
                            <select name="sort_order" class="form-select rounded-3">
                                <option value="desc" <?= $sortOrder === 'desc' ? 'selected' : ''; ?>>Descending</option>
                                <option value="asc" <?= $sortOrder === 'asc' ? 'selected' : ''; ?>>Ascending</option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-lg-2">
                            <label class="form-label small fw-bold text-uppercase">Program</label>
                            <select name="program_id" class="form-select rounded-3">
                                <option value="">All Programs</option>
                                <?php foreach($programs as $program): ?>
                                    <option value="<?= htmlspecialchars($program['program_id']); ?>" <?= (string) $programId === (string) $program['program_id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($program['program_code'] . ' - ' . $program['program_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-lg-2">
                            <label class="form-label small fw-bold text-uppercase">Application Status</label>
                            <select name="status" class="form-select rounded-3">
                                <option value="">All Statuses</option>
                                <?php foreach($statuses as $status): ?>
                                    <option value="<?= htmlspecialchars($status); ?>" <?= $statusFilter === $status ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars(formatStatusLabel($status)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-lg-1 d-grid">
                            <button type="submit" class="btn btn-primary rounded-3">
                                Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- CARD -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-lg-row
                                justify-content-between
                                align-items-lg-center
                                gap-3
                                mb-4">
                        <div>
                            <h4 class="fw-bold mb-1">
                                Application Records
                            </h4>
                            <p class="text-secondary mb-0">
                                Showing <?= count($applications); ?> of <?= $totalApplications; ?> submitted enrollment requests.
                            </p>
                        </div>
                    </div>

                    <!-- DESKTOP TABLE -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table align-middle">
                            <thead>
                                <tr class="text-uppercase small text-secondary">
                                    <th class="border-0">Application ID</th>
                                    <th class="border-0">Applicant</th>
                                    <th class="border-0">Program</th>
                                    <th class="border-0">Status</th>
                                    <th class="border-0">Date Submitted</th>
                                    <th class="border-0 text-end"></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if(count($applications) > 0): ?>
                                <?php foreach($applications as $row): ?>
                                    <tr>
                                        <!-- ID -->
                                        <td>
                                            <span class="fw-semibold">
                                                #<?= htmlspecialchars($row['application_id']); ?>
                                            </span>
                                        </td>

                                        <!-- APPLICANT -->
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="rounded-circle
                                                            d-flex
                                                            align-items-center
                                                            justify-content-center
                                                            text-white fw-bold"
                                                     style="width:45px; height:45px; background:#0b1f5f;">
                                                    <?= htmlspecialchars(initialsFromName($row['full_name'])); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold">
                                                        <?= htmlspecialchars($row['full_name']); ?>
                                                    </div>
                                                    <div class="small text-secondary">
                                                        <?= htmlspecialchars($row['email']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- PROGRAM -->
                                        <td>
                                            <span class="fw-semibold">
                                                <?= htmlspecialchars($row['program_code']); ?>
                                            </span>
                                            <div class="small text-secondary">
                                                <?= htmlspecialchars($row['program_name']); ?>
                                            </div>
                                        </td>

                                        <!-- STATUS -->
                                        <td>
                                            <span class="badge rounded-pill px-3 py-2 <?= statusBadgeClass($row['application_status']); ?>">
                                                <?= htmlspecialchars(formatStatusLabel($row['application_status'])); ?>
                                            </span>
                                        </td>

                                        <!-- DATE -->
                                        <td>
                                            <span class="text-secondary small">
                                                <?= htmlspecialchars(safeDate($row['submitted_at'])); ?>
                                            </span>
                                        </td>

                                        <!-- VIEW -->
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-light border"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#applicationModal<?= htmlspecialchars($row['application_id']); ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6"
                                        class="text-center py-5">
                                        <div class="mb-2">
                                            <i class="bi bi-folder-x fs-1 text-secondary"></i>
                                        </div>
                                        <div class="text-secondary">
                                            No applications found.
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- MOBILE CARDS -->
                    <div class="d-md-none">
                        <?php if(count($applications) > 0): ?>
                            <?php foreach($applications as $row): ?>
                                <div class="border rounded-4 p-3 mb-3 bg-white">
                                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                        <div>
                                            <div class="fw-bold">
                                                #<?= htmlspecialchars($row['application_id']); ?>
                                            </div>
                                            <div class="fw-semibold">
                                                <?= htmlspecialchars($row['full_name']); ?>
                                            </div>
                                            <div class="small text-secondary">
                                                <?= htmlspecialchars($row['program_code'] . ' - ' . $row['program_name']); ?>
                                            </div>
                                        </div>

                                        <button class="btn btn-sm btn-light border"
                                                data-bs-toggle="modal"
                                                data-bs-target="#applicationModal<?= htmlspecialchars($row['application_id']); ?>">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <span class="badge rounded-pill px-3 py-2 <?= statusBadgeClass($row['application_status']); ?>">
                                            <?= htmlspecialchars(formatStatusLabel($row['application_status'])); ?>
                                        </span>
                                        <span class="small text-secondary">
                                            Submitted: <?= htmlspecialchars(safeDate($row['submitted_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5 text-secondary">
                                <i class="bi bi-folder-x fs-1 d-block mb-2"></i>
                                No applications found.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- PAGINATION -->
                    <?php if($totalPages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center flex-wrap">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?= htmlspecialchars(pageLink($page - 1)); ?>">Previous</a>
                                </li>

                                <?php for($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= $page === $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?= htmlspecialchars(pageLink($i)); ?>"><?= $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?= htmlspecialchars(pageLink($page + 1)); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- MODALS -->
<?php foreach($applications as $row): ?>
    <?php
        $documents = $manager->getDocumentsByApplication((int) $row['application_id']);
        $payment = $manager->getPaymentByApplication((int) $row['application_id']);
        $actions = getApplicationActions($row['application_status']);
    ?>

    <div class="modal fade" id="applicationModal<?= htmlspecialchars($row['application_id']); ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header border-0 p-4">
                    <div>
                        <h5 class="modal-title fw-bold" style="color:#0f172a;">
                            Application #<?= htmlspecialchars($row['application_id']); ?>
                        </h5>
                        <p class="text-secondary mb-0">
                            <?= htmlspecialchars($row['full_name']); ?> • <?= htmlspecialchars(formatStatusLabel($row['application_status'])); ?>
                        </p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4 pt-0">
                    <div class="row g-4">
                        <div class="col-12 col-lg-6">
                            <div class="border rounded-4 p-4 h-100">
                                <h6 class="fw-bold text-uppercase mb-3">Personal Information</h6>
                                <p><span class="fw-semibold">Full Name:</span> <?= htmlspecialchars($row['full_name']); ?></p>
                                <p><span class="fw-semibold">Email:</span> <?= htmlspecialchars($row['email']); ?></p>
                                <p><span class="fw-semibold">Birth Date:</span> <?= htmlspecialchars(safeDate($row['birth_date'])); ?></p>
                                <p><span class="fw-semibold">Gender:</span> <?= htmlspecialchars($row['gender'] ?? 'Not provided'); ?></p>
                                <p><span class="fw-semibold">Nationality:</span> <?= htmlspecialchars($row['nationality'] ?? 'Not provided'); ?></p>
                                <p class="mb-0"><span class="fw-semibold">Contact Number:</span> <?= htmlspecialchars($row['phone'] ?? 'Not provided'); ?></p>
                            </div>
                        </div>

                        <div class="col-12 col-lg-6">
                            <div class="border rounded-4 p-4 h-100">
                                <h6 class="fw-bold text-uppercase mb-3">Address Information</h6>
                                <p><span class="fw-semibold">Street Address:</span> <?= htmlspecialchars($row['street_address'] ?? 'Not provided'); ?></p>
                                <p><span class="fw-semibold">City:</span> <?= htmlspecialchars($row['city'] ?? 'Not provided'); ?></p>
                                <p><span class="fw-semibold">Province:</span> <?= htmlspecialchars($row['province'] ?? 'Not provided'); ?></p>
                                <p class="mb-0"><span class="fw-semibold">Zip Code:</span> <?= htmlspecialchars($row['zip_code'] ?? 'Not provided'); ?></p>
                            </div>
                        </div>

                        <div class="col-12 col-lg-6">
                            <div class="border rounded-4 p-4 h-100">
                                <h6 class="fw-bold text-uppercase mb-3">Academic Background</h6>
                                <p><span class="fw-semibold">Last School Attended:</span> <?= htmlspecialchars($row['previous_school'] ?? 'Not provided'); ?></p>
                                <p><span class="fw-semibold">School Address:</span> <?= htmlspecialchars($row['previous_school_address'] ?? 'Not provided'); ?></p>
                                <p><span class="fw-semibold">Year Graduated:</span> <?= htmlspecialchars($row['year_graduated'] ?? 'Not provided'); ?></p>
                                <p><span class="fw-semibold">Entry Type:</span> <?= htmlspecialchars(formatStatusLabel($row['entry_type'] ?? 'Not provided')); ?></p>
                                <p class="mb-0"><span class="fw-semibold">School Year:</span> <?= htmlspecialchars($row['school_year'] ?? 'Not provided'); ?></p>
                            </div>
                        </div>

                        <div class="col-12 col-lg-6">
                            <div class="border rounded-4 p-4 h-100">
                                <h6 class="fw-bold text-uppercase mb-3">Program and Guardian</h6>
                                <p><span class="fw-semibold">Program:</span> <?= htmlspecialchars($row['program_code'] . ' - ' . $row['program_name']); ?></p>
                                <p><span class="fw-semibold">Guardian:</span> <?= htmlspecialchars($row['guardian_name'] ?? 'Not provided'); ?></p>
                                <p><span class="fw-semibold">Relationship:</span> <?= htmlspecialchars($row['guardian_relationship'] ?? 'Not provided'); ?></p>
                                <p class="mb-0"><span class="fw-semibold">Guardian Contact:</span> <?= htmlspecialchars($row['guardian_phone'] ?? 'Not provided'); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6 class="fw-bold text-uppercase mb-3">Uploaded Documents</h6>

                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr class="small text-uppercase text-secondary">
                                        <th>Document Type</th>
                                        <th>File Name</th>
                                        <th>Date Submitted</th>
                                        <th class="text-end">View</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($documents) > 0): ?>
                                        <?php foreach($documents as $document): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($document['document_name']); ?></td>
                                                <td><?= htmlspecialchars($document['file_name']); ?></td>
                                                <td><?= htmlspecialchars(safeDateTime($document['uploaded_at'])); ?></td>
                                                <td class="text-end">
                                                    <a href="<?= htmlspecialchars($document['file_path']); ?>" target="_blank" class="btn btn-sm btn-light border">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-secondary py-4">
                                                No uploaded documents found.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6 class="fw-bold text-uppercase mb-3">Payment Proof</h6>

                        <?php if($payment): ?>
                            <div class="border rounded-4 p-3 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                                <div>
                                    <div class="fw-semibold">Proof of Payment</div>
                                    <div class="small text-secondary">
                                        Status: <?= htmlspecialchars(formatStatusLabel($payment['payment_status'])); ?> • Submitted: <?= htmlspecialchars(safeDateTime($payment['submitted_at'])); ?>
                                    </div>
                                </div>
                                <a href="<?= htmlspecialchars($payment['proof_of_payment']); ?>" target="_blank" class="btn btn-sm btn-light border">
                                    <i class="bi bi-eye me-1"></i>View Proof
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-secondary border rounded-4 p-4">
                                No proof of payment submitted yet.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4">
                        <h6 class="fw-bold text-uppercase mb-3">Admin Actions</h6>

                        <?php if(count($actions) > 0): ?>
                            <div class="d-flex flex-column flex-md-row gap-2">
                                <?php foreach($actions as $action): ?>
                                    <button type="button"
                                            class="btn <?= htmlspecialchars($action['class']); ?> rounded-3 px-4"
                                            data-bs-toggle="modal"
                                            data-bs-target="#confirm<?= htmlspecialchars($row['application_id'] . $action['action']); ?>">
                                        <i class="bi <?= htmlspecialchars($action['icon']); ?> me-2"></i>
                                        <?= htmlspecialchars($action['label']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-secondary border rounded-4 p-4">
                                No admin action is available for this status.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light border rounded-3" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php foreach($actions as $action): ?>
        <div class="modal fade" id="confirm<?= htmlspecialchars($row['application_id'] . $action['action']); ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 rounded-4">
                    <div class="modal-header border-0 p-4">
                        <h5 class="modal-title fw-bold">Please Confirm</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body px-4">
                        Are you sure you want to <?= htmlspecialchars($action['confirm']); ?> for <?= htmlspecialchars($row['full_name']); ?>?
                    </div>

                    <div class="modal-footer border-0 p-4">
                        <button type="button" class="btn btn-light border rounded-3" data-bs-dismiss="modal">Cancel</button>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="application_id" value="<?= htmlspecialchars($row['application_id']); ?>">
                            <input type="hidden" name="application_action" value="<?= htmlspecialchars($action['action']); ?>">
                            <button type="submit" class="btn <?= htmlspecialchars($action['class']); ?> rounded-3">
                                Confirm
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endforeach; ?>

<!-- FOOTER -->
<?php include 'admin page/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
