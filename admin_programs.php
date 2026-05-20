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

$currentAdminPage = 'programs';

require_once __DIR__ . '/config.php';

if (!isset($pdo)) {
    if (isset($conn) && $conn instanceof PDO) {
        $pdo = $conn;
    } else {
        die('Database connection was not found. Please check config.php.');
    }
}

$message = '';
$messageType = 'success';

function cleanInput(string $value): string
{
    return trim($value);
}

function getProgramById(PDO $pdo, int $programId): ?array
{
    $statement = $pdo->prepare("\n        SELECT\n            program_id,\n            program_code,\n            program_name,\n            description,\n            slots_available,\n            is_active\n        FROM programs\n        WHERE program_id = :program_id\n        LIMIT 1\n    ");

    $statement->execute([
        ':program_id' => $programId
    ]);

    $program = $statement->fetch(PDO::FETCH_ASSOC);
    return $program ?: null;
}

function countProgramApplications(PDO $pdo, int $programId): int
{
    $statement = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM enrollment_applications\n        WHERE program_id = :program_id\n    ");

    $statement->execute([
        ':program_id' => $programId
    ]);

    return (int) $statement->fetchColumn();
}

function countEnrolledStudents(PDO $pdo, int $programId): int
{
    $statement = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM enrollment_applications\n        WHERE program_id = :program_id\n        AND application_status IN ('enrolled', 'fully_enrolled')\n    ");

    $statement->execute([
        ':program_id' => $programId
    ]);

    return (int) $statement->fetchColumn();
}

function addAdminLog(PDO $pdo, int $userId, string $actionType, string $targetTable, ?int $targetId, string $description): void
{
    $statement = $pdo->prepare("\n        INSERT INTO admin_logs (\n            user_id,\n            action_type,\n            target_table,\n            target_id,\n            description,\n            ip_address\n        ) VALUES (\n            :user_id,\n            :action_type,\n            :target_table,\n            :target_id,\n            :description,\n            :ip_address\n        )\n    ");

    $statement->execute([
        ':user_id' => $userId,
        ':action_type' => $actionType,
        ':target_table' => $targetTable,
        ':target_id' => $targetId,
        ':description' => $description,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminUserId = (int) $_SESSION['user_id'];
    $adminAction = $_POST['admin_action'] ?? '';

    try {
        if ($adminAction === 'create_program') {
            $programName = cleanInput($_POST['program_name'] ?? '');
            $programCode = strtoupper(cleanInput($_POST['program_code'] ?? ''));
            $description = cleanInput($_POST['description'] ?? '');
            $slotsAvailable = (int) ($_POST['slots_available'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($programName === '' || $programCode === '' || $slotsAvailable < 0) {
                throw new Exception('Please complete the program name, program code, and valid slot count.');
            }

            $statement = $pdo->prepare("\n                INSERT INTO programs (\n                    program_code,\n                    program_name,\n                    description,\n                    slots_available,\n                    is_active\n                ) VALUES (\n                    :program_code,\n                    :program_name,\n                    :description,\n                    :slots_available,\n                    :is_active\n                )\n            ");

            $statement->execute([
                ':program_code' => $programCode,
                ':program_name' => $programName,
                ':description' => $description !== '' ? $description : null,
                ':slots_available' => $slotsAvailable,
                ':is_active' => $isActive
            ]);

            $newProgramId = (int) $pdo->lastInsertId();

            addAdminLog(
                $pdo,
                $adminUserId,
                'create_program',
                'programs',
                $newProgramId,
                'Created program ' . $programCode . ' - ' . $programName
            );

            header('Location: admin_programs.php?message=created');
            exit;
        }

        if ($adminAction === 'update_program') {
            $programId = (int) ($_POST['program_id'] ?? 0);
            $programName = cleanInput($_POST['program_name'] ?? '');
            $programCode = strtoupper(cleanInput($_POST['program_code'] ?? ''));
            $description = cleanInput($_POST['description'] ?? '');
            $slotsAvailable = (int) ($_POST['slots_available'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($programId <= 0 || $programName === '' || $programCode === '' || $slotsAvailable < 0) {
                throw new Exception('Please complete the program details properly.');
            }

            $statement = $pdo->prepare("\n                UPDATE programs\n                SET\n                    program_code = :program_code,\n                    program_name = :program_name,\n                    description = :description,\n                    slots_available = :slots_available,\n                    is_active = :is_active\n                WHERE program_id = :program_id\n            ");

            $statement->execute([
                ':program_code' => $programCode,
                ':program_name' => $programName,
                ':description' => $description !== '' ? $description : null,
                ':slots_available' => $slotsAvailable,
                ':is_active' => $isActive,
                ':program_id' => $programId
            ]);

            addAdminLog(
                $pdo,
                $adminUserId,
                'update_program',
                'programs',
                $programId,
                'Updated program ' . $programCode . ' - ' . $programName
            );

            header('Location: admin_programs.php?program_id=' . $programId . '&message=updated');
            exit;
        }

        if ($adminAction === 'delete_program') {
            $programId = (int) ($_POST['program_id'] ?? 0);

            if ($programId <= 0) {
                throw new Exception('Program was not found.');
            }

            $program = getProgramById($pdo, $programId);
            if (!$program) {
                throw new Exception('Program was not found.');
            }

            $applicationCount = countProgramApplications($pdo, $programId);

            if ($applicationCount > 0) {
                throw new Exception('This program already has applications or enrolled students. Deactivate it instead of deleting it.');
            }

            $statement = $pdo->prepare("\n                DELETE FROM programs\n                WHERE program_id = :program_id\n            ");

            $statement->execute([
                ':program_id' => $programId
            ]);

            addAdminLog(
                $pdo,
                $adminUserId,
                'delete_program',
                'programs',
                $programId,
                'Deleted program ' . $program['program_code'] . ' - ' . $program['program_name']
            );

            header('Location: admin_programs.php?message=deleted');
            exit;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

if (isset($_GET['message'])) {
    $messageType = 'success';
    $message = match ($_GET['message']) {
        'created' => 'Program was created successfully.',
        'updated' => 'Program was updated successfully.',
        'deleted' => 'Program was deleted successfully.',
        default => ''
    };
}

$selectedProgramId = isset($_GET['program_id']) ? (int) $_GET['program_id'] : 0;
$selectedProgram = $selectedProgramId > 0 ? getProgramById($pdo, $selectedProgramId) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Programs</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light-subtle">

<?php include 'admin page/navbar.php'; ?>

<div class="container-fluid">
    <div class="row g-0">

        <?php include 'admin page/sidebar.php'; ?>

        <main class="col-12 col-lg-10 col-xl-10 ms-auto px-3 px-md-4 px-lg-4 py-4" style="min-height: calc(100vh - 140px);">

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= htmlspecialchars($messageType); ?> rounded-4 border-0 shadow-sm mb-4">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php
            if ($selectedProgram) {
                include __DIR__ . '/admin page/programs page/program_details.php';
            } else {
                include __DIR__ . '/admin page/programs page/program_list.php';
            }
            ?>

        </main>
    </div>
</div>

<?php include 'admin page/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
