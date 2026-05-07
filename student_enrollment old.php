<?php
$currentStudentPage = 'enrollment';

$studentData = [
    'student_name' => 'John Doe',
    'student_initials' => 'JD',
    'class_year' => 'Class of 2024'
];

$formMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application = new StudentEnrollmentApplication($_POST, $_FILES);
    $formMessage = $application->submit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Enrollment</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light-subtle">

<?php include 'student page/navbar.php'; ?>

<div class="container-fluid">
    <div class="row g-0">

        <?php include 'student page/sidebar.php'; ?>

        <main class="col-12 col-lg-10 col-xl-10 ms-auto px-3 px-md-4 px-lg-5 py-4" style="min-height: calc(100vh - 140px);">

            <div class="mb-4">
                <p class="text-uppercase fw-bold mb-2" style="letter-spacing: 3px; color: #334155;">Admissions Process</p>
                <h1 class="fw-bold mb-3" style="color: #0b1f5f; font-size: clamp(2.2rem, 4vw, 4rem);">
                    Student Enrollment
                </h1>
                <p class="fs-5 text-secondary mb-0" style="max-width: 850px;">
                    Complete the enrollment application below with accurate information. All fields are required.
                </p>
            </div>

            <?php if ($formMessage): ?>
                <div class="alert alert-info rounded-4 border-0 shadow-sm mb-4">
                    <?= htmlspecialchars($formMessage); ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">

                <!-- PERSONAL INFORMATION -->
                <section class="card border-0 rounded-4 shadow-sm mb-4">
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #eff6ff;">
                                <i class="bi bi-person-fill fs-4" style="color: #0b1f5f;"></i>
                            </div>
                            <h2 class="fw-bold mb-0" style="color: #0b1f5f;">Personal Information</h2>
                        </div>

                        <div class="row g-4">
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-uppercase">Full Name</label>
                                <input type="text" name="full_name" class="form-control form-control-lg border-0 bg-light" placeholder="e.g. Elena Rodriguez" required>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-uppercase">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="form-control form-control-lg border-0 bg-light" required>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-uppercase">Gender</label>
                                <select name="gender" class="form-select form-select-lg border-0 bg-light" required>
                                    <option value="">Select Option</option>
                                    <option value="Female">Female</option>
                                    <option value="Male">Male</option>
                                    <option value="Prefer not to say">Prefer not to say</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-uppercase">Nationality</label>
                                <input type="text" name="nationality" class="form-control form-control-lg border-0 bg-light" placeholder="e.g. Filipino" required>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-uppercase">Contact Number</label>
                                <input type="text" name="contact_number" class="form-control form-control-lg border-0 bg-light" placeholder="+63 900 000 0000" required>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-uppercase">Email Address</label>
                                <input type="email" name="email_address" class="form-control form-control-lg border-0 bg-light" placeholder="student@example.edu" required>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ADDRESS INFORMATION -->
                <section class="card border-0 rounded-4 shadow-sm mb-4">
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #eff6ff;">
                                <i class="bi bi-geo-alt-fill fs-4" style="color: #0b1f5f;"></i>
                            </div>
                            <h2 class="fw-bold mb-0" style="color: #0b1f5f;">Address Information</h2>
                        </div>

                        <div class="row g-4">
                            <div class="col-12">
                                <label class="form-label small fw-bold text-uppercase">Street Address</label>
                                <textarea name="street_address" class="form-control form-control-lg border-0 bg-light" rows="3" placeholder="House number, street, subdivision, barangay" required></textarea>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold text-uppercase">City / Municipality</label>
                                <input type="text" name="city" class="form-control form-control-lg border-0 bg-light" placeholder="e.g. Dasmariñas" required>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold text-uppercase">Province</label>
                                <input type="text" name="province" class="form-control form-control-lg border-0 bg-light" placeholder="e.g. Cavite" required>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold text-uppercase">Zip Code</label>
                                <input type="text" name="zip_code" class="form-control form-control-lg border-0 bg-light" placeholder="e.g. 4114" required>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ACADEMIC BACKGROUND -->
                <section class="card border-0 rounded-4 shadow-sm mb-4">
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #eff6ff;">
                                <i class="bi bi-mortarboard-fill fs-4" style="color: #0b1f5f;"></i>
                            </div>
                            <h2 class="fw-bold mb-0" style="color: #0b1f5f;">Academic Background</h2>
                        </div>

                        <div class="row g-4">
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-uppercase">Last School Attended</label>
                                <input type="text" name="last_school_attended" class="form-control form-control-lg border-0 bg-light" placeholder="School name" required>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-uppercase">Year Graduated</label>
                                <input type="number" name="year_graduated" class="form-control form-control-lg border-0 bg-light" placeholder="e.g. 2024" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-bold text-uppercase">School Address</label>
                                <textarea name="school_address" class="form-control form-control-lg border-0 bg-light" rows="3" placeholder="Complete school address" required></textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- PROGRAM SELECTION -->
                <section class="card border-0 rounded-4 shadow-sm mb-4">
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #eff6ff;">
                                <i class="bi bi-journal-bookmark-fill fs-4" style="color: #0b1f5f;"></i>
                            </div>
                            <h2 class="fw-bold mb-0" style="color: #0b1f5f;">Program or Course Selection</h2>
                        </div>

                        <div class="row g-4">
                            <div class="col-12 col-md-8">
                                <label class="form-label small fw-bold text-uppercase">Preferred Course</label>
                                <select name="preferred_course" class="form-select form-select-lg border-0 bg-light" required>
                                    <option value="">Select Course</option>
                                    <option value="Bachelor of Science in Information Technology">Bachelor of Science in Information Technology</option>
                                    <option value="Bachelor of Science in Business Administration">Bachelor of Science in Business Administration</option>
                                    <option value="Bachelor of Arts in Communication">Bachelor of Arts in Communication</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <div class="alert alert-light border rounded-4 mb-0">
                                    <i class="bi bi-info-circle-fill me-2" style="color: #0b1f5f;"></i>
                                    Course availability and final enrollment approval will be confirmed by the registrar.
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- GUARDIAN CONTACT -->
                <section class="card border-0 rounded-4 shadow-sm mb-4">
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #eff6ff;">
                                <i class="bi bi-people-fill fs-4" style="color: #0b1f5f;"></i>
                            </div>
                            <h2 class="fw-bold mb-0" style="color: #0b1f5f;">Guardian / Emergency Contact</h2>
                        </div>

                        <div class="row g-4">
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold text-uppercase">Parent or Guardian Name</label>
                                <input type="text" name="guardian_name" class="form-control form-control-lg border-0 bg-light" placeholder="Full name" required>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold text-uppercase">Relationship</label>
                                <input type="text" name="guardian_relationship" class="form-control form-control-lg border-0 bg-light" placeholder="e.g. Mother, Father, Guardian" required>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold text-uppercase">Contact Number</label>
                                <input type="text" name="guardian_contact_number" class="form-control form-control-lg border-0 bg-light" placeholder="+63 900 000 0000" required>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- DOCUMENT REQUIREMENTS -->
                <section class="card border-0 rounded-4 shadow-sm mb-4">
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #eff6ff;">
                                <i class="bi bi-cloud-upload-fill fs-4" style="color: #0b1f5f;"></i>
                            </div>
                            <h2 class="fw-bold mb-0" style="color: #0b1f5f;">Document Requirements</h2>
                        </div>

                        <div class="row g-4">
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold text-uppercase">TOR</label>
                                <input type="file" name="tor" class="form-control form-control-lg border-0 bg-light" required>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold text-uppercase">Birth Certificate</label>
                                <input type="file" name="birth_certificate" class="form-control form-control-lg border-0 bg-light" required>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold text-uppercase">ID Picture</label>
                                <input type="file" name="id_picture" class="form-control form-control-lg border-0 bg-light" required>
                            </div>

                            <div class="col-12">
                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" name="confirmation" value="1" id="confirmation" required>
                                    <label class="form-check-label fw-semibold" for="confirmation">
                                        I confirm that the information provided is true and correct.
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- FORM BUTTONS -->
                <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-5">
                    <a href="student_dashboard.php" class="btn btn-light border rounded-3 px-4 py-3 fw-semibold">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>

                    <div class="d-flex flex-column flex-sm-row gap-3">
                        <button type="reset" class="btn btn-light border rounded-3 px-4 py-3 fw-semibold">
                            Clear Form
                        </button>

                        <button type="submit" class="btn text-white rounded-3 px-4 py-3 fw-semibold" style="background-color: #052c65;">
                            Submit Application <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>

            </form>

        </main>
    </div>
