<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/layout.php";

$id = (int) ($_GET["id"] ?? 0);
$inc = $id ? get_incident($id) : null;

if (!$inc) {
    header("HTTP/1.0 404 Not Found");
    page_header("Not Found");
    echo '<div class="container"><p>Incident not found. <a href="incidents.php">← Back to list</a></p></div>';
    page_footer();
    exit();
}

page_header(htmlspecialchars($inc["location"]));
?>
<div class="container">

    <p style="margin-bottom:1.5rem"><a href="incidents.php">← All Incidents</a></p>

    <div class="page-intro">
        <div class="eyebrow"><?= date(
            "F j, Y",
            strtotime($inc["incident_date"]),
        ) ?></div>
        <h1><?= htmlspecialchars($inc["location"]) ?></h1>
        <?php if ($inc["city"] || $inc["state"]): ?>
        <p style="color:var(--ink-soft)"><?= htmlspecialchars(
            implode(", ", array_filter([$inc["city"], $inc["state"]])),
        ) ?></p>
        <?php endif; ?>
    </div>

    <div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
        <div class="stat-card accent-red">
            <div class="stat-value"><?= (int) $inc["deaths"] ?></div>
            <div class="stat-label">Deaths</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= (int) $inc["injuries"] ?></div>
            <div class="stat-label">Injuries</div>
        </div>
        <div class="stat-card accent-gold">
            <div class="stat-value"><?= (int) $inc["total_harmed"] ?></div>
            <div class="stat-label">Total Harmed</div>
        </div>
        <div class="stat-card <?= $inc["had_trans_assailant"]
            ? "accent-blue"
            : "" ?>">
            <div class="stat-value"><?= $inc["had_trans_assailant"]
                ? "Yes"
                : "No" ?></div>
            <div class="stat-label">Trans Assailant</div>
        </div>
    </div>

    <div class="detail-panel">
        <div>
            <?php if ($inc["description"]): ?>
            <h2 style="margin-bottom:.8rem">Description</h2>
            <p style="font-size:1rem;line-height:1.8"><?= nl2br(
                htmlspecialchars($inc["description"]),
            ) ?></p>
            <?php endif; ?>
        </div>
        <div>
            <h3 style="margin-bottom:.8rem">Incident Details</h3>
            <table class="detail-meta" style="width:100%;font-size:.88rem">
                <tr><td style="padding:.3rem .6rem .3rem 0;color:var(--ink-soft)">Date</td><td><?= date(
                    "F j, Y",
                    strtotime($inc["incident_date"]),
                ) ?></td></tr>
                <tr><td style="padding:.3rem .6rem .3rem 0;color:var(--ink-soft)">Location</td><td><?= htmlspecialchars(
                    $inc["location"],
                ) ?></td></tr>
                <?php if (
                    $inc["city"]
                ): ?><tr><td style="padding:.3rem .6rem .3rem 0;color:var(--ink-soft)">City</td><td><?= htmlspecialchars(
    $inc["city"],
) ?></td></tr><?php endif; ?>
                <?php if (
                    $inc["state"]
                ): ?><tr><td style="padding:.3rem .6rem .3rem 0;color:var(--ink-soft)">State</td><td><?= htmlspecialchars(
    $inc["state"],
) ?></td></tr><?php endif; ?>
                <tr><td style="padding:.3rem .6rem .3rem 0;color:var(--ink-soft)">Deaths</td><td><?= (int) $inc[
                    "deaths"
                ] ?></td></tr>
                <tr><td style="padding:.3rem .6rem .3rem 0;color:var(--ink-soft)">Injuries</td><td><?= (int) $inc[
                    "injuries"
                ] ?></td></tr>
                <tr><td style="padding:.3rem .6rem .3rem 0;color:var(--ink-soft)">Total Harmed</td><td><?= (int) $inc[
                    "total_harmed"
                ] ?></td></tr>
                <tr><td style="padding:.3rem .6rem .3rem 0;color:var(--ink-soft)">Total Assailants</td><td><?= (int) $inc[
                    "total_assailants"
                ] ?></td></tr>
                <tr><td style="padding:.3rem .6rem .3rem 0;color:var(--ink-soft)">Assailant Gender(s)</td><td><?= htmlspecialchars(
                    $inc["assailant_genders"] ?? "—",
                ) ?></td></tr>
                <tr><td style="padding:.3rem .6rem .3rem 0;color:var(--ink-soft)">Trans Assailant?</td><td><?= $inc[
                    "had_trans_assailant"
                ]
                    ? "Yes (" . (int) $inc["trans_assailant_count"] . ")"
                    : "No" ?></td></tr>
                <?php if ($inc["source_url"]): ?>
                <tr><td style="padding:.3rem .6rem .3rem 0;color:var(--ink-soft)">Source</td><td><a href="<?= htmlspecialchars(
                    $inc["source_url"],
                ) ?>" target="_blank" rel="noopener noreferrer">Link ↗</a></td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

</div>
<?php page_footer(); ?>
