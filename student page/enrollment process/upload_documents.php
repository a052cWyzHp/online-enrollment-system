<?php
$documentUploadMessage = null;
$documentUploadSuccessful = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_documents_submit'])) {
    $documentUpload = new StudentDocumentUpload($_FILES);
    $documentUploadMessage = $documentUpload->submit();

    if ($documentUpload->isSuccessful()) {
        $documentUploadSuccessful = true;

        // replace this in the future to be connected to database
        $_SESSION['mock_application_status'] = 'documents_submitted';
    }
}

class StudentDocumentUpload
{
    private array $files;
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

    public function __construct(array $files)
    {
        $this->files = $files;
    }

    public function submit(): string
    {
        $errors = $this->validateRequiredFiles();

        if (!empty($errors)) {
            return 'Please upload the following required documents: ' . implode(', ', $errors) . '.';
        }
        $this->successful = true;
        return 'Documents uploaded successfully. Your application is now ready for checking.';
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    private function validateRequiredFiles(): array
    {
        $errors = [];

        foreach ($this->requiredFiles as $fieldName => $label) {
            if (
                !isset($this->files[$fieldName]) ||
                $this->files[$fieldName]['error'] !== UPLOAD_ERR_OK ||
                $this->files[$fieldName]['size'] <= 0
            ) {
                $errors[] = $label;
            }
        }

        return $errors;
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
            <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4"
                 style="width: 80px; height: 80px; background-color: #dcfce7;">
                <i class="bi bi-check-circle-fill fs-1" style="color: #16a34a;"></i>
            </div>

            <h2 class="fw-bold mb-3" style="color: #0b1f5f;">
                Documents Submitted
            </h2>

            <p class="fs-5 text-secondary mx-auto mb-4" style="max-width: 720px;">
                Your required documents have been uploaded. The registrar will now check your application and documents.
            </p>

            <a href="student_enrollment.php"
               class="btn text-white rounded-3 px-4 py-3 fw-semibold"
               style="background-color: #052c65;">
                View Enrollment Status
                <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>
    </section>

<?php else: ?>

<form method="post" action="student_enrollment.php" enctype="multipart/form-data">

    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5">

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
                <div>
                    <p class="text-uppercase fw-bold mb-2" style="letter-spacing: 3px; color: #64748b;">
                        Document Upload
                    </p>

                    <h2 class="fw-bold mb-2" style="color: #0b1f5f;">
                        Upload Required Documents
                    </h2>

                    <p class="text-secondary fs-5 mb-0" style="max-width: 750px;">
                        Please upload clear scanned copies or photos of your documents. Required documents must be completed before submission.
                    </p>
                </div>

                <div class="rounded-4 px-4 py-3 text-center" style="background-color: #eff6ff;">
                    <div class="fw-bold fs-4" style="color: #0b1f5f;">3</div>
                    <div class="small text-uppercase fw-semibold text-secondary">Required Files</div>
                </div>
            </div>

            <div class="alert border-0 rounded-4 mb-4" style="background-color: #eff6ff;">
                <div class="d-flex gap-3">
                    <i class="bi bi-info-circle-fill fs-4" style="color: #0b1f5f;"></i>
                    <div>
                        <div class="fw-bold" style="color: #0b1f5f;">Upload Reminder</div>
                        <div class="text-secondary">
                            Accepted files may be PDF, JPG, JPEG, or PNG. File validation and storage will be added when the backend upload handler is connected.
                        </div>
                    </div>
                </div>
            </div>

            <!-- absolutely required documents -->
            <h5 class="fw-bold text-uppercase mb-3" style="letter-spacing: 2px; color: #111827;">
                Required Documents
            </h5>

            <div class="list-group rounded-4 overflow-hidden mb-4">

                <div class="list-group-item border-0 border-bottom p-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-lg-5">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-4 d-flex align-items-center justify-content-center"
                                     style="width: 52px; height: 52px; background-color: #eff6ff;">
                                    <i class="bi bi-file-earmark-text-fill fs-4" style="color: #0b1f5f;"></i>
                                </div>
                                <div>
                                    <div class="fw-bold fs-5">Transcript of Records</div>
                                    <div class="text-secondary small">Required official school record</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-3">
                            <span class="badge rounded-pill text-danger-emphasis px-3 py-2" style="background-color: #fee2e2;">
                                Required
                            </span>
                        </div>

                        <div class="col-12 col-lg-4">
                            <input type="file" name="tor" class="form-control form-control-lg border-0 bg-light" accept=".pdf,.jpg,.jpeg,.png" required>
                        </div>
                    </div>
                </div>

                <div class="list-group-item border-0 border-bottom p-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-lg-5">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-4 d-flex align-items-center justify-content-center"
                                     style="width: 52px; height: 52px; background-color: #eff6ff;">
                                    <i class="bi bi-file-person-fill fs-4" style="color: #0b1f5f;"></i>
                                </div>
                                <div>
                                    <div class="fw-bold fs-5">Birth Certificate</div>
                                    <div class="text-secondary small">PSA or official birth certificate copy</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-3">
                            <span class="badge rounded-pill text-danger-emphasis px-3 py-2" style="background-color: #fee2e2;">
                                Required
                            </span>
                        </div>

                        <div class="col-12 col-lg-4">
                            <input type="file" name="birth_certificate" class="form-control form-control-lg border-0 bg-light" accept=".pdf,.jpg,.jpeg,.png" required>
                        </div>
                    </div>
                </div>

                <div class="list-group-item border-0 p-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-lg-5">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-4 d-flex align-items-center justify-content-center"
                                     style="width: 52px; height: 52px; background-color: #eff6ff;">
                                    <i class="bi bi-person-bounding-box fs-4" style="color: #0b1f5f;"></i>
                                </div>
                                <div>
                                    <div class="fw-bold fs-5">2x2 ID Picture</div>
                                    <div class="text-secondary small">Recent clear student identification photo</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-3">
                            <span class="badge rounded-pill text-danger-emphasis px-3 py-2" style="background-color: #fee2e2;">
                                Required
                            </span>
                        </div>

                        <div class="col-12 col-lg-4">
                            <input type="file" name="id_picture" class="form-control form-control-lg border-0 bg-light" accept=".pdf,.jpg,.jpeg,.png" required>
                        </div>
                    </div>
                </div>

            </div>

            <!-- optional documents -->
            <h5 class="fw-bold text-uppercase mb-3" style="letter-spacing: 2px; color: #111827;">
                Optional Documents
            </h5>

            <div class="list-group rounded-4 overflow-hidden mb-4">

                <div class="list-group-item border-0 border-bottom p-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-lg-5">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-4 d-flex align-items-center justify-content-center"
                                     style="width: 52px; height: 52px; background-color: #f8fafc;">
                                    <i class="bi bi-award-fill fs-4 text-secondary"></i>
                                </div>
                                <div>
                                    <div class="fw-bold fs-5">Certificate of Good Moral Character</div>
                                    <div class="text-secondary small">Optional supporting school document</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-3">
                            <span class="badge rounded-pill text-secondary-emphasis px-3 py-2" style="background-color: #e5e7eb;">
                                Optional
                            </span>
                        </div>

                        <div class="col-12 col-lg-4">
                            <input type="file" name="good_moral" class="form-control form-control-lg border-0 bg-light" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    </div>
                </div>

                <div class="list-group-item border-0 p-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-lg-5">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-4 d-flex align-items-center justify-content-center"
                                     style="width: 52px; height: 52px; background-color: #f8fafc;">
                                    <i class="bi bi-clipboard2-pulse-fill fs-4 text-secondary"></i>
                                </div>
                                <div>
                                    <div class="fw-bold fs-5">Medical Certificate</div>
                                    <div class="text-secondary small">Optional health clearance document</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-3">
                            <span class="badge rounded-pill text-secondary-emphasis px-3 py-2" style="background-color: #e5e7eb;">
                                Optional
                            </span>
                        </div>

                        <div class="col-12 col-lg-4">
                            <input type="file" name="medical_certificate" class="form-control form-control-lg border-0 bg-light" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    </div>
                </div>

            </div>

            <!-- buttons -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mt-4">
                <a href="student_dashboard.php" class="btn btn-light border rounded-3 px-4 py-3 fw-semibold">
                    <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                </a>

                <button type="submit"
                        name="upload_documents_submit"
                        value="1"
                        class="btn text-white rounded-3 px-4 py-3 fw-semibold"
                        style="background-color: #052c65;">
                    Submit Documents
                    <i class="bi bi-arrow-right ms-2"></i>
                </button>
            </div>

        </div>
    </section>

</form>

<?php endif; ?>