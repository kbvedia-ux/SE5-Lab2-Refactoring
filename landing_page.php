<?php
function landingAssetUrl(array $baseNames): ?string
{
    $folders = ['images', 'assets', 'uploads'];
    $extensions = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];

    foreach ($folders as $folder) {
        foreach ($baseNames as $baseName) {
            foreach ($extensions as $extension) {
                $relativePath = $folder . '/' . $baseName . '.' . $extension;
                $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

                if (is_file($absolutePath)) {
                    return $relativePath . '?v=' . filemtime($absolutePath);
                }
            }
        }
    }

    return null;
}

$landingLogo = landingAssetUrl(['landing-logo', 'logo', 'ua-logo', 'school-logo']);
$heroImage = landingAssetUrl(['UA-GATE2', 'ua-gate2', 'landing-hero', 'hero', 'ua-gate', 'UA-Gate', 'gate', 'landing-image', 'boarding-hero']);
$aboutImage = landingAssetUrl(['download1', 'ua', 'about-image', 'about', 'boarding-about']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boarding House Accreditation</title>
    <link rel="stylesheet" href="css/style.css?v=52">
</head>
<body>

<header>
    <div class="logo">
        <?php if ($landingLogo): ?>
            <img src="<?php echo htmlspecialchars($landingLogo); ?>" class="logo-img" alt="Boarding House logo">
        <?php else: ?>
            <div class="logo-fallback" aria-hidden="true">BH</div>
        <?php endif; ?>
        <div class="logo-text">
            <h3>Boarding House Accreditation</h3>
            <p>University of Antique</p>
        </div>
    </div>

    <nav>
        <ul>
            <li><a href="#features">Features</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#contact">Contact</a></li>
            <li><a href="login.php" class="login-btn">Login</a></li>
        </ul>
    </nav>
</header>

<section class="hero">
    <div class="hero-left">
        <span class="tag">Official Accreditation System</span>
        <h1>Streamline Your Boarding House Management</h1>
        <p>
            A comprehensive accreditation system designed for boarding
            house owners and students of the University of Antique.
            Manage rooms, documents, and communication with ease.
        </p>
        <div class="hero-buttons">
            <a href="login.php" class="btn btn-green">Get Started</a>
        </div>
    </div>

    <div class="hero-right">
        <?php if ($heroImage): ?>
            <div class="landing-image-card">
                <img src="<?php echo htmlspecialchars($heroImage); ?>" class="landing-display-image" alt="Boarding house accreditation">
            </div>
        <?php else: ?>
            <div class="image-placeholder">
                Upload Hero Image Later
            </div>
        <?php endif; ?>
    </div>
</section>

<section id="features">
    <h2 class="section-title">Powerful Features For Landlords</h2>
    <p class="section-sub">
        Everything you need to manage your boarding house efficiently.
    </p>

    <div class="features">
        <div class="feature-card">
            <div class="feature-icon">🛡️</div>
            <h3>Secure Authentication</h3>
            <p>Protected access and user management.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">🏠</div>
            <h3>Room Management</h3>
            <p>Manage rooms and availability.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">📄</div>
            <h3>Document Uploads</h3>
            <p>Upload accreditation requirements.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">🚫</div>
            <h3>Blacklist Management</h3>
            <p>Monitor restricted tenants.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">🔔</div>
            <h3>Notifications</h3>
            <p>Stay updated with system alerts.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">💬</div>
            <h3>Internal Messaging</h3>
            <p>Communicate directly through the platform.</p>
        </div>
    </div>
</section>

<section id="about">
    <div class="about">
        <?php if ($aboutImage): ?>
            <div class="about-image has-upload">
                <img src="<?php echo htmlspecialchars($aboutImage); ?>" class="landing-display-image" alt="About the boarding house system">
            </div>
        <?php else: ?>
            <div class="about-image">
                Upload About Image Later
            </div>
        <?php endif; ?>

        <div class="about-content">
            <h2>About the System</h2>
            <p>
                The Boarding House Accreditation System is designed
                to simplify accreditation procedures while ensuring
                quality standards for landlords and students.
            </p>
            <ul class="about-list">
                <li>✔ Official University Verification</li>
                <li>✔ Secure & Reliable</li>
                <li>✔ Community Focused</li>
            </ul>
        </div>
    </div>
</section>

<section class="cta">
    <h2>Ready to Get Started?</h2>
    <p>Join the official boarding house accreditation system today.</p>
    <a href="dashboard.php" class="cta-btn">Access Your Dashboard</a>
</section>

<section id="contact">
    <h2 class="section-title">Get in Touch</h2>

    <div class="contact-grid">
        <div class="contact-card">
            <h3>Email</h3>
            <p>Landlord@gmail.com</p>
        </div>

        <div class="contact-card">
            <h3>Phone</h3>
            <p>+63 966 221 0876</p>
        </div>

        <div class="contact-card">
            <h3>Location</h3>
            <p>Sibalom, Antique, Philippines</p>
        </div>
    </div>
</section>

<footer>
    <div class="footer-grid">
        <div>
            <h3>Boarding House</h3>
            <p class="footer-desc">Official Accreditation System</p>
        </div>

        <div>
            <h3>Quick Links</h3>
            <ul>
                <li><a href="#features">Features</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
        </div>

        <div>
            <h3>For Landlords</h3>
            <ul>
                <li><a href="register.php">Register</a></li>
                <li><a href="login.php">Login</a></li>
            </ul>
        </div>

        <div>
            <h3>Contact</h3>
            <p class="footer-desc">Sibalom, Antique</p>
        </div>
    </div>

    <div class="copyright">
        &copy; <?php echo date('Y'); ?> Boarding House Accreditation System
    </div>
</footer>

</body>
</html>
