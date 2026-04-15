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
<aside class="col-lg-2 d-none d-lg-block bg-white border-end">
    <div class="p-4 position-sticky" style="top: 73px;">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="rounded-circle overflow-hidden d-flex align-items-center justify-content-center text-white fw-bold" style="width: 40px; height: 40px; background-color: #0b1f5f;">
                <?= htmlspecialchars($studentData['student_initials']); ?>
            </div>
            <div>
                <div class="fw-bold" style="color: #0f172a;">Student</div>
                <div class="small text-secondary text-uppercase"><?= htmlspecialchars($studentData['class_year']); ?></div>
            </div>
        </div>

        <div class="nav flex-column gap-2">
            <a href="student_dashboard.php"
                class="nav-link rounded-4 px-3 py-3 fw-semibold <?= $currentStudentPage === 'dashboard' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-house-fill me-3"></i>Dashboard
            </a>

            <a href="#"
                class="nav-link rounded-4 px-3 py-3 fw-medium <?= $currentStudentPage === 'profile' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-person-fill me-3"></i>Profile
            </a>

            <a href="#"
                class="nav-link rounded-4 px-3 py-3 fw-medium <?= $currentStudentPage === 'enrollment' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-briefcase-fill me-3"></i>Enrollment
            </a>

            <a href="#"
                class="nav-link rounded-4 px-3 py-3 fw-medium <?= $currentStudentPage === 'documents' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-cloud-arrow-up-fill me-3"></i>Documents
            </a>

            <a href="#"
                class="nav-link rounded-4 px-3 py-3 fw-medium <?= $currentStudentPage === 'status' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-check2-square me-3"></i>Status
            </a>

            <div class="my-4"></div>
            <a href="#" class="nav-link rounded-4 px-3 py-3 text-secondary fw-medium <?= $currentStudentPage === 'settings' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-gear-fill me-3"></i>Settings
            </a>
            <a href="#" class="nav-link rounded-4 px-3 py-3 fw-medium logoutHover">
                <i class="bi bi-box-arrow-left me-3"></i>Logout
            </a>
        </div>
    </div>
</aside>

<!-- FOR MOBILE -->
<div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="mobileStudentSidebar" aria-labelledby="mobileStudentSidebarLabel">

    <div class="offcanvas-body">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="rounded-circle overflow-hidden d-flex align-items-center justify-content-center text-white fw-bold" style="width: 40px; height: 40px; background-color: #0b1f5f;">
                <?= htmlspecialchars($studentData['student_initials']); ?>
            </div>
            <div>
                <div class="fw-bold">Student Hub</div>
                <div class="small text-secondary text-uppercase"><?= htmlspecialchars($studentData['class_year']); ?></div>
            </div>
        </div>

        <div class="nav flex-column gap-2">
            <a href="student_dashboard.php"
                class="nav-link rounded-4 px-3 py-3 fw-semibold <?= $currentStudentPage === 'dashboard' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-house-fill me-3"></i>Dashboard
            </a>

            <a href="#"
                class="nav-link rounded-4 px-3 py-3 fw-medium <?= $currentStudentPage === 'profile' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-person-fill me-3"></i>Profile
            </a>

            <a href="#"
                class="nav-link rounded-4 px-3 py-3 fw-medium <?= $currentStudentPage === 'enrollment' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-briefcase-fill me-3"></i>Enrollment
            </a>

            <a href="#"
                class="nav-link rounded-4 px-3 py-3 fw-medium <?= $currentStudentPage === 'documents' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-cloud-arrow-up-fill me-3"></i>Documents
            </a>

            <a href="#"
                class="nav-link rounded-4 px-3 py-3 fw-medium <?= $currentStudentPage === 'status' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-check2-square me-3"></i>Status
            </a>

            <a href="#" class="nav-link rounded-4 px-3 py-3 text-secondary fw-medium <?= $currentStudentPage === 'settings' ? 'text-white currentlySelected' : 'text-secondary menuHover' ?>">
                <i class="bi bi-gear-fill me-3"></i>Settings
            </a>

            <a href="#" class="nav-link rounded-4 px-3 py-3 fw-medium logoutHover">
                <i class="bi bi-box-arrow-left me-3"></i>Logout
            </a>
        </div>
    </div>
</div>