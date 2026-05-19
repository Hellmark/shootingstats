<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';

$per_page = 30;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$filters = [
    'state'      => $_GET['state'] ?? '',
    'year_from'  => $_GET['year_from'] ?? '',
    'year_to'    => $_GET['year_to'] ?? '',
    'trans_only' => !empty($_GET['trans_only']),
];

$total    = get_incident_count($filters);
$incidents = get_incidents($filters, $per_page, $offset);
$states   = get_states();
$pages    = (int)ceil($total / $per_page);

function query_string(array $overrides = []): string {
    global $filters;
    $params = array_merge([
        'state'     => $filters['state'],
        'year_from' => $filters['year_from'],
        'year_to'   => $filters['year_to'],
        'trans_only'=> $filters['trans_only'] ? '1' : '',
    ], $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== false);
    return http_build_query($params);
}

page_header('All Incidents');
?>
<div class="container">

    <div class="page-intro">
        <div class="eyebrow">Complete Record</div>
        <h1>All Incidents</h1>
        <p>Browse the full dataset of documented school shooting incidents. Use the filters below to narrow by state, year range, or assailant demographics.</p>
    </div>

    <!-- Filters -->
    <form method="get" action="incidents.php">
        <div class="filter-bar">
            <div class="filter-group">
                <label>State</label>
                <select name="state">
                    <option value="">All States</option>
                    <?php foreach ($states as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $filters['state'] === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Year From</label>
                <input type="number" name="year_from" min="1900" max="2099" value="<?= htmlspecialchars($filters['year_from']) ?>" placeholder="e.g. 1990">
            </div>
            <div class="filter-group">
                <label>Year To</label>
                <input type="number" name="year_to" min="1900" max="2099" value="<?= htmlspecialchars($filters['year_to']) ?>" placeholder="e.g. 2024">
            </div>
            <div class="filter-group">
                <label>Trans Assailant</label>
                <select name="trans_only">
                    <option value="">All Incidents</option>
                    <option value="1" <?= $filters['trans_only'] ? 'selected' : '' ?>>Trans Assailant Only</option>
                </select>
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
            <?php if (array_filter([$filters['state'], $filters['year_from'], $filters['year_to'], $filters['trans_only']])): ?>
            <div class="filter-group">
                <label>&nbsp;</label>
                <a href="incidents.php" class="btn btn-ghost">Clear</a>
            </div>
            <?php endif; ?>
        </div>
    </form>

    <p style="font-size:.85rem;color:var(--ink-soft);margin-bottom:1.2rem;">
        Showing <?= number_format(count($incidents)) ?> of <?= number_format($total) ?> incidents
        <?php if ($filters['state']): ?> in <strong><?= htmlspecialchars($filters['state']) ?></strong><?php endif; ?>
    </p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Location</th>
                    <th class="td-num">Deaths</th>
                    <th class="td-num">Injured</th>
                    <th class="td-num">Total<br>Harmed</th>
                    <th>Assailant<br>Gender(s)</th>
                    <th class="td-badge">Trans<br>Assailant?</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($incidents as $inc): ?>
                <tr>
                    <td class="td-date"><?= date('M j, Y', strtotime($inc['incident_date'])) ?></td>
                    <td class="td-loc"><?= format_location_html($inc) ?></td>
                    <td class="td-num td-deaths"><?= $inc['deaths'] ?></td>
                    <td class="td-num"><?= $inc['injuries'] ?></td>
                    <td class="td-num" style="font-weight:600"><?= $inc['total_harmed'] ?></td>
                    <td style="font-size:.82rem"><?= htmlspecialchars($inc['assailant_genders'] ?? '—') ?></td>
                    <td class="td-badge">
                        <?php if ($inc['had_trans_assailant']): ?>
                        <span class="badge badge-trans">Yes (<?= (int)$inc['trans_assailant_count'] ?>)</span>
                        <?php else: ?>
                        <span class="badge badge-none">No</span>
                        <?php endif; ?>
                    </td>
                    <td><a href="incident.php?id=<?= $inc['id'] ?>" style="white-space:nowrap">Details →</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($incidents)): ?>
                <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--ink-soft)">No incidents match your filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?<?= query_string(['page' => $page - 1]) ?>">← Prev</a>
        <?php endif; ?>

        <?php for ($p = max(1, $page - 3); $p <= min($pages, $page + 3); $p++): ?>
        <?php if ($p == $page): ?>
        <span class="current"><?= $p ?></span>
        <?php else: ?>
        <a href="?<?= query_string(['page' => $p]) ?>"><?= $p ?></a>
        <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $pages): ?>
        <a href="?<?= query_string(['page' => $page + 1]) ?>">Next →</a>
        <?php endif; ?>

        <span style="font-size:.82rem;color:var(--ink-soft);margin-left:.5rem">Page <?= $page ?> of <?= $pages ?></span>
    </div>
    <?php endif; ?>

</div>
<?php page_footer(); ?>
