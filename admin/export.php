<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/layout.php";

require_auth();

// Trigger download immediately if requested
if (isset($_POST["download"])) {
    require_csrf();
    export_csv(); // streams file and exits
}

$summary = get_summary();
page_header("Export Data", true);
?>
<div class="container" style="max-width:600px">
<p style="margin-bottom:1rem"><a href="index.php">← Dashboard</a></p>
<div class="page-intro">
    <div class="eyebrow">Administration</div>
    <h1>Export to CSV</h1>
</div>

<div style="background:white;border:1px solid var(--rule);padding:2rem;box-shadow:var(--shadow)">
    <p style="margin-bottom:1.2rem">
        Downloads all <strong><?= number_format(
            $summary["total_incidents"] ?? 0,
        ) ?></strong> incidents
        as a CSV file using your exact spreadsheet column format — ready to open in Excel or Google Sheets,
        and compatible with the importer.
    </p>
    <p style="font-size:.85rem;color:var(--ink-soft);margin-bottom:1.5rem">
        Columns exported: <em>Date, City, State, School, Deaths, Injuries, Total, Description,
        Transgender Involvement, Total # of assailants, # of trans assailants, Gender, URLs</em>
    </p>
    <form method="post">
        <?= csrf_field() ?>
        <button type="submit" name="download" value="1" class="btn btn-primary">↓ Download CSV</button>
    </form>
</div>
</div>
<?php page_footer(); ?>
