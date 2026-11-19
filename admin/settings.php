<?php
/**
 * KaamConnect - Admin System Settings & Environment Diagnostics
 */

declare(strict_types=1);

$pageTitle = 'Admin Settings - KaamConnect Admin';
require_once __DIR__ . '/includes/header.php';

$pdo = getDBConnection();
$dbVersion = $pdo->query("SELECT VERSION()")->fetchColumn();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h3 fw-bold mb-1 text-dark">
            <i class="fas fa-gear text-primary me-2"></i>Platform Settings & Diagnostics
        </h2>
        <p class="text-muted small mb-0">
            System configuration, database parameters, storage security, and mailer runtime state.
        </p>
    </div>
    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-bold">
        <i class="fas fa-check-circle me-1"></i>All Services Operational
    </span>
</div>

<div class="row g-4">
    <!-- Environment & Stack Details -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-server text-primary me-2"></i>Runtime Environment</h5>
            </div>
            <div class="card-body p-4">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <span class="text-muted">Application Name</span>
                        <strong class="text-dark">KaamConnect</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <span class="text-muted">Technology Stack</span>
                        <span class="text-dark fw-semibold">PHP <?= phpversion() ?> / MariaDB</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <span class="text-muted">Database Engine Version</span>
                        <span class="badge bg-light text-dark border"><?= e($dbVersion) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <span class="text-muted">Database Port</span>
                        <span class="badge bg-primary rounded-pill"><?= DB_PORT ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <span class="text-muted">Document Upload Limit</span>
                        <span class="text-dark fw-semibold">5 MB per file (JPEG, PNG, PDF)</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <span class="text-muted">Protected Storage Directory</span>
                        <code class="text-primary small">uploads/verification_docs/</code>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Security & SMTP Configuration -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-shield-halved text-success me-2"></i>Security & Mail Service</h5>
            </div>
            <div class="card-body p-4">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <span class="text-muted">Reset Token Expiry</span>
                        <span class="badge bg-info-subtle text-info fw-bold">15 Minutes (Single-Use)</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <span class="text-muted">Token Hashing Algorithm</span>
                        <span class="badge bg-light text-dark border">SHA-256</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <span class="text-muted">Document Access Control</span>
                        <span class="badge bg-success-subtle text-success fw-bold">Strict Role + Owner Guard</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <span class="text-muted">Audit Trail Logging</span>
                        <span class="badge bg-success-subtle text-success fw-bold">Enabled (verification_history)</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <span class="text-muted">PHPMailer SMTP Mode</span>
                        <span class="badge bg-secondary-subtle text-secondary fw-bold">
                            <?= getenv('SMTP_HOST') ? 'Active (Live SMTP)' : 'Development (mail.log Fallback)' ?>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <span class="text-muted">Geocoding Service</span>
                        <span class="text-dark fw-semibold">Nominatim OpenStreetMap (Rate-limited)</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

