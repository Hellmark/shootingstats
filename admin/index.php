<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/layout.php";

require_auth();

$summary = get_summary();
$recent = get_incidents([], 10, 0);

page_header("Admin Dashboard", true);
?>
<div class="container">

    <div class="page-intro">
        <div class="eyebrow">Administration</div>
        <h1>Dashboard</h1>
    </div>

    <div class="stat-grid">
        <div class="stat-card accent-red">
            <div class="stat-value"><?= number_format(
                $summary["total_incidents"] ?? 0,
            ) ?></div>
            <div class="stat-label">Total Incidents</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format(
                $summary["total_deaths"] ?? 0,
            ) ?></div>
            <div class="stat-label">Total Deaths</div>
        </div>
        <div class="stat-card accent-blue">
            <div class="stat-value"><?= number_format(
                $summary["incidents_with_trans_assailant"] ?? 0,
            ) ?></div>
            <div class="stat-label">Trans Assailant<br>Incidents</div>
        </div>
    </div>

    <div style="display:flex;gap:1rem;margin-bottom:2rem;flex-wrap:wrap">
        <a href="edit.php" class="btn btn-primary">+ Add New Incident</a>
        <a href="import.php" class="btn btn-ghost">↑ Import Spreadsheet</a>
        <a href="export.php" class="btn btn-ghost">↓ Export CSV</a>
        <a href="logout.php" class="btn btn-ghost" style="margin-left:auto">Sign Out</a>
    </div>

    <div class="section-header">
        <h2>Recent Incidents</h2>
        <a href="list.php">Manage all →</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Location</th>
                    <th class="td-num">Deaths</th>
                    <th class="td-num">Injuries</th>
                    <th class="td-badge">Trans</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $inc): ?>
                <tr>
                    <td class="td-date"><?= date(
                        "M j, Y",
                        strtotime($inc["incident_date"]),
                    ) ?></td>
                    <td class="td-loc"><?= format_location_html($inc) ?></td>
                    <td class="td-num td-deaths"><?= $inc["deaths"] ?></td>
                    <td class="td-num"><?= $inc["injuries"] ?></td>
                    <td class="td-badge"><?= $inc["had_trans_assailant"]
                        ? '<span class="badge badge-trans">Yes</span>'
                        : '<span class="badge badge-none">No</span>' ?></td>
                    <td style="white-space:nowrap">
                        <a href="edit.php?id=<?= $inc["id"] ?>">Edit</a> ·
                        <form method="post" action="delete.php" style="display:inline" onsubmit="return confirm('Delete this incident?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $inc[
                                "id"
                            ] ?>">
                            <button type="submit" style="background:none;border:none;color:var(--accent);cursor:pointer;font:inherit;padding:0;text-decoration:underline">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent)): ?>
                <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--ink-soft)">No incidents yet. <a href="edit.php">Add one</a> or <a href="import.php">import a spreadsheet</a>.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
<?php page_footer(); ?>
