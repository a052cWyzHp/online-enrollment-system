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
            <div class="rounded-circle overflow-hidden d-flex align-items-center justify-content-center text-white fw-bold" style="width: 40px; height: 40px; background-color: #0b1f5f;">
                P
            </div>
            <div>
                <div class="fw-bold" style="color: #0f172a;">Mr. Person</div>
                <div class="small text-secondary">Administrator</div>
            </div>
        </div>

        <div class="nav flex-column gap-2">
            <a href="admin_dashboard.php"
                class="nav-link rounded-4 px-3 py-3 fw-semibold <?= $currentAdminPage === 'dashboard' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                    <i class="bi bi-grid-fill me-3"></i>Dashboard
            </a>

            <a href="#" class="nav-link rounded-4 px-3 py-3 text-secondary fw-medium <?= $currentAdminPage === 'applications' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-file-earmark-text-fill me-3"></i>Applications
            </a>

            <a href="#" class="nav-link rounded-4 px-3 py-3 text-secondary fw-medium <?= $currentAdminPage === 'students' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-people-fill me-3"></i>Students
            </a>

            <a href="#" class="nav-link rounded-4 px-3 py-3 text-secondary fw-medium <?= $currentAdminPage === 'activitylogs' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-clock-history me-3"></i>Activity Logs
            </a>

            <div class="my-4"></div>

            <a href="#" class="nav-link rounded-4 px-3 py-3 text-secondary fw-medium <?= $currentAdminPage === 'settings' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-gear-fill me-3"></i>Settings
            </a>
            <a href="#" class="nav-link rounded-4 px-3 py-3 fw-medium logoutHover">
                <i class="bi bi-box-arrow-left me-3"></i>Logout
            </a>
        </div>
    </div>
</aside>

<!-- FOR MOBILE -->
<div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">

    <div class="offcanvas-body">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="rounded-circle overflow-hidden d-flex align-items-center justify-content-center text-white fw-bold" style="width: 40px; height: 40px; background-color: #0b1f5f;">
                P
            </div>
            <div>
                <div class="fw-bold">Mr. Person</div>
                <div class="small text-secondary">Administrator</div>
            </div>
        </div>

        <div class="nav flex-column gap-2">
            <a href="admin_dashboard.php"
                class="nav-link rounded-4 px-3 py-3 fw-semibold <?= $currentAdminPage === 'dashboard' ? 'text-white' : 'text-secondary' ?>"
                style="background-color: <?= $currentAdminPage === 'dashboard' ? '#052c65' : 'transparent' ?>;">
                <i class="bi bi-grid-fill me-3"></i>Dashboard
            </a>

            <a href="#" class="nav-link rounded-4 px-3 py-3 text-secondary fw-medium menuHover">
                <i class="bi bi-file-earmark-text-fill me-3"></i>Applications
            </a>

            <a href="#" class="nav-link rounded-4 px-3 py-3 text-secondary fw-medium menuHover">
                <i class="bi bi-people-fill me-3"></i>Students
            </a>

            <a href="#" class="nav-link rounded-4 px-3 py-3 text-secondary fw-medium menuHover">
                <i class="bi bi-clock-history me-3"></i>Activity Logs
            </a>

            <a href="#" class="nav-link rounded-4 px-3 py-3 text-secondary fw-medium menuHover">
                <i class="bi bi-gear-fill me-3"></i>Settings
            </a>

            <a href="#" class="nav-link rounded-4 px-3 py-3 fw-medium logoutHover">
                <i class="bi bi-box-arrow-left me-3"></i>Logout
            </a>
        </div>
    </div>
</div>