<?php
session_start();
require_once 'config.php';

$errorMessage = '';
$successMessage = '';

if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $successMessage = 'Registration successful. You may now log in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errorMessage = 'Please enter your email and password.';
    } else {
        try {
            $statement = $pdo->prepare("
                SELECT user_id, full_name, email, password_hash, role, status
                FROM users
                WHERE email = :email
                LIMIT 1
            ");

            $statement->execute([':email' => $email]);
            $user = $statement->fetch();

            if (!$user) {
                $errorMessage = 'Invalid email or password.';
            } elseif ($user['status'] !== 'active') {
                $errorMessage = 'This account is inactive. Please contact the administrator.';
            } elseif (!password_verify($password, $user['password_hash'])) {
                $errorMessage = 'Invalid email or password.';
            } else {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] === 'admin') {
                    header('Location: admin_dashboard.php');
                    exit;
                }

                header('Location: student_dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            $errorMessage = 'Login failed. Please try again.';
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

                            <?php if (!empty($successMessage)): ?>
                                <div class="alert alert-success rounded-3">
                                    <?= htmlspecialchars($successMessage); ?>
                                </div>
                            <?php endif; ?>

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
