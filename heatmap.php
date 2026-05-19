<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.cookie_secure', '1');
    session_start();
}
if (empty($_SESSION['geocode_token'])) {
    $_SESSION['geocode_token'] = bin2hex(random_bytes(16));
}
$geocode_token = $_SESSION['geocode_token'];

// JSON endpoint for live refresh during geocoding
if (isset($_GET['json'])) {
    header('Content-Type: application/json');
    echo json_encode(array_values(array_map(fn($r) => [
        'city'      => $r['city'],
        'state'     => $r['state'],
        'incidents' => (int)$r['incidents'],
        'deaths'    => (int)$r['deaths'],
        'injuries'  => (int)$r['injuries'],
        'trans'     => (int)$r['trans_incidents'],
        'lat'       => (float)$r['lat'],
        'lng'       => (float)$r['lng'],
    ], get_heatmap_data())));
    exit;
}

$heatmap_data = get_heatmap_data();
$progress     = get_geocode_progress();
$pending      = $progress['total'] - $progress['cached'] - $progress['failed'];
$is_retry     = isset($_GET['retry']);

page_header('Incident Map');
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
#map {
    width: 100%; height: 620px;
    border: 1px solid var(--rule);
    box-shadow: var(--shadow-lg);
    background: #e8e2d6;
}
.map-controls { display:flex; align-items:center; gap:1.2rem; flex-wrap:wrap; margin-bottom:1rem; }
.map-toggle { display:flex; gap:0; }
.map-toggle label {
    display:flex; align-items:center; gap:.4rem;
    padding:.4rem 1rem; border:1px solid var(--rule); margin-right:-1px;
    cursor:pointer; font-family:var(--font-body); font-size:.82rem; font-weight:600;
    background:white; transition:background .15s; user-select:none;
}
.map-toggle label:first-child { border-radius:var(--radius) 0 0 var(--radius); }
.map-toggle label:last-child  { border-radius:0 var(--radius) var(--radius) 0; margin-right:0; }
.map-toggle input[type=radio] { display:none; }
.map-toggle label:has(input:checked) { background:var(--ink); color:var(--paper); border-color:var(--ink); z-index:1; }
.map-legend { display:flex; align-items:center; gap:.8rem; font-size:.8rem; color:var(--ink-soft); flex-wrap:wrap; }
.legend-dot { width:12px; height:12px; border-radius:50%; display:inline-block; }
#geocode-progress {
    background:white; border:1px solid var(--rule); border-left:4px solid var(--gold);
    padding:.7rem 1.2rem; font-size:.85rem; margin-bottom:1rem;
    display:flex; align-items:center; gap:1rem; flex-wrap:wrap;
}
.progress-bar-wrap { flex:1; min-width:160px; height:6px; background:var(--paper-2); border-radius:3px; overflow:hidden; }
.progress-bar-fill { height:100%; background:var(--gold); border-radius:3px; transition:width .4s ease; }
.leaflet-tooltip.map-tip {
    font-family:'Source Serif 4',serif; font-size:.82rem; line-height:1.6;
    background:rgba(26,20,16,.92); color:#f5f0e8; border:none;
    box-shadow:0 2px 8px rgba(0,0,0,.3); padding:.5rem .8rem; border-radius:3px;
}
.leaflet-tooltip.map-tip::before { display:none; }
</style>

