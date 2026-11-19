<?php
/**
 * KaamConnect - About Us & Platform Vision
 * Technology: PHP 8+, Bootstrap 5.3, Font Awesome 6
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/header.php';

$curLang = getCurrentLanguage();
$pageTitle = __('nav_about');
?>

<!-- About Hero Section -->
<section class="py-5" style="background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);">
    <div class="container text-center py-4">
        <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-2 rounded-pill mb-3">
            <i class="fas fa-handshake me-1"></i><?= ($curLang === 'hi') ? 'हमारा मिशन और विज़न' : 'Our Mission & Vision' ?>
        </span>
        <h1 class="display-4 fw-extrabold text-dark mb-3">
            <?= ($curLang === 'hi') ? 'स्थानीय कारीगरों और समाज का सशक्तिकरण' : 'Empowering Local Blue-Collar Artisans & Communities' ?>
        </h1>
        <p class="lead text-muted mx-auto" style="max-width: 750px;">
            <?= ($curLang === 'hi') 
                ? 'काम-कनेक्ट कुशल इलेक्ट्रीशियन, प्लंबर, पेंटर और मजदूरों को सीधे उनके आस-पास के ग्राहकों से जोड़ता है — बिना किसी बिचौलिए या कमीशन के।' 
                : 'KaamConnect is an open digital employment marketplace bridging skilled, unorganized trade workers with neighbourhood residential and commercial customers — completely commission-free.' ?>
        </p>
    </div>
</section>

<!-- The Problem & Solution Section -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="badge bg-danger-subtle text-danger fw-semibold px-3 py-1 rounded-pill mb-2">The Real-World Challenge</span>
                <h2 class="fw-bold mb-3">Why KaamConnect Was Built</h2>
                <p class="text-muted">
                    In India, over <strong>90% of blue-collar artisans and technicians</strong> operate in the unorganized informal sector. Despite carrying exceptional vocational skills, they face systemic hurdles:
                </p>
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex align-items-start gap-3">
                        <div class="p-2 bg-danger-subtle text-danger rounded-circle mt-1">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div>
                            <strong class="text-dark">Middleman Commission Exploitation:</strong>
                            <p class="text-muted small mb-0">Commercial aggregators and agencies deduct 20% to 35% commission on hard-earned wages of daily laborers.</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-start gap-3">
                        <div class="p-2 bg-danger-subtle text-danger rounded-circle mt-1">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div>
                            <strong class="text-dark">No Shareable Digital Portfolio:</strong>
                            <p class="text-muted small mb-0">Craftsmen have no online credibility history, testimonials, or verified badge to show their true craftsmanship.</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-start gap-3">
                        <div class="p-2 bg-danger-subtle text-danger rounded-circle mt-1">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div>
                            <strong class="text-dark">Customer Safety and Trust Gap:</strong>
                            <p class="text-muted small mb-0">Households struggle to find trusted, background-verified technicians during household plumbing, electrical, or structural emergencies.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="kc-card p-4 p-md-5 bg-light border-0 shadow">
                    <span class="badge bg-success-subtle text-success fw-semibold px-3 py-1 rounded-pill mb-2">The KaamConnect Solution</span>
                    <h3 class="fw-bold mb-3 text-dark">A Transparent, Direct Local Bridge</h3>
                    <p class="text-muted">
                        KaamConnect solves these issues by establishing a direct, hyper-local peer-to-peer ecosystem:
                    </p>
                    <ul class="list-unstyled d-flex flex-column gap-3 mb-0 text-muted">
                        <li class="d-flex align-items-start gap-2">
                            <i class="fas fa-check-circle text-success fs-5 mt-1"></i>
                            <div><strong>0% Platform Commission:</strong> Customers pay workers directly on site; workers retain 100% of their compensation.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="fas fa-check-circle text-success fs-5 mt-1"></i>
                            <div><strong>Aadhaar & Skill Verification:</strong> Dedicated administrative checks provide verifiable trust badges for peace of mind.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="fas fa-check-circle text-success fs-5 mt-1"></i>
                            <div><strong>Built-In Private Chat:</strong> Negotiate scope, timing, and custom wage proposals in real-time before booking.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="fas fa-check-circle text-success fs-5 mt-1"></i>
                            <div><strong>Full Bilingual Support:</strong> Accessible in English and Hindi to bridge linguistic barriers.</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Core Platform Pillars Grid -->
<section class="py-5 bg-light border-top border-bottom">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Platform Fundamentals</span>
            <h2 class="fw-bold">Our Foundational Pillars</h2>
            <p class="text-muted mx-auto" style="max-width: 600px;">
                Designed specifically to balance worker dignity and customer convenience.
            </p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="kc-card p-4 h-100 text-center">
                    <div class="p-3 bg-primary-subtle text-primary rounded-circle d-inline-flex mb-3">
                        <i class="fas fa-hand-holding-dollar fa-2x"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Zero Commission Fee</h5>
                    <p class="text-muted small mb-0">
                        We believe labor wages belong completely to the worker. Our platform never takes cuts, listing fees, or application penalties.
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="kc-card p-4 h-100 text-center">
                    <div class="p-3 bg-success-subtle text-success rounded-circle d-inline-flex mb-3">
                        <i class="fas fa-id-card-clip fa-2x"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Identity & Skill Verification</h5>
                    <p class="text-muted small mb-0">
                        Admin verification protocols ensure background legitimacy and check vocational competencies before awarding verified credentials.
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="kc-card p-4 h-100 text-center">
                    <div class="p-3 bg-warning-subtle text-warning rounded-circle d-inline-flex mb-3">
                        <i class="fas fa-comments fa-2x"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Integrated Safe Messaging</h5>
                    <p class="text-muted small mb-0">
                        Customers and workers converse directly through real-time threaded chat with AJAX polling, keeping contact private until desired.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Academic Context & Technology Specifications -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="kc-card p-4 p-md-5 border-start border-4 border-primary">
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                        <div>
                            <span class="badge bg-dark text-white px-3 py-1 rounded-pill small mb-1">Academic Project</span>
                            <h3 class="fw-bold mb-0 text-dark">B.Tech Computer Science and Engineering</h3>
                        </div>
                        <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-2">Final-Year Major Project</span>
                    </div>

                    <p class="text-muted">
                        KaamConnect is engineered as an enterprise-grade, lightweight web platform built using clean architectural patterns without heavy framework dependencies:
                    </p>

                    <div class="row g-3 my-2 text-dark">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <h6 class="fw-bold mb-1 text-primary"><i class="fas fa-server me-2"></i>Backend Architecture</h6>
                                <p class="small text-muted mb-0">PHP 8+ with PHP Data Objects (PDO), strict typing, prepared statements, and MVC-patterned route separation.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <h6 class="fw-bold mb-1 text-success"><i class="fas fa-database me-2"></i>Database Modeling</h6>
                                <p class="small text-muted mb-0">MySQL / MariaDB 14 normalized relational tables with foreign key cascades, multi-column indexes, and transactions.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <h6 class="fw-bold mb-1 text-warning"><i class="fas fa-palette me-2"></i>User Interface</h6>
                                <p class="small text-muted mb-0">Responsive HTML5, CSS3 design tokens, Bootstrap 5.3, Font Awesome 6, and Vanilla JavaScript (ES6+).</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <h6 class="fw-bold mb-1 text-info"><i class="fas fa-shield-alt me-2"></i>Security Suite</h6>
                                <p class="small text-muted mb-0">Cryptographic CSRF token validation, bcrypt password hashing, input sanitization against XSS, and IP rate limiting.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="py-5" style="background: linear-gradient(135deg, #1E40AF 0%, #1D4ED8 100%);">
    <div class="container text-center text-white py-2">
        <h2 class="fw-bold mb-3">Join the KaamConnect Community Today</h2>
        <p class="lead opacity-90 mx-auto mb-4" style="max-width: 600px;">
            Whether you want to earn with your trade or hire reliable local craftsmanship, KaamConnect is built for you.
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="<?= url('/register.php?role=worker') ?>" class="btn btn-warning fw-bold px-4 py-2 text-dark shadow-sm">
                <i class="fas fa-hammer me-2"></i>Register as a Worker
            </a>
            <a href="<?= url('/register.php?role=customer') ?>" class="btn btn-light fw-bold px-4 py-2 text-primary shadow-sm">
                <i class="fas fa-user-plus me-2"></i>Hire Verified Workers
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

