<?php
// includes/config.php
// Defaults only. Override any constant in config.local.php (never committed).

// Local overrides load first so their defines win
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

defined('DB_HOST')    || define('DB_HOST',    'localhost');
defined('DB_NAME')    || define('DB_NAME',    'school_shootings');
defined('DB_USER')    || define('DB_USER',    'your_db_user');
defined('DB_PASS')    || define('DB_PASS',    'your_db_password');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

defined('SITE_TITLE')     || define('SITE_TITLE',     'U.S. School Shooting Statistics');
defined('ADMIN_PASSWORD') || define('ADMIN_PASSWORD', password_hash('changeme', PASSWORD_DEFAULT));

// US transgender population percentage (approximate, per Williams Institute 2022)
// https://williamsinstitute.law.ucla.edu/publications/trans-adults-united-states/
defined('TRANS_POPULATION_PCT')    || define('TRANS_POPULATION_PCT',    0.008);
defined('TRANS_POPULATION_SOURCE') || define('TRANS_POPULATION_SOURCE', 'Williams Institute, UCLA School of Law (2025)');
