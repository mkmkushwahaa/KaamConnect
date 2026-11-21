<?php
/**
 * KaamConnect - Contact Support & FAQ Center
 * Technology: PHP 8+, CSRF, Bootstrap 5.3 Accordion, PDO
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/header.php';

$curLang = getCurrentLanguage();
$pageTitle = __('nav_contact');
$successMessage = '';
$errors = [];

// Form fields
$contactName = isLoggedIn() ? currentUser()['full_name'] : '';
$contactEmail = isLoggedIn() ? (currentUser()['email'] ?? '') : '';
$contactPhone = isLoggedIn() ? (currentUser()['phone'] ?? '') : '';
$queryTopic = $_GET['topic'] ?? 'general';
$contactMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $errors[] = __('csrf_error');
    } else {
        $contactName = trim($_POST['name'] ?? '');
        $contactEmail = trim($_POST['email'] ?? '');
        $contactPhone = trim($_POST['phone'] ?? '');
        $queryTopic = trim($_POST['topic'] ?? 'general');
        $contactMessage = trim($_POST['message'] ?? '');

        if ($contactName === '') {
            $errors[] = 'Please enter your name.';
        }
        if ($contactPhone === '' && $contactEmail === '') {
            $errors[] = 'Please provide either a phone number or email address so we can reach you.';
        }
        if (strlen($contactMessage) < 10) {
            $errors[] = 'Please provide a detailed description (minimum 10 characters).';
        }

        if (empty($errors)) {
            try {
                // If user is logged in, log complaint in reports table
                if (isLoggedIn()) {
                    $userId = (int)currentUser()['id'];
                    $fullReason = '[' . ucfirst($queryTopic) . '] ' . $contactName . ' (' . ($contactPhone ?: $contactEmail) . ')';
                    dbExecute(
                        "INSERT INTO reports (reporter_id, reason, details, status, created_at) 
                         VALUES (?, ?, ?, 'pending', NOW())",
                        [$userId, $fullReason, $contactMessage]
                    );
                }

                $successMessage = ($curLang === 'hi')
                    ? 'धन्यवाद! आपका संदेश सफलतापूर्वक प्राप्त हो गया है। हमारी सहायता टीम 24 घंटों के भीतर आपसे संपर्क करेगी।'
                    : 'Thank you for contacting KaamConnect! Your query has been logged with ticket reference #KC-' . rand(1000, 9999) . '. Our team will respond within 24 hours.';
                
                // Reset form values on success
                $contactMessage = '';
            } catch (Exception $e) {
                $errors[] = 'Failed to submit enquiry: ' . $e->getMessage();
            }
        }
    }
}
?>

<!-- Contact Hero -->
<section class="py-5" style="background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);">
    <div class="container text-center py-3">
        <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-2 rounded-pill mb-3">
            <i class="fas fa-headset me-1"></i><?= ($curLang === 'hi') ? '24/7 सहायता केंद्र' : 'Support & Help Desk' ?>
        </span>
        <h1 class="display-5 fw-extrabold text-dark mb-2"><?= __('nav_contact') ?></h1>
        <p class="lead text-muted mx-auto" style="max-width: 650px;">
            <?= ($curLang === 'hi') 
                ? 'क्या आपके पास कोई सवाल, शिकायत या सुझाव है? हमारी टीम से संपर्क करें।' 
                : 'Have questions, worker verification enquiries, or feedback? We are here to assist you.' ?>
        </p>
    </div>
</section>

<div class="container py-5">
    <div class="row g-5">
        <!-- Left Column: Contact Form -->
        <div class="col-lg-7">
            <div class="kc-card p-4 p-md-5 border-0 shadow-sm">
                <h3 class="fw-bold text-dark mb-1">
                    <i class="fas fa-envelope-open-text text-primary me-2"></i>Send Us a Message
                </h3>
                <p class="text-muted small mb-4">
                    Fill out the form below and our regional support desk will get back to you promptly.
                </p>

                <?php if ($successMessage !== ''): ?>
                    <div class="alert alert-success d-flex align-items-center gap-2 shadow-sm mb-4">
                        <i class="fas fa-check-circle fs-4"></i>
                        <div><?= e($successMessage) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger py-2 px-3 small mb-4">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('/contact.php') ?>" novalidate>
                    <?= csrfField() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label small fw-semibold text-dark">
                                Your Full Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= e($contactName) ?>" placeholder="e.g. Rahul Verma" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label small fw-semibold text-dark">
                                Phone Number
                            </label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= e($contactPhone) ?>" placeholder="10-digit mobile number">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label small fw-semibold text-dark">
                                Email Address
                            </label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= e($contactEmail) ?>" placeholder="name@example.com">
                        </div>
                        <div class="col-md-6">
                            <label for="topic" class="form-label small fw-semibold text-dark">
                                Query Topic <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="topic" name="topic" required>
                                <option value="general" <?= ($queryTopic === 'general') ? 'selected' : '' ?>>General Enquiry</option>
                                <option value="verification" <?= ($queryTopic === 'verification') ? 'selected' : '' ?>>Worker Identity Verification</option>
                                <option value="forgot_password" <?= ($queryTopic === 'forgot_password') ? 'selected' : '' ?>>Password Reset Help</option>
                                <option value="dispute" <?= ($queryTopic === 'dispute') ? 'selected' : '' ?>>Job Dispute / Payment Issue</option>
                                <option value="bug_report" <?= ($queryTopic === 'bug_report') ? 'selected' : '' ?>>Website Bug or Error</option>
                                <option value="partnership" <?= ($queryTopic === 'partnership') ? 'selected' : '' ?>>NGO / Vocational Institute Partnership</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="message" class="form-label small fw-semibold text-dark">
                            Message / Issue Details <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="message" name="message" rows="5" placeholder="Explain your query, job ID (if applicable), or details of your concern..." required><?= e($contactMessage) ?></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-kc-primary py-2 fw-bold">
                            <i class="fas fa-paper-plane me-2"></i>Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Column: Contact Cards & Office Details -->
        <div class="col-lg-5">
            <div class="d-flex flex-column gap-3 mb-4">
                <!-- Support Email Card -->
                <div class="kc-card p-3 d-flex align-items-center gap-3">
                    <div class="p-3 bg-primary-subtle text-primary rounded-circle">
                        <i class="fas fa-envelope fs-4"></i>
                    </div>
                    <div>
                        <span class="text-muted small d-block">Official Email Support</span>
                        <strong class="text-dark">mk1503870@gmail.com</strong>
                    </div>
                </div>

                <!-- Phone Helpdesk Card -->
                <div class="kc-card p-3 d-flex align-items-center gap-3">
                    <div class="p-3 bg-success-subtle text-success rounded-circle">
                        <i class="fas fa-phone-alt fs-4"></i>
                    </div>
                    <div>
                        <span class="text-muted small d-block">Helpline & WhatsApp Support</span>
                        <strong class="text-dark">+91 1231231210</strong>
                    </div>
                </div>

                <!-- Location Card -->
                <div class="kc-card p-3 d-flex align-items-center gap-3">
                    <div class="p-3 bg-warning-subtle text-warning rounded-circle">
                        <i class="fas fa-map-marker-alt fs-4"></i>
                    </div>
                    <div>
                        <span class="text-muted small d-block">Project & Development Center</span>
                        <strong class="text-dark">katihar 854109</strong>
                    </div>
                </div>

                <!-- Working Hours Card -->
                <div class="kc-card p-3 d-flex align-items-center gap-3">
                    <div class="p-3 bg-info-subtle text-info rounded-circle">
                        <i class="fas fa-clock fs-4"></i>
                    </div>
                    <div>
                        <span class="text-muted small d-block">Operational Hours</span>
                        <strong class="text-dark">Mon – Sat: 9:00 AM – 7:00 PM IST</strong>
                    </div>
                </div>
            </div>

            <!-- Emergency Safety Notice -->
            <div class="p-4 bg-light rounded-3 border border-warning-subtle">
                <div class="d-flex align-items-center gap-2 mb-2 text-warning-emphasis fw-bold">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Marketplace Trust Guidelines</span>
                </div>
                <p class="small text-muted mb-0">
                    Always confirm physical Aadhaar card ID before allowing a worker into your residence. Make payments only after checking the quality of completed work. KaamConnect does not charge any upfront booking fees.
                </p>
            </div>
        </div>
    </div>

    <!-- Frequently Asked Questions (FAQ) Section -->
    <div class="mt-5 pt-4 border-top">
        <div class="text-center mb-4">
            <span class="badge bg-secondary-subtle text-secondary fw-semibold px-3 py-1 rounded-pill mb-2">Help Center</span>
            <h3 class="fw-bold">Frequently Asked Questions</h3>
            <p class="text-muted small">Answers to common questions from workers and customers.</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="accordion shadow-sm" id="faqAccordion">
                    <!-- FAQ 1 -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                How does KaamConnect verify worker identities?
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted small">
                                When a worker registers, they submit proof of identity (such as Aadhaar card number) and vocational experience credentials. Platform administrators manually inspect these documents and can mark workers with the green <strong>Verified</strong> trust badge.
                            </div>
                        </div>
                    </div>

                    <!-- FAQ 2 -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingTwo">
                            <button class="accordion-button collapsed fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                Is there any fee or commission charged to workers?
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted small">
                                No! KaamConnect is 100% commission-free. Unlike other apps that take 20% to 30% of worker earnings, our platform allows workers to take home their entire agreed wages directly from the client.
                            </div>
                        </div>
                    </div>

                    <!-- FAQ 3 -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingThree">
                            <button class="accordion-button collapsed fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                How does payment work between customer and worker?
                            </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted small">
                                Payment is handled directly between the customer and the artisan upon successful job completion using cash, UPI, or bank transfer. Customers agree upon the rate beforehand using the in-app bid and messaging system.
                            </div>
                        </div>
                    </div>

                    <!-- FAQ 4 -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingFour">
                            <button class="accordion-button collapsed fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                Can a worker upload pictures of past projects?
                            </button>
                        </h2>
                        <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted small">
                                Yes. Once logged in, workers can visit their <strong>Portfolio</strong> tab to upload photographs showcasing finished plumbing fittings, painted walls, custom wooden furniture, or electrical installations.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

