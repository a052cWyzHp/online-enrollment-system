<?php
$student = fetchStudent($pdo, $userId);

$appFormMessage = null;
$appFormSuccessful = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'submit_application') {
    $applicationForm = new StudentEnrollmentApplicationForm($pdo, $_POST, $student, $currentApplication, $userId);
    $appFormMessage = $applicationForm->submit();
    $appFormSuccessful = $applicationForm->isSuccessful();

    if ($appFormSuccessful) {
        $currentApplication = fetchLatestApplication($pdo, (int) $student['student_id']);
        $applicationStatus = $currentApplication['application_status'] ?? 'documents_pending';
    }
}

class StudentEnrollmentApplicationForm
{
    private PDO $pdo;
    private array $formData;
    private array $student;
    private ?array $currentApplication;
    private int $userId;
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

    public function __construct(PDO $pdo, array $formData, array $student, ?array $currentApplication, int $userId)
    {
        $this->pdo = $pdo;
        $this->formData = $formData;
        $this->student = $student;
        $this->currentApplication = $currentApplication;
        $this->userId = $userId;
    }

    public function submit(): string
    {
        $errors = $this->validate();

        if (!empty($errors)) {
            return 'Please complete the following required fields: ' . implode(', ', $errors) . '.';
        }

        try {
            $this->pdo->beginTransaction();

            $cleanData = $this->sanitizeFormData();
            $oldStatus = $this->currentApplication['application_status'] ?? null;
            $newStatus = 'documents_pending';

            $this->updateUser($cleanData);
            $this->updateStudentProfile($cleanData);
            $applicationId = $this->saveEnrollmentApplication($cleanData, $newStatus);

            insertStatusHistory(
                $this->pdo,
                $applicationId,
                $oldStatus,
                $newStatus,
                $this->userId,
                'Student submitted the enrollment application form.'
            );

            $this->pdo->commit();
            $this->successful = true;

            return 'Application form submitted successfully. You may now proceed to uploading your documents.';
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return 'Application submission failed. Please check your database fields and try again. Error: ' . $e->getMessage();
        }
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

        if (!empty($this->formData['email_address']) && !filter_var($this->formData['email_address'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid Email Address';
        }

        if (!empty($this->formData['preferred_course'])) {
            $stmt = $this->pdo->prepare("
                                            SELECT program_id 
                                            FROM programs 
                                            WHERE program_id = :program_id AND is_active = 1 
                                            LIMIT 1
                                        ");
            $stmt->execute([':program_id' => (int) $this->formData['preferred_course']]);

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = 'Valid Preferred Course';
            }
        }

        return array_unique($errors);
    }

    private function sanitizeFormData(): array
    {
        $cleanData = [];

        foreach ($this->formData as $key => $value) {
            $cleanData[$key] = is_string($value) ? trim($value) : $value;
        }

        return $cleanData;
    }

    private function updateUser(array $cleanData): void
    {
        $stmt = $this->pdo->prepare("
                                        SELECT user_id 
                                        FROM users 
                                        WHERE email = :email AND user_id <> :user_id 
                                        LIMIT 1
                                    ");
        $stmt->execute([
            ':email' => $cleanData['email_address'],
            ':user_id' => $this->userId
        ]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception('The email address is already used by another account.');
        }

        $stmt = $this->pdo->prepare("
                                        UPDATE users 
                                        SET full_name = :full_name, email = :email 
                                        WHERE user_id = :user_id
                                    ");
        $stmt->execute([
            ':full_name' => $cleanData['full_name'],
            ':email' => $cleanData['email_address'],
            ':user_id' => $this->userId
        ]);

        $_SESSION['full_name'] = $cleanData['full_name'];
        $_SESSION['email'] = $cleanData['email_address'];
    }

    private function updateStudentProfile(array $cleanData): void
    {
        $stmt = $this->pdo->prepare("
                                    UPDATE student_profiles 
                                    SET 
                                        birth_date = :birth_date, 
                                        gender = :gender, 
                                        phone = :phone, address = :address, 
                                        guardian_name = :guardian_name, 
                                        guardian_phone = :guardian_phone, 
                                        nationality = :nationality, 
                                        street_address = :street_address, 
                                        city = :city, province = :province, 
                                        zip_code = :zip_code, 
                                        guardian_relationship = :guardian_relationship 
                                    WHERE student_id = :student_id
                                ");

        $fullAddress = $cleanData['street_address'] . ', ' . $cleanData['city'] . ', ' . $cleanData['province'] . ' ' . $cleanData['zip_code'];

        $stmt->execute([
            ':birth_date' => $cleanData['date_of_birth'],
            ':gender' => $cleanData['gender'],
            ':phone' => $cleanData['contact_number'],
            ':address' => $fullAddress,
            ':guardian_name' => $cleanData['guardian_name'],
            ':guardian_phone' => $cleanData['guardian_contact_number'],
            ':nationality' => $cleanData['nationality'],
            ':street_address' => $cleanData['street_address'],
            ':city' => $cleanData['city'],
            ':province' => $cleanData['province'],
            ':zip_code' => $cleanData['zip_code'],
            ':guardian_relationship' => $cleanData['guardian_relationship'],
            ':student_id' => (int) $this->student['student_id']
        ]);
    }

    private function saveEnrollmentApplication(array $cleanData, string $newStatus): int
    {
        $studentId = (int) $this->student['student_id'];
        $programId = (int) $cleanData['preferred_course'];
        $schoolYear = date('Y') . '-' . (date('Y') + 1);

        if ($this->currentApplication) {
            $stmt = $this->pdo->prepare("
                                            UPDATE enrollment_applications 
                                            SET 
                                                program_id = :program_id, 
                                                school_year = :school_year, 
                                                previous_school = :previous_school, 
                                                previous_school_address = :previous_school_address, 
                                                year_graduated = :year_graduated, 
                                                entry_type = 'new', 
                                                application_status = :application_status, 
                                                submitted_at = COALESCE(submitted_at, NOW()) 
                                            WHERE application_id = :application_id
                                        ");
            $stmt->execute([
                ':program_id' => $programId,
                ':school_year' => $schoolYear,
                ':previous_school' => $cleanData['last_school_attended'],
                ':previous_school_address' => $cleanData['school_address'],
                ':year_graduated' => $cleanData['year_graduated'],
                ':application_status' => $newStatus,
                ':application_id' => (int) $this->currentApplication['application_id']
            ]);

            return (int) $this->currentApplication['application_id'];
        }

        $stmt = $this->pdo->prepare("
                                        INSERT INTO enrollment_applications (student_id, program_id, school_year, previous_school, previous_school_address, year_graduated, entry_type, application_status, submitted_at) 
                                        VALUES (:student_id, :program_id, :school_year, :previous_school, :previous_school_address, :year_graduated, 'new', :application_status, NOW())
                                    ");
        $stmt->execute([
            ':student_id' => $studentId,
            ':program_id' => $programId,
            ':school_year' => $schoolYear,
            ':previous_school' => $cleanData['last_school_attended'],
            ':previous_school_address' => $cleanData['school_address'],
            ':year_graduated' => $cleanData['year_graduated'],
            ':application_status' => $newStatus
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
?>

<?php if ($appFormMessage): ?>
    <div class="alert <?= $appFormSuccessful ? 'alert-success' : 'alert-warning'; ?> rounded-4 border-0 shadow-sm mb-4">
        <?= htmlspecialchars($appFormMessage); ?>
    </div>
<?php endif; ?>

<?php if ($appFormSuccessful): ?>
    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5 text-center">
            <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4" style="width: 80px; height: 80px; background-color: #dcfce7;">
                <i class="bi bi-check-circle-fill fs-1" style="color: #16a34a;"></i>
            </div>
            <h2 class="fw-bold mb-3" style="color: #0b1f5f;">Application Form Completed</h2>
            <p class="fs-5 text-secondary mx-auto mb-4" style="max-width: 720px;">Your application form has been saved in the database. The next step is to upload your required documents.</p>
            <a href="student_enrollment.php" class="btn text-white rounded-3 px-4 py-3 fw-semibold" style="background-color: #052c65;">Continue to Document Upload <i class="bi bi-arrow-right ms-2"></i></a>
        </div>
    </section>
<?php else: ?>

<form method="post" action="student_enrollment.php">
    <input type="hidden" name="form_action" value="submit_application">

    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #eff6ff;"><i class="bi bi-person-fill fs-4" style="color: #0b1f5f;"></i></div>
                <h2 class="fw-bold mb-0" style="color: #0b1f5f;">Personal Information</h2>
            </div>
            <div class="row g-4">
                <div class="col-12 col-md-6"><label class="form-label small fw-bold text-uppercase">Full Name</label><input type="text" name="full_name" class="form-control form-control-lg border-0 bg-light" value="<?= htmlspecialchars($student['full_name'] ?? '') ?>" required></div>
                <div class="col-12 col-md-6"><label class="form-label small fw-bold text-uppercase">Date of Birth</label><input type="date" name="date_of_birth" class="form-control form-control-lg border-0 bg-light" required></div>
                <div class="col-12 col-md-6"><label class="form-label small fw-bold text-uppercase">Gender</label><select name="gender" class="form-select form-select-lg border-0 bg-light" required><option value="">Select Option</option><option value="Female">Female</option><option value="Male">Male</option><option value="Prefer not to say">Prefer not to say</option></select></div>
                <div class="col-12 col-md-6"><label class="form-label small fw-bold text-uppercase">Nationality</label><input type="text" name="nationality" class="form-control form-control-lg border-0 bg-light" placeholder="e.g. Filipino" required></div>
                <div class="col-12 col-md-6"><label class="form-label small fw-bold text-uppercase">Contact Number</label><input type="text" name="contact_number" class="form-control form-control-lg border-0 bg-light" placeholder="+63 900 000 0000" required></div>
                <div class="col-12 col-md-6"><label class="form-label small fw-bold text-uppercase">Email Address</label><input type="email" name="email_address" class="form-control form-control-lg border-0 bg-light" value="<?= htmlspecialchars($student['email'] ?? '') ?>" required></div>
            </div>
        </div>
    </section>

    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-center gap-3 mb-4"><div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #eff6ff;"><i class="bi bi-geo-alt-fill fs-4" style="color: #0b1f5f;"></i></div><h2 class="fw-bold mb-0" style="color: #0b1f5f;">Address Information</h2></div>
            <div class="row g-4">
                <div class="col-12"><label class="form-label small fw-bold text-uppercase">Street Address</label><textarea name="street_address" class="form-control form-control-lg border-0 bg-light" rows="3" required></textarea></div>
                <div class="col-12 col-md-4"><label class="form-label small fw-bold text-uppercase">City / Municipality</label><input type="text" name="city" class="form-control form-control-lg border-0 bg-light" required></div>
                <div class="col-12 col-md-4"><label class="form-label small fw-bold text-uppercase">Province</label><input type="text" name="province" class="form-control form-control-lg border-0 bg-light" required></div>
                <div class="col-12 col-md-4"><label class="form-label small fw-bold text-uppercase">Zip Code</label><input type="text" name="zip_code" class="form-control form-control-lg border-0 bg-light" required></div>
            </div>
        </div>
    </section>

    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-center gap-3 mb-4"><div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #eff6ff;"><i class="bi bi-mortarboard-fill fs-4" style="color: #0b1f5f;"></i></div><h2 class="fw-bold mb-0" style="color: #0b1f5f;">Academic Background</h2></div>
            <div class="row g-4">
                <div class="col-12 col-md-6"><label class="form-label small fw-bold text-uppercase">Last School Attended</label><input type="text" name="last_school_attended" class="form-control form-control-lg border-0 bg-light" required></div>
                <div class="col-12 col-md-6"><label class="form-label small fw-bold text-uppercase">Year Graduated</label><input type="number" name="year_graduated" class="form-control form-control-lg border-0 bg-light" min="1900" max="2100" required></div>
                <div class="col-12"><label class="form-label small fw-bold text-uppercase">School Address</label><textarea name="school_address" class="form-control form-control-lg border-0 bg-light" rows="3" required></textarea></div>
            </div>
        </div>
    </section>

    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-center gap-3 mb-4"><div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #eff6ff;"><i class="bi bi-journal-bookmark-fill fs-4" style="color: #0b1f5f;"></i></div><h2 class="fw-bold mb-0" style="color: #0b1f5f;">Program or Course Selection</h2></div>
            <div class="row g-4">
                <div class="col-12 col-md-8">
                    <label class="form-label small fw-bold text-uppercase">Preferred Course</label>
                    <select name="preferred_course" class="form-select form-select-lg border-0 bg-light" required>
                        <option value="">Select Course</option>
                        <?php foreach ($programs as $program): ?>
                            <?php $slots = is_null($program['slots_available']) ? 'Slots not set' : ((int) $program['slots_available'] . ' slots left'); ?>
                            <option value="<?= (int) $program['program_id']; ?>">
                                <?= htmlspecialchars($program['program_name']); ?><?= !empty($program['program_code']) ? ' (' . htmlspecialchars($program['program_code']) . ')' : ''; ?> - <?= htmlspecialchars($slots); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12"><div class="alert alert-light border rounded-4 mb-0"><i class="bi bi-info-circle-fill me-2" style="color: #0b1f5f;"></i>Course availability and final enrollment approval will be confirmed by the registrar.</div></div>
            </div>
        </div>
    </section>

    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-center gap-3 mb-4"><div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #eff6ff;"><i class="bi bi-people-fill fs-4" style="color: #0b1f5f;"></i></div><h2 class="fw-bold mb-0" style="color: #0b1f5f;">Guardian / Emergency Contact</h2></div>
            <div class="row g-4">
                <div class="col-12 col-md-4"><label class="form-label small fw-bold text-uppercase">Parent or Guardian Name</label><input type="text" name="guardian_name" class="form-control form-control-lg border-0 bg-light" required></div>
                <div class="col-12 col-md-4"><label class="form-label small fw-bold text-uppercase">Relationship</label><input type="text" name="guardian_relationship" class="form-control form-control-lg border-0 bg-light" required></div>
                <div class="col-12 col-md-4"><label class="form-label small fw-bold text-uppercase">Contact Number</label><input type="text" name="guardian_contact_number" class="form-control form-control-lg border-0 bg-light" required></div>
            </div>
        </div>
    </section>

    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="confirmation" value="1" id="confirmation" required>
                <label class="form-check-label fw-semibold" for="confirmation">I confirm that the information provided is true and correct.</label>
            </div>
        </div>
    </section>

    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-5">
        <a href="student_dashboard.php" class="btn btn-light border rounded-3 px-4 py-3 fw-semibold"><i class="bi bi-arrow-left me-2"></i>Back to Dashboard</a>
        <div class="d-flex flex-column flex-sm-row gap-3">
            <button type="reset" class="btn btn-light border rounded-3 px-4 py-3 fw-semibold">Clear Form</button>
            <button type="submit" class="btn text-white rounded-3 px-4 py-3 fw-semibold" style="background-color: #052c65;">Submit Application <i class="bi bi-arrow-right ms-2"></i></button>
        </div>
    </div>
</form>
<?php endif; ?>