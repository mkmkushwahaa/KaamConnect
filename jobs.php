<?php
/**
 * KaamConnect - Public Job Board & Opportunities Search
 * Technology: PHP 8+, MySQL PDO Parameterized Filtering, Bootstrap 5.3
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/header.php';

$curLang = getCurrentLanguage();

// 1. Capture and Sanitize Filter Inputs
$searchQuery = trim($_GET['q'] ?? '');
$selectedCategory = !empty($_GET['category']) ? (int)$_GET['category'] : null;
$selectedCity = trim($_GET['city'] ?? '');
$selectedWageType = trim($_GET['wage_type'] ?? '');
$selectedStatus = trim($_GET['status'] ?? 'open');
$sortBy = trim($_GET['sort'] ?? 'newest');

// Location & Proximity Parameters
$lat = isset($_GET['lat']) && is_numeric($_GET['lat']) ? (float)$_GET['lat'] : null;
$lon = isset($_GET['lon']) && is_numeric($_GET['lon']) ? (float)$_GET['lon'] : null;
$radius = isset($_GET['radius']) && is_numeric($_GET['radius']) ? (int)$_GET['radius'] : 50;
$selectedDistrict = trim($_GET['district'] ?? '');
$selectedState = trim($_GET['state'] ?? '');
$hasCoordinates = ($lat !== null && $lon !== null && $lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180);

// Default sort to distance_asc if GPS coordinates provided and no explicit sort chosen
if ($hasCoordinates && !isset($_GET['sort'])) {
    $sortBy = 'distance_asc';
}

// 2. Fetch Active Categories for Filter Dropdown
$categories = [];
try {
    $categories = dbFetchAll("SELECT id, name_en, name_hi, icon FROM categories WHERE is_active = 1 ORDER BY name_en ASC");
} catch (Exception $e) {
    $categories = [];
}

// 3. Fetch Distinct Cities for Autocomplete/Dropdown
$distinctCities = [];
try {
    $distinctCities = dbFetchAll("SELECT DISTINCT city FROM jobs WHERE city IS NOT NULL AND city != '' ORDER BY city ASC");
} catch (Exception $e) {
    $distinctCities = [];
}

// 4. Build Dynamic Parameterized Query
$where = [];
$params = [];
$distanceSql = '';
$selectParams = [];

// Haversine Proximity Calculation (6371 km Earth radius)
if ($hasCoordinates) {
    $distanceSql = ", (6371 * acos(LEAST(1.0, GREATEST(-1.0, 
        cos(radians(?)) * cos(radians(j.latitude)) * cos(radians(j.longitude) - radians(?)) + 
        sin(radians(?)) * sin(radians(j.latitude))
    )))) AS distance_km";
    $selectParams = [$lat, $lon, $lat];
}

if ($selectedStatus === 'all') {
    // Show all non-cancelled jobs
    $where[] = "j.status != 'cancelled'";
} elseif (in_array($selectedStatus, ['open', 'assigned', 'completed'], true)) {
    $where[] = "j.status = ?";
    $params[] = $selectedStatus;
} else {
    $where[] = "j.status = 'open'";
}

if ($searchQuery !== '') {
    $where[] = "(j.title LIKE ? OR j.description LIKE ? OR j.required_skills LIKE ? OR j.locality LIKE ? OR c.name_en LIKE ? OR c.name_hi LIKE ?)";
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($selectedCategory) {
    $where[] = "j.category_id = ?";
    $params[] = $selectedCategory;
}

// Location / Radius Filtering
if ($hasCoordinates && $radius > 0) {
    $where[] = "((j.latitude IS NOT NULL AND j.longitude IS NOT NULL AND (6371 * acos(LEAST(1.0, GREATEST(-1.0, 
        cos(radians(?)) * cos(radians(j.latitude)) * cos(radians(j.longitude) - radians(?)) + 
        sin(radians(?)) * sin(radians(j.latitude))
    )))) <= ?) OR (j.city LIKE ? OR j.locality LIKE ? OR j.district LIKE ?))";
    $params[] = $lat;
    $params[] = $lon;
    $params[] = $lat;
    $params[] = $radius;
    $params[] = '%' . $selectedCity . '%';
    $params[] = '%' . $selectedCity . '%';
    $params[] = '%' . $selectedCity . '%';
} elseif ($selectedCity !== '') {
    $where[] = "(j.city LIKE ? OR j.locality LIKE ? OR j.district LIKE ? OR j.state LIKE ?)";
    $params[] = '%' . $selectedCity . '%';
    $params[] = '%' . $selectedCity . '%';
    $params[] = '%' . $selectedCity . '%';
    $params[] = '%' . $selectedCity . '%';
}

if ($selectedWageType !== '' && in_array($selectedWageType, ['fixed', 'daily', 'hourly'], true)) {
    $where[] = "j.wage_type = ?";
    $params[] = $selectedWageType;
}

// Determine ORDER BY clause
$orderBy = match ($sortBy) {
    'distance_asc' => 'CASE WHEN distance_km IS NULL THEN 1 ELSE 0 END, distance_km ASC, j.created_at DESC',
    'budget_desc'  => 'j.budget DESC',
    'budget_asc'   => 'j.budget ASC',
    'date_asc'     => 'j.preferred_date ASC',
    default        => 'j.created_at DESC'
};

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Execute Main Query
$jobs = [];
try {
    $sql = "SELECT j.*, c.name_en AS cat_name_en, c.name_hi AS cat_name_hi, c.icon AS cat_icon,
                   u.full_name AS customer_name,
                   (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = j.id) AS application_count
                   {$distanceSql}
            FROM jobs j
            JOIN categories c ON j.category_id = c.id
            JOIN customer_profiles cp ON j.customer_id = cp.id
            JOIN users u ON cp.user_id = u.id
            {$whereSql}
            ORDER BY {$orderBy}";
    $allQueryParams = array_merge($selectParams, $params);
    $jobs = dbFetchAll($sql, $allQueryParams);
} catch (Exception $e) {
    error_log("Jobs query error: " . $e->getMessage());
    $jobs = [];
}
?>

<div class="bg-primary text-white py-4 mb-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h2 fw-bold mb-1">
                    <i class="fas fa-briefcase me-2 text-warning"></i><?= __('nav_find_jobs') ?>
                </h1>
                <p class="mb-0 text-white-50 small">
                    <?= ($curLang === 'hi') ? 'ग्राहकों द्वारा पोस्ट किए गए काम देखें, बोली लगाएं और सीधे कमाई शुरू करें' : 'Browse customer requirements, submit proposals & earn directly with zero middleman commissions' ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <?php if (isCustomer()): ?>
                    <?php if (isUserVerified()): ?>
                        <a href="<?= url('/customer/post-job.php') ?>" class="btn btn-warning text-dark fw-bold btn-sm py-2 px-3 shadow-sm">
                            <i class="fas fa-plus-circle me-1"></i><?= __('post_new_job') ?>
                        </a>
                    <?php else: ?>
                        <a href="<?= url('/customer/verify.php') ?>" class="btn btn-warning text-dark fw-bold btn-sm py-2 px-3 shadow-sm" title="Identity verification required before posting jobs">
                            <i class="fas fa-shield-halved me-1"></i>Verify ID to Post Job
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge bg-white text-primary px-3 py-2 rounded-pill fw-bold">
                        <?= count($jobs) ?> <?= ($curLang === 'hi') ? 'काम उपलब्ध' : 'Open Listings' ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <!-- Verification Alert Banner for Pending/Unverified Users -->
    <?php if (isWorker() && !isUserVerified()): ?>
        <div class="alert alert-warning border-start border-4 border-warning shadow-sm rounded-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <i class="fas fa-shield-halved text-warning fs-5 me-2"></i>
                <strong>Identity Verification Required:</strong> Your profile is currently unverified. Submit your government ID to unlock job applications and bidding privileges.
            </div>
            <a href="<?= url('/worker/verify.php') ?>" class="btn btn-warning text-dark btn-sm fw-bold">
                <i class="fas fa-arrow-right me-1"></i>Verify Identity Now
            </a>
        </div>
    <?php elseif (isCustomer() && !isUserVerified()): ?>
        <div class="alert alert-warning border-start border-4 border-warning shadow-sm rounded-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <i class="fas fa-shield-halved text-warning fs-5 me-2"></i>
                <strong>Identity Verification Required:</strong> Your account is currently unverified. Complete identity verification to publish new job listings and hire workers.
            </div>
            <a href="<?= url('/customer/verify.php') ?>" class="btn btn-warning text-dark btn-sm fw-bold">
                <i class="fas fa-arrow-right me-1"></i>Verify Identity Now
            </a>
        </div>
    <?php endif; ?>
    <div class="row g-4">
        <!-- Filter Sidebar -->
        <div class="col-lg-3">
            <div class="kc-card p-3 p-md-4 sticky-top" style="top: 80px; z-index: 10;">
                <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-filter text-primary me-2"></i><?= __('filter_by') ?>
                    </h5>
                    <a href="<?= url('/jobs.php') ?>" class="text-danger small text-decoration-none">
                        <?= __('reset_filters') ?>
                    </a>
                </div>

                <form method="GET" action="<?= url('/jobs.php') ?>" id="kcWorkerSearchForm" data-api-url="<?= url('/api/geocode.php') ?>">
                    <!-- Hidden Geo State -->
                    <input type="hidden" name="lat" id="kcLatInput" value="<?= e($lat ?? '') ?>">
                    <input type="hidden" name="lon" id="kcLngInput" value="<?= e($lon ?? '') ?>">
                    <input type="hidden" name="district" id="kcDistrictInput" value="<?= e($selectedDistrict) ?>">
                    <input type="hidden" name="state" id="kcStateInput" value="<?= e($selectedState) ?>">

                    <!-- Keyword Search -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1"><?= __('search_job_placeholder') ?></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="q" class="form-control" value="<?= e($searchQuery) ?>" placeholder="Title or skill...">
                        </div>
                    </div>

                    <!-- Category Filter -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1"><?= __('job_category') ?></label>
                        <select name="category" class="form-select form-select-sm">
                            <option value=""><?= __('select_category') ?></option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>" <?= ($selectedCategory === (int)$cat['id']) ? 'selected' : '' ?>>
                                    <?= ($curLang === 'hi') ? e($cat['name_hi']) : e($cat['name_en']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- City Filter with Autocomplete & Map Picker -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1"><?= __('select_city') ?></label>
                        <div class="kc-location-wrapper">
                            <div class="kc-location-input-group">
                                <input type="text" 
                                       name="city" 
                                       id="kcLocationInput" 
                                       class="form-control form-control-sm" 
                                       value="<?= e($selectedCity) ?>" 
                                       placeholder="e.g. Noida, Delhi, Lajpat Nagar" 
                                       autocomplete="off">
                                <button type="button" 
                                        class="kc-btn-map-picker btn-sm py-1 px-2" 
                                        id="kcBtnChooseOnMap" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#kcMapModal" 
                                        title="Choose on map">
                                    <i class="fas fa-map-marked-alt text-primary"></i>
                                </button>
                            </div>
                            <div id="kcLocationDropdown" class="kc-autocomplete-dropdown"></div>
                        </div>
                    </div>

                    <!-- Search Radius Filter -->
                    <div class="mb-3" id="kcRadiusFilterGroup">
                        <label class="form-label small fw-semibold text-muted mb-1">
                            <i class="fas fa-bullseye text-primary me-1"></i>Search Radius
                        </label>
                        <select name="radius" class="form-select form-select-sm">
                            <option value="10" <?= ($radius === 10) ? 'selected' : '' ?>>Within 10 km</option>
                            <option value="25" <?= ($radius === 25) ? 'selected' : '' ?>>Within 25 km</option>
                            <option value="50" <?= ($radius === 50) ? 'selected' : '' ?>>Within 50 km (Default)</option>
                            <option value="100" <?= ($radius === 100) ? 'selected' : '' ?>>Within 100 km</option>
                            <option value="0" <?= ($radius === 0) ? 'selected' : '' ?>>Any Distance</option>
                        </select>
                    </div>

                    <!-- Wage Type Filter -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1"><?= __('job_wage_type') ?></label>
                        <select name="wage_type" class="form-select form-select-sm">
                            <option value=""><?= __('wage_type_all') ?></option>
                            <option value="fixed" <?= ($selectedWageType === 'fixed') ? 'selected' : '' ?>><?= __('wage_type_fixed') ?></option>
                            <option value="daily" <?= ($selectedWageType === 'daily') ? 'selected' : '' ?>><?= __('wage_type_daily') ?></option>
                            <option value="hourly" <?= ($selectedWageType === 'hourly') ? 'selected' : '' ?>><?= __('wage_type_hourly') ?></option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="open" <?= ($selectedStatus === 'open') ? 'selected' : '' ?>>Open for Bids Only</option>
                            <option value="all" <?= ($selectedStatus === 'all') ? 'selected' : '' ?>>All Statuses</option>
                            <option value="assigned" <?= ($selectedStatus === 'assigned') ? 'selected' : '' ?>>In Progress (Assigned)</option>
                            <option value="completed" <?= ($selectedStatus === 'completed') ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </div>

                    <!-- Sort By -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Sort By</label>
                        <select name="sort" class="form-select form-select-sm">
                            <?php if ($hasCoordinates): ?>
                                <option value="distance_asc" <?= ($sortBy === 'distance_asc') ? 'selected' : '' ?>>Nearest to Me</option>
                            <?php endif; ?>
                            <option value="newest" <?= ($sortBy === 'newest') ? 'selected' : '' ?>>Newest Postings First</option>
                            <option value="budget_desc" <?= ($sortBy === 'budget_desc') ? 'selected' : '' ?>>Budget (Highest First)</option>
                            <option value="budget_asc" <?= ($sortBy === 'budget_asc') ? 'selected' : '' ?>>Budget (Lowest First)</option>
                            <option value="date_asc" <?= ($sortBy === 'date_asc') ? 'selected' : '' ?>>Work Date (Soonest First)</option>
                        </select>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-kc-primary btn-sm py-2">
                            <i class="fas fa-filter me-1"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Job Listings -->
        <div class="col-lg-9">
            <!-- Results Header -->
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 bg-white p-3 rounded-3 border">
                <div class="small text-muted">
                    Showing <strong class="text-dark"><?= count($jobs) ?></strong> job requirement<?= count($jobs) === 1 ? '' : 's' ?>
                    <?php if ($searchQuery !== ''): ?>
                        for "<strong><?= e($searchQuery) ?></strong>"
                    <?php endif; ?>
                </div>
                <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0">
                    <span class="small text-muted">Quick Filter:</span>
                    <a href="<?= url('/jobs.php?wage_type=fixed') ?>" class="badge bg-light text-primary border text-decoration-none">Fixed Budget</a>
                    <a href="<?= url('/jobs.php?wage_type=daily') ?>" class="badge bg-light text-primary border text-decoration-none">Daily Rate</a>
                    <a href="<?= url('/jobs.php?status=open') ?>" class="badge bg-success-subtle text-success border text-decoration-none">Active Bids</a>
                </div>
            </div>

            <!-- Empty State -->
            <?php if (empty($jobs)): ?>
                <div class="kc-card p-5 text-center my-4">
                    <div class="mb-3 text-muted">
                        <i class="fas fa-clipboard-list fa-4x opacity-50"></i>
                    </div>
                    <h4 class="fw-bold text-dark">No Jobs Matching Filters</h4>
                    <p class="text-muted small mx-auto" style="max-width: 450px;">
                        There are currently no job listings that match your search filters. Try clearing your filters or checking back soon.
                    </p>
                    <div>
                        <a href="<?= url('/jobs.php') ?>" class="btn btn-kc-primary btn-sm">
                            <i class="fas fa-undo me-1"></i>Reset All Filters
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($jobs as $job): ?>
                        <?php 
                            $skills = !empty($job['required_skills']) ? array_filter(array_map('trim', explode(',', $job['required_skills']))) : [];
                        ?>
                        <div class="kc-card p-4">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-2">
                                <div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                                        <span class="badge bg-light text-primary border px-2 py-1">
                                            <i class="fas <?= e($job['cat_icon']) ?> me-1"></i>
                                            <?= ($curLang === 'hi') ? e($job['cat_name_hi']) : e($job['cat_name_en']) ?>
                                        </span>
                                        <?= renderStatusBadge($job['status']) ?>
                                        <span class="text-muted small">
                                            <i class="fas fa-clock me-1"></i><?= timeAgo($job['created_at']) ?>
                                        </span>
                                    </div>
                                    <h4 class="fw-bold text-dark mb-1">
                                        <?= e($job['title']) ?>
                                    </h4>
                                    <div class="d-flex flex-wrap align-items-center gap-3 small text-muted">
                                        <span>
                                            <i class="fas fa-map-marker-alt text-danger me-1"></i><?= e($job['locality']) ?>, <?= e($job['city']) ?>
                                        </span>
                                        <?php if (isset($job['distance_km']) && is_numeric($job['distance_km'])): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2">
                                                <i class="fas fa-location-arrow me-1"></i><?= round((float)$job['distance_km'], 1) ?> km away
                                            </span>
                                        <?php endif; ?>
                                        <span>
                                            <i class="fas fa-calendar-check text-info me-1"></i>Work Date: <strong><?= date('d M Y', strtotime($job['preferred_date'])) ?></strong>
                                        </span>
                                        <span>
                                            <i class="fas fa-user text-secondary me-1"></i>Posted by <strong><?= e($job['customer_name']) ?></strong>
                                        </span>
                                    </div>
                                </div>

                                <div class="text-md-end flex-shrink-0">
                                    <span class="text-muted small d-block"><?= __('job_budget') ?></span>
                                    <span class="fw-extrabold fs-3 text-success">
                                        ₹<?= number_format((float)$job['budget']) ?>
                                    </span>
                                    <span class="badge bg-light text-dark border small ms-1 text-capitalize">
                                        <?= e($job['wage_type']) ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Description -->
                            <p class="text-muted small my-3">
                                <?= nl2br(e($job['description'])) ?>
                            </p>

                            <!-- Skills Required -->
                            <?php if (!empty($skills)): ?>
                                <div class="d-flex flex-wrap align-items-center gap-1 mb-3">
                                    <span class="small text-muted me-1">Required Skills:</span>
                                    <?php foreach ($skills as $sk): ?>
                                        <span class="badge bg-light text-dark border small fw-normal"><?= e($sk) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Bottom Action Bar -->
                            <div class="pt-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div class="small text-muted">
                                    <i class="fas fa-users text-primary me-1"></i>
                                    <strong><?= (int)$job['application_count'] ?></strong> applicant<?= (int)$job['application_count'] === 1 ? '' : 's' ?> submitted proposals
                                </div>
                                <div>
                                    <?php if (isWorker()): ?>
                                        <?php if (isUserVerified()): ?>
                                            <a href="<?= url('/worker/job-details.php?id=' . (int)$job['id']) ?>" class="btn btn-kc-primary btn-sm px-3">
                                                <i class="fas fa-paper-plane me-1"></i><?= __('apply_for_job') ?>
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= url('/worker/verify.php') ?>" class="btn btn-outline-warning text-dark btn-sm px-3 fw-bold" title="Identity verification required before submitting proposals">
                                                <i class="fas fa-shield-halved text-warning me-1"></i>Verify ID to Apply
                                            </a>
                                        <?php endif; ?>
                                    <?php elseif (isCustomer()): ?>
                                        <a href="<?= url('/customer/manage-jobs.php') ?>" class="btn btn-kc-outline btn-sm px-3">
                                            <i class="fas fa-eye me-1"></i>View in Dashboard
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= url('/login.php?redirect=' . urlencode(url('/jobs.php'))) ?>" class="btn btn-kc-primary btn-sm px-3">
                                            <i class="fas fa-sign-in-alt me-1"></i>Login to Apply
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Interactive Map Location Picker Modal -->
<div class="modal fade" id="kcMapModal" tabindex="-1" aria-labelledby="kcMapModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: var(--kc-radius-lg);">
            <div class="modal-header bg-light border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="kcMapModalLabel">
                    <i class="fas fa-map-marked-alt text-primary"></i>
                    Choose Location on Map
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <p class="text-muted small mb-3">
                    Drag the pin marker or click anywhere on the map to set your location. You can also search for a specific city or locality.
                </p>

                <!-- In-Modal Location Search -->
                <div class="input-group input-group-sm mb-3">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="kcMapSearchInput" class="form-control" placeholder="Search city, town, locality or landmark in India...">
                    <button class="btn btn-outline-primary" type="button" id="kcBtnMapSearch">
                        <i class="fas fa-search"></i>
                    </button>
                </div>

                <!-- Leaflet Interactive Map Container -->
                <div id="kcMapContainer" class="kc-map-container mb-3 shadow-sm"></div>

                <!-- Reverse Geocoded Address Preview -->
                <div class="kc-map-address-preview">
                    <div class="small text-muted mb-1 fw-semibold">
                        <i class="fas fa-location-dot text-primary me-1"></i>Selected Address / Locality:
                    </div>
                    <div class="small text-dark fw-bold" id="kcMapAddressPreviewText">
                        Click on the map or drag the pin to select a location.
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top py-2 px-3 d-flex justify-content-between align-items-center">
                <span class="small text-muted d-none d-sm-inline">
                    <i class="fas fa-info-circle me-1"></i>Powered by OpenStreetMap
                </span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-kc-primary btn-sm px-4 fw-bold" id="kcBtnConfirmLocation">
                        <i class="fas fa-check me-1"></i>Confirm Location
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

