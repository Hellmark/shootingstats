<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';

$summary  = get_summary();
$by_year  = get_by_year();
$recent   = get_incidents([], 8, 0);

// Trans context calculations
$total_assailants   = (int)($summary['total_assailants'] ?? 0);
$trans_assailants   = (int)($summary['total_trans_assailants'] ?? 0);
$trans_pct_actual   = $total_assailants > 0 ? ($trans_assailants / $total_assailants * 100) : 0;
$trans_pct_pop      = TRANS_POPULATION_PCT * 100;

page_header('Overview');
?>
<div class="container">

    <div class="page-intro">
        <div class="eyebrow">Comprehensive Data</div>
        <h1>School Shooting Statistics<br>in the United States</h1>
        <p>A factual record of school shooting incidents, casualties, and assailant demographics, compiled from public records and official sources.</p>
    </div>

    <!-- Summary Stats -->
    <div class="stat-grid">
        <div class="stat-card accent-red">
            <div class="stat-value"><?= number_format($summary['total_incidents'] ?? 0) ?></div>
            <div class="stat-label">Total Incidents</div>
            <div class="stat-sub">Since <?= htmlspecialchars($summary['earliest_incident'] ? date('Y', strtotime($summary['earliest_incident'])) : '—') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($summary['total_deaths'] ?? 0) ?></div>
            <div class="stat-label">Deaths</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($summary['total_injuries'] ?? 0) ?></div>
            <div class="stat-label">Injuries</div>
        </div>
        <div class="stat-card accent-gold">
            <div class="stat-value"><?= number_format($summary['total_harmed'] ?? 0) ?></div>
            <div class="stat-label">Total Harmed</div>
        </div>
        <div class="stat-card accent-blue">
            <div class="stat-value"><?= number_format($summary['incidents_with_trans_assailant'] ?? 0) ?></div>
            <div class="stat-label">Incidents with<br>Trans Assailant</div>
            <div class="stat-sub">of <?= number_format($summary['total_incidents'] ?? 0) ?> total incidents</div>
        </div>
    </div>

    <!-- Transgender Context Panel -->
    <div class="context-panel">
        <h2>Context: Transgender People &amp; School Shootings</h2>
        <p>Understanding these numbers requires population context. Transgender people are statistically <strong>rare</strong> among school shooting assailants — consistent with or below their share of the general population.</p>
        <div class="context-grid">
            <div class="context-stat">
                <div class="num"><?= number_format($trans_pct_actual, 2) ?>%</div>
                <div class="lbl">Trans assailants as % of all assailants in this dataset</div>
            </div>
            <div class="context-stat">
                <div class="num"><?= number_format($trans_pct_pop, 1) ?>%</div>
                <div class="lbl">Transgender people as % of U.S. adult population</div>
                <div style="font-size:.78rem;color:var(--ink-soft);margin-top:.3rem;"><?= TRANS_POPULATION_SOURCE ?></div>
            </div>
            <div class="context-stat">
                <div class="num"><?= number_format($summary['total_trans_assailants'] ?? 0) ?></div>
                <div class="lbl">Total transgender assailants across all incidents on record</div>
            </div>
            <div class="context-stat">
                <?php
                $non_trans = $total_assailants - $trans_assailants;
                $non_trans_pct = $total_assailants > 0 ? ($non_trans / $total_assailants * 100) : 0;
                ?>
                <div class="num"><?= number_format($non_trans_pct, 1) ?>%</div>
                <div class="lbl">Non-transgender assailants as % of all assailants</div>
            </div>
        </div>
        <p style="margin-top:1.2rem;font-size:.85rem;color:var(--ink-soft);">
            <strong>Note:</strong> Even if transgender representation among assailants equaled their population share (~<?= number_format($trans_pct_pop, 1) ?>%), the <em>vast majority</em> of school shootings would still be carried out by non-transgender individuals. These data do not support the claim that transgender identity is a risk factor for school violence. See the <a href="analysis.php">Analysis</a> page for full statistical breakdown.
        </p>
    </div>

    <!-- Charts -->
    <div class="chart-grid">
        <div class="chart-box">
            <h3>Incidents Per Year</h3>
            <canvas id="chartByYear"></canvas>
        </div>
        <div class="chart-box">
            <h3>Deaths &amp; Injuries Per Year</h3>
            <canvas id="chartCasualties"></canvas>
        </div>
    </div>

    <!-- Recent Incidents -->
    <div class="section-header">
        <h2>Recent Incidents</h2>
        <a href="incidents.php">View all →</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Location</th>
                    <th class="td-num">Deaths</th>
                    <th class="td-num">Injuries</th>
                    <th class="td-badge">Trans<br>Assailant</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $inc): ?>
                <tr>
                    <td class="td-date"><?= date('M j, Y', strtotime($inc['incident_date'])) ?></td>
                    <td class="td-loc"><?= format_location_html($inc) ?></td>
                    <td class="td-num td-deaths"><?= $inc['deaths'] ?></td>
                    <td class="td-num"><?= $inc['injuries'] ?></td>
                    <td class="td-badge">
                        <?php if ($inc['had_trans_assailant']): ?>
                        <span class="badge badge-trans">Yes</span>
                        <?php else: ?>
                        <span class="badge badge-none">No</span>
                        <?php endif; ?>
                    </td>
                    <td><a href="incident.php?id=<?= $inc['id'] ?>">Details</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--ink-soft);padding:2rem;">No incidents in database yet. <a href="admin/">Import data →</a></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
