<?php
$student = fetchStudent($pdo, $userId);

$documentUploadMessage = null;
$documentUploadSuccessful = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_documents_submit'])) {
    $documentUpload = new StudentDocumentUpload($pdo, $_FILES, $currentApplication, $userId);
    $documentUploadMessage = $documentUpload->submit();
    $documentUploadSuccessful = $documentUpload->isSuccessful();

    if ($documentUploadSuccessful) {
        $currentApplication = fetchLatestApplication($pdo, $studentId);
        $applicationStatus = $currentApplication['application_status'] ?? 'documents_submitted';
    }
}

class StudentDocumentUpload
{
    private PDO $pdo;
    private array $files;
    private ?array $application;
    private int $userId;
    private bool $successful = false;

    private array $requiredFiles = [
        'tor' => 'Transcript of Records',
        'birth_certificate' => 'Birth Certificate',
        'id_picture' => '2x2 ID Picture'
    ];

    private array $optionalFiles = [
        'good_moral' => 'Certificate of Good Moral Character',
        'medical_certificate' => 'Medical Certificate'
    ];

    public function __construct(PDO $pdo, array $files, ?array $application, int $userId)
    {
        $this->pdo = $pdo;
        $this->files = $files;
        $this->application = $application;
        $this->userId = $userId;
    }

