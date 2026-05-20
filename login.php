<?php
session_start();
require_once 'config.php';

// authentication manager class
class AuthManager {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function loginUser(string $email, string $password): array {
        $statement = $this->pdo->prepare("
            SELECT user_id, full_name, email, password_hash, role, status
            FROM users
            WHERE email = :email
            LIMIT 1
        ");
        $statement->execute([':email' => $email]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user) { // show this error if the sql query wasn't able to find a match, meaning it was mistyped because the inputted login does not exist
            throw new Exception('Invalid email or password.');
        }
        if ($user['status'] !== 'active') { // show this error if the user's status is inactive
            throw new Exception('This account is inactive. Please contact the administrator.');
        }
        if (!password_verify($password, $user['password_hash'])) { // show this error after it hashes the inputted password and it did not match with the hashed password in the database
            throw new Exception('Invalid email or password.');
        }

        return $user; // if it did not trigger the 3 previous if statements, return all login details to $user which will be used for authentication, meaning a success
    }
}

$errorMessage = '';
$successMessage = '';

if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $successMessage = 'Registration successful. You may now log in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') { // show this error if theres nothing written in email or password
        $errorMessage = 'Please enter your email and password.';
    } else {
        try { //authenticate the user using the AuthManager class
            $auth = new AuthManager($pdo);
            $user = $auth->loginUser($email, $password); // this will decide if the login is correct, if failed the following code won't work

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') { // if the account's role is admin, send to admin dashboard
                header('Location: admin_dashboard.php');
                exit;
            }

            header('Location: student_dashboard.php'); // if not, send to student dashboard instead
            exit;
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<style>
    body {
        background-color: #F8F9FA;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }
</style>

<body>
<?php include 'login page/navbar.php'; ?>

<main class="container-fluid d-flex align-items-center" style="min-height: calc(100vh - 140px);">
    <div class="row justify-content-center align-items-center w-100">

        <div class="col-12 col-lg-10 col-xl-9">
            <div class="card border-0 shadow rounded-4 overflow-hidden">
                <div class="row g-0">

                    <div class="col-md-6 d-none d-md-flex text-white p-5 flex-column justify-content-end" style="min-height: 620px; background-color: #002349;">
                        <div>
                            <p class="text-uppercase small fw-semibold mb-2 opacity-75">Enrollment System</p>
                            <h1 class="fw-bold display-6 mb-3">Elevating the Academic Journey.</h1>
                            <p class="fs-5 mb-0 opacity-75">
                                Enrollment made easier for both students and registrars!
                            </p>
                        </div>
                    </div>

                    <div class="col-md-6 bg-white p-4 p-lg-5 d-flex align-items-center" style="min-height: 620px;">
                        <div class="w-100">
                            <h2 class="fw-bold mb-3">Welcome</h2>
                            <p class="text-secondary mb-4">
                                Please enter your credentials to continue.
                            </p>

                            <!-- this shows the success message after registering -->
                            <?php if (!empty($successMessage)): ?>
                                <div class="alert alert-success rounded-3">
                                    <?= htmlspecialchars($successMessage); ?>
                                </div>
                            <?php endif; ?>

                            <!-- this shows the error message if there is any -->
                            <?php if (!empty($errorMessage)): ?>
                                <div class="alert alert-danger rounded-3">
                                    <?= htmlspecialchars($errorMessage); ?>
                                </div>
                            <?php endif; ?>

                            <form method="post">
                                <div class="mb-3">
                                    <label for="email" class="form-label fw-semibold small text-uppercase">username or email</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">@</span>
                                        <input type="email" name="email" class="form-control border-start-0 bg-light" id="email" placeholder="e.g. j.smith@university.edu" value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <label for="password" class="form-label fw-semibold small text-uppercase mb-1">Password</label>
                                        <a href="#" class="text-decoration-none small fw-semibold">Forgot password?</a>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="bi bi-lock-fill"></i>
                                        </span>
                                        <input type="password" name="password" class="form-control border-start-0 bg-light" id="password" placeholder="••••••••••" required>
                                    </div>
                                </div>

                                <div class="d-grid mb-4">
                                    <button type="submit" class="btn btn-primary btn-lg fw-semibold rounded-3">
                                        Login
                                    </button>
                                </div>

                                <div class="text-center text-secondary small text-uppercase fw-semibold mb-3">
                                    <span class="px-2 bg-white">New Here?</span>
                                </div>

                                <div class="d-grid mb-5">
                                    <a href="register.php" class="btn btn-light btn-lg fw-semibold rounded-3 border border-1">
                                        Register
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</main>

<?php include 'login page/footer.php'; ?>
</body>

</html>
