<?php
/**
 * KaamConnect - Secure Unified Login Page
 * Technology: PHP 8+, MySQL PDO, CSRF & Rate-Limiting Protection
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

// Redirect if already authenticated
redirectIfLoggedIn();

$pageTitle = __('login_title');
$curLang = getCurrentLanguage();
$errors = [];
$identifier = '';
$redirectParam = $_GET['redirect'] ?? '';

// Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if (!verifyCsrfToken()) {
        $errors[] = __('csrf_error');
    } elseif ($action === 'forgot_password') {
        // --- 1. FORGOT PASSWORD HANDLER ---
        $resetEmail = trim($_POST['reset_email'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        logMailActivity($resetEmail, 'PASSWORD_RESET_SUBMITTED', "Forgot Password request received from IP {$ip}.");

        if (empty($resetEmail) || !filter_var($resetEmail, FILTER_VALIDATE_EMAIL)) {
            logMailActivity($resetEmail, 'VALIDATION_FAILED', 'Invalid email address format submitted.');
            $errors[] = 'Please enter a valid registered email address.';
        } else {
            // Check rate limiting on password reset requests (max 5 requests per 15 minutes)
            try {
                $recentResets = dbFetchOne(
                    "SELECT COUNT(*) AS cnt FROM password_resets pr 
                     JOIN users u ON pr.user_id = u.id 
                     WHERE u.email = ? AND pr.created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
                    [$resetEmail]
                );
                if ((int)($recentResets['cnt'] ?? 0) >= 5) {
                    logMailActivity($resetEmail, 'RATE_LIMITED', 'Too many password reset requests within 15 minutes.');
                    $errors[] = 'Too many password reset requests. Please wait a few minutes before trying again.';
                }
            } catch (Exception $e) {
                // Ignore rate limit check error if table issues
                logMailActivity($resetEmail, 'RATE_LIMIT_CHECK_ERROR', $e->getMessage());
            }

            if (empty($errors)) {
                try {
                    // Check user in database
                    $user = dbFetchOne(
                        "SELECT id, full_name, email, role, status
                         FROM users
                         WHERE email = ?
                         LIMIT 1",
                        [$resetEmail]
                    );

                    // Check if user was found
                    if (!$user) {
                        logMailActivity(
                            $resetEmail,
                            'USER_NOT_FOUND',
                            'Email lookup returned no results in database users table. Reset email was NOT dispatched because account does not exist.'
                        );
                    } elseif ($user['status'] === 'suspended') {
                        logMailActivity(
                            $resetEmail,
                            'USER_SUSPENDED',
                            "User ID {$user['id']} exists but is suspended. Reset email was NOT dispatched."
                        );
                    } else {
                        // User exists and is active
                        logMailActivity(
                            $resetEmail,
                            'USER_FOUND',
                            "Active account found (User ID: {$user['id']}). Generating reset token."
                        );

                        // Generate secure random token
                        $plainToken = bin2hex(random_bytes(32));
                        $tokenHash = hash('sha256', $plainToken);

                        // Token expires after 15 minutes
                        $expiresAt = date('Y-m-d H:i:s', time() + 15 * 60);

                        // Invalidate previous unused reset requests
                        dbExecute(
                            "UPDATE password_resets
                             SET used_at = NOW()
                             WHERE user_id = ? AND used_at IS NULL",
                            [(int)$user['id']]
                        );

                        // Store new hashed token in database
                        dbExecute(
                            "INSERT INTO password_resets
                             (user_id, token_hash, expires_at, created_at)
                             VALUES (?, ?, ?, NOW())",
                            [
                                (int)$user['id'],
                                $tokenHash,
                                $expiresAt
                            ]
                        );

                        logMailActivity(
                            $resetEmail,
                            'TOKEN_STORED',
                            'New reset token stored in password_resets table. Valid for 15 minutes.'
                        );

                        // Generate password reset URL
                        $resetLink = getAbsoluteUrl(
                            '/reset_password.php?token=' . $plainToken
                        );

                        logMailActivity(
                            $resetEmail,
                            'SMTP_DISPATCH_START',
                            'Calling sendPasswordResetEmail() via Gmail SMTP...'
                        );

                        // Send password reset email through Gmail SMTP
                        $mailResult = sendPasswordResetEmail(
                            $user['email'],
                            $user['full_name'],
                            $resetLink
                        );

                        // Check whether email was successfully sent
                        if (empty($mailResult['success'])) {
                            // Invalidate reset token if email delivery fails
                            dbExecute(
                                "UPDATE password_resets
                                 SET used_at = NOW()
                                 WHERE token_hash = ? AND used_at IS NULL",
                                [$tokenHash]
                            );

                            logMailActivity(
                                $resetEmail,
                                'SMTP_DISPATCH_FAILED',
                                'Email delivery failed. Reason: ' . ($mailResult['message'] ?? 'Unknown SMTP failure.')
                            );
                        } else {
                            logMailActivity(
                                $resetEmail,
                                'SMTP_DISPATCH_SUCCESS',
                                'Password reset email successfully accepted by SMTP server for delivery.'
                            );

                            // Create in-app notification after successful email delivery
                            try {
                                createNotification(
                                    (int)$user['id'],
                                    'Password Reset Requested',
                                    'A password reset request was initiated for your KaamConnect account. This reset link expires in 15 minutes.',
                                    url('/login.php'),
                                    'security'
                                );
                            } catch (Throwable $notificationError) {
                                // Notification failure should not invalidate a successful email
                                logMailActivity(
                                    $resetEmail,
                                    'NOTIFICATION_ERROR',
                                    'In-app notification error: ' . $notificationError->getMessage()
                                );
                            }
                        }
                    }

                    // Generic response to prevent email/account enumeration
                    setFlash(
                        'success',
                        'If an account is associated with that email address, password reset instructions may be sent. Please check your inbox and spam folder.'
                    );

                    redirect('/login.php');

                } catch (Throwable $e) {
                    logMailActivity(
                        $resetEmail,
                        'FATAL_EXCEPTION',
                        'Password Reset Exception: ' . $e->getMessage()
                    );

                    // Display a safe message to the user
                    $errors[] = 'Unable to process your request right now. Please try again later.';
                }
            }
        }
    } else {
        // --- 2. STANDARD LOGIN HANDLER ---
        $identifier = trim($_POST['identifier'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($identifier === '') {
            $errors[] = __('phone_number') . ' / ' . __('email_address') . ' ' . __('field_required');
        }
        if ($password === '') {
            $errors[] = __('password') . ' ' . __('field_required');
        }

        // Check Rate Limiting (5 failed attempts per 15 mins)
        if (empty($errors) && isRateLimited($identifier)) {
            $errors[] = 'Too many failed login attempts detected. Please wait 15 minutes before trying again.';
        }

        if (empty($errors)) {
            try {
                // Find user by phone OR email
                $sql = "SELECT id, full_name, email, phone, password_hash, role, preferred_language, status 
                        FROM users 
                        WHERE phone = ? OR email = ? 
                        LIMIT 1";
                $user = dbFetchOne($sql, [$identifier, $identifier]);

                if (!$user || !password_verify($password, $user['password_hash'])) {
                    recordLoginAttempt($identifier, false);
                    $errors[] = __('invalid_credentials');
                } elseif ($user['status'] === 'suspended') {
                    recordLoginAttempt($identifier, false);
                    $errors[] = __('account_suspended');
                } else {
                    // Success! Record successful login
                    recordLoginAttempt($identifier, true);

                    // Prevent session fixation
                    session_regenerate_id(true);

                    // Set User Session
                    $_SESSION['user'] = [
                        'id' => (int)$user['id'],
                        'full_name' => $user['full_name'],
                        'email' => $user['email'],
                        'phone' => $user['phone'],
                        'role' => $user['role'],
                        'preferred_language' => $user['preferred_language'],
                        'status' => $user['status']
                    ];

                    if (!empty($user['preferred_language'])) {
                        $_SESSION['lang'] = $user['preferred_language'];
                    }

                    setFlash('success', __('login_success'));

                    // Redirect logic
                    if (!empty($redirectParam) && !str_starts_with($redirectParam, 'http://') && !str_starts_with($redirectParam, 'https://')) {
                        redirect($redirectParam);
                    }

                    if ($user['role'] === 'worker') {
                        redirect('/worker/dashboard.php');
                    } elseif ($user['role'] === 'customer') {
                        redirect('/customer/dashboard.php');
                    } elseif ($user['role'] === 'admin') {
                        redirect('/admin/index.php');
                    } else {
                        redirect('/index.php');
                    }
                }
            } catch (Exception $e) {
                $errors[] = 'System authentication error: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <!-- Login Card -->
            <div class="kc-card p-4 p-md-5 shadow-lg border-0">
                <div class="text-center mb-4">
                    <div class="d-inline-flex p-3 bg-primary-subtle text-primary rounded-circle mb-3 shadow-sm">
                        <i class="fas fa-sign-in-alt fa-2x"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= __('login_title') ?></h3>
                    <p class="text-muted small">
                        <?= ($curLang === 'hi') ? 'अपने खाते में सुरक्षित लॉगिन करें' : 'Sign in to access your customized dashboard' ?>
                    </p>
                </div>

                <!-- <?php if (!empty($_SESSION['demo_reset_link'])): ?>
                    <?php $demoLink = $_SESSION['demo_reset_link']; unset($_SESSION['demo_reset_link']); ?>
                    <div class="alert alert-info py-2 px-3 small mb-4 border-info shadow-sm">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <strong class="text-primary"><i class="fas fa-paper-plane me-1"></i>Password Reset Link (Local Simulation):</strong><br>
                                <span class="text-break font-monospace" style="font-size: 11px;"><?= e($demoLink) ?></span>
                            </div>
                            <a href="<?= e($demoLink) ?>" class="btn btn-sm btn-primary py-1 px-3 fw-bold">Test Reset Link</a>
                        </div>
                    </div>
                <?php endif; ?> -->

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger py-2 px-3 small mb-4">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('/login.php' . (!empty($redirectParam) ? '?redirect=' . urlencode($redirectParam) : '')) ?>" novalidate>
                    <?= csrfField() ?>

                    <!-- Identifier Input (Phone or Email) -->
                    <div class="mb-3">
                        <label for="identifier" class="form-label fw-semibold small text-dark">
                            <?= __('phone_number') ?> / <?= __('email_address') ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" 
                                   class="form-control border-start-0 ps-0" 
                                   id="identifier" 
                                   name="identifier" 
                                   value="<?= e($identifier) ?>" 
                                   placeholder="e.g. 9876500001 or user@example.com" 
                                   required 
                                   autofocus>
                        </div>
                    </div>

                    <!-- Password Input -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="password" class="form-label fw-semibold small text-dark mb-0">
                                <?= __('password') ?>
                            </label>
                            <a href="#forgotPasswordModal" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal" class="small text-primary text-decoration-none fw-semibold">
                                <?= __('forgot_password') ?>
                            </a>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" 
                                   class="form-control border-start-0 ps-0" 
                                   id="password" 
                                   name="password" 
                                   placeholder="••••••••" 
                                   required>
                            <button class="btn btn-outline-secondary border-start-0" type="button" id="togglePassword">
                                <i class="fas fa-eye text-muted"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me Checkbox -->
                    <div class="mb-4 d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="rememberMe" name="remember_me">
                            <label class="form-check-label small text-muted" for="rememberMe">
                                <?= __('remember_me') ?>
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-kc-primary py-2 fw-bold">
                            <i class="fas fa-sign-in-alt me-2"></i><?= __('login_btn') ?>
                        </button>
                    </div>

                    <!-- Registration Link -->
                    <div class="text-center pt-2 border-top small text-muted">
                        <?= __('dont_have_account') ?>? 
                        <a href="<?= url('/register.php') ?>" class="fw-semibold text-primary text-decoration-none">
                            <?= __('register_btn') ?>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Pre-Seeded Demonstration Accounts Quick-Fill Box for Examiners
            <div class="card mt-4 border border-info-subtle bg-light shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-bold small text-info-emphasis">
                            <i class="fas fa-key me-1"></i>Demo Accounts (Examiner One-Click Fill)
                        </span>
                        <span class="badge bg-info-subtle text-info small">Project Evaluation</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm quick-fill" 
                                data-id="admin@kaamconnect.com" data-pass="Admin@123">
                            <i class="fas fa-user-shield me-1 text-danger"></i>Admin
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm quick-fill" 
                                data-id="9876500001" data-pass="Password@123">
                            <i class="fas fa-bolt me-1 text-warning"></i>Worker (Ramesh)
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm quick-fill" 
                                data-id="9876500002" data-pass="Password@123">
                            <i class="fas fa-wrench me-1 text-info"></i>Worker (Aslam)
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm quick-fill" 
                                data-id="9876500003" data-pass="Password@123">
                            <i class="fas fa-user me-1 text-success"></i>Customer (Sunita)
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm quick-fill"   
                                data-id="9876500004" data-pass="Password@123">
                            <i class="fas fa-user me-1 text-success"></i>Customer (Vikram)
                        </button>
                    </div>
                </div>
            </div> -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Password toggle
    const toggleBtn = document.getElementById('togglePassword');
    const passInput = document.getElementById('password');
    if (toggleBtn && passInput) {
        toggleBtn.addEventListener('click', () => {
            const isPassword = passInput.getAttribute('type') === 'password';
            passInput.setAttribute('type', isPassword ? 'text' : 'password');
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye', !isPassword);
                icon.classList.toggle('fa-eye-slash', isPassword);
            }
        });
    }

    // Demo Fill Helper
    const idInput = document.getElementById('identifier');
    document.querySelectorAll('.quick-fill').forEach(btn => {
        btn.addEventListener('click', () => {
            if (idInput && passInput) {
                idInput.value = btn.getAttribute('data-id');
                passInput.value = btn.getAttribute('data-pass');
            }
        });
    });
});
</script>

<!-- Modern Forgot Password Modal -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: var(--kc-radius-lg);">
            <div class="modal-header bg-light border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="forgotPasswordModalLabel">
                    <i class="fas fa-key text-primary"></i>
                    Forgot Password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= url('/login.php') ?>" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="forgot_password">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        Enter your registered email address and we'll send you a password reset link.
                    </p>
                    <div class="mb-3">
                        <label for="reset_email" class="form-label fw-semibold small text-dark">
                            Registered Email Address <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" 
                                   class="form-control border-start-0 ps-0" 
                                   id="reset_email" 
                                   name="reset_email" 
                                   placeholder="e.g. user@example.com" 
                                   required 
                                   autocomplete="email">
                        </div>
                        <div class="form-text small text-muted">
                            <i class="fas fa-shield-alt text-success me-1"></i>Reset links expire automatically in 15 minutes.
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top py-2 px-3 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-kc-primary btn-sm px-4 fw-bold">
                        <i class="fas fa-paper-plane me-1"></i>Send Reset Link
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