const yearData = <?= json_encode($by_year) ?>;
const years        = yearData.map(r => r.yr);
const incidents    = yearData.map(r => +r.incidents);
const deaths       = yearData.map(r => +r.deaths);
const injuries     = yearData.map(r => +r.injuries);
const transInc     = yearData.map(r => +r.trans_incidents);
const nonTransInc  = yearData.map(r => +r.incidents - +r.trans_incidents);
const transDeaths  = yearData.map(r => +r.trans_deaths);
const nonTransDeaths = yearData.map(r => +r.non_trans_deaths);
const transInjuries  = yearData.map(r => +r.trans_injuries);
const nonTransInjuries = yearData.map(r => +r.non_trans_injuries);

const fontFamily = "'Source Serif 4', serif";

const sharedTooltip = {
    backgroundColor: 'rgba(26,20,16,0.92)',
    titleFont: { family: fontFamily, size: 12, weight: 'bold' },
    bodyFont:  { family: fontFamily, size: 11 },
    padding: 12,
    cornerRadius: 3,
    callbacks: {}
};

const chartDefaults = {
    responsive: true,
    plugins: { legend: { position: 'bottom', labels: { font: { family: fontFamily, size: 11 } } } },
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 10 } } },
        y: { grid: { color: '#e8e2d6' }, ticks: { font: { size: 10 } } }
    }
};

new Chart(document.getElementById('chartByYear'), {
    type: 'bar',
    data: {
        labels: years,
        datasets: [
            { label: 'Non-Trans Assailant', data: nonTransInc, backgroundColor: 'rgba(26,20,16,0.75)', borderRadius: 2 },
            { label: 'Trans Assailant',     data: transInc,    backgroundColor: 'rgba(42,92,139,0.7)',  borderRadius: 2 }
        ]
    },
    options: {
        ...chartDefaults,
        plugins: {
            ...chartDefaults.plugins,
            tooltip: {
                ...sharedTooltip,
                callbacks: {
                    title: ctx => 'Year: ' + ctx[0].label,
                    afterBody: ctx => {
                        const i = ctx[0].dataIndex;
                        return [
                            '',
                            '  Total incidents:  ' + incidents[i],
                            '  — Non-trans:      ' + nonTransInc[i],
                            '  — Trans assailant:' + transInc[i],
                        ];
                    },
                    label: () => null
                }
            }
        }
    }
});

new Chart(document.getElementById('chartCasualties'), {
    type: 'line',
    data: {
        labels: years,
        datasets: [
            { label: 'Deaths',   data: deaths,   borderColor: '#8b1a1a', backgroundColor: 'rgba(139,26,26,.1)',  tension: .3, fill: true, pointRadius: 3 },
            { label: 'Injuries', data: injuries, borderColor: '#2a5c8b', backgroundColor: 'rgba(42,92,139,.08)', tension: .3, fill: true, pointRadius: 3 }
        ]
    },
    options: {
        ...chartDefaults,
        plugins: {
            ...chartDefaults.plugins,
            tooltip: {
                ...sharedTooltip,
                mode: 'index',
                intersect: false,
                callbacks: {
                    title: ctx => 'Year: ' + ctx[0].label,
                    afterBody: ctx => {
                        const i = ctx[0].dataIndex;
                        return [
                            '',
                            '  Deaths:',
                            '    Non-trans assailant: ' + nonTransDeaths[i],
                            '    Trans assailant:     ' + transDeaths[i],
                            '',
                            '  Injuries:',
                            '    Non-trans assailant: ' + nonTransInjuries[i],
                            '    Trans assailant:     ' + transInjuries[i],
                        ];
                    },
                    label: () => null
                }
            }
        }
    }
});
</script>
<?php page_footer(); ?>
