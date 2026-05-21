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
require_once __DIR__ . '/utilities/UIFormatter.php';

if (!isset($pdo) && isset($conn) && $conn instanceof PDO) {
    $pdo = $conn;
}

class ProgramManager {
    private PDO $pdo;
    private int $adminUserId;

    public function __construct(PDO $pdo, int $adminUserId) {
        $this->pdo = $pdo;
        $this->adminUserId = $adminUserId;
    }

    private function cleanInput(string $value): string {
        return trim($value);
    }

    private function logAction(string $actionType, ?int $targetId, string $description): void {
        // main logging function
        $statement = $this->pdo->prepare("
            INSERT INTO admin_logs (user_id, action_type, target_table, target_id, description, ip_address) 
            VALUES (:user_id, :action_type, 'programs', :target_id, :description, :ip_address)
        ");
        $statement->execute([
            ':user_id' => $this->adminUserId,
            ':action_type' => $actionType,
            ':target_id' => $targetId,
            ':description' => $description,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    }

    public function getProgramById(int $programId): ?array {
        // simply searches record from programs table by program id
        $stmt = $this->pdo->prepare("SELECT * FROM programs WHERE program_id = :id LIMIT 1");
        $stmt->execute([':id' => $programId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function countApplications(int $programId): int {
        // simply counts how many are applying into the program
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM enrollment_applications WHERE program_id = :id");
        $stmt->execute([':id' => $programId]);
        return (int) $stmt->fetchColumn();
    }

    public function countEnrolled(int $programId): int {
        // counts how many are enrolled into the program
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM enrollment_applications 
            WHERE program_id = :id AND application_status IN ('enrolled', 'fully_enrolled')
        ");
        $stmt->execute([':id' => $programId]);
        return (int) $stmt->fetchColumn();
    }

    public function processAction(string $action, array $postData): string {
        // main function for creation and management of programs
        // $postData is just the whole $_POST array taken from the creation/management/deletion program forms
        $programName = $this->cleanInput($postData['program_name'] ?? ''); // e.g. Bachelor of Science in Information Tech
        $programCode = strtoupper($this->cleanInput($postData['program_code'] ?? '')); // e.g. BSIT
        $description = $this->cleanInput($postData['description'] ?? '');
        $slotsAvailable = (int) ($postData['slots_available'] ?? 0);
        $isActive = isset($postData['is_active']) ? 1 : 0; // 1 is true, 0 false
        $programId = (int) ($postData['program_id'] ?? 0);

        $this->pdo->beginTransaction();
        try {
            if ($action === 'create_program') {
                if ($programName === '' || $programCode === '' || $slotsAvailable < 0) {
                    throw new Exception('Please complete the program name, code, and valid slot count.');
                } // throw this error if incomplete input
                $stmt = $this->pdo->prepare("
                    INSERT INTO programs (program_code, program_name, description, slots_available, is_active) 
                    VALUES (:code, :name, :desc, :slots, :active)
                ");
                $stmt->execute([
                    ':code' => $programCode, ':name' => $programName, 
                    ':desc' => $description !== '' ? $description : null, 
                    ':slots' => $slotsAvailable, ':active' => $isActive
                ]);
                
                $newId = (int) $this->pdo->lastInsertId();
                $this->logAction('create_program', $newId, "Created program $programCode - $programName"); // log the action of making a new program
                $this->pdo->commit();
                return 'created';
            }
            elseif ($action === 'update_program') {
                if ($programId <= 0 || $programName === '' || $programCode === '' || $slotsAvailable < 0) {
                    throw new Exception('Please complete the program details properly.');
                } // throw this error if incomplete input

                $oldProgram = $this->getProgramById($programId);
                if (!$oldProgram) {
                    throw new Exception('Program not found.');
                } // get the program data BEFORE updating


                $changes = []; // this will track the changes
                
                if ($oldProgram['program_name'] !== $programName) {
                    $changes[] = "name from '{$oldProgram['program_name']}' to '{$programName}'";
                }
                if ($oldProgram['program_code'] !== $programCode) {
                    $changes[] = "code from '{$oldProgram['program_code']}' to '{$programCode}'";
                }
                if ((int)$oldProgram['slots_available'] !== $slotsAvailable) {
                    $changes[] = "slots from {$oldProgram['slots_available']} to {$slotsAvailable}";
                }
                if ((int)$oldProgram['is_active'] !== $isActive) {
                    $statusStr = $isActive ? 'Enabled' : 'Disabled';
                    $changes[] = "status changed to {$statusStr}";
                }

                if (!empty($changes)) {
                    $stmt = $this->pdo->prepare("
                        UPDATE programs SET program_code = :code, program_name = :name, 
                        description = :desc, slots_available = :slots, is_active = :active 
                        WHERE program_id = :id
                    ");
                    $stmt->execute([
                        ':code' => $programCode, ':name' => $programName, 
                        ':desc' => $description !== '' ? $description : null, 
                        ':slots' => $slotsAvailable, ':active' => $isActive, ':id' => $programId
                    ]);
                    
                    $changeLog = "Updated {$programCode}: " . implode(', ', $changes);
                    // combines "updated" keyword and changes, e.g. "Updated BSIT: status changed from enabled to disabled"
                    
                    $this->logAction('update_program', $programId, $changeLog);
                } // only log if there are actual changes
                
                $this->pdo->commit();
                return 'updated';
            }
            elseif ($action === 'delete_program') {
                if ($programId <= 0) throw new Exception('Program not found.');
                
                $appCount = $this->countApplications($programId);
                if ($appCount > 0) throw new Exception('Program has existing applications. Please deactivate it instead of deleting.');
                // we can't really delete programs as it will cause problems with student data, if need to delete a program just disable instead

                $program = $this->getProgramById($programId);
                $stmt = $this->pdo->prepare("DELETE FROM programs WHERE program_id = :id");
                $stmt->execute([':id' => $programId]);
                
                $this->logAction('delete_program', $programId, "Deleted program {$program['program_code']} - {$program['program_name']}"); // log the action of deleting a program
                $this->pdo->commit();
                return 'deleted';
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            // cancel the SQL queries entirely if any step fails
            $this->pdo->rollBack();
            throw $e;
        }
        return '';
    }
}

$message = '';
$messageType = 'success';

$programManager = new ProgramManager($pdo, (int)$_SESSION['user_id']); // programmanager class instance

// these are to be used by the subpages
function getProgramById(PDO $pdo, int $id) { global $programManager; return $programManager->getProgramById($id); }
function countProgramApplications(PDO $pdo, int $id) { global $programManager; return $programManager->countApplications($id); }
function countEnrolledStudents(PDO $pdo, int $id) { global $programManager; return $programManager->countEnrolled($id); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminAction = $_POST['admin_action'] ?? '';
    try {
        $result = $programManager->processAction($adminAction, $_POST);
        if ($result) {
            $redirectUrl = 'admin_programs.php?message=' . $result;
            if ($result === 'updated') {
                $redirectUrl = 'admin_programs.php?program_id=' . (int)$_POST['program_id'] . '&message=updated'; // this is purely for the sweetalert2 modal
            }
            header('Location: ' . $redirectUrl);
            exit;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

if (isset($_GET['message'])) {
    // purely for sweetalert2 modal
    $messageType = 'success';
    $message = match ($_GET['message']) {
        'created' => 'Program was created successfully.',
        'updated' => 'Program was updated successfully.',
        'deleted' => 'Program was deleted successfully.',
        default => ''
    };
}

$selectedProgramId = isset($_GET['program_id']) ? (int) $_GET['program_id'] : 0;
$selectedProgram = $selectedProgramId > 0 ? $programManager->getProgramById($selectedProgramId) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Programs</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    // just recently found this nice modal library
</head>
<body class="bg-light-subtle">

<?php include 'admin page/navbar.php'; ?>

<div class="container-fluid">
    <div class="row g-0">

        <?php include 'admin page/sidebar.php'; ?>

        <main class="col-12 col-lg-10 col-xl-10 ms-auto px-3 px-md-4 px-lg-4 py-4" style="min-height: calc(100vh - 140px);">

            <?php if (!empty($message)): ?>
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        Swal.fire({
                            icon: '<?= $messageType === "success" ? "success" : "error" ?>',
                            title: '<?= $messageType === "success" ? "Success!" : "Action Failed" ?>',
                            text: '<?= htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>',
                            confirmButtonColor: '#002349'
                        });
                    });
                </script>
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