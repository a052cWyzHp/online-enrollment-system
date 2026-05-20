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

$currentAdminPage = 'students';
$basePath = '';

require_once 'config.php';
require_once 'utilities/UIFormatter.php'; // this is for format helper and colors based on enrollment status

if (!isset($pdo) && isset($conn) && $conn instanceof PDO) {
    $pdo = $conn;
}

class StudentView {
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

    public function getStudentStatuses(): array {
        return [
            'enrolled',
            'fully_enrolled'
        ];
    }

    private function buildWhere(string $search, string $programId, string $status, array &$params): string {
        $where = [
            "ea.application_status IN ('enrolled', 'fully_enrolled')",
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

    public function countStudents(string $search, string $programId, string $status): int {
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

    public function getAllStudents(string $search, string $sortBy, string $sortOrder, string $programId, string $status, int $limit, int $offset): array {
        $allowedSort = [
            'student_id' => 'sp.student_id',
            'student_number' => 'sp.student_number',
            'name' => 'u.full_name',
            'program' => 'p.program_name',
            'status' => 'ea.application_status',
            'date_updated' => 'ea.updated_at'
        ];

        $sortColumn = $allowedSort[$sortBy] ?? 'sp.student_id';
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
}

function pageLink(int $pageNumber): string {
    $query = $_GET;
    $query['page'] = $pageNumber;

    return '?' . http_build_query($query);
}

$search = trim($_GET['search'] ?? '');
$sortBy = $_GET['sort_by'] ?? 'student_id';
$sortOrder = $_GET['sort_order'] ?? 'desc';
$programId = $_GET['program_id'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$student = new StudentView($pdo);
$programs = $student->getPrograms();
$statuses = $student->getStudentStatuses();
$totalStudents = $student->countStudents($search, $programId, $statusFilter);
$totalPages = max(1, (int) ceil($totalStudents / $limit));

if($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

$students = $student->getAllStudents($search, $sortBy, $sortOrder, $programId, $statusFilter, $limit, $offset);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>Student Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>

<body class="bg-light-subtle">

<!-- NAVBAR -->
<?php include $basePath . 'admin page/navbar.php'; ?>

<div class="container-fluid">
    <div class="row g-0">

        <!-- SIDEBAR -->
        <?php include $basePath . 'admin page/sidebar.php'; ?>

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
                        Student Records
                    </h1>
                    <p class="text-secondary mb-0">
                        Manage fully enrolled students and review their submitted details.
                    </p>
                </div>

                <a href="admin_applications.php" class="btn btn-primary rounded-pill px-4">
                    <i class="bi bi-folder-check me-2"></i>
                    View Applicants
                </a>
            </div>

            <!-- FILTERS -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <form method="get" class="row g-3 align-items-end">
                        <div class="col-12 col-lg-3">
                            <label class="form-label small fw-bold text-uppercase">Search Student Name</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" class="form-control rounded-3" placeholder="Search by name">
                        </div>

                        <div class="col-12 col-sm-6 col-lg-2">
                            <label class="form-label small fw-bold text-uppercase">Sort By</label>
                            <select name="sort_by" class="form-select rounded-3">
                                <option value="student_id" <?= $sortBy === 'student_id' ? 'selected' : ''; ?>>Student ID</option>
                                <option value="student_number" <?= $sortBy === 'student_number' ? 'selected' : ''; ?>>Student Number</option>
                                <option value="name" <?= $sortBy === 'name' ? 'selected' : ''; ?>>Name</option>
                                <option value="program" <?= $sortBy === 'program' ? 'selected' : ''; ?>>Program</option>
                                <option value="status" <?= $sortBy === 'status' ? 'selected' : ''; ?>>Status</option>
                                <option value="date_updated" <?= $sortBy === 'date_updated' ? 'selected' : ''; ?>>Date Updated</option>
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
                            <label class="form-label small fw-bold text-uppercase">Student Status</label>
                            <select name="status" class="form-select rounded-3">
                                <option value="">All Statuses</option>
                                <?php foreach($statuses as $status): ?>
                                    <option value="<?= htmlspecialchars($status); ?>" <?= $statusFilter === $status ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars(UIFormatter::formatStatusLabel($status)); ?>
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
                                Student Directory
                            </h4>
                            <p class="text-secondary mb-0">
                                Showing <?= count($students); ?> of <?= $totalStudents; ?> enrolled students.
                            </p>
                        </div>
                    </div>

                    <!-- DESKTOP TABLE -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table align-middle">
                            <thead>
                                <tr class="text-uppercase small text-secondary">
                                    <th class="border-0">Student ID</th>
                                    <th class="border-0">Student</th>
                                    <th class="border-0">Program</th>
                                    <th class="border-0">Status</th>
                                    <th class="border-0">Date Updated</th>
                                    <th class="border-0 text-end"></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if(count($students) > 0): ?>
                                <?php foreach($students as $row): ?>
                                    <tr>
                                        <!-- ID -->
                                        <td>
                                            <span class="fw-semibold">
                                                <?= htmlspecialchars($row['student_number'] ?: '#' . $row['student_id']); ?>
                                            </span>
                                        </td>

                                        <!-- NAME -->
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="rounded-circle
                                                            d-flex
                                                            align-items-center
                                                            justify-content-center
                                                            text-white fw-bold"
                                                     style="width:45px; height:45px; background:#0b1f5f;">
                                                    <?= htmlspecialchars(UIFormatter::initialsFromName($row['full_name'])); ?>
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
                                            <span class="badge rounded-pill px-3 py-2 <?= UIFormatter::statusBadgeClass($row['application_status']); ?>">
                                                <?= htmlspecialchars(UIFormatter::formatStatusLabel($row['application_status'])); ?>
                                            </span>
                                        </td>

                                        <!-- DATE -->
                                        <td>
                                            <span class="text-secondary small">
                                                <?= htmlspecialchars(UIFormatter::safeDate($row['updated_at'])); ?>
                                            </span>
                                        </td>

                                        <!-- VIEW -->
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-light border"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#studentModal<?= htmlspecialchars($row['student_id']); ?>">
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
                                            <i class="bi bi-people fs-1 text-secondary"></i>
                                        </div>
                                        <div class="text-secondary">
                                            No students found.
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- MOBILE CARDS -->
                    <div class="d-md-none">
                        <?php if(count($students) > 0): ?>
                            <?php foreach($students as $row): ?>
                                <div class="border rounded-4 p-3 mb-3 bg-white">
                                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                        <div>
                                            <div class="fw-bold">
                                                <?= htmlspecialchars($row['student_number'] ?: '#' . $row['student_id']); ?>
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
                                                data-bs-target="#studentModal<?= htmlspecialchars($row['student_id']); ?>">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <span class="badge rounded-pill px-3 py-2 <?= UIFormatter::statusBadgeClass($row['application_status']); ?>">
                                            <?= htmlspecialchars(UIFormatter::formatStatusLabel($row['application_status'])); ?>
                                        </span>
                                        <span class="small text-secondary">
                                            Updated: <?= htmlspecialchars(UIFormatter::safeDate($row['updated_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5 text-secondary">
                                <i class="bi bi-people fs-1 d-block mb-2"></i>
                                No students found.
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
<?php foreach($students as $row): ?>
    <?php
        $documents = $student->getDocumentsByApplication((int) $row['application_id']);
        $payment = $student->getPaymentByApplication((int) $row['application_id']);
    ?>

    <div class="modal fade" id="studentModal<?= htmlspecialchars($row['student_id']); ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header border-0 p-4">
                    <div>
                        <h5 class="modal-title fw-bold" style="color:#0f172a;">
                            <?= htmlspecialchars($row['full_name']); ?>
                        </h5>
                        <p class="text-secondary mb-0">
                            <?= htmlspecialchars($row['student_number'] ?: 'No student number'); ?> • <?= htmlspecialchars(UIFormatter::formatStatusLabel($row['application_status'])); ?>
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
                                <p><span class="fw-semibold">Birth Date:</span> <?= htmlspecialchars(UIFormatter::safeDate($row['birth_date'])); ?></p>
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
                                <p><span class="fw-semibold">Entry Type:</span> <?= htmlspecialchars(UIFormatter::formatStatusLabel($row['entry_type'] ?? 'Not provided')); ?></p>
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
                                                <td><?= htmlspecialchars(UIFormatter::safeDateTime($document['uploaded_at'])); ?></td>
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
                                        Status: <?= htmlspecialchars(UIFormatter::formatStatusLabel($payment['payment_status'])); ?> • Submitted: <?= htmlspecialchars(UIFormatter::safeDateTime($payment['submitted_at'])); ?>
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
                </div>

                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light border rounded-3" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- FOOTER -->
<?php include $basePath . 'admin page/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