</div>

<?php include 'student page/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php

class StudentEnrollmentApplication
{
    private array $formData;
    private array $files;

    private array $requiredFields = [
        'full_name' => 'Full Name',
        'date_of_birth' => 'Date of Birth',
        'gender' => 'Gender',
        'nationality' => 'Nationality',
        'contact_number' => 'Contact Number',
        'email_address' => 'Email Address',
        'street_address' => 'Street Address',
        'city' => 'City / Municipality',
        'province' => 'Province',
        'zip_code' => 'Zip Code',
        'last_school_attended' => 'Last School Attended',
        'school_address' => 'School Address',
        'year_graduated' => 'Year Graduated',
        'preferred_course' => 'Preferred Course',
        'guardian_name' => 'Parent or Guardian Name',
        'guardian_relationship' => 'Relationship',
        'guardian_contact_number' => 'Guardian Contact Number'
    ];

    private array $requiredFiles = [
        'tor' => 'TOR',
        'birth_certificate' => 'Birth Certificate',
        'id_picture' => 'ID Picture'
    ];

    public function __construct(array $formData, array $files)
    {
        $this->formData = $formData;
        $this->files = $files;
    }

    public function submit(): string
    {
        $errors = $this->validate();

        if (!empty($errors)) {
            return 'Please complete the following required fields: ' . implode(', ', $errors) . '.';
        }

        $cleanData = $this->sanitizeFormData();

        /*
        Future database insertion:

        $repository = new EnrollmentApplicationRepository($conn);
        $repository->save($cleanData, $this->files);

        Future file upload handling:

        $uploadService = new EnrollmentDocumentUploadService();
        $uploadService->upload($this->files);
        */

        return 'Enrollment application received. Database saving is not yet connected.';
    }

    private function validate(): array
    {
        $errors = [];

        foreach ($this->requiredFields as $fieldName => $label) {
            if (
                !isset($this->formData[$fieldName]) ||
                trim($this->formData[$fieldName]) === ''
            ) {
                $errors[] = $label;
            }
        }

        foreach ($this->requiredFiles as $fieldName => $label) {
            if (
                !isset($this->files[$fieldName]) ||
                $this->files[$fieldName]['error'] !== UPLOAD_ERR_OK ||
                $this->files[$fieldName]['size'] <= 0
            ) {
                $errors[] = $label;
            }
        }

        if (
            !isset($this->formData['confirmation']) ||
            $this->formData['confirmation'] !== '1'
        ) {
            $errors[] = 'Confirmation checkbox';
        }

        return $errors;
    }

    private function sanitizeFormData(): array
    {
        $cleanData = [];

        foreach ($this->formData as $key => $value) {
            if (is_string($value)) {
                $cleanData[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            } else {
                $cleanData[$key] = $value;
            }
        }

        return $cleanData;
    }
}

?>