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

require_once 'config.php';
if (!isset($pdo) && isset($conn) && $conn instanceof PDO) {
    $pdo = $conn;
}
if (!isset($pdo) || !$pdo instanceof PDO) {
    die('Database connection was not found. Please check config.php.');
}

$currentAdminPage = 'admin_logs';
$basePath = '';

class AdminLog {
    private PDO $conn;
    public function __construct(PDO $pdo) {
        $this->conn = $pdo;
    }
    public function getActionTypes(): array {
        $query = "
            SELECT DISTINCT action_type
            FROM admin_logs
            WHERE action_type IS NOT NULL
              AND action_type != ''
            ORDER BY action_type ASC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    public function getLogs(string $search, string $actionType, int $limit, int $offset): array {
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = "
                (
                    u.full_name LIKE :search
                    OR al.action_type LIKE :search
                    OR al.description LIKE :search
                    OR al.target_table LIKE :search
                )
            ";
            $params[':search'] = '%' . $search . '%';
        }
        if ($actionType !== '') {
            $where[] = "al.action_type = :action_type";
            $params[':action_type'] = $actionType;
        }
        $whereSql = '';
        if (!empty($where)) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }
        $query = "
            SELECT 
                al.log_id,
                al.user_id,
                al.action_type,
                al.target_table,
                al.target_id,
                al.description,
                al.ip_address,
                al.created_at,
                u.full_name AS admin_name,
                u.email AS admin_email
            FROM admin_logs al
            INNER JOIN users u ON al.user_id = u.user_id
            $whereSql
            ORDER BY al.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function countLogs(string $search, string $actionType): int {
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = "
                (
                    u.full_name LIKE :search
                    OR al.action_type LIKE :search
                    OR al.description LIKE :search
                    OR al.target_table LIKE :search
                )
            ";
            $params[':search'] = '%' . $search . '%';
        }
        if ($actionType !== '') {
            $where[] = "al.action_type = :action_type";
            $params[':action_type'] = $actionType;
        }
        $whereSql = '';
        if (!empty($where)) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }
        $query = "
            SELECT COUNT(*) AS total
            FROM admin_logs al
            INNER JOIN users u ON al.user_id = u.user_id
            $whereSql
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
function getLogStyle(string $actionType): array {
    $action = strtolower($actionType);
    if (str_contains($action, 'approve') || str_contains($action, 'verified') || str_contains($action, 'enrolled')) {
        return [
            'badge' => 'text-success-emphasis',
            'background' => '#dcfce7',
            'icon' => 'bi-check-circle-fill',
            'icon_bg' => '#dcfce7',
            'icon_color' => '#16a34a'
        ];
    }
    if (str_contains($action, 'reject') || str_contains($action, 'delete')) {
        return [
            'badge' => 'text-danger-emphasis',
            'background' => '#fee2e2',
            'icon' => 'bi-x-circle-fill',
            'icon_bg' => '#fee2e2',
            'icon_color' => '#dc2626'
        ];
    }
    if (str_contains($action, 'resubmit') || str_contains($action, 'reupload') || str_contains($action, 'pending')) {
        return [
            'badge' => 'text-warning-emphasis',
            'background' => '#fef3c7',
            'icon' => 'bi-exclamation-triangle-fill',
            'icon_bg' => '#fef3c7',
            'icon_color' => '#d97706'
        ];
    }
    if (str_contains($action, 'payment')) {
        return [
            'badge' => 'text-info-emphasis',
            'background' => '#cffafe',
            'icon' => 'bi-credit-card-fill',
            'icon_bg' => '#cffafe',
            'icon_color' => '#0891b2'
        ];
    }
    if (str_contains($action, 'login') || str_contains($action, 'logout')) {
        return [
            'badge' => 'text-secondary-emphasis',
            'background' => '#e5e7eb',
            'icon' => 'bi-box-arrow-in-right',
            'icon_bg' => '#e5e7eb',
            'icon_color' => '#64748b'
        ];
    }
    return [
        'badge' => 'text-primary-emphasis',
        'background' => '#dbeafe',
        'icon' => 'bi-clock-history',
        'icon_bg' => '#dbeafe',
        'icon_color' => '#2563eb'
    ];
}
function getInitials(string $name): string {
    $nameParts = preg_split('/\s+/', trim($name));
    if (count($nameParts) >= 2) {
        return strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
    }
    if ($name !== '') {
        return strtoupper(substr($name, 0, 1));
    }
    return 'A';
}
function formatLogDate(?string $date): string {
    if (empty($date)) {
        return 'No date';
    }
    return date('M d, Y h:i A', strtotime($date));
}
$search = trim($_GET['search'] ?? '');
$actionType = trim($_GET['action_type'] ?? '');
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$log = new AdminLog($pdo);
$actionTypes = $log->getActionTypes();
$totalLogs = $log->countLogs($search, $actionType);
$totalPages = max(1, (int) ceil($totalLogs / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}
$logs = $log->getLogs($search, $actionType, $limit, $offset);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>Admin Logs</title>
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
        <!-- MAIN CONTENT -->
        <main class="col-12 col-lg-10 col-xl-10 ms-auto px-3 px-md-4 px-lg-4 py-4"
              style="min-height: calc(100vh - 120px);">
            <!-- PAGE HEADER -->
            <div class="d-flex flex-column flex-md-row
                        justify-content-between
                        align-items-md-center
                        gap-3
                        mb-4">
                <div>
                    <h1 class="fw-bold mb-1"
                        style="color:#0f172a;">
                        Admin Activity Logs
                    </h1>
                    <p class="mb-0 text-secondary fs-6">
                        Review administrator actions,
                        application decisions, and system activity.
                    </p>
                </div>
                <a href="admin_logs.php"
                   class="btn btn-primary rounded-pill px-4">
                    <i class="bi bi-arrow-clockwise me-2"></i>
                    Refresh Logs
                </a>
            </div>
            <!-- FILTER CARD -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <form method="get"
                          class="row g-3 align-items-end">
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-semibold small text-uppercase text-secondary">
                                Search Logs
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0">
                                    <i class="bi bi-search text-secondary"></i>
                                </span>
                                <input type="text"
                                       name="search"
                                       value="<?= htmlspecialchars($search); ?>"
                                       class="form-control bg-light border-0"
                                       placeholder="Search admin, action, table, or description">
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <label class="form-label fw-semibold small text-uppercase text-secondary">
                                Action Type
                            </label>
                            <select name="action_type"
                                    class="form-select bg-light border-0">
                                <option value="">All Actions</option>
                                <?php foreach ($actionTypes as $type): ?>
                                    <option value="<?= htmlspecialchars($type); ?>"
                                            <?= $actionType === $type ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-2 d-grid">
                            <button type="submit"
                                    class="btn text-white fw-semibold"
                                    style="background-color:#052c65;">
                                Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <!-- LOG CARD -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row
                                justify-content-between
                                align-items-md-center
                                gap-3
                                mb-4">
                        <div>
                            <h4 class="fw-bold mb-1"
                                style="color:#111827;">
                                Recent Activities
                            </h4>
                            <p class="text-secondary mb-0">
                                Showing <?= count($logs); ?> of <?= $totalLogs; ?> log record<?= $totalLogs === 1 ? '' : 's'; ?>.
                            </p>
                        </div>
                    </div>
                    <!-- DESKTOP TABLE -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table align-middle">
                            <thead>
                                <tr class="text-uppercase small text-secondary">
                                    <th class="border-0">Log ID</th>
                                    <th class="border-0">Admin</th>
                                    <th class="border-0">Action</th>
                                    <th class="border-0">Target</th>
                                    <th class="border-0">Description</th>
                                    <th class="border-0">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if(count($logs) > 0): ?>
                                <?php foreach($logs as $row) : ?>
                                    <?php $style = getLogStyle($row['action_type'] ?? ''); ?>
                                    <tr>
                                        <!-- LOG ID -->
                                        <td>
                                            <span class="fw-semibold">
                                                #<?= htmlspecialchars($row['log_id']); ?>
                                            </span>
                                        </td>
                                        <!-- ADMIN -->
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded-circle
                                                            d-flex
                                                            align-items-center
                                                            justify-content-center"
                                                     style="
                                                        width:40px;
                                                        height:40px;
                                                        background:#dbeafe;
                                                        color:#2563eb;
                                                        font-weight:700;
                                                     ">
                                                    <?= htmlspecialchars(getInitials($row['admin_name'] ?? 'Admin')); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold">
                                                        <?= htmlspecialchars($row['admin_name'] ?? 'Administrator'); ?>
                                                    </div>
                                                    <div class="small text-secondary">
                                                        ID:
                                                        <?= htmlspecialchars($row['user_id']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <!-- ACTION -->
                                        <td>
                                            <span class="badge rounded-pill
                                                         <?= $style['badge']; ?>"
                                                  style="
                                                    background:<?= $style['background']; ?>;
                                                    padding:8px 14px;
                                                  ">
                                                <i class="bi <?= $style['icon']; ?> me-1"></i>
                                                <?= htmlspecialchars($row['action_type']); ?>
                                            </span>
                                        </td>
                                        <!-- TARGET -->
                                        <td>
                                            <div class="small">
                                                <div class="fw-semibold">
                                                    <?= htmlspecialchars($row['target_table'] ?? 'System'); ?>
                                                </div>
                                                <div class="text-secondary">
                                                    Target ID:
                                                    <?= !empty($row['target_id']) ? htmlspecialchars($row['target_id']) : 'N/A'; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <!-- DESCRIPTION -->
                                        <td style="max-width:350px;">
                                            <span class="text-secondary">
                                                <?= htmlspecialchars($row['description']); ?>
                                            </span>
                                        </td>
                                        <!-- DATE -->
                                        <td>
                                            <span class="small text-secondary">
                                                <?= htmlspecialchars(formatLogDate($row['created_at'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6"
                                        class="text-center py-5">
                                        <div class="mb-2">
                                            <i class="bi bi-journal-x
                                                      fs-1
                                                      text-secondary"></i>
                                        </div>
                                        <div class="text-secondary">
                                            No activity logs available.
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- MOBILE CARD VIEW -->
                    <div class="d-md-none">
                        <?php if(count($logs) > 0): ?>
                            <div class="d-grid gap-3">
                                <?php foreach($logs as $row) : ?>
                                    <?php $style = getLogStyle($row['action_type'] ?? ''); ?>
                                    <div class="card border-0 rounded-4"
                                         style="background-color:#f8fafc;">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-start gap-3 mb-3">
                                                <div class="rounded-circle
                                                            d-flex
                                                            align-items-center
                                                            justify-content-center
                                                            flex-shrink-0"
                                                     style="
                                                        width:46px;
                                                        height:46px;
                                                        background:<?= $style['icon_bg']; ?>;
                                                        color:<?= $style['icon_color']; ?>;
                                                     ">
                                                    <i class="bi <?= $style['icon']; ?> fs-5"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between gap-2">
                                                        <div class="fw-bold"
                                                             style="color:#111827;">
                                                            #<?= htmlspecialchars($row['log_id']); ?>
                                                        </div>
                                                        <div class="small text-secondary text-end">
                                                            <?= htmlspecialchars(formatLogDate($row['created_at'])); ?>
                                                        </div>
                                                    </div>
                                                    <span class="badge rounded-pill
                                                                 <?= $style['badge']; ?> mt-2"
                                                          style="
                                                            background:<?= $style['background']; ?>;
                                                            padding:7px 12px;
                                                          ">
                                                        <?= htmlspecialchars($row['action_type']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="fw-semibold">
                                                    <?= htmlspecialchars($row['description']); ?>
                                                </div>
                                            </div>
                                            <div class="border-top pt-3">
                                                <div class="small text-secondary">
                                                    Admin:
                                                    <span class="fw-semibold text-dark">
                                                        <?= htmlspecialchars($row['admin_name'] ?? 'Administrator'); ?>
                                                    </span>
                                                </div>
                                                <div class="small text-secondary">
                                                    Target:
                                                    <span class="fw-semibold text-dark">
                                                        <?= htmlspecialchars($row['target_table'] ?? 'System'); ?>
                                                        <?= !empty($row['target_id']) ? '#' . htmlspecialchars($row['target_id']) : ''; ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($row['ip_address'])): ?>
                                                    <div class="small text-secondary">
                                                        IP Address:
                                                        <span class="fw-semibold text-dark">
                                                            <?= htmlspecialchars($row['ip_address']); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <div class="mb-2">
                                    <i class="bi bi-journal-x
                                              fs-1
                                              text-secondary"></i>
                                </div>
                                <div class="text-secondary">
                                    No activity logs available.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- PAGINATION -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center flex-wrap">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link"
                                       href="?search=<?= urlencode($search); ?>&action_type=<?= urlencode($actionType); ?>&page=<?= $page - 1; ?>">
                                        Previous
                                    </a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link"
                                           href="?search=<?= urlencode($search); ?>&action_type=<?= urlencode($actionType); ?>&page=<?= $i; ?>">
                                            <?= $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link"
                                       href="?search=<?= urlencode($search); ?>&action_type=<?= urlencode($actionType); ?>&page=<?= $page + 1; ?>">
                                        Next
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<!-- FOOTER -->
<?php include $basePath . 'admin page/footer.php'; ?>
<!-- SAME JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
