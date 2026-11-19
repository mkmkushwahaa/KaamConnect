<?php
/**
 * KaamConnect - Dual-Role Registration (Worker & Customer)
 * Technology: PHP 8+, PDO Transactions, CSRF, Password Hashing
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Redirect if already authenticated
redirectIfLoggedIn();

$pageTitle = __('register_title');
$curLang = getCurrentLanguage();
$errors = [];

// Determine pre-selected role
$selectedRole = $_GET['role'] ?? 'worker';
if (!in_array($selectedRole, ['worker', 'customer'], true)) {
    $selectedRole = 'worker';
}

// Fetch categories for worker trade selection
$categories = [];
try {
    $categories = dbFetchAll("SELECT id, name_en, name_hi, icon FROM categories WHERE is_active = 1 ORDER BY name_en ASC");
} catch (Exception $e) {
    $categories = [];
}

// Form Values
$fullName = '';
$phone = '';
$email = '';
$city = '';
$locality = '';
$address = '';
$categoryId = '';
$experienceYears = '1';
$dailyWage = '500';
$hourlyWage = '100';
$wageType = 'daily';
$bio = '';

// Handle Registration Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $errors[] = __('csrf_error');
    } else {
        $selectedRole = trim($_POST['role'] ?? 'worker');
        if (!in_array($selectedRole, ['worker', 'customer'], true)) {
            $selectedRole = 'worker';
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $phone = preg_replace('/[^0-9]/', '', trim($_POST['phone'] ?? ''));
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $locality = trim($_POST['locality'] ?? '');
        $district = trim($_POST['district'] ?? '') ?: null;
        $state = trim($_POST['state'] ?? '') ?: null;
        $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT) ?: null;
        $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT) ?: null;
        $address = trim($_POST['address'] ?? '');

        // Worker specific fields
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $experienceYears = max(0, (int)($_POST['experience_years'] ?? 0));
        $wageType = in_array($_POST['wage_type'] ?? '', ['daily', 'hourly', 'negotiable'], true) ? $_POST['wage_type'] : 'daily';
        $dailyWage = max(0.0, (float)($_POST['daily_wage'] ?? 0.0));
        $hourlyWage = max(0.0, (float)($_POST['hourly_wage'] ?? 0.0));
        $bio = trim($_POST['bio'] ?? '');

        // Validations
        if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
            $errors[] = 'Full Name must be between 2 and 100 characters.';
        }

        if (strlen($phone) !== 10) {
            $errors[] = 'Please enter a valid 10-digit mobile phone number.';
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid email address.';
        }

        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Password and Confirmation Password do not match.';
        }

        if ($city === '') {
            $errors[] = 'City is required.';
        }

        if ($locality === '') {
            $errors[] = 'Locality / Area is required.';
        }

        if ($selectedRole === 'worker' && empty($categoryId)) {
            $errors[] = 'Please select your primary trade/category.';
        }

        // Check Unique Phone
        if (empty($errors)) {
            $existingPhone = dbFetchOne("SELECT id FROM users WHERE phone = ? LIMIT 1", [$phone]);
            if ($existingPhone) {
                $errors[] = 'This mobile number is already registered. Please log in instead.';
            }
        }

        // Check Unique Email (if entered)
        if (empty($errors) && $email !== '') {
            $existingEmail = dbFetchOne("SELECT id FROM users WHERE email = ? LIMIT 1", [$email]);
            if ($existingEmail) {
                $errors[] = 'This email address is already in use by another account.';
            }
        }

        // Insert Record within a Transaction
        if (empty($errors)) {
            $pdo = getDBConnection();
            try {
                $pdo->beginTransaction();

                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $cleanEmail = ($email !== '') ? $email : null;

                // 1. Insert into users table
                $stmt = $pdo->prepare(
                    "INSERT INTO users (full_name, email, phone, password_hash, role, preferred_language, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())"
                );
                $stmt->execute([$fullName, $cleanEmail, $phone, $passwordHash, $selectedRole, $curLang]);
                $userId = (int)$pdo->lastInsertId();

                // 2. Insert into role profile table
                if ($selectedRole === 'worker') {
                    $stmtWorker = $pdo->prepare(
                        "INSERT INTO worker_profiles (user_id, primary_category_id, experience_years, daily_wage, hourly_wage, wage_type, bio, city, locality, district, state, latitude, longitude, address, is_available, is_verified, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, NOW())"
                    );
                    $stmtWorker->execute([
                        $userId,
                        $categoryId,
                        $experienceYears,
                        $dailyWage,
                        $hourlyWage,
                        $wageType,
                        $bio,
                        $city,
                        $locality,
                        $district,
                        $state,
                        $latitude,
                        $longitude,
                        $address ?: null
                    ]);
                } else {
                    $stmtCustomer = $pdo->prepare(
                        "INSERT INTO customer_profiles (user_id, city, locality, district, state, latitude, longitude, address, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                    );
                    $stmtCustomer->execute([
                        $userId, 
                        $city, 
                        $locality, 
                        $district, 
                        $state, 
                        $latitude, 
                        $longitude, 
                        $address ?: null
                    ]);
                }

                // 3. Welcome Notification
                $notifTitle = 'Welcome to KaamConnect!';
                $notifMsg = ($selectedRole === 'worker')
                    ? 'Your worker profile has been registered. You can now browse customer jobs and apply for projects.'
                    : 'Your customer account is ready. You can now post jobs and hire verified artisans.';
                
                $stmtNotif = $pdo->prepare(
                    "INSERT INTO notifications (user_id, title, message, link, type, is_read, created_at) 
                     VALUES (?, ?, ?, ?, 'system', 0, NOW())"
                );
                $dashboardLink = ($selectedRole === 'worker') ? '/worker/dashboard.php' : '/customer/dashboard.php';
                $stmtNotif->execute([$userId, $notifTitle, $notifMsg, $dashboardLink]);

                $pdo->commit();

                // Auto-login newly registered user
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => $userId,
                    'full_name' => $fullName,
                    'email' => $cleanEmail,
                    'phone' => $phone,
                    'role' => $selectedRole,
                    'preferred_language' => $curLang,
                    'status' => 'active'
                ];

                setFlash('success', __('reg_success'));

                if ($selectedRole === 'worker') {
                    redirect('/worker/dashboard.php');
                } else {
                    redirect('/customer/dashboard.php');
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="kc-card p-4 p-md-5 shadow-lg border-0">
                <div class="text-center mb-4">
                    <div class="d-inline-flex p-3 bg-primary-subtle text-primary rounded-circle mb-3 shadow-sm">
                        <i class="fas fa-user-plus fa-2x"></i>
                    </div>
                    <h2 class="fw-bold mb-1"><?= __('register_title') ?></h2>
                    <p class="text-muted small">
                        <?= ($curLang === 'hi') ? 'कारीगर या ग्राहक के रूप में निःशुल्क खाता बनाएं' : 'Join thousands of local artisans and customers on KaamConnect' ?>
                    </p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger py-2 px-3 small mb-4">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('/register.php') ?>" id="form-register" novalidate>
                    <?= csrfField() ?>

                    <!-- Role Selector Section -->
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-dark d-block text-center mb-3">
                            <?= __('choose_role') ?>
                        </label>
                        <div class="row g-3">
                            <!-- Worker Choice Card -->
                            <div class="col-sm-6">
                                <div class="role-select-card card h-100 p-3 text-center border-2 cursor-pointer transition <?= ($selectedRole === 'worker') ? 'border-primary bg-light' : '' ?>" style="cursor: pointer;">
                                    <div class="card-body p-1">
                                        <input class="form-check-input visually-hidden" type="radio" name="role" id="roleWorker" value="worker" <?= ($selectedRole === 'worker') ? 'checked' : '' ?>>
                                        <div class="mb-2 text-primary">
                                            <i class="fas fa-tools fa-2x"></i>
                                        </div>
                                        <h6 class="fw-bold mb-1"><?= __('role_worker') ?></h6>
                                        <p class="text-muted small mb-0">
                                            <?= ($curLang === 'hi') ? 'काम खोजें, बोलियां लगाएं और ग्राहक पाएं' : 'Offer your services, find work & earn directly' ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Customer Choice Card -->
                            <div class="col-sm-6">
                                <div class="role-select-card card h-100 p-3 text-center border-2 cursor-pointer transition <?= ($selectedRole === 'customer') ? 'border-primary bg-light' : '' ?>" style="cursor: pointer;">
                                    <div class="card-body p-1">
                                        <input class="form-check-input visually-hidden" type="radio" name="role" id="roleCustomer" value="customer" <?= ($selectedRole === 'customer') ? 'checked' : '' ?>>
                                        <div class="mb-2 text-warning">
                                            <i class="fas fa-home fa-2x"></i>
                                        </div>
                                        <h6 class="fw-bold mb-1"><?= __('role_customer') ?></h6>
                                        <p class="text-muted small mb-0">
                                            <?= ($curLang === 'hi') ? 'काम पोस्ट करें और स्थानीय कारीगर बुलाएं' : 'Post tasks & hire background-verified workers' ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Core User Credentials -->
                    <h5 class="fw-bold mb-3 border-bottom pb-2 text-dark">
                        <i class="fas fa-id-badge text-primary me-2"></i>Account Information
                    </h5>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="fullName" class="form-label fw-semibold small text-dark">
                                <?= __('full_name') ?> <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="fullName" name="full_name" value="<?= e($fullName) ?>" placeholder="e.g. Ramesh Kumar" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label fw-semibold small text-dark">
                                <?= __('phone_number') ?> <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted small">+91</span>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?= e($phone) ?>" placeholder="10-digit number" maxlength="10" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold small text-dark">
                            <?= __('email_address') ?>
                        </label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" placeholder="optional@example.com">
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="password" class="form-label fw-semibold small text-dark">
                                <?= __('password') ?> <span class="text-danger">*</span>
                            </label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Min. 6 characters" required>
                        </div>
                        <div class="col-md-6">
                            <label for="confirmPassword" class="form-label fw-semibold small text-dark">
                                <?= __('confirm_password') ?> <span class="text-danger">*</span>
                            </label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" placeholder="Repeat password" required>
                        </div>
                    </div>

                    <!-- Location Information -->
                    <h5 class="fw-bold mb-3 border-bottom pb-2 text-dark">
                        <i class="fas fa-map-marked-alt text-primary me-2"></i>Service Location
                    </h5>

                    <!-- Hidden Geo Data -->
                    <input type="hidden" name="district" id="regDistrict" value="<?= e($district ?? '') ?>">
                    <input type="hidden" name="state" id="regState" value="<?= e($state ?? '') ?>">
                    <input type="hidden" name="latitude" id="regLat" value="<?= e($latitude ?? '') ?>">
                    <input type="hidden" name="longitude" id="regLng" value="<?= e($longitude ?? '') ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="city" class="form-label fw-semibold small text-dark">
                                City <span class="text-danger">*</span>
                            </label>
                            <div class="kc-location-wrapper">
                                <div class="kc-location-input-group">
                                    <input type="text" 
                                           class="form-control" 
                                           id="city" 
                                           name="city" 
                                           value="<?= e($city) ?>" 
                                           placeholder="e.g. Noida, Patna, Delhi" 
                                           autocomplete="off" 
                                           required>
                                    <button type="button" 
                                            class="kc-btn-map-picker" 
                                            id="kcBtnChooseOnMap" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#kcMapModal" 
                                            title="Choose on map">
                                        <i class="fas fa-map-marked-alt text-primary"></i>
                                    </button>
                                </div>
                                <div id="regCityDropdown" class="kc-autocomplete-dropdown"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="locality" class="form-label fw-semibold small text-dark">
                                Locality / Colony / Sector <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="locality" name="locality" value="<?= e($locality) ?>" placeholder="e.g. Sector 15, Lajpat Nagar" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label fw-semibold small text-dark">
                            Street Address (Optional)
                        </label>
                        <input type="text" class="form-control" id="address" name="address" value="<?= e($address) ?>" placeholder="House/Flat number, Street name">
                    </div>

                    <!-- Dynamic Worker-Only Trade Details -->
                    <div id="workerExtraFields" style="<?= ($selectedRole === 'customer') ? 'display: none;' : '' ?>">
                        <h5 class="fw-bold mb-3 border-bottom pb-2 text-dark mt-4">
                            <i class="fas fa-briefcase text-primary me-2"></i>Trade & Wage Preferences
                        </h5>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="categoryId" class="form-label fw-semibold small text-dark">
                                    Primary Trade / Specialty <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="categoryId" name="category_id">
                                    <option value="">Select your main trade</option>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= (int)$c['id'] ?>" <?= ((int)$categoryId === (int)$c['id']) ? 'selected' : '' ?>>
                                            <?= ($curLang === 'hi') ? e($c['name_hi']) : e($c['name_en']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="experienceYears" class="form-label fw-semibold small text-dark">
                                    <?= __('experience_years') ?>
                                </label>
                                <input type="number" class="form-control" id="experienceYears" name="experience_years" value="<?= e($experienceYears) ?>" min="0" max="50">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="wageType" class="form-label fw-semibold small text-dark">Wage Type</label>
                                <select class="form-select" id="wageType" name="wage_type">
                                    <option value="daily" <?= ($wageType === 'daily') ? 'selected' : '' ?>>Daily Rate (₹)</option>
                                    <option value="hourly" <?= ($wageType === 'hourly') ? 'selected' : '' ?>>Hourly Rate (₹)</option>
                                    <option value="negotiable" <?= ($wageType === 'negotiable') ? 'selected' : '' ?>>Negotiable</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="dailyWage" class="form-label fw-semibold small text-dark">Daily Rate (₹)</label>
                                <input type="number" class="form-control" id="dailyWage" name="daily_wage" value="<?= e($dailyWage) ?>" step="50" min="0">
                            </div>
                            <div class="col-md-4">
                                <label for="hourlyWage" class="form-label fw-semibold small text-dark">Hourly Rate (₹)</label>
                                <input type="number" class="form-control" id="hourlyWage" name="hourly_wage" value="<?= e($hourlyWage) ?>" step="10" min="0">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="bio" class="form-label fw-semibold small text-dark">
                                Short Bio / Skills Description
                            </label>
                            <textarea class="form-control" id="bio" name="bio" rows="2" placeholder="Briefly describe your tools, experience, and the kind of work you do..."><?= e($bio) ?></textarea>
                        </div>
                    </div>

                    <!-- Terms Notice -->
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="termsAgree" name="terms_agree" checked required>
                        <label class="form-check-label small text-muted" for="termsAgree">
                            I agree to the KaamConnect Terms of Service and acknowledge that identity verification may be conducted for marketplace safety.
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-kc-primary py-2 fw-bold fs-6">
                            <i class="fas fa-check-circle me-2"></i><?= __('register_btn') ?>
                        </button>
                    </div>

                    <!-- Login Link -->
                    <div class="text-center pt-2 border-top small text-muted">
                        <?= __('already_have_account') ?>? 
                        <a href="<?= url('/login.php') ?>" class="fw-semibold text-primary text-decoration-none">
                            <?= __('nav_login') ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Map Location Picker Modal (Leaflet.js) -->
<div class="modal fade" id="kcMapModal" tabindex="-1" aria-labelledby="kcMapModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: var(--kc-radius);">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="kcMapModalLabel">
                    <i class="fas fa-map-marked-alt text-primary"></i>
                    <span>Choose Location on Map</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <!-- Location Search Bar inside Map Modal -->
                <div class="kc-map-search-bar">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" 
                               id="kcMapSearchInput" 
                               class="form-control border-start-0" 
                               placeholder="Search city, town, locality or landmark in India...">
                        <button class="btn btn-kc-primary px-3" type="button" id="kcBtnMapSearch">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <div class="form-text small text-muted mt-1">
                        <i class="fas fa-info-circle me-1"></i>Search an area or click and drag the blue pin to pinpoint your service locality.
                    </div>
                </div>

                <!-- Leaflet Interactive Map Container -->
                <div id="kcMapContainer" class="kc-map-container mb-3 shadow-sm"></div>

                <!-- Selected Address Live Preview Box -->
                <div class="kc-map-address-preview">
                    <i class="fas fa-map-pin"></i>
                    <div class="flex-grow-1 min-w-0">
                        <span class="text-muted small d-block">Selected Location:</span>
                        <strong class="text-dark small d-block text-truncate" id="kcMapAddressPreviewText">
                            Click anywhere on the map or drag the pin to set your location
                        </strong>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-kc-primary btn-sm px-4 fw-bold" id="kcBtnConfirmLocation">
                    <i class="fas fa-check-circle me-1"></i>Confirm Location
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const roleWorkerRadio = document.getElementById('roleWorker');
    const roleCustomerRadio = document.getElementById('roleCustomer');
    const workerFields = document.getElementById('workerExtraFields');

    function syncRoleUI() {
        if (roleWorkerRadio && roleCustomerRadio && workerFields) {
            if (roleWorkerRadio.checked) {
                workerFields.style.display = 'block';
            } else {
                workerFields.style.display = 'none';
            }
        }
    }

    if (roleWorkerRadio && roleCustomerRadio) {
        roleWorkerRadio.addEventListener('change', syncRoleUI);
        roleCustomerRadio.addEventListener('change', syncRoleUI);
    }

    // Role card click integration
    document.querySelectorAll('.role-select-card').forEach(card => {
        card.addEventListener('click', () => {
            const radio = card.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                syncRoleUI();
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

