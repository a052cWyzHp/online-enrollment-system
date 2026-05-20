<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($currentStudentPage)) {
    $currentStudentPage = '';
}

$sidebarUserName = $_SESSION['full_name'] ?? 'Student User';
$sidebarStudentNumber = 'Student';
$sidebarInitials = 'S';

$configPath = __DIR__ . '/../config.php';

if (file_exists($configPath) && isset($_SESSION['user_id'])) {
    require_once $configPath;

    $userId = $_SESSION['user_id'];

    try {

        $databaseConnection = null;

        if (isset($conn) && $conn instanceof PDO) {
            $databaseConnection = $conn;
        } elseif (isset($pdo) && $pdo instanceof PDO) {
            $databaseConnection = $pdo;
        } elseif (isset($connection) && $connection instanceof PDO) {
            $databaseConnection = $connection;
        }

        if ($databaseConnection instanceof PDO) {
            $statement = $databaseConnection->prepare("
                SELECT 
                    u.full_name,
                    sp.student_number
                FROM users u
                LEFT JOIN student_profiles sp ON sp.user_id = u.user_id
                WHERE u.user_id = :user_id
                LIMIT 1
            ");

            $statement->execute([
                ':user_id' => $userId
            ]);

            $student = $statement->fetch(PDO::FETCH_ASSOC);

            if ($student) {
                $sidebarUserName = $student['full_name'] ?? $sidebarUserName;
                $sidebarStudentNumber = !empty($student['student_number'])
                    ? $student['student_number']
                    : 'Student';
            }
        }

        elseif (isset($conn) && $conn instanceof mysqli) {
            $statement = $conn->prepare("
                SELECT 
                    u.full_name,
                    sp.student_number
                FROM users u
                LEFT JOIN student_profiles sp ON sp.user_id = u.user_id
                WHERE u.user_id = ?
                LIMIT 1
            ");

            $statement->bind_param("i", $userId);
            $statement->execute();

            $result = $statement->get_result();
            $student = $result->fetch_assoc();

            if ($student) {
                $sidebarUserName = $student['full_name'] ?? $sidebarUserName;
                $sidebarStudentNumber = !empty($student['student_number'])
                    ? $student['student_number']
                    : 'Student';
            }
        }

    } catch (Exception $e) {
        /*
        Keep safe fallback values if database lookup fails.
        Do not show database errors to regular users.
        */
    }
}

$nameParts = preg_split('/\s+/', trim($sidebarUserName));

if (count($nameParts) >= 2) {
    $sidebarInitials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
} elseif (!empty($sidebarUserName)) {
    $sidebarInitials = strtoupper(substr($sidebarUserName, 0, 1));
}
?>

<style>
.menuHover {
    background-color: transparent;
    transition: background-color 0.3s ease;
}
.menuHover:hover {
    background-color: #e3e5eb;
}
.logoutHover {
    background-color: transparent;
    color: #6C757D;
    transition: background-color 0.3s ease, color 0.3s ease;
}
.logoutHover:hover {
    background-color: #da3030;
    color: white;
}
.currentlySelected {
    background-color: #052c65;
}
</style>

<!-- FOR DESKTOP -->
<aside class="col-lg-2 d-none d-lg-block bg-white border-end" style="min-height: calc(100vh - 73px);">
    <div class="p-4 position-sticky" style="top: 73px;">

        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="rounded-circle overflow-hidden d-flex align-items-center justify-content-center text-white fw-bold"
                 style="width: 40px; height: 40px; background-color: #0b1f5f;">
                <?= htmlspecialchars($sidebarInitials); ?>
            </div>

            <div>
                <div class="fw-bold" style="color: #0f172a;">
                    <?= htmlspecialchars($sidebarUserName); ?>
                </div>
                <div class="small text-secondary">
                    <?= htmlspecialchars($sidebarStudentNumber); ?>
                </div>
            </div>
        </div>

        <div class="nav flex-column gap-2">
            <a href="student_dashboard.php"
               class="nav-link rounded-4 px-3 py-3 fw-semibold <?= $currentStudentPage === 'dashboard' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-house-fill me-3"></i>Dashboard
            </a>

            <a href="student_enrollment.php"
               class="nav-link rounded-4 px-3 py-3 fw-medium <?= $currentStudentPage === 'enrollment' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-briefcase-fill me-3"></i>Enrollment
            </a>

            <div class="my-4"></div>

            <a href="logout.php" class="nav-link rounded-4 px-3 py-3 fw-medium logoutHover">
                <i class="bi bi-box-arrow-left me-3"></i>Logout
            </a>
        </div>
    </div>
</aside>

<!-- FOR MOBILE -->
<div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="mobileStudentSidebar" aria-labelledby="mobileStudentSidebarLabel">

    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold" id="mobileStudentSidebarLabel" style="color: #0b1f5f;">
            Academic Portal
        </h5>

        <button type="button"
                class="btn-close"
                data-bs-dismiss="offcanvas"
                aria-label="Close">
        </button>
    </div>

    <div class="offcanvas-body">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="rounded-circle overflow-hidden d-flex align-items-center justify-content-center text-white fw-bold"
                 style="width: 40px; height: 40px; background-color: #0b1f5f;">
                <?= htmlspecialchars($sidebarInitials); ?>
            </div>

            <div>
                <div class="fw-bold">
                    <?= htmlspecialchars($sidebarUserName); ?>
                </div>
                <div class="small text-secondary">
                    <?= htmlspecialchars($sidebarStudentNumber); ?>
                </div>
            </div>
        </div>

        <div class="nav flex-column gap-2">
            <a href="student_dashboard.php"
               class="nav-link rounded-4 px-3 py-3 fw-semibold <?= $currentStudentPage === 'dashboard' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-house-fill me-3"></i>Dashboard
            </a>

            <a href="student_enrollment.php"
               class="nav-link rounded-4 px-3 py-3 fw-medium <?= $currentStudentPage === 'enrollment' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-briefcase-fill me-3"></i>Enrollment
            </a>

            <div class="my-3"></div>

            <a href="logout.php" class="nav-link rounded-4 px-3 py-3 fw-medium logoutHover">
                <i class="bi bi-box-arrow-left me-3"></i>Logout
            </a>
        </div>
    </div>
</div>