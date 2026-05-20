<?php
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'program_name';
$order = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

$allowedSorts = [
    'program_name' => 'p.program_name',
    'program_code' => 'p.program_code',
    'slots_available' => 'p.slots_available',
    'students_enrolled' => 'students_enrolled',
    'is_active' => 'p.is_active'
];

$sortColumn = $allowedSorts[$sortBy] ?? 'p.program_name';

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(p.program_name LIKE :search OR p.program_code LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if ($statusFilter === 'active') {
    $where[] = 'p.is_active = 1';
} elseif ($statusFilter === 'inactive') {
    $where[] = 'p.is_active = 0';
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$statement = $pdo->prepare("\n    SELECT\n        p.program_id,\n        p.program_code,\n        p.program_name,\n        p.description,\n        p.slots_available,\n        p.is_active,\n        COUNT(CASE WHEN ea.application_status IN ('enrolled', 'fully_enrolled') THEN 1 END) AS students_enrolled,\n        COUNT(ea.application_id) AS total_applications\n    FROM programs p\n    LEFT JOIN enrollment_applications ea ON ea.program_id = p.program_id\n    $whereSql\n    GROUP BY\n        p.program_id,\n        p.program_code,\n        p.program_name,\n        p.description,\n        p.slots_available,\n        p.is_active\n    ORDER BY $sortColumn $order\n");

$statement->execute($params);
$programs = $statement->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="fw-bold mb-1" style="color: #0f172a;">Programs</h1>
        <p class="mb-0 fs-5 text-secondary">Manage university courses, available slots, and student enrollment.</p>
    </div>

    <button class="btn text-white px-4 py-2 rounded-3 fw-semibold" style="background-color: #052c65;" data-bs-toggle="modal" data-bs-target="#createProgramModal">
        <i class="bi bi-plus-lg me-2"></i>Add Program
    </button>
</div>

<section class="card border-0 rounded-4 shadow-sm mb-4">
    <div class="card-body p-4">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-12 col-lg-4">
                <label class="form-label small fw-bold text-uppercase">Search Program</label>
                <input type="text" name="search" class="form-control border-0 bg-light" placeholder="Program name or code" value="<?= htmlspecialchars($search); ?>">
            </div>

            <div class="col-12 col-md-4 col-lg-3">
                <label class="form-label small fw-bold text-uppercase">Sort By</label>
                <select name="sort_by" class="form-select border-0 bg-light">
                    <option value="program_name" <?= $sortBy === 'program_name' ? 'selected' : ''; ?>>Program Name</option>
                    <option value="program_code" <?= $sortBy === 'program_code' ? 'selected' : ''; ?>>Program Code</option>
                    <option value="slots_available" <?= $sortBy === 'slots_available' ? 'selected' : ''; ?>>Slots Left</option>
                    <option value="students_enrolled" <?= $sortBy === 'students_enrolled' ? 'selected' : ''; ?>>Students Enrolled</option>
                    <option value="is_active" <?= $sortBy === 'is_active' ? 'selected' : ''; ?>>Status</option>
                </select>
            </div>

            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label small fw-bold text-uppercase">Order</label>
                <select name="order" class="form-select border-0 bg-light">
                    <option value="asc" <?= strtolower($_GET['order'] ?? 'asc') === 'asc' ? 'selected' : ''; ?>>Ascending</option>
                    <option value="desc" <?= strtolower($_GET['order'] ?? '') === 'desc' ? 'selected' : ''; ?>>Descending</option>
                </select>
            </div>

            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label small fw-bold text-uppercase">Availability</label>
                <select name="status" class="form-select border-0 bg-light">
                    <option value="">All</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <div class="col-12 col-lg-1 d-grid">
                <button type="submit" class="btn text-white rounded-3" style="background-color: #052c65;">
                    <i class="bi bi-funnel-fill"></i>
                </button>
            </div>
        </form>
    </div>
</section>

<div class="row g-4">
    <?php if (!empty($programs)): ?>
        <?php foreach ($programs as $program): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <a href="admin_programs.php?program_id=<?= (int) $program['program_id']; ?>" class="text-decoration-none text-dark">
                    <div class="card border-0 rounded-4 shadow-sm h-100" style="transition: transform 0.2s ease, box-shadow 0.2s ease;">
                        <div class="card-body p-4 d-flex flex-column" style="min-height: 260px;">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <h3 class="fw-bold mb-1" style="color: #0b1f5f;">
                                        <?= htmlspecialchars($program['program_name']); ?>
                                    </h3>
                                    <div class="fs-5 fw-semibold text-secondary">
                                        <?= htmlspecialchars($program['program_code']); ?>
                                    </div>
                                </div>

                                <?php if ((int) $program['is_active'] === 1): ?>
                                    <span class="badge rounded-pill text-success-emphasis px-3 py-2" style="background-color: #dcfce7;">Active</span>
                                <?php else: ?>
                                    <span class="badge rounded-pill text-secondary-emphasis px-3 py-2" style="background-color: #e5e7eb;">Inactive</span>
                                <?php endif; ?>
                            </div>

                            <p class="text-secondary mb-4">
                                <?= htmlspecialchars($program['description'] ?: 'No program description provided.'); ?>
                            </p>

                            <div class="mt-auto">
                                <div class="d-flex justify-content-between border-top pt-3">
                                    <div>
                                        <div class="fw-bold fs-5"><?= number_format((int) $program['students_enrolled']); ?></div>
                                        <div class="small text-secondary text-uppercase">Students Enrolled</div>
                                    </div>

                                    <div class="text-end">
                                        <div class="fw-bold fs-5"><?= number_format((int) $program['slots_available']); ?></div>
                                        <div class="small text-secondary text-uppercase">Slots Left</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="card border-0 rounded-4 shadow-sm">
                <div class="card-body p-5 text-center text-secondary">
                    <i class="bi bi-journal-x fs-1 d-block mb-3"></i>
                    No programs found.
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="createProgramModal" tabindex="-1" aria-labelledby="createProgramModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4">
            <form method="post">
                <input type="hidden" name="admin_action" value="create_program">

                <div class="modal-header border-0 p-4">
                    <h5 class="modal-title fw-bold" id="createProgramModalLabel" style="color: #0b1f5f;">Add New Program</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body px-4 pb-4">
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <label class="form-label small fw-bold text-uppercase">Program Name</label>
                            <input type="text" name="program_name" class="form-control border-0 bg-light" placeholder="Bachelor of Science in Information Technology" required>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-uppercase">Program Code</label>
                            <input type="text" name="program_code" class="form-control border-0 bg-light" placeholder="BSIT" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase">Description</label>
                            <textarea name="description" class="form-control border-0 bg-light" rows="4" placeholder="Brief description of the program"></textarea>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Slots Available</label>
                            <input type="number" name="slots_available" class="form-control border-0 bg-light" min="0" value="0" required>
                        </div>

                        <div class="col-12 col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="createIsActive" checked>
                                <label class="form-check-label fw-semibold" for="createIsActive">Program is active</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light border rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white rounded-3 px-4" style="background-color: #052c65;">Create Program</button>
                </div>
            </form>
        </div>
    </div>
</div>
