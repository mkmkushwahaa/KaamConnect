<?php
/**
 * KaamConnect - Public Worker Directory & Artisan Search
 * Technology: PHP 8+, MySQL PDO Parameterized Filtering, Bootstrap 5.3
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/header.php';

$curLang = getCurrentLanguage();

// 1. Capture and Sanitize Filter Inputs
$searchQuery = trim($_GET['q'] ?? '');
$selectedCategory = !empty($_GET['category']) ? (int)$_GET['category'] : null;
$selectedCity = trim($_GET['city'] ?? '');
$selectedDistrict = trim($_GET['district'] ?? '');
$selectedState = trim($_GET['state'] ?? '');

$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lon = filter_input(INPUT_GET, 'lon', FILTER_VALIDATE_FLOAT);
if ($lat === false || $lat === null || $lat < 6.0 || $lat > 38.0) $lat = null;
if ($lon === false || $lon === null || $lon < 68.0 || $lon > 98.0) $lon = null;
$hasCoordinates = ($lat !== null && $lon !== null);

$radius = filter_input(INPUT_GET, 'radius', FILTER_VALIDATE_INT) ?? 50;
if ($radius <= 0) $radius = 50;

$minRating = !empty($_GET['rating']) ? (float)$_GET['rating'] : 0.0;
$minExp = !empty($_GET['exp']) ? (int)$_GET['exp'] : 0;
$verifiedOnly = isset($_GET['verified']) && $_GET['verified'] === '1';
$availableOnly = isset($_GET['available']) && $_GET['available'] === '1';
$sortBy = trim($_GET['sort'] ?? ($hasCoordinates ? 'distance_asc' : 'rating_desc'));

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
    $distinctCities = dbFetchAll(
        "SELECT DISTINCT city FROM worker_profiles 
         WHERE city IS NOT NULL AND city != '' AND city != 'Not Set' 
         ORDER BY city ASC"
    );
} catch (Exception $e) {
    $distinctCities = [];
}

// 4. Build Dynamic Parameterized Query
$where = ["u.status = 'active'"];
$params = [];

// Parameter slots for distance calculation in SELECT list
$selectParams = [];
if ($hasCoordinates) {
    $distanceSql = ", (6371 * acos(LEAST(1.0, GREATEST(-1.0, 
        cos(radians(?)) * cos(radians(w.latitude)) * cos(radians(w.longitude) - radians(?)) + 
        sin(radians(?)) * sin(radians(w.latitude))
    )))) AS distance_km";
    $selectParams = [$lat, $lon, $lat];
} else {
    $distanceSql = ", NULL AS distance_km";
}

if ($searchQuery !== '') {
    $where[] = "(u.full_name LIKE ? OR w.bio LIKE ? OR w.locality LIKE ? OR c.name_en LIKE ? OR c.name_hi LIKE ? OR EXISTS (
        SELECT 1 FROM worker_skills ws WHERE ws.worker_id = w.id AND ws.skill_name LIKE ?
    ))";
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($selectedCategory) {
    $where[] = "w.primary_category_id = ?";
    $params[] = $selectedCategory;
}

// Location / Radius Filtering
if ($hasCoordinates && $radius > 0) {
    $where[] = "((w.latitude IS NOT NULL AND w.longitude IS NOT NULL AND (6371 * acos(LEAST(1.0, GREATEST(-1.0, 
        cos(radians(?)) * cos(radians(w.latitude)) * cos(radians(w.longitude) - radians(?)) + 
        sin(radians(?)) * sin(radians(w.latitude))
    )))) <= ?) OR (w.city LIKE ? OR w.locality LIKE ? OR w.district LIKE ?))";
    $params[] = $lat;
    $params[] = $lon;
    $params[] = $lat;
    $params[] = $radius;
    $params[] = '%' . $selectedCity . '%';
    $params[] = '%' . $selectedCity . '%';
    $params[] = '%' . $selectedCity . '%';
} elseif ($selectedCity !== '') {
    $where[] = "(w.city LIKE ? OR w.locality LIKE ? OR w.district LIKE ? OR w.state LIKE ?)";
    $params[] = '%' . $selectedCity . '%';
    $params[] = '%' . $selectedCity . '%';
    $params[] = '%' . $selectedCity . '%';
    $params[] = '%' . $selectedCity . '%';
}

if ($minRating > 0) {
    $where[] = "w.rating_avg >= ?";
    $params[] = $minRating;
}

if ($minExp > 0) {
    $where[] = "w.experience_years >= ?";
    $params[] = $minExp;
}

if ($verifiedOnly) {
    $where[] = "w.is_verified = 1";
}

if ($availableOnly) {
    $where[] = "w.is_available = 1";
}

// Determine ORDER BY clause
$orderBy = match ($sortBy) {
    'distance_asc' => 'CASE WHEN distance_km IS NULL THEN 1 ELSE 0 END, distance_km ASC, w.rating_avg DESC',
    'exp_desc'     => 'w.experience_years DESC, w.rating_avg DESC',
    'wage_asc'     => 'CASE WHEN w.daily_wage > 0 THEN w.daily_wage ELSE w.hourly_wage END ASC',
    'wage_desc'    => 'CASE WHEN w.daily_wage > 0 THEN w.daily_wage ELSE w.hourly_wage END DESC',
    'newest'       => 'w.created_at DESC',
    default        => 'w.is_verified DESC, w.rating_avg DESC, w.rating_count DESC'
};

$whereSql = implode(' AND ', $where);

// Execute Main Query combining SELECT params and WHERE params
$workers = [];
try {
    $sql = "SELECT w.*, u.full_name, u.phone, 
                   c.name_en AS cat_name_en, c.name_hi AS cat_name_hi, c.icon AS cat_icon,
                   (SELECT GROUP_CONCAT(skill_name SEPARATOR '||') FROM worker_skills ws WHERE ws.worker_id = w.id) AS skills_combined
                   {$distanceSql}
            FROM worker_profiles w
            JOIN users u ON w.user_id = u.id
            LEFT JOIN categories c ON w.primary_category_id = c.id
            WHERE {$whereSql}
            ORDER BY {$orderBy}";
    $allQueryParams = array_merge($selectParams, $params);
    $workers = dbFetchAll($sql, $allQueryParams);
} catch (Exception $e) {
    $workers = [];
}
?>

<div class="bg-primary text-white py-4 mb-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h2 fw-bold mb-1">
                    <i class="fas fa-user-tie me-2 text-warning"></i><?= __('nav_find_workers') ?>
                </h1>
                <p class="mb-0 text-white-50 small">
                    <?= ($curLang === 'hi') ? 'अपने नजदीकी सत्यापित प्लंबर, इलेक्ट्रीशियन, पेंटर और अन्य कुशल कारीगर खोजें' : 'Browse trusted, background-checked craftsmen, technicians, and local helpers' ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <span class="badge bg-white text-primary px-3 py-2 rounded-pill fw-bold">
                    <?= count($workers) ?> <?= ($curLang === 'hi') ? 'कारीगर उपलब्ध' : 'Workers Available' ?>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <?php if (isCustomer() && !isUserVerified()): ?>
        <div class="alert alert-warning border-start border-4 border-warning shadow-sm rounded-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <i class="fas fa-shield-halved text-warning fs-5 me-2"></i>
                <strong>Customer Verification Required:</strong> Identity verification is required before you can book or directly contact marketplace workers.
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
                        <i class="fas fa-sliders-h text-primary me-2"></i><?= __('filter_by') ?>
                    </h5>
                    <a href="<?= url('/workers.php') ?>" class="text-danger small text-decoration-none">
                        <?= __('reset_filters') ?>
                    </a>
                </div>

                <form method="GET" action="<?= url('/workers.php') ?>" id="kcWorkerSearchForm" data-api-url="<?= url('/api/geocode.php') ?>">
                    <!-- Hidden Geo State -->
                    <input type="hidden" name="lat" id="kcLatInput" value="<?= e($lat ?? '') ?>">
                    <input type="hidden" name="lon" id="kcLngInput" value="<?= e($lon ?? '') ?>">
                    <input type="hidden" name="district" id="kcDistrictInput" value="<?= e($selectedDistrict) ?>">
                    <input type="hidden" name="state" id="kcStateInput" value="<?= e($selectedState) ?>">

                    <!-- Keyword Search -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1"><?= __('search_worker_placeholder') ?></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="q" class="form-control" value="<?= e($searchQuery) ?>" placeholder="Name or skill...">
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

                    <!-- City & Locality Filter with Autocomplete & Map -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1"><?= __('select_city') ?></label>
                        <div class="kc-location-wrapper">
                            <div class="kc-location-input-group">
                                <input type="text" 
                                       name="city" 
                                       id="kcLocationInput" 
                                       class="form-control form-control-sm" 
                                       value="<?= e($selectedCity) ?>" 
                                       placeholder="e.g. Noida, Patna, Lajpat Nagar" 
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

                    <!-- Distance Radius Filter -->
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

                    <!-- Minimum Experience -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1"><?= __('experience_years') ?></label>
                        <select name="exp" class="form-select form-select-sm">
                            <option value="0" <?= ($minExp === 0) ? 'selected' : '' ?>>Any Experience</option>
                            <option value="2" <?= ($minExp === 2) ? 'selected' : '' ?>>2+ Years</option>
                            <option value="5" <?= ($minExp === 5) ? 'selected' : '' ?>>5+ Years</option>
                            <option value="10" <?= ($minExp === 10) ? 'selected' : '' ?>>10+ Years</option>
                        </select>
                    </div>

                    <!-- Minimum Star Rating -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1"><?= __('min_rating') ?></label>
                        <select name="rating" class="form-select form-select-sm">
                            <option value="0" <?= ($minRating <= 0.0) ? 'selected' : '' ?>>All Ratings</option>
                            <option value="4.5" <?= ($minRating == 4.5) ? 'selected' : '' ?>>★ 4.5 & Above</option>
                            <option value="4.0" <?= ($minRating == 4.0) ? 'selected' : '' ?>>★ 4.0 & Above</option>
                            <option value="3.0" <?= ($minRating == 3.0) ? 'selected' : '' ?>>★ 3.0 & Above</option>
                        </select>
                    </div>

                    <!-- Verified Only Checkbox -->
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="verified" value="1" id="filterVerified" <?= $verifiedOnly ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-semibold text-dark" for="filterVerified">
                            <i class="fas fa-check-circle text-success me-1"></i><?= __('verified_only') ?>
                        </label>
                    </div>

                    <!-- Available Only Checkbox -->
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="available" value="1" id="filterAvailable" <?= $availableOnly ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-semibold text-dark" for="filterAvailable">
                            <i class="fas fa-bolt text-warning me-1"></i><?= __('available_now') ?>
                        </label>
                    </div>

                    <!-- Sort By -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Sort Results By</label>
                        <select name="sort" class="form-select form-select-sm">
                            <?php if ($hasCoordinates): ?>
                                <option value="distance_asc" <?= ($sortBy === 'distance_asc') ? 'selected' : '' ?>>Nearest Distance</option>
                            <?php endif; ?>
                            <option value="rating_desc" <?= ($sortBy === 'rating_desc') ? 'selected' : '' ?>>Highest Rated</option>
                            <option value="exp_desc" <?= ($sortBy === 'exp_desc') ? 'selected' : '' ?>>Most Experienced</option>
                            <option value="wage_asc" <?= ($sortBy === 'wage_asc') ? 'selected' : '' ?>>Wage (Lowest First)</option>
                            <option value="wage_desc" <?= ($sortBy === 'wage_desc') ? 'selected' : '' ?>>Wage (Highest First)</option>
                            <option value="newest" <?= ($sortBy === 'newest') ? 'selected' : '' ?>>Recently Joined</option>
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

        <!-- Worker Results List -->
        <div class="col-lg-9">
            <!-- Results Header / Filter Tags -->
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 bg-white p-3 rounded-3 border">
                <div class="small text-muted">
                    Showing <strong class="text-dark"><?= count($workers) ?></strong> verified worker<?= count($workers) === 1 ? '' : 's' ?>
                    <?php if ($searchQuery !== ''): ?>
                        for "<strong><?= e($searchQuery) ?></strong>"
                    <?php endif; ?>
                </div>
                <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0">
                    <span class="small text-muted">Quick Search:</span>
                    <a href="<?= url('/workers.php?category=3') ?>" class="badge bg-light text-primary border text-decoration-none">Electricians</a>
                    <a href="<?= url('/workers.php?category=2') ?>" class="badge bg-light text-primary border text-decoration-none">Plumbers</a>
                    <a href="<?= url('/workers.php?verified=1') ?>" class="badge bg-success-subtle text-success border text-decoration-none">Verified</a>
                </div>
            </div>

            <!-- Empty State -->
            <?php if (empty($workers)): ?>
                <div class="kc-card p-5 text-center my-4">
                    <div class="mb-3 text-muted">
                        <i class="fas fa-user-slash fa-4x opacity-50"></i>
                    </div>
                    <h4 class="fw-bold text-dark">No Workers Found</h4>
                    <p class="text-muted small mx-auto" style="max-width: 450px;">
                        We couldn't find any artisans matching your specified criteria. Try clearing some filters or searching with a different trade or city.
                    </p>
                    <div>
                        <a href="<?= url('/workers.php') ?>" class="btn btn-kc-primary btn-sm">
                            <i class="fas fa-undo me-1"></i>Reset All Filters
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($workers as $worker): ?>
                        <?php 
                            $skills = !empty($worker['skills_combined']) ? explode('||', $worker['skills_combined']) : [];
                        ?>
                        <div class="col-md-6 col-xl-6">
                            <div class="kc-card h-100 d-flex flex-column">
                                <div class="p-3 p-md-4 border-bottom d-flex align-items-start gap-3">
                                    <div class="position-relative flex-shrink-0">
                                        <img src="<?= asset($worker['profile_photo'] ?? 'assets/images/default-avatar.svg') ?>" 
                                             alt="<?= e($worker['full_name']) ?>" 
                                             width="70" height="70" 
                                             class="rounded-circle border border-2 border-primary object-fit-cover shadow-sm">
                                        <?php if ((int)$worker['is_available'] === 1): ?>
                                            <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-white rounded-circle" title="Available for work">
                                                <span class="visually-hidden">Available</span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <h5 class="fw-bold text-dark mb-0 text-truncate" style="max-width: 170px;">
                                                <?= e($worker['full_name']) ?>
                                            </h5>
                                            <?php if ((int)$worker['is_verified'] === 1): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill small py-1 px-2" title="Aadhaar & Background Verified">
                                                    <i class="fas fa-check-circle me-1"></i>Verified
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted small mt-1">
                                            <i class="fas <?= e($worker['cat_icon'] ?? 'fa-tools') ?> text-primary me-1"></i>
                                            <strong><?= ($curLang === 'hi') ? e($worker['cat_name_hi'] ?? 'कारीगर') : e($worker['cat_name_en'] ?? 'Artisan') ?></strong>
                                        </div>
                                        <div class="mt-1 d-flex align-items-center gap-1">
                                            <?= renderStars((float)$worker['rating_avg']) ?>
                                            <span class="text-muted small">(<?= (int)$worker['rating_count'] ?>)</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-3 p-md-4 flex-grow-1 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex justify-content-between align-items-center small text-muted mb-2">
                                            <span>
                                                <i class="fas fa-map-marker-alt text-danger me-1"></i><?= e($worker['locality']) ?>, <?= e($worker['city']) ?>
                                                <?php if (!empty($worker['state'])): ?>
                                                    <span class="text-muted small">(<?= e($worker['state']) ?>)</span>
                                                <?php endif; ?>
                                            </span>
                                            <span><i class="fas fa-history text-secondary me-1"></i><?= (int)$worker['experience_years'] ?> yrs experience</span>
                                        </div>

                                        <?php if (isset($worker['distance_km']) && $worker['distance_km'] !== null): ?>
                                            <div class="mb-2">
                                                <span class="badge bg-primary-subtle text-primary border rounded-pill small py-1 px-2">
                                                    <i class="fas fa-location-arrow me-1"></i><?= number_format((float)$worker['distance_km'], 1) ?> km away
                                                </span>
                                            </div>
                                        <?php endif; ?>

                                        <p class="text-muted small line-clamp-2 mb-3">
                                            <?= e($worker['bio'] ?: 'Experienced local professional providing reliable artisan service with verified credentials.') ?>
                                        </p>

                                        <!-- Skills Tags -->
                                        <?php if (!empty($skills)): ?>
                                            <div class="d-flex flex-wrap gap-1 mb-3">
                                                <?php foreach (array_slice($skills, 0, 3) as $sk): ?>
                                                    <span class="badge bg-light text-dark border small fw-normal"><?= e($sk) ?></span>
                                                <?php endforeach; ?>
                                                <?php if (count($skills) > 3): ?>
                                                    <span class="badge bg-light text-muted border small">+<?= count($skills) - 3 ?> more</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="pt-3 border-top d-flex align-items-center justify-content-between">
                                        <div>
                                            <span class="text-muted small d-block"><?= __('expected_wage') ?></span>
                                            <span class="fw-bolder text-dark fs-5">
                                                ₹<?= number_format((float)($worker['daily_wage'] > 0 ? $worker['daily_wage'] : $worker['hourly_wage'])) ?>
                                            </span>
                                            <span class="text-muted small">/<?= $worker['daily_wage'] > 0 ? 'day' : 'hr' ?></span>
                                        </div>
                                        <div>
                                            <?php if (isCustomer()): ?>
                                                <?php if (isUserVerified()): ?>
                                                    <a href="<?= url('/customer/book-worker.php?worker_id=' . (int)$worker['id']) ?>" class="btn btn-kc-primary btn-sm">
                                                        <i class="fas fa-handshake me-1"></i><?= __('contact_worker') ?>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?= url('/customer/verify.php') ?>" class="btn btn-outline-warning text-dark btn-sm fw-bold" title="Identity verification required before contacting workers">
                                                        <i class="fas fa-shield-halved text-warning me-1"></i>Verify ID to Contact
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <a href="<?= url('/login.php?redirect=' . urlencode(url('/workers.php'))) ?>" class="btn btn-kc-primary btn-sm">
                                                    <i class="fas fa-comment-dots me-1"></i><?= __('contact_worker') ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>

