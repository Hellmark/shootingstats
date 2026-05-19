<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';

$summary = get_summary();
$by_year = get_by_year();
$by_state = get_by_state();

$total_incidents  = (int)($summary['total_incidents'] ?? 0);
$total_assailants = (int)($summary['total_assailants'] ?? 0);
$trans_assailants = (int)($summary['total_trans_assailants'] ?? 0);
$trans_incidents  = (int)($summary['incidents_with_trans_assailant'] ?? 0);

// Percentage calculations
$trans_pct_pop    = TRANS_POPULATION_PCT * 100; // ~0.6%
$trans_pct_actual = $total_assailants > 0 ? ($trans_assailants / $total_assailants * 100) : 0;
$trans_pct_incidents = $total_incidents > 0 ? ($trans_incidents / $total_incidents * 100) : 0;

// Expected trans assailants if proportional to population
$expected_trans   = round($total_assailants * TRANS_POPULATION_PCT);
$ratio_to_expected = $expected_trans > 0 ? round($trans_assailants / $expected_trans, 2) : 0;

page_header('Statistical Analysis');
?>
<div class="container">

    <div class="page-intro">
        <div class="eyebrow">Statistical Analysis</div>
        <h1>Understanding the Data</h1>
        <p>A deeper look at trends, demographics, and what the numbers actually tell us — including rigorous context around transgender representation among assailants.</p>
    </div>

    <!-- Main explainer -->
    <div class="analysis-explainer">
        <strong>How to read these statistics:</strong> Raw counts without population context can mislead. A group that makes up 0.6% of the population committing 0.6% of crimes is not "overrepresented" — it's exactly proportional. The analysis below places all figures in their proper demographic context.
    </div>

    <!-- Trans Context — Full Section -->
    <div class="section-header"><h2>Transgender Assailants: Full Context</h2></div>

    <div class="stat-grid">
        <div class="stat-card accent-blue">
            <div class="stat-value"><?= number_format($trans_assailants) ?></div>
            <div class="stat-label">Transgender Assailants<br>on Record</div>
            <div class="stat-sub">out of <?= number_format($total_assailants) ?> total assailants</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($trans_pct_actual, 2) ?>%</div>
            <div class="stat-label">% of All Assailants<br>Who Were Trans</div>
        </div>
        <div class="stat-card accent-safe">
            <div class="stat-value"><?= number_format($trans_pct_pop, 1) ?>%</div>
            <div class="stat-label">Trans People as %<br>of U.S. Population</div>
            <div class="stat-sub"><?= TRANS_POPULATION_SOURCE ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($trans_pct_incidents, 2) ?>%</div>
            <div class="stat-label">% of Incidents with<br>a Trans Assailant</div>
        </div>
    </div>

    <div class="context-panel" style="border-left-color:var(--accent-2)">
        <h2 style="color:var(--accent-2)">Expected vs. Actual: A Population Comparison</h2>
        <p>
            If transgender people committed school shootings at exactly the same rate as their share of the population (~<?= number_format($trans_pct_pop, 1) ?>%), 
            we would expect roughly <strong><?= number_format($expected_trans) ?> transgender assailants</strong> out of <?= number_format($total_assailants) ?> total.
            The actual count is <strong><?= number_format($trans_assailants) ?></strong>.
        </p>
        <div class="context-grid" style="margin-top:1.2rem">
            <div class="context-stat">
                <div class="num" style="color:var(--accent-2)"><?= number_format($expected_trans) ?></div>
                <div class="lbl">Expected (if proportional to population)</div>
            </div>
            <div class="context-stat">
                <div class="num"><?= number_format($trans_assailants) ?></div>
                <div class="lbl">Actual transgender assailants recorded</div>
            </div>
            <div class="context-stat">
                <div class="num" style="color:var(--safe)"><?= $ratio_to_expected ?>×</div>
                <div class="lbl">Ratio of actual to expected (1.0 = exactly proportional)</div>
            </div>
            <div class="context-stat">
                <div class="num" style="color:var(--ink-soft)"><?= number_format(100 - $trans_pct_actual, 1) ?>%</div>
                <div class="lbl">Incidents carried out by non-transgender assailants</div>
            </div>
        </div>
        <p style="margin-top:1.2rem;font-size:.88rem;color:var(--ink-soft)">
            A ratio of <?= $ratio_to_expected ?>× means transgender people <?= $ratio_to_expected < 1 ? 'appear <strong>less</strong> frequently among assailants than their population share would predict' : ($ratio_to_expected == 1 ? 'appear at <strong>exactly</strong> their population proportion' : 'appear slightly above their population proportion — though the absolute numbers remain very small and the data may reflect identification/reporting variation') ?>.
            In all cases, the <strong>overwhelming majority</strong> of school shooting assailants are not transgender.
        </p>
    </div>

    <!-- Year over year charts -->
    <div class="section-header" style="margin-top:2.5rem"><h2>Trends Over Time</h2></div>
    <div class="chart-grid">
        <div class="chart-box">
            <h3>Incidents Per Year: Trans vs. Non-Trans Assailant</h3>
            <canvas id="chartTransYear"></canvas>
        </div>
        <div class="chart-box">
            <h3>Trans Assailants as % of All Assailants (by Year)</h3>
            <canvas id="chartTransPct"></canvas>
        </div>
    </div>

    <!-- State breakdown -->
    <div class="section-header" style="margin-top:1rem"><h2>By State (Top 20)</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>State</th>
                    <th class="td-num">Incidents</th>
                    <th class="td-num">Deaths</th>
                    <th class="td-num">Injuries</th>
                    <th class="td-num">Deaths/<br>Incident</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($by_state, 0, 20) as $row): ?>
                <tr>
                    <td class="td-loc"><?= htmlspecialchars($row['state']) ?></td>
                    <td class="td-num"><?= $row['incidents'] ?></td>
                    <td class="td-num td-deaths"><?= $row['deaths'] ?></td>
                    <td class="td-num"><?= $row['injuries'] ?></td>
                    <td class="td-num"><?= $row['incidents'] > 0 ? number_format($row['deaths'] / $row['incidents'], 2) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($by_state)): ?>
                <tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--ink-soft)">No data available.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p style="margin-top:2rem;font-size:.82rem;color:var(--ink-soft);font-style:italic">
        All statistics on this page are derived directly from the incidents database. Transgender population figures are sourced from the <a href="https://williamsinstitute.law.ucla.edu/publications/trans-adults-united-states/" target="_blank">Williams Institute at UCLA School of Law (2022)</a> and represent the estimated percentage of U.S. adults who identify as transgender.
    </p>
