<?php
// includes/layout.php

function page_header(string $title = "", bool $is_admin = false): void
{
    // Security headers
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("X-XSS-Protection: 0"); // modern browsers handle this via CSP
    header(
        "Content-Security-Policy: default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; " .
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; " .
            "font-src 'self' https://fonts.gstatic.com; " .
            "img-src 'self' data: https://*.tile.openstreetmap.org https://*.basemaps.cartocdn.com https://*.openstreetmap.org; " .
            "connect-src 'self' https://nominatim.openstreetmap.org; " .
            "form-action 'self'; " .
            "frame-ancestors 'self'; " .
            "frame-src 'self'; " .
            "base-uri 'self'",
    );
    header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');

    $full_title = trim(($title ? $title . " — " : "") . SITE_TITLE);
    $base = $is_admin ? "../" : "";
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($full_title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Source+Serif+4:ital,opsz,wght@0,8..60,300;0,8..60,400;0,8..60,600;1,8..60,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base ?>assets/css/main.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<header class="site-header">
    <div class="container">
        <a href="<?= $base ?>index.php" class="site-brand">
            <span class="brand-eyebrow">United States</span>
            <span class="brand-title">School Shooting Statistics</span>
        </a>
        <nav class="site-nav">
            <a href="<?= $base ?>index.php">Overview</a>
            <a href="<?= $base ?>incidents.php">All Incidents</a>
            <a href="<?= $base ?>analysis.php">Analysis</a>
            <a href="<?= $base ?>heatmap.php">Map</a>
            <?php if ($is_admin): ?>
            <a href="<?= $base ?>admin/index.php" class="nav-admin">Admin</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="site-main">
    <?php
}

function page_footer(): void
{
    ?>
</main>
<footer class="site-footer">
    <div class="container">
        <p>Data compiled from public records, news sources, and official reports. This site exists to promote evidence-based understanding of gun violence in schools.</p>
        <p class="footer-small">Statistical comparisons use U.S. transgender population estimates from the <a href="https://williamsinstitute.law.ucla.edu/publications/trans-adults-united-states/" target="_blank">Williams Institute, UCLA (2022)</a>.</p>
    </div>
</footer>
</body>
</html>
    <?php
}

function flash(string $msg, string $type = "success"): void
{
    echo "<div class=\"alert alert--{$type}\">" .
        htmlspecialchars($msg) .
        "</div>";
}
