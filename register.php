<?php
session_start();
require_once 'config.php';

$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($fullName === '' || $email === '' || $password === '' || $confirmPassword === '') {
        $errorMessage = 'Please complete all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } elseif ($password !== $confirmPassword) {
        $errorMessage = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $errorMessage = 'Password must be at least 8 characters long.';
    } else {
        try {
            $checkEmail = $pdo->prepare("SELECT user_id FROM users WHERE email = :email LIMIT 1");
            $checkEmail->execute([':email' => $email]);

            if ($checkEmail->fetch()) {
                $errorMessage = 'This email is already registered.';
            } else {
                $pdo->beginTransaction();

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $insertUser = $pdo->prepare("
                    INSERT INTO users (full_name, email, password_hash, role, status)
                    VALUES (:full_name, :email, :password_hash, 'student', 'active')
                ");

                $insertUser->execute([
                    ':full_name' => $fullName,
                    ':email' => $email,
                    ':password_hash' => $passwordHash
                ]);

                $userId = $pdo->lastInsertId();

                $insertProfile = $pdo->prepare("
                    INSERT INTO student_profiles (user_id)
                    VALUES (:user_id)
                ");

                $insertProfile->execute([':user_id' => $userId]);

                $pdo->commit();

                header('Location: login.php?registered=1');
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessage = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>

<style>
    body {
        background-color: #F8F9FA;
    }
</style>

<body>

<?php include 'login page/navbar.php'; ?>

<main class="container-fluid">
    <div class="row justify-content-center align-items-center py-5">

        <div class="col-12 col-lg-10 col-xl-9">
            <div class="card border-0 shadow rounded-4 overflow-hidden">
                <div class="row g-0">

                    <div class="col-md-6 d-none d-md-flex text-white p-5 flex-column justify-content-between position-relative" style="min-height: 780px; background-color: #002d72;">
                        <div class="position-relative" style="z-index: 2;">
                            <h4 class="fw-semibold mb-5">Academic Portal</h4>
                            <h1 class="fw-bold lh-1 mb-4" style="font-size: 3.3rem;">
                                Begin your journey at the University.
                            </h1>
                            <p class="fs-3 mb-0 opacity-75" style="max-width: 90%; font-size: 1.5rem !important;">
                                Join a community of scholars and innovators in an environment designed for focused excellence.
                            </p>
                        </div>
                    </div>

                    <div class="col-md-6 bg-white p-4 p-lg-5 d-flex align-items-center" style="min-height: 780px;">
                        <div class="w-100 px-lg-2">
                            <h2 class="fw-bold mb-3" style="color: #0f2257; font-size: 3rem;">Create Account</h2>
                            <p class="text-secondary mb-4 fs-4">Please enter your details to register.</p>

                            <?php if (!empty($errorMessage)): ?>
                                <div class="alert alert-danger rounded-3">
                                    <?= htmlspecialchars($errorMessage); ?>
                                </div>
                            <?php endif; ?>

                            <form method="post">
                                <div class="mb-4">
                                    <label for="full_name" class="form-label fw-semibold small text-uppercase" style="letter-spacing: 2px;">
                                        Full Name
                                    </label>
                                    <input type="text" name="full_name" class="form-control form-control-lg border-0 rounded-3"
                                        id="full_name"
                                        placeholder="Enter your legal name"
                                        value="<?= htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                                        style="background-color: #F3F4F6; height: 70px;" required>
                                </div>

                                <div class="mb-4">
                                    <label for="email" class="form-label fw-semibold small text-uppercase" style="letter-spacing: 2px;">
                                        Email Address
                                    </label>
                                    <input type="email" name="email" class="form-control form-control-lg border-0 rounded-3"
                                        id="email"
                                        placeholder="student@university.edu"
                                        value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>"
                                        style="background-color: #F3F4F6; height: 70px;" required>
                                </div>

                                <div class="input-group input-group-lg mb-4">
                                    <input type="password" name="password" class="form-control border-0 rounded-start-3"
                                        id="password"
                                        placeholder="Password"
                                        style="background-color: #F3F4F6; height: 70px;" required>
                                    <button type="button"
                                            class="input-group-text border-0 rounded-end-3"
                                            onclick="togglePassword('password', this)"
                                            style="background-color: #F3F4F6;">
                                        <i class="bi bi-eye-fill text-secondary"></i>
                                    </button>
                                </div>

                                <div class="input-group input-group-lg mb-4">
                                    <input type="password" name="confirm_password" class="form-control border-0 rounded-start-3"
                                        id="confirm_password"
                                        placeholder="Confirm Password"
                                        style="background-color: #F3F4F6; height: 70px;" required>
                                    <button type="button"
                                            class="input-group-text border-0 rounded-end-3"
                                            onclick="togglePassword('confirm_password', this)"
                                            style="background-color: #F3F4F6;">
                                        <i class="bi bi-eye-fill text-secondary"></i>
                                    </button>
                                </div>

                                <div class="d-grid mt-4 mb-5">
                                    <button type="submit" class="btn btn-lg fw-semibold rounded-3 text-white" style="background-color: #002d72; height: 72px;">Register<i class="bi bi-arrow-right ms-2"></i></button>
                                </div>

                                <div class="d-flex align-items-center mb-4">
                                    <div class="flex-grow-1 border-top"></div>
                                        <span class="px-3 text-uppercase small fw-semibold text-secondary opacity-75" style="letter-spacing: 2px;">
                                            Already Registered?
                                        </span>
                                    <div class="flex-grow-1 border-top"></div>
                                </div>

                                <div class="text-center mb-5">
                                    <a href="login.php" class="text-decoration-none fw-semibold fs-5" style="color: #111827;">
                                        <i class="bi bi-box-arrow-in-right me-2"></i>Back to Login
                                    </a>
                                </div>

                                <hr class="my-4">

                                <div class="d-flex align-items-start text-secondary small mt-4">
                                    <i class="bi bi-info-circle-fill me-3 mt-1"></i>
                                    <p class="mb-0" style="font-size: 1rem;">
                                        By registering, you agree to the Terms of Service and Privacy Policy.
                                    </p>
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
<script>
    function togglePassword(inputId, button) {
        const input = document.getElementById(inputId);
        const icon = button.querySelector("i");

        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove("bi-eye-fill");
            icon.classList.add("bi-eye-slash-fill");
        } else {
            input.type = "password";
            icon.classList.remove("bi-eye-slash-fill");
            icon.classList.add("bi-eye-fill");
        }
    }
</script>
</html>
