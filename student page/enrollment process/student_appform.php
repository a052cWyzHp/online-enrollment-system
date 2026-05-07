<?php
$formMessage = null;
$formSubmittedSuccessfully = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application = new StudentEnrollmentApplication($_POST);
    $formMessage = $application->submit();

    if ($application->isSuccessful()) {
        $formSubmittedSuccessfully = true;

        // need to i-replace sa future, need to be connected to database, must be based on users.application_status
        $_SESSION['mock_application_status'] = 'documents_pending';
    }
}

// tried making it OOP
class StudentEnrollmentApplication
{
    private array $formData;
    private bool $successful = false;

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
        'guardian_contact_number' => 'Guardian Contact Number',

        'confirmation' => 'Confirmation Checkbox'
    ];

    public function __construct(array $formData)
    {
        $this->formData = $formData;
    }

    public function submit(): string
    {
        $errors = $this->validate();

        if (!empty($errors)) {
            return 'Please complete the following required fields: ' . implode(', ', $errors) . '.';
        }
        $cleanData = $this->sanitizeFormData();
        $this->successful = true;
        return 'Application form submitted successfully. You may now proceed to uploading your documents.';
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    private function validate(): array
    {
        $errors = [];

        foreach ($this->requiredFields as $fieldName => $label) {
            if (!isset($this->formData[$fieldName]) || trim((string) $this->formData[$fieldName]) === '') {
                $errors[] = $label;
            }
        }

        if (isset($this->formData['confirmation']) && $this->formData['confirmation'] !== '1') {
            $errors[] = 'Confirmation Checkbox';
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

<?php if ($formMessage): ?>
    <div class="alert <?= $formSubmittedSuccessfully ? 'alert-success' : 'alert-warning'; ?> rounded-4 border-0 shadow-sm mb-4">
        <?= htmlspecialchars($formMessage); ?>
    </div>
<?php endif; ?>

<?php if ($formSubmittedSuccessfully): ?>

    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5 text-center">
            <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4"
                 style="width: 80px; height: 80px; background-color: #dcfce7;">
                <i class="bi bi-check-circle-fill fs-1" style="color: #16a34a;"></i>
            </div>

            <h2 class="fw-bold mb-3" style="color: #0b1f5f;">
                Application Form Completed
            </h2>

            <p class="fs-5 text-secondary mx-auto mb-4" style="max-width: 720px;">
                Your application form has been submitted. The next step is to upload your required documents.
            </p>

            <a href="student_enrollment.php"
               class="btn text-white rounded-3 px-4 py-3 fw-semibold"
               style="background-color: #052c65;">
                Continue to Document Upload
                <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>
    </section>

<?php else: ?>

<form method="post" action="student_enrollment.php">

    <!-- PERSONAL INFORMATION -->
    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="rounded-4 d-flex align-items-center justify-content-center"
                     style="width: 52px; height: 52px; background-color: #eff6ff;">
                    <i class="bi bi-person-fill fs-4" style="color: #0b1f5f;"></i>
                </div>
                <h2 class="fw-bold mb-0" style="color: #0b1f5f;">Personal Information</h2>
            </div>

            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <label class="form-label small fw-bold text-uppercase">Full Name</label>
                    <input type="text" name="full_name" class="form-control form-control-lg border-0 bg-light" placeholder="e.g. Juan Dela Cruz" required>
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
                    <input type="email" name="email_address" class="form-control form-control-lg border-0 bg-light" placeholder="student@example.com" required>
                </div>
            </div>
        </div>
    </section>

    <!-- ADDRESS INFORMATION -->
    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="rounded-4 d-flex align-items-center justify-content-center"
                     style="width: 52px; height: 52px; background-color: #eff6ff;">
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
                <div class="rounded-4 d-flex align-items-center justify-content-center"
                     style="width: 52px; height: 52px; background-color: #eff6ff;">
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
                <div class="rounded-4 d-flex align-items-center justify-content-center"
                     style="width: 52px; height: 52px; background-color: #eff6ff;">
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

    <!-- GUARDIAN OR PARENTS CONTACT -->
    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="rounded-4 d-flex align-items-center justify-content-center"
                     style="width: 52px; height: 52px; background-color: #eff6ff;">
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

    <!-- confirmation checkbox -->
    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="confirmation" value="1" id="confirmation" required>
                <label class="form-check-label fw-semibold" for="confirmation">
                    I confirm that the information provided is true and correct.
                </label>
            </div>
        </div>
    </section>

    <!-- clear everything and submit -->
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-5">
        <a href="student_dashboard.php" class="btn btn-light border rounded-3 px-4 py-3 fw-semibold">
            <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
        </a>

        <div class="d-flex flex-column flex-sm-row gap-3">
            <button type="reset" class="btn btn-light border rounded-3 px-4 py-3 fw-semibold">
                Clear Form
            </button>

            <button type="submit" class="btn text-white rounded-3 px-4 py-3 fw-semibold" style="background-color: #052c65;">
                Submit Application
                <i class="bi bi-arrow-right ms-2"></i>
            </button>
        </div>
    </div>

</form>

<?php endif; ?>