</div>

<script>
const yearData = <?= json_encode($by_year) ?>;

const years          = yearData.map(r => r.yr);
const transInc       = yearData.map(r => +r.trans_incidents);
const nonTrans       = yearData.map(r => +r.incidents - +r.trans_incidents);
const incidents      = yearData.map(r => +r.incidents);
const transDeaths    = yearData.map(r => +r.trans_deaths);
const nonTransDeaths = yearData.map(r => +r.non_trans_deaths);
const transInj       = yearData.map(r => +r.trans_injuries);
const nonTransInj    = yearData.map(r => +r.non_trans_injuries);
const transPct       = yearData.map(r => +r.incidents > 0 ? (+r.trans_incidents / +r.incidents * 100).toFixed(2) : 0);

const fontFamily = "'Source Serif 4', serif";

const sharedTooltip = {
    backgroundColor: 'rgba(26,20,16,0.92)',
    titleFont: { family: fontFamily, size: 12, weight: 'bold' },
    bodyFont:  { family: fontFamily, size: 11 },
    padding: 12,
    cornerRadius: 3,
};

const opts = {
    responsive: true,
    plugins: { legend: { position: 'bottom', labels: { font: { family: fontFamily, size: 11 } } } },
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 10 } } },
        y: { grid: { color: '#e8e2d6' }, ticks: { font: { size: 10 } } }
    }
};

new Chart(document.getElementById('chartTransYear'), {
    type: 'bar',
    data: {
        labels: years,
        datasets: [
            { label: 'Non-Trans Assailant', data: nonTrans, backgroundColor: 'rgba(26,20,16,0.7)',  borderRadius: 2 },
            { label: 'Trans Assailant',     data: transInc, backgroundColor: 'rgba(42,92,139,0.75)', borderRadius: 2 }
        ]
    },
    options: {
        ...opts,
        scales: { ...opts.scales, x: { ...opts.scales.x, stacked: true }, y: { ...opts.scales.y, stacked: true } },
        plugins: {
            ...opts.plugins,
            tooltip: {
                ...sharedTooltip,
                callbacks: {
                    title: ctx => 'Year: ' + ctx[0].label,
                    afterBody: ctx => {
                        const i = ctx[0].dataIndex;
                        return [
                            '',
                            '  Total incidents:       ' + incidents[i],
                            '  Non-trans assailant:   ' + nonTrans[i],
                            '  Trans assailant:       ' + transInc[i],
                            '',
                            '  Deaths (non-trans):    ' + nonTransDeaths[i],
                            '  Deaths (trans):        ' + transDeaths[i],
                            '',
                            '  Injuries (non-trans):  ' + nonTransInj[i],
                            '  Injuries (trans):      ' + transInj[i],
                        ];
                    },
                    label: () => null
                }
            }
        }
    }
});

new Chart(document.getElementById('chartTransPct'), {
    type: 'line',
    data: {
        labels: years,
        datasets: [
            { label: '% of Incidents w/ Trans Assailant', data: transPct, borderColor: '#2a5c8b', backgroundColor: 'rgba(42,92,139,.1)', tension: .3, fill: true, pointRadius: 4 },
            { label: 'Trans % of U.S. Population (<?= number_format($trans_pct_pop, 1) ?>%)', data: years.map(() => <?= $trans_pct_pop ?>), borderColor: '#c49a2a', borderDash: [6,3], pointRadius: 0, tension: 0 }
        ]
    },
    options: {
        ...opts,
        plugins: {
            ...opts.plugins,
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
                            '  Incidents this year:   ' + incidents[i],
                            '  Trans assailant:       ' + transInc[i] + ' (' + transPct[i] + '%)',
                            '  Non-trans assailant:   ' + nonTrans[i],
                            '  U.S. trans population: ~<?= number_format($trans_pct_pop, 1) ?>%',
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
