<?php
$student = fetchStudent($pdo, $userId);

$paymentMessage = null;
$paymentSuccessful = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_submit'])) {
    $payment = new StudentReservationPayment($pdo, $_FILES, $currentApplication, $userId);
    $paymentMessage = $payment->submit();
    $paymentSuccessful = $payment->isSuccessful();

    if ($paymentSuccessful) {
        $currentApplication = fetchLatestApplication($pdo, (int) $student['student_id']);
        $applicationStatus = $currentApplication['application_status'] ?? 'payment_submitted';
    }
}

class StudentReservationPayment
{
    private PDO $pdo;
    private array $files;
    private ?array $application;
    private int $userId;
    private bool $successful = false;

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
            return 'No approved application was found for this payment.';
        }

        $errors = $this->validateProofOfPayment();

        if (!empty($errors)) {
            return implode(' ', $errors);
        }

        try {
            $this->pdo->beginTransaction();

            $applicationId = (int) $this->application['application_id'];
            $oldStatus = $this->application['application_status'];
            $newStatus = 'payment_submitted';
            $proofPath = $this->storeFile($this->files['proof_of_payment'], 'payments');

            $this->savePayment($applicationId, $proofPath);

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
                'Student submitted proof of reservation payment.'
            );

            $this->pdo->commit();
            $this->successful = true;

            return 'Proof of payment submitted successfully. Please wait while the registrar verifies your payment.';
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return 'Payment submission failed. Please try again. Error: ' . $e->getMessage();
        }
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    private function validateProofOfPayment(): array
    {
        $errors = [];

        if (!isset($this->files['proof_of_payment']) || $this->files['proof_of_payment']['error'] !== UPLOAD_ERR_OK || $this->files['proof_of_payment']['size'] <= 0) {
            $errors[] = 'Please upload your proof of payment before submitting.';
        }

        return $errors;
    }

    private function savePayment(int $applicationId, string $proofPath): void
    {
        $stmt = $this->pdo->prepare("
                                        SELECT payment_id 
                                        FROM payments 
                                        WHERE application_id = :application_id 
                                        ORDER BY payment_id DESC 
                                        LIMIT 1
                                    ");
        $stmt->execute([':application_id' => $applicationId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmt = $this->pdo->prepare("
                                            UPDATE payments 
                                            SET 
                                                amount = 3000.00, 
                                                payment_status = 'submitted', 
                                                proof_of_payment = :proof_of_payment, 
                                                submitted_at = NOW() 
                                            WHERE payment_id = :payment_id
                                        ");
            $stmt->execute([
                ':proof_of_payment' => $proofPath,
                ':payment_id' => (int) $existing['payment_id']
            ]);
            return;
        }

        $stmt = $this->pdo->prepare("
                                        INSERT INTO payments (application_id, amount, payment_status, proof_of_payment, submitted_at) 
                                        VALUES (:application_id, 3000.00, 'submitted', :proof_of_payment, NOW())
                                    ");
        $stmt->execute([
            ':application_id' => $applicationId,
            ':proof_of_payment' => $proofPath
        ]);
    }

    private function storeFile(array $file, string $folder): string
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new Exception('Invalid proof of payment file type. Please upload JPG, JPEG, or PNG.');
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
            throw new Exception('Unable to save uploaded proof of payment.');
        }

        return 'uploads/' . $folder . '/' . $safeFileName;
    }
}
?>

<?php if ($paymentMessage): ?>
    <div class="alert <?= $paymentSuccessful ? 'alert-success' : 'alert-warning'; ?> rounded-4 border-0 shadow-sm mb-4">
        <?= htmlspecialchars($paymentMessage); ?>
    </div>
<?php endif; ?>

<?php if ($paymentSuccessful): ?>
    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5 text-center">
            <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4" style="width: 80px; height: 80px; background-color: #dcfce7;"><i class="bi bi-check-circle-fill fs-1" style="color: #16a34a;"></i></div>
            <h2 class="fw-bold mb-3" style="color: #0b1f5f;">Proof of Payment Submitted</h2>
            <p class="fs-5 text-secondary mx-auto mb-4" style="max-width: 720px;">Your proof of payment has been submitted. This does not automatically complete your enrollment yet. The registrar will manually verify your payment before your enrollment is finalized.</p>
            <a href="student_enrollment.php" class="btn text-white rounded-3 px-4 py-3 fw-semibold" style="background-color: #052c65;">View Enrollment Status <i class="bi bi-arrow-right ms-2"></i></a>
        </div>
    </section>
<?php else: ?>

<form method="post" action="student_enrollment.php" enctype="multipart/form-data">
    <section class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-4 mb-4">
                <div>
                    <p class="text-uppercase fw-bold mb-2" style="letter-spacing: 3px; color: #64748b;">Enrollment Confirmation</p>
                    <h2 class="fw-bold mb-3" style="color: #0b1f5f; font-size: clamp(2rem, 4vw, 3.5rem);">Reservation Payment</h2>
                    <p class="text-secondary fs-5 mb-0" style="max-width: 800px;">Your enrollment application has been approved. To confirm your intention to enroll and reserve your slot, please pay the reservation fee and upload your proof of payment below.</p>
                </div>
                <div class="card border-0 rounded-4 shadow-sm" style="min-width: 260px;"><div class="card-body p-4"><div class="small text-uppercase fw-bold text-secondary mb-2" style="letter-spacing: 2px;">Amount Due</div><div class="fw-bold" style="font-size: 2.5rem; color: #0b1f5f;">₱3,000</div><div class="text-secondary small">Slot reservation fee</div></div></div>
            </div>

            <div class="alert border-0 rounded-4 mb-4" style="background-color: #eff6ff;"><div class="d-flex gap-3"><i class="bi bi-info-circle-fill fs-4" style="color: #0b1f5f;"></i><div><div class="fw-bold" style="color: #0b1f5f;">Why is this payment required?</div><div class="text-secondary">The reservation fee confirms your intention to enroll and allows the university to reserve your slot while the registrar verifies your payment. Your enrollment will still need final approval after verification.</div></div></div></div>

            <div class="row g-4 mb-4">
                <div class="col-12 col-lg-7">
                    <div class="card border-0 rounded-4 h-100" style="background-color: #f8fafc;"><div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-4"><div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #e0f2fe;"><i class="bi bi-bank2 fs-4" style="color: #0b1f5f;"></i></div><div><h4 class="fw-bold mb-1" style="color: #0b1f5f;">Bank Transfer Details</h4><p class="text-secondary mb-0">Please send your reservation payment to the fictional university bank account below.</p></div></div>
                        <div class="row g-3">
                            <div class="col-12"><div class="border rounded-4 p-3 bg-white"><div class="small text-uppercase fw-bold text-secondary mb-1" style="letter-spacing: 2px;">Bank Name</div><div class="fw-semibold fs-5">Academic Trust Bank</div></div></div>
                            <div class="col-12 col-md-6"><div class="border rounded-4 p-3 bg-white"><div class="small text-uppercase fw-bold text-secondary mb-1" style="letter-spacing: 2px;">Account Name</div><div class="fw-semibold fs-5">Digital Atheneum University</div></div></div>
                            <div class="col-12 col-md-6"><div class="border rounded-4 p-3 bg-white"><div class="small text-uppercase fw-bold text-secondary mb-1" style="letter-spacing: 2px;">Account Number</div><div class="fw-semibold fs-5">0923-4567-8910</div></div></div>
                            <div class="col-12"><div class="border rounded-4 p-3 bg-white"><div class="small text-uppercase fw-bold text-secondary mb-1" style="letter-spacing: 2px;">Payment Note</div><div class="fw-semibold">Use your full name as the transfer note or payment reference.</div></div></div>
                        </div>
                    </div></div>
                </div>
                <div class="col-12 col-lg-5"><div class="card border-0 rounded-4 h-100" style="background-color: #052c65;"><div class="card-body p-4 text-white"><div class="rounded-4 d-flex align-items-center justify-content-center mb-4" style="width: 56px; height: 56px; background-color: rgba(255,255,255,0.12);"><i class="bi bi-shield-check fs-3"></i></div><h4 class="fw-bold mb-3">Manual Verification Required</h4><p class="text-white-50 mb-0">Uploading your proof of payment does not automatically approve your enrollment. The admin or registrar will manually review your payment before your enrollment status is updated.</p></div></div></div>
            </div>

            <section class="card border-0 rounded-4 shadow-sm mb-4"><div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4"><div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background-color: #eff6ff;"><i class="bi bi-upload fs-4" style="color: #0b1f5f;"></i></div><div><h4 class="fw-bold mb-1" style="color: #0b1f5f;">Upload Proof of Payment</h4><p class="text-secondary mb-0">Upload a clear image of your payment receipt or bank transfer confirmation.</p></div></div>
                <label class="form-label small fw-bold text-uppercase" style="letter-spacing: 2px;">Proof of Payment</label>
                <input type="file" name="proof_of_payment" class="form-control form-control-lg border-0 bg-light" accept=".jpg,.jpeg,.png" required>
                <div class="form-text mt-2">Accepted image formats: JPG, JPEG, PNG.</div>
            </div></section>

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mt-4">
                <a href="student_dashboard.php" class="btn btn-light border rounded-3 px-4 py-3 fw-semibold"><i class="bi bi-arrow-left me-2"></i>Back to Dashboard</a>
                <button type="submit" name="payment_submit" value="1" class="btn text-white rounded-3 px-4 py-3 fw-semibold" style="background-color: #052c65;">Submit Proof of Payment <i class="bi bi-arrow-right ms-2"></i></button>
            </div>
        </div>
    </section>
</form>
<?php endif; ?>
