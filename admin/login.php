<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../includes/layout.php";

$error = "";

// ── First-run: no password set yet ────────────────────────────────────────
if (is_first_run()) {
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["new_password"])) {
        require_csrf();

        $db_host  = trim($_POST["db_host"] ?? "");
        $db_name  = trim($_POST["db_name"] ?? "");
        $db_user  = trim($_POST["db_user"] ?? "");
        $db_pass  = $_POST["db_pass"] ?? "";
        $new_pass = $_POST["new_password"] ?? "";
        $confirm  = $_POST["confirm_password"] ?? "";
        $config_path = __DIR__ . "/../includes/config.local.php";

        if (empty($db_host) || empty($db_name) || empty($db_user)) {
            $error = "Database host, name, and username are required.";
        } elseif (strlen($new_pass) < 10) {
            $error = "Admin password must be at least 10 characters.";
        } elseif ($new_pass !== $confirm) {
            $error = "Admin passwords do not match.";
        } elseif (!is_writable($config_path) && !is_writable(dirname($config_path))) {
            $error = "Cannot write to config.local.php — check file permissions.";
        } else {
            // Test the DB connection before saving anything
            $db_error = test_db_connection($db_host, $db_name, $db_user, $db_pass);
            if ($db_error) {
                $error = "Database connection failed: " . $db_error;
            } else {
                write_local_config($config_path, $db_host, $db_name, $db_user, $db_pass, $new_pass);

                $_SESSION["admin_authed"] = true;
                clear_failed_attempts();
                session_regenerate_id();
                header("Location: index.php");
                exit();
            }
        }
    }

    // Pre-fill with whatever is already in config
    $pre_host = defined('DB_HOST') && DB_HOST !== 'your_db_host' ? DB_HOST : 'localhost';
    $pre_name = defined('DB_NAME') && DB_NAME !== 'your_db_name' ? DB_NAME : 'school_shootings';
    $pre_user = defined('DB_USER') && DB_USER !== 'your_db_user' ? DB_USER : '';

    page_header("Admin Setup", true);
    ?>
    <div class="container" style="max-width:480px;padding-top:3rem">
        <h1 style="margin-bottom:.4rem">Admin Setup</h1>
        <p style="color:var(--ink-soft);margin-bottom:1.5rem;font-size:.9rem">Configure your database and admin password to get started.</p>
        <?php if ($error) flash($error, "error"); ?>
        <div style="background:white;border:1px solid var(--rule);padding:2rem;box-shadow:var(--shadow)">
            <form method="post" action="login.php">
                <?= csrf_field() ?>

                <h3 style="margin:0 0 1rem;font-size:.95rem;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft)">Database</h3>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-bottom:.8rem">
                    <div class="form-group">
                        <label for="db_host">Host</label>
                        <input type="text" id="db_host" name="db_host"
                               value="<?= htmlspecialchars($pre_host) ?>"
                               placeholder="localhost">
                    </div>
                    <div class="form-group">
                        <label for="db_name">Database Name</label>
                        <input type="text" id="db_name" name="db_name"
                               value="<?= htmlspecialchars($pre_name) ?>"
                               placeholder="school_shootings">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-bottom:1.8rem">
                    <div class="form-group">
                        <label for="db_user">Username</label>
                        <input type="text" id="db_user" name="db_user"
                               value="<?= htmlspecialchars($pre_user) ?>"
                               autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="db_pass">Password</label>
                        <input type="password" id="db_pass" name="db_pass"
                               autocomplete="new-password">
                    </div>
                </div>

                <h3 style="margin:0 0 1rem;font-size:.95rem;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft)">Admin Password</h3>

                <div class="form-group" style="margin-bottom:.8rem">
                    <label for="new_password">Password <span style="color:var(--ink-soft);font-weight:400">(min 10 characters)</span></label>
                    <input type="password" id="new_password" name="new_password"
                           autofocus autocomplete="new-password"
                           style="font-size:1rem;padding:.7rem 1rem">
                </div>
                <div class="form-group" style="margin-bottom:1.5rem">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           autocomplete="new-password"
                           style="font-size:1rem;padding:.7rem 1rem">
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%">Save &amp; Sign In</button>
            </form>
        </div>
        <p style="text-align:center;margin-top:1rem;font-size:.85rem"><a href="../index.php">← Public Site</a></p>
    </div>
    <?php
    page_footer();
    exit();
}

// ── Normal login ───────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    $result = attempt_login($_POST["password"] ?? "");
    if ($result === "") {
        header("Location: index.php");
        exit();
    }
    $error = $result;
}

page_header("Admin Login", false);
?>
<div class="container" style="max-width:420px;padding-top:3rem">
    <h1 style="margin-bottom:1.5rem">Admin Login</h1>
    <?php if ($error) {
        flash($error, "error");
    } ?>
    <div style="background:white;border:1px solid var(--rule);padding:2rem;box-shadow:var(--shadow)">
        <form method="post" action="login.php">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autofocus
                       style="font-size:1rem;padding:.7rem 1rem">
            </div>
            <div style="margin-top:1.2rem">
                <button type="submit" class="btn btn-primary" style="width:100%">Sign In</button>
            </div>
        </form>
    </div>
    <p style="text-align:center;margin-top:1rem;font-size:.85rem"><a href="../index.php">← Public Site</a></p>
</div>
<?php page_footer(); ?>
