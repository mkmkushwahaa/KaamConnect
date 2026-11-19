<?php
/**
 * KaamConnect - Secure Password Reset Page
 * Technology: PHP 8+, MySQL PDO, CSRF & Password Strength Validation
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
redirectIfLoggedIn();

$pageTitle = 'Reset Password - KaamConnect';
$curLang = getCurrentLanguage();
$errors = [];
$successMessage = '';

// 1. Capture and Validate Plain Token from Query Parameter or Form
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$isValidTokenFormat = (bool)preg_match('/^[a-f0-9]{64}$/i', $token);

$resetRecord = null;
$tokenError = null;

if (!$isValidTokenFormat) {
    $tokenError = 'The password reset token is missing or has an invalid format.';
} else {
    // 2. Hash token to check database record
    $tokenHash = hash('sha256', $token);

    try {
        $sql = "SELECT pr.*, u.id AS uid, u.full_name, u.email, u.role, u.status AS user_status 
                FROM password_resets pr
                JOIN users u ON pr.user_id = u.id
                WHERE pr.token_hash = ?
                LIMIT 1";
        $resetRecord = dbFetchOne($sql, [$tokenHash]);

        if (!$resetRecord) {
            $tokenError = 'This password reset link is invalid. It may have been replaced by a newer request.';
        } elseif (!empty($resetRecord['used_at'])) {
            $tokenError = 'This password reset link has already been used. Reset links are single-use only. Please request a new link.';
        } elseif (strtotime($resetRecord['expires_at']) < time()) {
            $tokenError = 'This password reset link has expired. Links are valid for 15 minutes. Please request a new one.';
        } elseif ($resetRecord['user_status'] === 'suspended') {
            $tokenError = 'This account is currently suspended. Please contact KaamConnect support for assistance.';
        }
    } catch (Exception $e) {
        $tokenError = 'Unable to verify reset token at this time. Please try again later.';
    }
}

// 3. Handle Password Update Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$tokenError && $resetRecord) {
    if (!verifyCsrfToken()) {
        $errors[] = __('csrf_error');
    } else {
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        // Validation Rules
        if (strlen($newPassword) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        if (!preg_match('/[A-Z]/', $newPassword)) {
            $errors[] = 'Password must contain at least one uppercase letter (A-Z).';
        }
        if (!preg_match('/[a-z]/', $newPassword)) {
            $errors[] = 'Password must contain at least one lowercase letter (a-z).';
        }
        if (!preg_match('/[0-9]/', $newPassword)) {
            $errors[] = 'Password must contain at least one digit (0-9).';
        }
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>_\-]/', $newPassword)) {
            $errors[] = 'Password must contain at least one special character (!@#$%^&* etc).';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New Password and Confirm Password do not match.';
        }

        if (empty($errors)) {
            try {
                $userId = (int)$resetRecord['uid'];
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

                // Update user password
                dbExecute(
                    "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?",
                    [$newHash, $userId]
                );

                // Mark current reset token as used
                dbExecute(
                    "UPDATE password_resets SET used_at = NOW() WHERE id = ?",
                    [(int)$resetRecord['id']]
                );

                // Invalidate all other active reset tokens for this user
                dbExecute(
                    "UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL",
                    [$userId]
                );

                // Send in-app notification
                createNotification(
                    $userId,
                    'Password Changed Successfully',
                    'Your KaamConnect account password was successfully updated. If you did not make this change, please contact support immediately.',
                    url('/login.php'),
                    'security'
                );

                // Terminate session state
                if (session_status() === PHP_SESSION_ACTIVE) {
                    unset($_SESSION['user']);
                }

                setFlash('success', 'Your password has been successfully updated! You can now log in with your new password.');
                redirect('/login.php');
            } catch (Exception $e) {
                $errors[] = 'Failed to update password: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="kc-card p-4 p-md-5 shadow-lg border-0">
                <div class="text-center mb-4">
                    <div class="d-inline-flex p-3 bg-primary-subtle text-primary rounded-circle mb-3 shadow-sm">
                        <i class="fas fa-lock-open fa-2x"></i>
                    </div>
                    <h3 class="fw-bold mb-1">Set New Password</h3>
                    <p class="text-muted small">
                        <?php if ($resetRecord): ?>
                            Resetting password for: <strong class="text-dark"><?= e($resetRecord['email']) ?></strong>
                        <?php else: ?>
                            Choose a strong, secure password for your account.
                        <?php endif; ?>
                    </p>
                </div>

                <?php if ($tokenError): ?>
                    <!-- Token Error Banner -->
                    <div class="alert alert-danger p-3 mb-4">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fas fa-circle-exclamation fs-5 text-danger mt-1"></i>
                            <div>
                                <strong class="d-block mb-1">Unable to Reset Password</strong>
                                <span class="small"><?= e($tokenError) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="<?= url('/login.php') ?>" class="btn btn-kc-primary py-2 fw-bold">
                            <i class="fas fa-arrow-left me-1"></i>Back to Login
                        </a>
                        <a href="<?= url('/login.php') ?>" class="btn btn-outline-secondary btn-sm py-2" onclick="setTimeout(()=>{ const el = document.getElementById('forgotPasswordModal'); if(el) new bootstrap.Modal(el).show(); }, 300);">
                            <i class="fas fa-envelope me-1"></i>Request New Reset Link
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Password Reset Form -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger py-2 px-3 small mb-4">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= e($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= url('/reset_password.php?token=' . urlencode($token)) ?>" id="resetPasswordForm" novalidate>
                        <?= csrfField() ?>
                        <input type="hidden" name="token" value="<?= e($token) ?>">

                        <!-- New Password -->
                        <div class="mb-3">
                            <label for="new_password" class="form-label fw-semibold small text-dark">
                                New Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0">
                                    <i class="fas fa-key"></i>
                                </span>
                                <input type="password" 
                                       class="form-control border-start-0 ps-0" 
                                       id="new_password" 
                                       name="new_password" 
                                       placeholder="At least 8 characters" 
                                       required 
                                       autocomplete="new-password">
                                <button class="btn btn-outline-secondary border-start-0" type="button" id="toggleNewPassword">
                                    <i class="fas fa-eye text-muted"></i>
                                </button>
                            </div>
                            <div class="progress mt-2" style="height: 4px;">
                                <div id="passwordStrengthBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small id="passwordStrengthText" class="form-text text-muted" style="font-size: 11px;">
                                Use uppercase, lowercase, numbers & symbols.
                            </small>
                        </div>

                        <!-- Confirm Password -->
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label fw-semibold small text-dark">
                                Confirm New Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0">
                                    <i class="fas fa-check-double"></i>
                                </span>
                                <input type="password" 
                                       class="form-control border-start-0 ps-0" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       placeholder="Re-type new password" 
                                       required 
                                       autocomplete="new-password">
                                <button class="btn btn-outline-secondary border-start-0" type="button" id="toggleConfirmPassword">
                                    <i class="fas fa-eye text-muted"></i>
                                </button>
                            </div>
                            <small id="passwordMatchText" class="form-text" style="font-size: 11px;"></small>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-kc-primary py-2 fw-bold" id="btnSubmitReset">
                                <i class="fas fa-check-circle me-1"></i>Update Password
                            </button>
                        </div>

                        <div class="text-center pt-2 border-top small">
                            <a href="<?= url('/login.php') ?>" class="text-muted text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>Return to Login
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Password toggles
    const setupToggle = (btnId, inputId) => {
        const btn = document.getElementById(btnId);
        const input = document.getElementById(inputId);
        if (btn && input) {
            btn.addEventListener('click', () => {
                const isPass = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPass ? 'text' : 'password');
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye', !isPass);
                    icon.classList.toggle('fa-eye-slash', isPass);
                }
            });
        }
    };

    setupToggle('toggleNewPassword', 'new_password');
    setupToggle('toggleConfirmPassword', 'confirm_password');

    // Live Password Strength Meter
    const passInput = document.getElementById('new_password');
    const confirmInput = document.getElementById('confirm_password');
    const bar = document.getElementById('passwordStrengthBar');
    const strengthText = document.getElementById('passwordStrengthText');
    const matchText = document.getElementById('passwordMatchText');

    if (passInput && bar && strengthText) {
        passInput.addEventListener('input', () => {
            const val = passInput.value;
            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[a-z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[!@#$%^&*(),.?":{}|<>]/.test(val)) score++;

            const pct = (score / 5) * 100;
            bar.style.width = pct + '%';

            if (score <= 2) {
                bar.className = 'progress-bar bg-danger';
                strengthText.textContent = 'Weak password (needs 8+ chars with uppercase, numbers, symbols)';
                strengthText.className = 'form-text text-danger';
            } else if (score <= 4) {
                bar.className = 'progress-bar bg-warning';
                strengthText.textContent = 'Medium strength password';
                strengthText.className = 'form-text text-warning';
            } else {
                bar.className = 'progress-bar bg-success';
                strengthText.textContent = 'Strong, secure password!';
                strengthText.className = 'form-text text-success';
            }

            checkMatch();
        });
    }

    if (confirmInput) {
        confirmInput.addEventListener('input', checkMatch);
    }

    function checkMatch() {
        if (!confirmInput || !passInput || !matchText) return;
        if (!confirmInput.value) {
            matchText.textContent = '';
            return;
        }
        if (confirmInput.value === passInput.value) {
            matchText.textContent = '✓ Passwords match';
            matchText.className = 'form-text text-success';
        } else {
            matchText.textContent = '✗ Passwords do not match';
            matchText.className = 'form-text text-danger';
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

