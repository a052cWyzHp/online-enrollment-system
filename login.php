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
    }
</style>

<body>
<!-- navbar -->
    <?php include 'login page/navbar.php'; ?>

<!-- login card -->
    <main class="container-fluid d-flex align-items-center" style="min-height: calc(100vh - 140px);">
    <div class="row justify-content-center align-items-center w-100">

        <!-- centered card -->
        <div class="col-12 col-lg-10 col-xl-9">
            <div class="card border-0 shadow rounded-4 overflow-hidden">
                <div class="row g-0">

                        <!-- LEFT PART, text and flat bg -->
                        <div class="col-md-6 d-none d-md-flex text-white p-5 flex-column justify-content-end" style="min-height: 620px; background-color: #002349;">
                            <div>
                                <p class="text-uppercase small fw-semibold mb-2 opacity-75">Enrollment System</p>
                                <h1 class="fw-bold display-6 mb-3">Elevating the Academic Journey.</h1>
                                <p class="fs-5 mb-0 opacity-75">
                                    Enrollment made easier for both students and registrars!
                                </p>
                            </div>
                        </div>

                        <!-- RIGHT PART, actual login form -->
                        <div class="col-md-6 bg-white p-4 p-lg-5 d-flex align-items-center" style="min-height: 620px;">
                            <div class="w-100">
                                <h2 class="fw-bold mb-3">Welcome</h2>
                                <p class="text-secondary mb-4">
                                    Please enter your credentials to continue.
                                </p>

                                <!-- the actual form -->
                                <form method="post">

                                    <!-- username or email part -->
                                    <div class="mb-3">
                                        <label for="email" class="form-label fw-semibold small text-uppercase">username or email</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0">@</span>
                                            <input type="email" class="form-control border-start-0 bg-light" id="email" placeholder="e.g. j.smith@university.edu">
                                        </div>
                                    </div>

                                    <!-- password part -->
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <label for="password" class="form-label fw-semibold small text-uppercase mb-1">Password</label>
                                            <a href="#" class="text-decoration-none small fw-semibold">Forgot password?</a>
                                        </div>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0">
                                                <i class="bi bi-lock-fill"></i>
                                            </span>
                                            <input type="password" class="form-control border-start-0 bg-light" id="password" placeholder="••••••••••">
                                        </div>
                                    </div>

                                    <!-- checkbox to stay signed in for 30 days -->
                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="checkbox" id="staySignedIn">
                                        <label class="form-check-label text-secondary" for="staySignedIn">
                                            Stay signed in for 30 days
                                        </label>
                                    </div>

                                    <!-- login button -->
                                    <div class="d-grid mb-4">
                                        <button type="submit" class="btn btn-primary btn-lg fw-semibold rounded-3">
                                            Login
                                        </button>
                                    </div>

                                    <div class="text-center text-secondary small text-uppercase fw-semibold mb-3">
                                        <span class="px-2 bg-white">New Here?</span>
                                    </div>

                                    <!-- register button -->
                                    <div class="d-grid mb-5">
                                        <button type="button" class="btn btn-light btn-lg fw-semibold rounded-3 border border-1">
                                            Register
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </main>

<!-- footer -->
    <?php include 'login page/footer.php'; ?>
</body>

</html>