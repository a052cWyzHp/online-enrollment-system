<?php
$programId = (int) $selectedProgram['program_id'];
$studentsSearch = trim($_GET['student_search'] ?? '');
$statusFilter = $_GET['student_status'] ?? '';
$sortBy = $_GET['student_sort_by'] ?? 'name';
$order = strtolower($_GET['student_order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10; // how many students records per pagination
$offset = ($page - 1) * $limit;

$allowedSorts = [
    'name' => 'u.full_name',
    'student_number' => 'sp.student_number',
    'email' => 'u.email',
    'status' => 'ea.application_status',
    'updated_at' => 'ea.updated_at'
];

$sortColumn = $allowedSorts[$sortBy] ?? 'u.full_name';

$where = [
    'ea.program_id = :program_id',
    "ea.application_status IN ('enrolled', 'fully_enrolled')"
];

$params = [
    ':program_id' => $programId
];

if ($studentsSearch !== '') {
    $where[] = 'u.full_name LIKE :student_search';
    $params[':student_search'] = '%' . $studentsSearch . '%';
}

if ($statusFilter !== '') {
    $where[] = 'ea.application_status = :student_status';
    $params[':student_status'] = $statusFilter;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$countStatement = $pdo->prepare("\n    SELECT COUNT(*)\n    FROM enrollment_applications ea\n    INNER JOIN student_profiles sp ON ea.student_id = sp.student_id\n    INNER JOIN users u ON sp.user_id = u.user_id\n    $whereSql\n");

$countStatement->execute($params);
$totalStudents = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalStudents / $limit));

$studentsStatement = $pdo->prepare("\n    SELECT\n        ea.application_id,\n        ea.application_status,\n        ea.submitted_at,\n        ea.updated_at,\n        sp.student_id,\n        sp.student_number,\n        sp.phone,\n        u.full_name,\n        u.email\n    FROM enrollment_applications ea\n    INNER JOIN student_profiles sp ON ea.student_id = sp.student_id\n    INNER JOIN users u ON sp.user_id = u.user_id\n    $whereSql\n    ORDER BY $sortColumn $order\n    LIMIT :limit OFFSET :offset\n");

foreach ($params as $key => $value) {
    $studentsStatement->bindValue($key, $value);
}

$studentsStatement->bindValue(':limit', $limit, PDO::PARAM_INT);
$studentsStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$studentsStatement->execute();
$students = $studentsStatement->fetchAll(PDO::FETCH_ASSOC);

$enrolledCount = countEnrolledStudents($pdo, $programId);
$appCount = countProgramApplications($pdo, $programId);

$queryBase = $_GET;
unset($queryBase['page']);
$paginationBase = 'admin_programs.php?' . http_build_query(array_merge($queryBase, ['program_id' => $programId]));
$paginationSeparator = str_contains($paginationBase, '?') ? '&' : '?';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <a href="admin_programs.php" class="text-decoration-none fw-semibold" style="color: #0b1f5f;">
            <i class="bi bi-arrow-left me-2"></i>Back to Program List
        </a>
        <h1 class="fw-bold mb-1 mt-3" style="color: #0f172a;">
            <?= htmlspecialchars($selectedProgram['program_name']); ?>
        </h1>
        <p class="mb-0 fs-5 text-secondary">
            <?= htmlspecialchars($selectedProgram['program_code']); ?> · Program ID #<?= (int) $selectedProgram['program_id']; ?>
        </p>
    </div>

    <div class="d-flex flex-column flex-sm-row gap-2">
        <button class="btn text-white rounded-3 px-4" style="background-color: #052c65;" data-bs-toggle="modal" data-bs-target="#editProgramModal">
            <i class="bi bi-pencil-square me-2"></i>Manage Program
        </button>
    </div>
</div>



<div class="row g-4 mb-4">
    <div class="col">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3">
                    <div>
                        <div class="small text-uppercase fw-bold text-secondary mb-1" style="letter-spacing: 2px;">Program Details</div>
                        <h3 class="fw-bold mb-0" style="color: #0b1f5f;"><?= htmlspecialchars($selectedProgram['program_name']); ?></h3>
                    </div>

                    <?php if ((int) $selectedProgram['is_active'] === 1): ?>
                        <span class="badge rounded-pill text-success-emphasis align-self-start px-3 py-2" style="background-color: #dcfce7;">Active</span>
                    <?php else: ?>
                        <span class="badge rounded-pill text-secondary-emphasis align-self-start px-3 py-2" style="background-color: #e5e7eb;">Inactive</span>
                    <?php endif; ?>
                </div>

                <p class="text-secondary fs-5 mb-4">
                    <?= htmlspecialchars($selectedProgram['description'] ?: 'No program description provided.'); ?>
                </p>

                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <div class="border rounded-4 p-3 bg-light-subtle">
                            <div class="small text-uppercase fw-bold text-secondary mb-1">Program Code</div>
                            <div class="fw-bold fs-5"><?= htmlspecialchars($selectedProgram['program_code']); ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="border rounded-4 p-3 bg-light-subtle">
                            <div class="small text-uppercase fw-bold text-secondary mb-1">Slots Left</div>
                            <div class="fw-bold fs-5"><?= number_format((int) $selectedProgram['slots_available']); ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="border rounded-4 p-3 bg-light-subtle">
                            <div class="small text-uppercase fw-bold text-secondary mb-1">Students Enrolled</div>
                            <div class="fw-bold fs-5"><?= number_format($enrolledCount); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<section class="card border-0 rounded-4 shadow-sm mb-4">
    <div class="card-body p-4">
        <h4 class="fw-bold mb-3" style="color: #0b1f5f;">Enrolled Students</h4>

        <form method="get" class="row g-3 align-items-end mb-4">
            <input type="hidden" name="program_id" value="<?= $programId; ?>">

            <div class="col-12 col-lg-4">
                <label class="form-label small fw-bold text-uppercase">Search Name</label>
                <input type="text" name="student_search" class="form-control border-0 bg-light" value="<?= htmlspecialchars($studentsSearch); ?>" placeholder="Student name">
            </div>

            <div class="col-12 col-md-4 col-lg-3">
                <label class="form-label small fw-bold text-uppercase">Sort By</label>
                <select name="student_sort_by" class="form-select border-0 bg-light">
                    <option value="name" <?= $sortBy === 'name' ? 'selected' : ''; ?>>Name</option>
                    <option value="student_number" <?= $sortBy === 'student_number' ? 'selected' : ''; ?>>Student Number</option>
                    <option value="email" <?= $sortBy === 'email' ? 'selected' : ''; ?>>Email</option>
                    <option value="status" <?= $sortBy === 'status' ? 'selected' : ''; ?>>Status</option>
                    <option value="updated_at" <?= $sortBy === 'updated_at' ? 'selected' : ''; ?>>Date Updated</option>
                </select>
            </div>

            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label small fw-bold text-uppercase">Order</label>
                <select name="student_order" class="form-select border-0 bg-light">
                    <option value="asc" <?= strtolower($_GET['student_order'] ?? 'asc') === 'asc' ? 'selected' : ''; ?>>Ascending</option>
                    <option value="desc" <?= strtolower($_GET['student_order'] ?? '') === 'desc' ? 'selected' : ''; ?>>Descending</option>
                </select>
            </div>

            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label small fw-bold text-uppercase">Status</label>
                <select name="student_status" class="form-select border-0 bg-light">
                    <option value="">All</option>
                    <option value="enrolled" <?= $statusFilter === 'enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                    <option value="fully_enrolled" <?= $statusFilter === 'fully_enrolled' ? 'selected' : ''; ?>>Fully Enrolled</option>
                </select>
            </div>

            <div class="col-12 col-lg-1 d-grid">
                <button type="submit" class="btn text-white rounded-3" style="background-color: #052c65;">
                    <i class="bi bi-funnel-fill"></i>
                </button>
            </div>
        </form>

        <div class="table-responsive d-none d-md-block">
            <table class="table align-middle">
                <thead>
                    <tr class="text-secondary small text-uppercase">
                        <th class="border-0">Student</th>
                        <th class="border-0">Student Number</th>
                        <th class="border-0">Email</th>
                        <th class="border-0">Status</th>
                        <th class="border-0">Date Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($students)): ?>
                        <?php foreach ($students as $studentRow): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($studentRow['full_name']); ?></td>
                                <td><?= htmlspecialchars($studentRow['student_number'] ?: 'Not assigned'); ?></td>
                                <td><?= htmlspecialchars($studentRow['email']); ?></td>
                                <td>
                                    <span class="badge rounded-pill text-success-emphasis px-3 py-2" style="background-color: #dcfce7;">
                                        <?= htmlspecialchars(str_replace('_', ' ', ucwords($studentRow['application_status'], '_'))); ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($studentRow['updated_at'] ?: ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-secondary">No enrolled students found for this program.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="d-md-none">
            <?php if (!empty($students)): ?>
                <?php foreach ($students as $studentRow): ?>
                    <div class="card border-0 rounded-4 shadow-sm mb-3">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-1" style="color: #0b1f5f;"><?= htmlspecialchars($studentRow['full_name']); ?></h5>
                            <p class="text-secondary mb-2"><?= htmlspecialchars($studentRow['email']); ?></p>
                            <div class="small text-secondary">Student No.: <?= htmlspecialchars($studentRow['student_number'] ?: 'Not assigned'); ?></div>
                            <div class="mt-3">
                                <span class="badge rounded-pill text-success-emphasis px-3 py-2" style="background-color: #dcfce7;">
                                    <?= htmlspecialchars(str_replace('_', ' ', ucwords($studentRow['application_status'], '_'))); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center text-secondary py-5">No enrolled students found for this program.</div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center flex-wrap">
                    <!-- previous page -->
                    <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?= $paginationBase . $paginationSeparator; ?>page=<?= $page - 1; ?>">Previous</a>
                    </li>

                    <!-- numbers page -->
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="<?= $paginationBase . $paginationSeparator; ?>page=<?= $i; ?>"><?= $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <!-- next page -->
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?= $paginationBase . $paginationSeparator; ?>page=<?= $page + 1; ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</section>

<div class="modal fade" id="editProgramModal" tabindex="-1" aria-labelledby="editProgramModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4">
            <form method="post">
                <input type="hidden" name="admin_action" value="update_program">
                <input type="hidden" name="program_id" value="<?= $programId; ?>">

                <div class="modal-header border-0 p-4">
                    <h5 class="modal-title fw-bold" id="editProgramModalLabel" style="color: #0b1f5f;">Manage Program</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body px-4 pb-4">
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <label class="form-label small fw-bold text-uppercase">Program Name</label>
                            <input type="text" name="program_name" class="form-control border-0 bg-light" value="<?= htmlspecialchars($selectedProgram['program_name']); ?>" required>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-uppercase">Program Code</label>
                            <input type="text" name="program_code" class="form-control border-0 bg-light" value="<?= htmlspecialchars($selectedProgram['program_code']); ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase">Description</label>
                            <textarea name="description" class="form-control border-0 bg-light" rows="4"><?= htmlspecialchars($selectedProgram['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Slots Available</label>
                            <input type="number" name="slots_available" class="form-control border-0 bg-light" min="0" value="<?= (int) $selectedProgram['slots_available']; ?>" required>
                        </div>

                        <div class="col-12 col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="editIsActive" <?= (int) $selectedProgram['is_active'] === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="editIsActive">Program is active</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light border rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white rounded-3 px-4" style="background-color: #052c65;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
