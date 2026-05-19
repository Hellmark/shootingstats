<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";

require_auth();

// Only accept POST requests with a valid CSRF token
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: list.php");
    exit();
}

require_csrf();

$id = (int) ($_POST["id"] ?? 0);
if ($id) {
    delete_incident($id);
}
header("Location: list.php?deleted=1");
exit();
