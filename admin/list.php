<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/layout.php";

require_auth();

$per_page = 50;
$page = max(1, (int) ($_GET["page"] ?? 1));
$offset = ($page - 1) * $per_page;

$total = get_incident_count();
$incidents = get_incidents([], $per_page, $offset);
$pages = (int) ceil($total / $per_page);

page_header("Manage Incidents", true);
?>
<div class="container">

    <div class="page-intro">
        <div class="eyebrow">Administration</div>
        <h1>All Incidents</h1>
    </div>

    <?php if (!empty($_GET["deleted"])):
        flash("Incident deleted.");
    endif; ?>

    <div style="display:flex;gap:1rem;margin-bottom:1.5rem">
        <a href="edit.php" class="btn btn-primary">+ Add New</a>
        <a href="import.php" class="btn btn-ghost">↑ Import</a>
        <a href="index.php" class="btn btn-ghost">← Dashboard</a>
    </div>

    <p style="font-size:.85rem;color:var(--ink-soft);margin-bottom:1rem"><?= number_format(
        $total,
    ) ?> incidents total</p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Location</th>
                    <th class="td-num">Deaths</th>
                    <th class="td-num">Injuries</th>
                    <th class="td-badge">Trans</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($incidents as $inc): ?>
                <tr>
                    <td style="color:var(--ink-soft);font-size:.8rem"><?= $inc[
                        "id"
                    ] ?></td>
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
                    <td style="white-space:nowrap;font-size:.85rem">
                        <a href="edit.php?id=<?= $inc["id"] ?>">Edit</a> ·
                        <a href="../incident.php?id=<?= $inc[
                            "id"
                        ] ?>" target="_blank">View</a> ·
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
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?><a href="?page=<?= $page -
    1 ?>">← Prev</a><?php endif; ?>
        <?php for (
            $p = max(1, $page - 3);
            $p <= min($pages, $page + 3);
            $p++
        ): ?>
        <?php if (
            $p == $page
        ): ?><span class="current"><?= $p ?></span><?php else: ?><a href="?page=<?= $p ?>"><?= $p ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $pages): ?><a href="?page=<?= $page +
    1 ?>">Next →</a><?php endif; ?>
    </div>
    <?php endif; ?>

</div>
<?php page_footer(); ?>