<div class="container">

    <div class="page-intro">
        <div class="eyebrow">Geographic View</div>
        <h1>Incident Map</h1>
        <p>Heat map of school shooting incidents across the United States, weighted by number of incidents per location.</p>
    </div>

    <?php if ($pending > 0 || $is_retry): ?>
    <div id="geocode-progress">
        <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.4rem">
                <span style="font-weight:600">
                    Geocoding locations: <span id="geocode-count"><?= $progress['cached'] ?></span> of <?= $progress['total'] ?> complete
                </span>
                <span id="geocode-current" style="font-size:.78rem;color:var(--ink-soft);font-style:italic"></span>
            </div>
            <div class="progress-bar-wrap">
                <div class="progress-bar-fill" id="geocode-bar"
                     style="width:<?= $progress['total'] > 0 ? round($progress['cached'] / $progress['total'] * 100) : 0 ?>%"></div>
            </div>
        </div>
        <div style="display:flex;gap:.5rem;align-items:center;flex-shrink:0">
            <button id="geocode-pause" class="btn btn-ghost" style="font-size:.78rem;padding:.3rem .8rem">Pause</button>
            <a href="?retry=1" id="geocode-retry" class="btn btn-ghost" style="font-size:.78rem;padding:.3rem .8rem;display:none">Retry Failed</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="map-controls">
        <div class="map-toggle">
            <label><input type="radio" name="mapmode" value="heat" checked> Heatmap</label>
            <label><input type="radio" name="mapmode" value="dots"> Dot Map</label>
        </div>
        <div class="map-toggle" id="weight-toggle">
            <label><input type="radio" name="weight" value="incidents" checked> By Incidents</label>
            <label><input type="radio" name="weight" value="deaths"> By Deaths</label>
            <label><input type="radio" name="weight" value="total"> By Total Harmed</label>
        </div>
        <div class="map-legend" id="dot-legend" style="display:none">
            <span><span class="legend-dot" style="background:#C45070"></span> No trans assailant</span>
            <span><span class="legend-dot" style="background:#3D9BD5"></span> Trans assailant involved</span>
            <span style="font-size:.75rem">Circle size = incidents at location</span>
        </div>
    </div>

    <div id="map"></div>

    <p style="font-size:.78rem;color:var(--ink-soft);margin-top:.8rem">
        Coordinates from OpenStreetMap/Nominatim.
        <?= number_format(count($heatmap_data)) ?> of <?= number_format($progress['total']) ?> unique locations geocoded.
        <?php if ($progress['failed'] > 0): ?>
        <?= $progress['failed'] ?> location(s) could not be resolved.
        <?php endif; ?>
    </p>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script>
const rawData = <?= json_encode(array_values(array_map(fn($r) => [
    'city'      => $r['city'],
    'state'     => $r['state'],
    'incidents' => (int)$r['incidents'],
    'deaths'    => (int)$r['deaths'],
    'injuries'  => (int)$r['injuries'],
    'trans'     => (int)$r['trans_incidents'],
    'lat'       => (float)$r['lat'],
    'lng'       => (float)$r['lng'],
], $heatmap_data))) ?>;

const map = L.map('map', { center: [38.5, -96], zoom: 4 });

L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors © <a href="https://carto.com/">CARTO</a>',
    maxZoom: 18
}).addTo(map);

let heatLayer = null;
let dotLayer  = null;
let mode      = 'heat';
let weightBy  = 'incidents';

function getWeight(d) {
    return weightBy === 'deaths' ? d.deaths
         : weightBy === 'total'  ? (d.deaths + d.injuries)
         : d.incidents;
}

function drawHeat() {
    if (dotLayer)  { map.removeLayer(dotLayer);  dotLayer  = null; }
    if (heatLayer) { map.removeLayer(heatLayer); heatLayer = null; }
    const pts = rawData.map(d => [d.lat, d.lng, Math.max(getWeight(d), 0.5)]);
    heatLayer = L.heatLayer(pts, {
        radius: 28, blur: 22, maxZoom: 10,
        gradient: { 0.2: '#2a5c8b', 0.45: '#c49a2a', 0.7: '#8b1a1a', 1.0: '#3d0000' }
    }).addTo(map);
}

function drawDots() {
    if (heatLayer) { map.removeLayer(heatLayer); heatLayer = null; }
    if (dotLayer)  { map.removeLayer(dotLayer);  dotLayer  = null; }
    dotLayer = L.layerGroup();
    rawData.forEach(d => {
        const w     = getWeight(d);
        const r     = Math.max(5, Math.min(28, Math.sqrt(w) * 3.5));
        const color = d.trans > 0 ? '#3D9BD5' : '#C45070';
        const label = [d.city, d.state].filter(Boolean).join(', ');
        L.circleMarker([d.lat, d.lng], {
            radius: r, fillColor: color, color: 'white',
            weight: 1.5, opacity: .9, fillOpacity: .7
        })
        .bindTooltip(
            `<strong>${label}</strong><br>
             Incidents: ${d.incidents}<br>
             Deaths: ${d.deaths} &nbsp; Injuries: ${d.injuries}<br>
             Trans assailant: ${d.trans > 0 ? 'Yes (' + d.trans + ')' : 'No'}`,
            { sticky: true, className: 'map-tip' }
        )
        .addTo(dotLayer);
    });
    dotLayer.addTo(map);
}