    public function submit(): string
    {
        if (!$this->application) {
            return 'No enrollment application was found. Please complete the application form first.';
        }

        $errors = $this->validateRequiredFiles();

        if (!empty($errors)) {
            return 'Please upload the following required documents: ' . implode(', ', $errors) . '.';
        }

        try {
            $this->pdo->beginTransaction();

            $applicationId = (int) $this->application['application_id'];
            $oldStatus = $this->application['application_status'];
            $newStatus = 'documents_submitted';

            foreach ($this->requiredFiles as $fieldName => $documentName) {
                $this->saveDocument($applicationId, $fieldName, $documentName, true);
            }

            foreach ($this->optionalFiles as $fieldName => $documentName) {
                if ($this->hasUploadedFile($fieldName)) {
                    $this->saveDocument($applicationId, $fieldName, $documentName, false);
                }
            }

            $stmt = $this->pdo->prepare("
                                            UPDATE enrollment_applications 
                                            SET 
                                                application_status = :status 
                                            WHERE application_id = :application_id
                                        ");
            $stmt->execute([
                ':status' => $newStatus,
                ':application_id' => $applicationId
            ]);

            insertStatusHistory(
                $this->pdo,
                $applicationId,
                $oldStatus,
                $newStatus,
                $this->userId,
                'Student uploaded the required enrollment documents.'
            );

            $this->pdo->commit();
            $this->successful = true;

            return 'Documents uploaded successfully. Your application is now ready for checking.';
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return 'Document upload failed. Please try again. Error: ' . $e->getMessage();
        }
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    private function validateRequiredFiles(): array
    {
        $errors = [];

        foreach ($this->requiredFiles as $fieldName => $label) {
            if (!$this->hasUploadedFile($fieldName)) {
                $errors[] = $label;
            }
        }

        return $errors;
    }

    private function hasUploadedFile(string $fieldName): bool
    {
        return isset($this->files[$fieldName]) &&
            $this->files[$fieldName]['error'] === UPLOAD_ERR_OK &&
            $this->files[$fieldName]['size'] > 0;
    }

    private function saveDocument(int $applicationId, string $fieldName, string $documentName, bool $isRequired): void
    {
        $documentTypeId = $this->getOrCreateDocumentType($documentName, $isRequired);
        $file = $this->files[$fieldName];
        $storedPath = $this->storeFile($file, 'documents');

        $delete = $this->pdo->prepare("
                                        DELETE FROM uploaded_documents 
                                        WHERE application_id = :application_id AND document_type_id = :document_type_id
                                    ");
        $delete->execute([
            ':application_id' => $applicationId,
            ':document_type_id' => $documentTypeId
        ]);

        $stmt = $this->pdo->prepare("
                                        INSERT INTO uploaded_documents (application_id, document_type_id, file_name, file_path, file_status) 
                                        VALUES (:application_id, :document_type_id, :file_name, :file_path, 'uploaded')
                                    ");
        $stmt->execute([
            ':application_id' => $applicationId,
            ':document_type_id' => $documentTypeId,
            ':file_name' => basename($file['name']),
            ':file_path' => $storedPath
        ]);
    }

    private function getOrCreateDocumentType(string $documentName, bool $isRequired): int
    {
        $stmt = $this->pdo->prepare("
                                        SELECT document_type_id 
                                        FROM document_types 
                                        WHERE document_name = :document_name 
                                        LIMIT 1
                                    ");
        $stmt->execute([':document_name' => $documentName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return (int) $row['document_type_id'];
        }

        $stmt = $this->pdo->prepare("
                                    INSERT INTO document_types (document_name, is_required, allowed_file_types, max_file_size_mb) 
                                    VALUES (:document_name, :is_required, 'pdf,jpg,jpeg,png', 5)
                                    ");
        $stmt->execute([
            ':document_name' => $documentName,
            ':is_required' => $isRequired ? 1 : 0
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function storeFile(array $file, string $folder): string
    {
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new Exception('Invalid file type for ' . basename($file['name']) . '.');
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('File is too large. Maximum file size is 5MB.');
        }

        $uploadDirectory = __DIR__ . '/../../uploads/' . $folder;

        if (!is_dir($uploadDirectory)) {
            mkdir($uploadDirectory, 0777, true);
        }

        $safeFileName = uniqid($folder . '_', true) . '.' . $extension;
        $destination = $uploadDirectory . '/' . $safeFileName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception('Unable to save uploaded file.');
        }

        return 'uploads/' . $folder . '/' . $safeFileName;
    }
}
?>

<?php if ($documentUploadMessage): ?>
    <div class="alert <?= $documentUploadSuccessful ? 'alert-success' : 'alert-warning'; ?> rounded-4 border-0 shadow-sm mb-4">
        <?= htmlspecialchars($documentUploadMessage); ?>
    </div>
<?php endif; ?>

<?php if ($documentUploadSuccessful): ?>
    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5 text-center">
            <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4" style="width: 80px; height: 80px; background-color: #dcfce7;"><i class="bi bi-check-circle-fill fs-1" style="color: #16a34a;"></i></div>
            <h2 class="fw-bold mb-3" style="color: #0b1f5f;">Documents Submitted</h2>
            <p class="fs-5 text-secondary mx-auto mb-4" style="max-width: 720px;">Your documents have been uploaded and saved. The registrar will now check your application and documents.</p>
            <a href="student_enrollment.php" class="btn text-white rounded-3 px-4 py-3 fw-semibold" style="background-color: #052c65;">View Enrollment Status <i class="bi bi-arrow-right ms-2"></i></a>
        </div>
    </section>
<?php else: ?>

<form method="post" action="student_enrollment.php" enctype="multipart/form-data">
    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
                <div>
                    <p class="text-uppercase fw-bold mb-2" style="letter-spacing: 3px; color: #64748b;">Document Upload</p>
                    <h2 class="fw-bold mb-2" style="color: #0b1f5f;">Upload Required Documents</h2>
                    <p class="text-secondary fs-5 mb-0" style="max-width: 750px;">Upload clear scanned copies or photos of your documents. Required documents must be completed before submission.</p>
                </div>
                <div class="rounded-4 px-4 py-3 text-center" style="background-color: #eff6ff;"><div class="fw-bold fs-4" style="color: #0b1f5f;">3</div><div class="small text-uppercase fw-semibold text-secondary">Required Files</div></div>
            </div>

            <div class="alert border-0 rounded-4 mb-4" style="background-color: #eff6ff;">
                <div class="d-flex gap-3"><i class="bi bi-info-circle-fill fs-4" style="color: #0b1f5f;"></i><div><div class="fw-bold" style="color: #0b1f5f;">Upload Reminder</div><div class="text-secondary">Accepted files: PDF, JPG, JPEG, or PNG. Maximum size: 5MB per file.</div></div></div>
            </div>

            <h5 class="fw-bold text-uppercase mb-3" style="letter-spacing: 2px; color: #111827;">Required Documents</h5>
            <div class="list-group rounded-4 overflow-hidden mb-4">
                <?php
                $requiredRows = [
                    ['tor', 'Transcript of Records', 'Required official school record', 'bi-file-earmark-text-fill'],
                    ['birth_certificate', 'Birth Certificate', 'PSA or official birth certificate copy', 'bi-file-person-fill'],
                    ['id_picture', '2x2 ID Picture', 'Recent clear student identification photo', 'bi-person-bounding-box']
                ];
                foreach ($requiredRows as $index => $row):
                ?>
                <div class="list-group-item border-0 <?= $index < 2 ? 'border-bottom' : '' ?> p-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-lg-5"><div class="d-flex align-items-center gap-3"><div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #eff6ff;"><i class="bi <?= $row[3]; ?> fs-4" style="color: #0b1f5f;"></i></div><div><div class="fw-bold fs-5"><?= htmlspecialchars($row[1]); ?></div><div class="text-secondary small"><?= htmlspecialchars($row[2]); ?></div></div></div></div>
                        <div class="col-12 col-lg-3"><span class="badge rounded-pill text-danger-emphasis px-3 py-2" style="background-color: #fee2e2;">Required</span></div>
                        <div class="col-12 col-lg-4"><input type="file" name="<?= htmlspecialchars($row[0]); ?>" class="form-control form-control-lg border-0 bg-light" accept=".pdf,.jpg,.jpeg,.png" required></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <h5 class="fw-bold text-uppercase mb-3" style="letter-spacing: 2px; color: #111827;">Optional Documents</h5>
            <div class="list-group rounded-4 overflow-hidden mb-4">
                <?php
                $optionalRows = [
                    ['good_moral', 'Certificate of Good Moral Character', 'Optional supporting school document', 'bi-award-fill'],
                    ['medical_certificate', 'Medical Certificate', 'Optional health clearance document', 'bi-clipboard2-pulse-fill']
                ];
                foreach ($optionalRows as $index => $row):
                ?>
                <div class="list-group-item border-0 <?= $index < 1 ? 'border-bottom' : '' ?> p-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-lg-5"><div class="d-flex align-items-center gap-3"><div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #f8fafc;"><i class="bi <?= $row[3]; ?> fs-4 text-secondary"></i></div><div><div class="fw-bold fs-5"><?= htmlspecialchars($row[1]); ?></div><div class="text-secondary small"><?= htmlspecialchars($row[2]); ?></div></div></div></div>
                        <div class="col-12 col-lg-3"><span class="badge rounded-pill text-secondary-emphasis px-3 py-2" style="background-color: #e5e7eb;">Optional</span></div>
                        <div class="col-12 col-lg-4"><input type="file" name="<?= htmlspecialchars($row[0]); ?>" class="form-control form-control-lg border-0 bg-light" accept=".pdf,.jpg,.jpeg,.png"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mt-4">
                <a href="student_dashboard.php" class="btn btn-light border rounded-3 px-4 py-3 fw-semibold"><i class="bi bi-arrow-left me-2"></i>Back to Dashboard</a>
                <button type="submit" name="upload_documents_submit" value="1" class="btn text-white rounded-3 px-4 py-3 fw-semibold" style="background-color: #052c65;">Submit Documents <i class="bi bi-arrow-right ms-2"></i></button>
            </div>
        </div>
    </section>
</form>
<?php endif; ?>
