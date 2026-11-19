<?php
/**
 * KaamConnect - Customer Post New Job
 * Strictly protected: Identity-verified customers only
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

// Strict backend authorization guard for Module 5
requireVerifiedCustomer();

$user = currentUser();
$userId = (int)$user['id'];
$customerProfile = getCustomerProfile($userId);

$pdo = getDBConnection();
$categories = dbFetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name_en ASC");

$errors = [];
$title = '';
$description = '';
$categoryId = 0;
$requiredSkills = '';
$budget = '';
$wageType = 'fixed';
$preferredDate = date('Y-m-d', strtotime('+1 day'));
$city = $customerProfile['city'] ?? '';
$locality = $customerProfile['locality'] ?? '';
$district = $customerProfile['district'] ?? '';
$state = $customerProfile['state'] ?? '';
$latitude = $customerProfile['latitude'] ?? '';
$longitude = $customerProfile['longitude'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $errors[] = 'Session security token expired. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $requiredSkills = trim($_POST['required_skills'] ?? '');
        $budget = (float)($_POST['budget'] ?? 0);
        $wageType = trim($_POST['wage_type'] ?? 'fixed');
        $preferredDate = trim($_POST['preferred_date'] ?? date('Y-m-d'));
        $city = trim($_POST['city'] ?? '');
        $locality = trim($_POST['locality'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $lat = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $lon = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

        if (empty($title)) {
            $errors[] = 'Job title is required.';
        }
        if (empty($description)) {
            $errors[] = 'Job description is required.';
        }
        if ($categoryId <= 0) {
            $errors[] = 'Please select a valid work category.';
        }
        if ($budget <= 0) {
            $errors[] = 'Please enter a valid budget amount.';
        }
        if (empty($city)) {
            $errors[] = 'City location is required.';
        }

        if (empty($errors)) {
            dbExecute(
                "INSERT INTO jobs (customer_id, category_id, title, description, required_skills, city, locality, district, state, latitude, longitude, budget, wage_type, preferred_date, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW(), NOW())",
                [$userId, $categoryId, $title, $description, $requiredSkills, $city, $locality, $district, $state, $lat, $lon, $budget, $wageType, $preferredDate]
            );

            setFlash('success', 'Your job requirement has been published successfully! Verified workers in ' . e($city) . ' will be notified.');
            redirect('/jobs.php');
        }
    }
}

$pageTitle = 'Post a New Job - KaamConnect';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-primary text-white py-4 mb-4">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-dark mb-1 small">
                        <li class="breadcrumb-item"><a href="<?= url('/customer/dashboard.php') ?>" class="text-white-50">Dashboard</a></li>
                        <li class="breadcrumb-item active text-white" aria-current="page">Post New Job</li>
                    </ol>
                </nav>
                <h1 class="h3 fw-bold mb-0">
                    <i class="fas fa-plus-circle text-warning me-2"></i>Post a New Job Requirement
                </h1>
            </div>
            <div>
                <a href="<?= url('/jobs.php') ?>" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>View All Jobs
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="kc-card p-4 p-md-5">
                <div class="d-flex align-items-center gap-2 mb-4 pb-3 border-bottom">
                    <span class="badge bg-success-subtle text-success fs-6 px-3 py-2 rounded-pill">
                        <i class="fas fa-check-circle me-1"></i>Verified Customer Account
                    </span>
                    <span class="text-muted small">You are authorized to publish live job requirements.</span>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger p-3 mb-4">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="kcPostJobForm" data-api-url="<?= url('/api/geocode.php') ?>">
                    <?= csrfField() ?>

                    <!-- Job Title -->
                    <div class="mb-3">
                        <label for="title" class="form-label small fw-semibold text-dark">
                            Job Title / Requirement Summary <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control form-control-lg" id="title" name="title" 
                               value="<?= e($title) ?>" placeholder="e.g. Bathroom Pipe Leakage Repair and Tap Replacement" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <!-- Category -->
                        <div class="col-md-6">
                            <label for="category_id" class="form-label small fw-semibold text-dark">
                                Work Category <span class="text-danger">*</span>
                            </label>
                            <select name="category_id" id="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>" <?= ($categoryId === (int)$cat['id']) ? 'selected' : '' ?>>
                                        <?= e($cat['name_en']) ?> (<?= e($cat['name_hi'] ?? '') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Preferred Date -->
                        <div class="col-md-6">
                            <label for="preferred_date" class="form-label small fw-semibold text-dark">
                                Preferred Start Date
                            </label>
                            <input type="date" class="form-control" id="preferred_date" name="preferred_date" 
                                   value="<?= e($preferredDate) ?>" min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <!-- Job Description -->
                    <div class="mb-3">
                        <label for="description" class="form-label small fw-semibold text-dark">
                            Detailed Description <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="description" name="description" rows="4" 
                                  placeholder="Describe the exact work needed, tools required, material availability, and site details..." required><?= e($description) ?></textarea>
                    </div>

                    <!-- Required Skills -->
                    <div class="mb-3">
                        <label for="required_skills" class="form-label small fw-semibold text-dark">
                            Required Skills (Comma-separated)
                        </label>
                        <input type="text" class="form-control" id="required_skills" name="required_skills" 
                               value="<?= e($requiredSkills) ?>" placeholder="e.g. Pipe Fitting, CPVC Joints, Wall Drilling">
                    </div>

                    <div class="row g-3 mb-4">
                        <!-- Budget -->
                        <div class="col-md-6">
                            <label for="budget" class="form-label small fw-semibold text-dark">
                                Estimated Budget (₹) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">₹</span>
                                <input type="number" step="50" min="100" class="form-control" id="budget" name="budget" 
                                       value="<?= e((string)$budget) ?>" placeholder="e.g. 1500" required>
                            </div>
                        </div>

                        <!-- Wage Type -->
                        <div class="col-md-6">
                            <label for="wage_type" class="form-label small fw-semibold text-dark">
                                Wage / Payment Type
                            </label>
                            <select name="wage_type" id="wage_type" class="form-select">
                                <option value="fixed" <?= ($wageType === 'fixed') ? 'selected' : '' ?>>Fixed Contract Rate</option>
                                <option value="daily" <?= ($wageType === 'daily') ? 'selected' : '' ?>>Daily Rate (Per Day)</option>
                                <option value="hourly" <?= ($wageType === 'hourly') ? 'selected' : '' ?>>Hourly Rate (Per Hour)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Smart Location Autocomplete & Choose on Map Section (Module 1 Integration) -->
                    <h5 class="fw-bold text-dark pt-3 border-top mb-3">
                        <i class="fas fa-location-dot text-danger me-2"></i>Job Location Details
                    </h5>

                    <!-- Smart Autocomplete Input -->
                    <div class="mb-3 position-relative">
                        <label for="kcLocationInput" class="form-label small fw-semibold text-dark">
                            Search City / Locality (Smart Autocomplete) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" class="form-control" id="kcLocationInput" 
                                   placeholder="Type city or locality (e.g. Noida, Indirapuram, Patna)..." 
                                   value="<?= e($city . ($locality ? ', ' . $locality : '')) ?>" autocomplete="off">
                            <button type="button" class="btn btn-outline-primary" id="kcOpenMapBtn">
                                <i class="fas fa-map-location-dot me-1"></i>Choose on Map
                            </button>
                        </div>
                        <!-- Autocomplete Suggestions Dropdown -->
                        <div class="kc-suggestions-dropdown shadow" id="kcSuggestionsBox"></div>
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-sm-6">
                            <label class="form-label small text-muted mb-0">City:</label>
                            <input type="text" name="city" id="kcCityInput" class="form-control form-control-sm" value="<?= e($city) ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small text-muted mb-0">Locality:</label>
                            <input type="text" name="locality" id="kcLocalityInput" class="form-control form-control-sm" value="<?= e($locality) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small text-muted mb-0">District:</label>
                            <input type="text" name="district" id="kcDistrictInput" class="form-control form-control-sm" value="<?= e($district) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small text-muted mb-0">State:</label>
                            <input type="text" name="state" id="kcStateInput" class="form-control form-control-sm" value="<?= e($state) ?>">
                        </div>
                    </div>

                    <!-- Hidden Geo Coordinates -->
                    <input type="hidden" name="latitude" id="kcLatInput" value="<?= e((string)$latitude) ?>">
                    <input type="hidden" name="longitude" id="kcLonInput" value="<?= e((string)$longitude) ?>">

                    <button type="submit" class="btn btn-kc-primary btn-lg w-100 py-3 fw-bold">
                        <i class="fas fa-check-circle me-1"></i>Publish Job Listing
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Choose on Map Modal -->
<div class="modal fade" id="kcMapModal" tabindex="-1" aria-labelledby="kcMapModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="kcMapModalLabel">
                    <i class="fas fa-map-location-dot me-2"></i>Choose Job Location on Map
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 bg-light border-bottom small text-muted">
                    <i class="fas fa-info-circle text-primary me-1"></i> Click anywhere on the map or drag the marker to pinpoint the exact site location.
                </div>
                <div id="kcLeafletMap" style="height: 380px; width: 100%;"></div>
                <div class="p-3 bg-white border-top">
                    <span class="small text-muted d-block mb-1">Selected Location Preview:</span>
                    <strong class="text-dark" id="kcSelectedLocationText">Click on the map to select a point...</strong>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm px-4 fw-bold" id="kcConfirmLocationBtn">
                    <i class="fas fa-check me-1"></i>Confirm Location
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