function redraw() {
    if (mode === 'heat') drawHeat();
    else                 drawDots();
}

redraw();

document.querySelectorAll('input[name="mapmode"]').forEach(r => {
    r.addEventListener('change', () => {
        mode = r.value;
        document.getElementById('dot-legend').style.display    = mode === 'dots' ? 'flex' : 'none';
        document.getElementById('weight-toggle').style.display = mode === 'heat' ? 'flex' : 'none';
        redraw();
    });
});

document.querySelectorAll('input[name="weight"]').forEach(r => {
    r.addEventListener('change', () => { weightBy = r.value; redraw(); });
});

<?php if ($pending > 0 || $is_retry): ?>
(function() {
    const POLL_INTERVAL = 1300;
    const STALL_TIMEOUT = 15000;
    const total   = <?= $progress['total'] ?>;
    const bar     = document.getElementById('geocode-bar');
    const countEl = document.getElementById('geocode-count');
    const current = document.getElementById('geocode-current');
    const wrap    = document.getElementById('geocode-progress');
    const pauseBtn= document.getElementById('geocode-pause');
    const retryBtn= document.getElementById('geocode-retry');

    let paused = false, timer = null, stallTimer = null, failCount = 0;

    pauseBtn.addEventListener('click', () => {
        paused = !paused;
        pauseBtn.textContent = paused ? 'Resume' : 'Pause';
        if (!paused) scheduleNext(200);
        else { clearTimeout(timer); current.textContent = 'Paused.'; }
    });

    function resetStall() {
        clearTimeout(stallTimer);
        stallTimer = setTimeout(() => {
            current.textContent = 'Nominatim is slow — still trying…';
        }, STALL_TIMEOUT);
    }

    function scheduleNext(delay) {
        clearTimeout(timer);
        timer = setTimeout(fetchOne, delay || POLL_INTERVAL);
    }

    async function fetchOne() {
        if (paused) return;
        resetStall();
        const retry = window.location.search.includes('retry') ? '&retry=1' : '';
        const url = 'geocode.php?token=<?= htmlspecialchars($geocode_token, ENT_QUOTES) ?>' + retry;
        try {
            const res  = await fetch(url, { cache: 'no-store' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            clearTimeout(stallTimer);
            failCount = 0;

            bar.style.width     = Math.round((data.progress.cached / total) * 100) + '%';
            countEl.textContent = data.progress.cached;
            if (data.location) {
                current.innerHTML = `<span style="color:${data.success ? 'var(--safe)':'var(--accent)'}">${data.success?'✓':'✗'}</span> ${data.location}`;
            }
            if (data.progress.failed > 0) retryBtn.style.display = 'inline-block';

            if (data.done) {
                wrap.style.borderLeftColor = 'var(--safe)';
                pauseBtn.style.display = 'none';
                current.textContent = 'Complete.';
                // Refresh map data
                const pts = await fetch('heatmap.php?json=1', { cache: 'no-store' });
                const newData = await pts.json();
                rawData.length = 0;
                newData.forEach(p => rawData.push(p));
                redraw();
                return;
            }
            if (data.progress.cached % 10 === 0) {
                const pts = await fetch('heatmap.php?json=1', { cache: 'no-store' });
                const newData = await pts.json();
                rawData.length = 0;
                newData.forEach(p => rawData.push(p));
                redraw();
            }
            scheduleNext();
        } catch(err) {
            failCount++;
            clearTimeout(stallTimer);
            current.textContent = `Error: ${err.message}. Retrying in ${failCount * 3}s…`;
            scheduleNext(Math.min(failCount * 3000, 30000));
        }
    }
    scheduleNext(500);
})();
<?php endif; ?>
</script>

<?php page_footer(); ?>
