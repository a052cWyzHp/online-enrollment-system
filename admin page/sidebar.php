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

<!-- DESKTOP SIDEBAR -->
<aside class="col-lg-2 d-none d-lg-block bg-white border-end"
       style="min-height: calc(100vh - 73px);">

    <div class="p-4 position-sticky"
         style="top: 73px;">

        <!-- PROFILE -->
        <div class="d-flex align-items-center gap-3 mb-4">

            <div class="rounded-circle
                        d-flex
                        align-items-center
                        justify-content-center
                        text-white fw-bold"
                 style="
                    width:40px;
                    height:40px;
                    background:#0b1f5f;
                 ">

                P

            </div>

            <div>

                <div class="fw-bold"
                     style="color:#0f172a;">

                    Mr. Person

                </div>

                <div class="small text-secondary">

                    Administrator

                </div>

            </div>

        </div>

        <!-- NAVIGATION -->
        <div class="nav flex-column gap-2">

            <!-- DASHBOARD -->
            <a href="admin_dashboard.php" 
                class="nav-link rounded-4 px-3 py-3 fw-semibold <?= $currentAdminPage === 'dashboard' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">

                <i class="bi bi-grid-fill me-3"></i>

                Dashboard

            </a>

            <!-- APPLICATIONS -->
            <a href="admin_applications.php"
               class="nav-link rounded-4 px-3 py-3 fw-medium <?= $currentAdminPage === 'applications' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">

                <i class="bi bi-file-earmark-text-fill me-3"></i>

                Applications

            </a>

            <!-- STUDENTS -->
            <a href="admin_studview.php"
               class="nav-link rounded-4 px-3 py-3 fw-medium <?= $currentAdminPage === 'students' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">

                <i class="bi bi-people-fill me-3"></i>

                Students

            </a>

            <a href="admin_programs.php"
               class="nav-link rounded-4 px-3 py-3 fw-medium <?= $currentAdminPage === 'programs' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">

                <i class="bi bi-journal-text me-3"></i>

                Programs

            </a>

            <!-- LOGS -->
            <a href="admin_logs.php"
               class="nav-link rounded-4 px-3 py-3 fw-medium <?= $currentAdminPage === 'admin_logs' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">

                <i class="bi bi-clock-history me-3"></i>

                Activity Logs

            </a>

            <div class="my-4"></div>

            <!-- LOGOUT -->
            <a href="logout.php"
               class="nav-link rounded-4 px-3 py-3 fw-medium logoutHover">

                <i class="bi bi-box-arrow-left me-3"></i>

                Logout

            </a>

        </div>

    </div>

</aside>

<!-- MOBILE SIDEBAR -->
<div class="offcanvas offcanvas-start d-lg-none"
     tabindex="-1"
     id="mobileSidebar">

    <div class="offcanvas-header">

        <h5 class="offcanvas-title fw-bold">

            OES Admin

        </h5>

        <button type="button"
                class="btn-close"
                data-bs-dismiss="offcanvas">

        </button>

    </div>

    <div class="offcanvas-body">

        <div class="nav flex-column gap-2">

            <!-- DASHBOARD -->
            <a href="admin_dashboard.php"
               class="nav-link rounded-4 px-3 py-3 fw-medium <?= $currentAdminPage === 'dashboard' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">

                <i class="bi bi-grid-fill me-3"></i>

                Dashboard

            </a>

            <!-- APPLICATIONS -->
            <a href="admin_applications.php"
               class="nav-link rounded-4 px-3 py-3 fw-medium <?= $currentAdminPage === 'applications' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">

                <i class="bi bi-file-earmark-text-fill me-3"></i>

                Applications

            </a>

            <!-- STUDENTS -->
            <a href="admin_studview.php"
               class="nav-link rounded-4 px-3 py-3 fw-medium <?= $currentAdminPage === 'students' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">

                <i class="bi bi-people-fill me-3"></i>

                Students

            </a>

            <!-- LOGS -->
            <a href="admin_logs.php"
               class="nav-link rounded-4 px-3 py-3 fw-medium <?= $currentAdminPage === 'admin_logs' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">

                <i class="bi bi-clock-history me-3"></i>

                Activity Logs

            </a>

            <!-- LOGOUT -->
            <a href="logout.php"
               class="nav-link rounded-4 px-3 py-3 fw-medium logoutHover">

                <i class="bi bi-box-arrow-left me-3"></i>

                Logout

            </a>

        </div>

    </div>

</div>