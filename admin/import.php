<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/layout.php";

require_auth();

// ── Column alias map ───────────────────────────────────────────────────────

function normalize_col(string $h): string
{
    $h = preg_replace("/[#?!]+/", "", $h); // strip #, ?, !
    $h = preg_replace("/\bof\b/i", "", $h); // strip filler "of"
    return strtolower(trim(preg_replace("/[\s_\-]+/", "_", $h)));
}

function map_headers(array $headers): array
{
    $map = [];
    $aliases = [
        "incident_date" => ["date", "incident_date", "event_date"],
        "city" => ["city"],
        "state" => ["state"],
        "school_name" => ["school", "school_name", "name"],
        "deaths" => ["deaths", "death", "killed", "fatalities", "fatality"],
        "injuries" => ["injuries", "injured", "injury", "wounded", "wounds"],
        // Total is computed — skipped intentionally
        "description" => ["description", "desc", "notes", "summary", "details"],
        "had_trans_assailant" => [
            "transgender_involvement", // ← "Transgender Involvement"
            "trans",
            "had_trans",
            "trans_assailant",
            "had_trans_assailant",
            "transgender",
            "trans_shooter",
            "transgender_involvement",
        ],
        "trans_assailant_count" => [
            "trans_assailants", // ← "# of trans assailants" after stripping #/of
            "trans_count",
            "num_trans",
            "trans_assailant_count",
            "number_of_trans",
            "transgender_count",
        ],
        "total_assailants" => [
            "total_assailants", // ← "Total # of assailants" after stripping #/of
            "assailants",
            "num_assailants",
            "total_shooters",
            "shooters",
        ],
        "assailant_genders" => [
            "gender",
            "genders",
            "assailant_genders",
            "assailant_gender",
            "shooter_gender",
        ],
        "source_url" => [
            "urls",
            "source",
            "source_url",
            "url",
            "link",
            "reference",
        ],
    ];
    foreach ($headers as $idx => $raw) {
        $norm = normalize_col($raw);
        foreach ($aliases as $field => $alts) {
            if (in_array($norm, $alts, true) && !isset($map[$field])) {
                $map[$field] = $idx;
                break;
            }
        }
    }
    return $map;
}

// ── State / territory lookup ───────────────────────────────────────────────

function state_abbr_map(): array
{
    return [
        // 50 states
        "alabama" => "AL",
        "alaska" => "AK",
        "arizona" => "AZ",
        "arkansas" => "AR",
        "california" => "CA",
        "colorado" => "CO",
        "connecticut" => "CT",
        "delaware" => "DE",
        "florida" => "FL",
        "georgia" => "GA",
        "hawaii" => "HI",
        "idaho" => "ID",
        "illinois" => "IL",
        "indiana" => "IN",
        "iowa" => "IA",
        "kansas" => "KS",
        "kentucky" => "KY",
        "louisiana" => "LA",
        "maine" => "ME",
        "maryland" => "MD",
        "massachusetts" => "MA",
        "michigan" => "MI",
        "minnesota" => "MN",
        "mississippi" => "MS",
        "missouri" => "MO",
        "montana" => "MT",
        "nebraska" => "NE",
        "nevada" => "NV",
        "new hampshire" => "NH",
        "new jersey" => "NJ",
        "new mexico" => "NM",
        "new york" => "NY",
        "north carolina" => "NC",
        "north dakota" => "ND",
        "ohio" => "OH",
        "oklahoma" => "OK",
        "oregon" => "OR",
        "pennsylvania" => "PA",
        "rhode island" => "RI",
        "south carolina" => "SC",
        "south dakota" => "SD",
        "tennessee" => "TN",
        "texas" => "TX",
        "utah" => "UT",
        "vermont" => "VT",
        "virginia" => "VA",
        "washington" => "WA",
        "west virginia" => "WV",
        "wisconsin" => "WI",
        "wyoming" => "WY",
        // DC — all common variants
        "district of columbia" => "DC",
        "d.c." => "DC",
        "dc" => "DC",
        "washington dc" => "DC",
        "washington d.c." => "DC",
        // Territories
        "puerto rico" => "PR",
        "u.s. virgin islands" => "VI",
        "us virgin islands" => "VI",
        "united states virgin islands" => "VI",
        "virgin islands" => "VI",
        "guam" => "GU",
        "american samoa" => "AS",
        "northern mariana islands" => "MP",
        "commonwealth of the northern mariana islands" => "MP",
    ];
}

function resolve_state(string $raw): string
{
    $map = state_abbr_map();
    $lower = strtolower(trim($raw));
    if (isset($map[$lower])) {
        return $map[$lower];
    }
    // Strip dots and re-check (handles D.C., U.S.A., etc.)
    $nodots = str_replace(".", "", $lower);
    if (isset($map[$nodots])) {
        return $map[$nodots];
    }
    $up = strtoupper(trim($raw));
    $up_nodots = str_replace(".", "", $up);
    if (strlen($up_nodots) <= 3 && ctype_alpha($up_nodots)) {
        return $up_nodots;
    }
    return trim($raw); // unknown — keep as-is for manual review
}

// ── Helpers ────────────────────────────────────────────────────────────────

function parse_bool_val(string $val): int
{
    $v = strtolower(trim($val));
    return in_array(
        $v,
        ["1", "yes", "true", "y", "x", "\u{2713}", "involved", "✓"],
        true,
    )
        ? 1
        : 0;
}

function parse_date_val(string $val): ?string
{
    if (empty(trim($val))) {
        return null;
    }
    $ts = strtotime($val);
    return $ts ? date("Y-m-d", $ts) : null;
}

function parse_row(array $row, array $map, int $row_num): array
{
    $get = fn($f) => isset($map[$f]) && isset($row[$map[$f]])
        ? trim($row[$map[$f]])
        : "";

    $issues = [];

    $raw_date = $get("incident_date");
    $date = parse_date_val($raw_date);
    if (!$date) {
        $issues[] =
            "Date '" . ($raw_date ?: "(empty)") . "' could not be parsed.";
    }

    $state = resolve_state($get("state"));
    // Flag if state column was non-empty but we couldn't resolve it
    if ($get("state") !== "" && strlen($state) > 3) {
        $issues[] =
            "State '" . $get("state") . "' not recognized — please correct.";
    }

    $has_trans = parse_bool_val($get("had_trans_assailant"));
    $trans_count = (int) $get("trans_assailant_count");
    if ($has_trans && $trans_count === 0) {
        $trans_count = 1;
    }

    return [
        "incident_date" => $date ?? "",
        "city" => $get("city"),
        "state" => $state,
        "school_name" => $get("school_name"),
        "deaths" => max(0, (int) $get("deaths")),
        "injuries" => max(0, (int) $get("injuries")),
        "description" => $get("description"),
        "had_trans_assailant" => $has_trans,
        "trans_assailant_count" => $trans_count,
        "total_assailants" => max(1, (int) ($get("total_assailants") ?: 1)),
        "assailant_genders" => $get("assailant_genders"),
        "source_url" => $get("source_url"),
        "_row_num" => $row_num,
        "_issues" => $issues,
        "_status" => empty($issues) ? "ok" : "review",
    ];
}

// ── Stage routing ──────────────────────────────────────────────────────────

$stage = $_POST["stage"] ?? "upload";
$errors = [];
$preview = [];
$filename = "";
$import_result = null;
$detected_map = [];
$detected_heads = [];

// ── STAGE: upload → parse ──────────────────────────────────────────────────
if (
    $stage === "upload" &&
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_FILES["import_file"])
) {
    require_csrf();
    $file = $_FILES["import_file"];
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

    if ($file["error"] !== UPLOAD_ERR_OK) {
        $errors[] = "Upload failed (error code " . $file["error"] . ").";
        $stage = "upload";
    } elseif (!in_array($ext, ["csv", "tsv", "txt"])) {
        $errors[] = "Only CSV/TSV files are accepted.";
        $stage = "upload";
    } else {
        $delimiter = $ext === "tsv" ? "\t" : ",";
        $rows = [];
        if (($fh = fopen($file["tmp_name"], "r")) !== false) {
            // Strip UTF-8 BOM if present
            $bom = fread($fh, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($fh);
            } // no BOM, rewind
            while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
                $rows[] = $row;
            }
            fclose($fh);
        }
        if (count($rows) < 2) {
            $errors[] = "File appears empty or contains only a header row.";
            $stage = "upload";
        } else {
            $headers = $rows[0];
            $map = map_headers($headers);
            if (!isset($map["incident_date"])) {
                $errors[] =
                    "Could not find a Date column. Headers found: " .
                    implode(", ", $headers);
                $stage = "upload";
            } else {
                for ($i = 1; $i < count($rows); $i++) {
                    if (count(array_filter($rows[$i])) === 0) {
                        continue;
                    }
                    $preview[] = parse_row($rows[$i], $map, $i + 1);
                }
                $filename = $file["name"];
                $detected_map = $map;
                $detected_heads = $headers;
                $stage = "review";
            }
        }
    }
}

// ── STAGE: confirm → write DB ──────────────────────────────────────────────
if ($stage === "confirm" && $_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    $all_rows = json_decode($_POST["rows_json"] ?? "[]", true) ?? [];
    $import_mode = $_POST["import_mode"] ?? "append"; // append | update | overwrite
    $pdo = get_pdo();
    $imported = 0;
    $skipped = 0;
    $updated = 0;
    $db_errors = [];

    // Overwrite: wipe table first
    if ($import_mode === "overwrite") {
        if (function_exists("delete_all_incidents")) {
            delete_all_incidents();
        } else {
            get_pdo()->exec("DELETE FROM incidents");
        }
    }

    foreach ($all_rows as $rec) {
        if (($rec["_status"] ?? "") === "skip") {
            $skipped++;
            continue;
        }
        if (empty($rec["incident_date"])) {
            $skipped++;
            continue;
        }

        $data = [
            "incident_date" => $rec["incident_date"],
            "city" => $rec["city"] ?? "",
            "state" => strtoupper($rec["state"] ?? ""),
            "school_name" => $rec["school_name"] ?? "",
            "deaths" => (int) ($rec["deaths"] ?? 0),
            "injuries" => (int) ($rec["injuries"] ?? 0),
            "description" => $rec["description"] ?? "",
            "had_trans_assailant" => (int) ($rec["had_trans_assailant"] ?? 0),
            "trans_assailant_count" =>
                (int) ($rec["trans_assailant_count"] ?? 0),
            "assailant_genders" => $rec["assailant_genders"] ?? "",
            "total_assailants" => max(1, (int) ($rec["total_assailants"] ?? 1)),
            "source_url" => $rec["source_url"] ?? "",
        ];

        try {
            if ($import_mode === "update") {
                // Match on date + city + state + school_name to handle multiple
                // incidents that share the same date and location
                $chk = $pdo->prepare(
                    'SELECT id FROM incidents
                     WHERE incident_date=? AND city=? AND state=? AND COALESCE(school_name,"")=?
                     LIMIT 1',
                );
                $chk->execute([
                    $data["incident_date"],
                    $data["city"],
                    strtoupper($data["state"]),
                    $data["school_name"] ?? "",
                ]);
                $existing = $chk->fetch();
                if ($existing) {
                    upsert_incident($data, (int) $existing["id"]);
                    $updated++;
                } else {
                    upsert_incident($data);
                    $imported++;
                }
            } else {
                // append or overwrite — just insert
                upsert_incident($data);
                $imported++;
            }
        } catch (PDOException $e) {
            $skipped++;
            $db_errors[] = "Row {$rec["_row_num"]}: " . $e->getMessage();
        }
    }

    $notes =
        ($updated ? "Updated: $updated. " : "") . implode("\n", $db_errors);
    $pdo->prepare(
        "INSERT INTO import_log (filename,rows_imported,rows_skipped,notes) VALUES (?,?,?,?)",
    )->execute([
        $_POST["filename"] ?? "",
        $imported + $updated,
        $skipped,
        $notes,
    ]);

    $import_result = compact(
        "imported",
        "updated",
        "skipped",
        "db_errors",
        "import_mode",
    );
    $stage = "done";
}

page_header("Import Data", true);
?>
<div class="container" style="max-width:1100px">
<p style="margin-bottom:1rem"><a href="index.php">← Dashboard</a></p>
<div class="page-intro">
    <div class="eyebrow">Administration</div>
    <h1>Import Spreadsheet</h1>
</div>

<?php foreach ($errors as $e):
    flash($e, "error");
endforeach; ?>

<?php
/* ── DONE ─────────────────────────────────────────────────────────── */
?>
<?php if ($stage === "done" && $import_result): ?>
<div class="alert alert--<?= $import_result["imported"] +
    $import_result["updated"] >
0
    ? "success"
    : "info" ?>">
    <strong>Import complete</strong>
    (<?= ucfirst($import_result["import_mode"]) ?> mode) —
    <?php if ($import_result["import_mode"] === "update"): ?>
        <?= $import_result["imported"] ?> new rows added,
        <?= $import_result["updated"] ?> rows updated,
    <?php else: ?>
        <?= $import_result["imported"] ?> rows imported,
    <?php endif; ?>
    <?= $import_result["skipped"] ?> skipped.
    <?php if (!empty($import_result["db_errors"])): ?>
    <ul style="margin:.5rem 0 0 1.2rem;font-size:.85rem">
        <?php foreach (
            $import_result["db_errors"]
            as $e
        ): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
<div style="display:flex;gap:1rem;flex-wrap:wrap">
    <a href="import.php" class="btn btn-ghost">Import another file</a>
    <a href="export.php" class="btn btn-ghost">↓ Export CSV</a>
    <a href="list.php" class="btn btn-primary">View all incidents →</a>
</div>

<?php
    /* ── REVIEW ───────────────────────────────────────────────────────── */
    ?>
<?php // Build a human-readable column detection summary
    // review rows

    /* ── UPLOAD FORM ──────────────────────────────────────────────────── */

    elseif ($stage === "review"): ?>
<?php
$ok_count = count(array_filter($preview, fn($r) => $r["_status"] === "ok"));
$review_count = count(
    array_filter($preview, fn($r) => $r["_status"] === "review"),
);
$import_mode = $_POST["import_mode"] ?? "append";
?>
<div class="alert alert--info" style="margin-bottom:1rem">
    Parsed <strong><?= count($preview) ?></strong> rows from
    <strong><?= htmlspecialchars($filename) ?></strong> —
    <strong><?= $ok_count ?></strong> ready,
    <?php if ($review_count > 0): ?>
    <strong style="color:var(--warn)"><?= $review_count ?> need attention</strong>.
    <?php else: ?>
    <strong style="color:var(--safe)">all rows look good!</strong>
    <?php endif; ?>
    Nothing is saved until you click <strong>Confirm Import</strong>.
</div>

<?php
$field_labels = [
    "incident_date" => "Date",
    "city" => "City",
    "state" => "State",
    "school_name" => "School",
    "deaths" => "Deaths",
    "injuries" => "Injuries",
    "description" => "Description",
    "had_trans_assailant" => "Transgender Involvement",
    "trans_assailant_count" => "# Trans Assailants",
    "total_assailants" => "Total Assailants",
    "assailant_genders" => "Gender",
    "source_url" => "URLs",
];
$unmapped = array_diff(array_keys($field_labels), array_keys($detected_map));
?>
<details style="margin-bottom:1.5rem;background:white;border:1px solid var(--rule);padding:.8rem 1.2rem;font-size:.82rem">
    <summary style="cursor:pointer;font-weight:600;color:var(--ink-soft)">
        Column detection
        <?php if (
            in_array("deaths", $unmapped) ||
            in_array("injuries", $unmapped)
        ): ?>
        — <span style="color:var(--accent)">⚠ some key columns not found</span>
        <?php else: ?>
        — all key columns found ✓
        <?php endif; ?>
    </summary>
    <div style="display:flex;gap:2rem;flex-wrap:wrap;margin-top:.8rem">
        <div>
            <strong style="font-size:.75rem;text-transform:uppercase;letter-spacing:.08em">Mapped</strong>
            <table style="margin-top:.4rem;font-size:.82rem;box-shadow:none;border:none">
                <?php foreach ($detected_map as $field => $col_idx): ?>
                <tr>
                    <td style="padding:.15rem .8rem .15rem 0;color:var(--ink-soft)"><?= htmlspecialchars(
                        $field_labels[$field] ?? $field,
                    ) ?></td>
                    <td style="padding:.15rem 0;font-family:monospace">"<?= htmlspecialchars(
                        $detected_heads[$col_idx] ?? "col $col_idx",
                    ) ?>" (col <?= $col_idx + 1 ?>)</td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php if (!empty($unmapped)): ?>
        <div>
            <strong style="font-size:.75rem;text-transform:uppercase;letter-spacing:.08em;color:var(--accent)">Not detected</strong>
            <ul style="margin-top:.4rem;padding-left:1rem;color:var(--ink-soft)">
                <?php foreach ($unmapped as $f): ?>
                <li><?= htmlspecialchars($field_labels[$f] ?? $f) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</details>

<form method="post" action="import.php" id="review-form">
    <?= csrf_field() ?>
    <input type="hidden" name="stage" value="confirm">
    <input type="hidden" name="filename" value="<?= htmlspecialchars(
        $filename,
    ) ?>">
    <input type="hidden" name="import_mode" value="<?= htmlspecialchars(
        $import_mode,
    ) ?>">
    <input type="hidden" name="rows_json" id="rows_json" value="">

    <!-- Import mode reminder + confirm button -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.8rem">
        <div style="font-size:.88rem">
            Mode: <strong><?php
            $labels = [
                "append" => "Append (add new rows only)",
                "update" => "Update (update existing + add new)",
                "overwrite" => "Overwrite (delete all, then import)",
            ];
            echo htmlspecialchars($labels[$import_mode] ?? $import_mode);
            ?></strong>
            <?php if ($import_mode === "overwrite"): ?>
            <span style="color:var(--accent);font-weight:700"> ⚠ All existing data will be deleted on confirm.</span>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary" onclick="return prepareSubmit()">
            Confirm &amp; Import →
        </button>
    </div>

    <?php if ($review_count > 0): ?>
    <div class="section-header">
        <h2 style="color:var(--warn)">⚠ Rows Needing Attention (<?= $review_count ?>)</h2>
    </div>
    <p style="font-size:.88rem;color:var(--ink-soft);margin-bottom:1.2rem">
        Edit fields inline — your changes will be used on import. Check <em>Skip</em> to exclude a row entirely.
    </p>
    <?php foreach ($preview as $rec):
        if ($rec["_status"] !== "review") {
            continue;
        } ?>
    <?php $key = "row_" . $rec["_row_num"]; ?>
    <div class="review-card" id="card-<?= $key ?>">
        <div class="review-card-header">
            <span class="review-row-num">Row <?= $rec["_row_num"] ?></span>
            <div class="review-issues">
                <?php foreach ($rec["_issues"] as $iss): ?>
                <span class="issue-badge">⚠ <?= htmlspecialchars($iss) ?></span>
                <?php endforeach; ?>
            </div>
            <label class="skip-label">
                <input type="checkbox" class="skip-check" data-key="<?= $key ?>"> Skip row
            </label>
        </div>
        <div class="review-fields">
            <div class="rf-group">
                <label>Date</label>
                <input type="date" class="rf-input" data-key="<?= $key ?>" data-field="incident_date"
                       value="<?= htmlspecialchars($rec["incident_date"]) ?>">
            </div>
            <div class="rf-group">
                <label>City</label>
                <input type="text" class="rf-input" data-key="<?= $key ?>" data-field="city"
                       value="<?= htmlspecialchars($rec["city"]) ?>">
            </div>
            <div class="rf-group">
                <label>State (abbr)</label>
                <input type="text" class="rf-input" data-key="<?= $key ?>" data-field="state"
                       value="<?= htmlspecialchars(
                           $rec["state"],
                       ) ?>" maxlength="3"
                       style="text-transform:uppercase">
            </div>
            <div class="rf-group">
                <label>School Name</label>
                <input type="text" class="rf-input" data-key="<?= $key ?>" data-field="school_name"
                       value="<?= htmlspecialchars($rec["school_name"]) ?>">
            </div>
            <div class="rf-group">
                <label>Deaths</label>
                <input type="number" class="rf-input" data-key="<?= $key ?>" data-field="deaths"
                       value="<?= (int) $rec["deaths"] ?>" min="0">
            </div>
            <div class="rf-group">
                <label>Injuries</label>
                <input type="number" class="rf-input" data-key="<?= $key ?>" data-field="injuries"
                       value="<?= (int) $rec["injuries"] ?>" min="0">
            </div>
            <div class="rf-group">
                <label>Trans Involved?</label>
                <select class="rf-input" data-key="<?= $key ?>" data-field="had_trans_assailant">
                    <option value="0" <?= !$rec["had_trans_assailant"]
                        ? "selected"
                        : "" ?>>No</option>
                    <option value="1" <?= $rec["had_trans_assailant"]
                        ? "selected"
                        : "" ?>>Yes</option>
                </select>
            </div>
            <div class="rf-group">
                <label># Trans Assailants</label>
                <input type="number" class="rf-input" data-key="<?= $key ?>" data-field="trans_assailant_count"
                       value="<?= (int) $rec[
                           "trans_assailant_count"
                       ] ?>" min="0">
            </div>
            <div class="rf-group" style="grid-column:1/-1">
                <label>Description</label>
                <input type="text" class="rf-input" data-key="<?= $key ?>" data-field="description"
                       value="<?= htmlspecialchars(
                           $rec["description"],
                       ) ?>" style="width:100%">
            </div>
        </div>
    </div>
    <?php
    endforeach; ?>
    <?php endif;
    // review rows
    ?>

    <div class="section-header" style="margin-top:2rem">
        <h2>✓ Rows Ready to Import (<?= $ok_count ?>)</h2>
    </div>
    <div class="table-wrap" style="margin-bottom:2rem">
        <table style="font-size:.82rem">
            <thead>
                <tr>
                    <th>Row</th><th>Date</th><th>City</th><th>State</th><th>School</th>
                    <th class="td-num">Deaths</th><th class="td-num">Injuries</th>
                    <th class="td-badge">Trans</th><th style="text-align:center">Skip</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($preview as $rec):
                    if ($rec["_status"] !== "ok") {
                        continue;
                    } ?>
                <?php $key = "row_" . $rec["_row_num"]; ?>
                <tr id="okrow-<?= $key ?>">
                    <td style="color:var(--ink-soft);font-size:.78rem"><?= $rec[
                        "_row_num"
                    ] ?></td>
                    <td class="td-date"><?= $rec["incident_date"]
                        ? date("M j, Y", strtotime($rec["incident_date"]))
                        : "—" ?></td>
                    <td><?= htmlspecialchars($rec["city"]) ?></td>
                    <td><?= htmlspecialchars($rec["state"]) ?></td>
                    <td><?= htmlspecialchars($rec["school_name"]) ?></td>
                    <td class="td-num td-deaths"><?= (int) $rec[
                        "deaths"
                    ] ?></td>
                    <td class="td-num"><?= (int) $rec["injuries"] ?></td>
                    <td class="td-badge">
                        <?= $rec["had_trans_assailant"]
                            ? '<span class="badge badge-trans">Yes</span>'
                            : '<span class="badge badge-none">No</span>' ?>
                    </td>
                    <td style="text-align:center">
                        <input type="checkbox" class="skip-check" data-key="<?= $key ?>" title="Skip this row">
                    </td>
                </tr>
                <?php
                endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.8rem">
        <a href="import.php" class="btn btn-ghost">← Start over</a>
        <button type="submit" class="btn btn-primary" onclick="return prepareSubmit()">Confirm &amp; Import →</button>
    </div>
</form>

<script>
const rowData = <?= json_encode(
    array_combine(
        array_map(fn($r) => "row_" . $r["_row_num"], $preview),
        $preview,
    ),
    JSON_HEX_TAG | JSON_HEX_APOS,
) ?>;

document.querySelectorAll('.rf-input').forEach(el => {
    const ev = (el.tagName === 'SELECT') ? 'change' : 'input';
    el.addEventListener(ev, () => {
        const { key, field } = el.dataset;
        if (key && field && rowData[key]) rowData[key][field] = el.value;
    });
});

document.querySelectorAll('.skip-check').forEach(cb => {
    cb.addEventListener('change', () => {
        const key = cb.dataset.key;
        if (!rowData[key]) return;
        rowData[key]['_status'] = cb.checked ? 'skip'
            : (rowData[key]['_issues']?.length ? 'review' : 'ok');
        const card = document.getElementById('card-' + key)
                  || document.getElementById('okrow-' + key);
        if (card) card.style.opacity = cb.checked ? '0.35' : '1';
    });
});

function prepareSubmit() {
    document.getElementById('rows_json').value = JSON.stringify(Object.values(rowData));
    return true;
}
</script>

<style>
.review-card {
    background: white; border: 1px solid var(--rule);
    border-left: 4px solid var(--warn);
    margin-bottom: 1.2rem; padding: 1.2rem 1.4rem; box-shadow: var(--shadow);
}
.review-card-header {
    display: flex; align-items: flex-start; gap: 1rem;
    flex-wrap: wrap; margin-bottom: 1rem;
}
.review-row-num {
    font-size: .72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .1em; color: var(--ink-soft); white-space: nowrap; padding-top: .2rem;
}
.review-issues { display: flex; flex-wrap: wrap; gap: .4rem; flex: 1; }
.issue-badge {
    background: #fdf3d0; border: 1px solid #e8c84a;
    color: #6b4a00; font-size: .75rem; padding: .2rem .6rem; border-radius: 2px;
}
.skip-label {
    display: flex; align-items: center; gap: .4rem;
    font-size: .82rem; font-weight: 600; color: var(--accent);
    cursor: pointer; white-space: nowrap;
}
.review-fields {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(155px, 1fr)); gap: .8rem;
}
.rf-group { display: flex; flex-direction: column; gap: .25rem; }
.rf-group label {
    font-size: .68rem; text-transform: uppercase;
    letter-spacing: .1em; color: var(--ink-soft); font-weight: 600;
}
.rf-group input, .rf-group select {
    border: 1px solid var(--rule); background: white;
    padding: .4rem .6rem; font-family: var(--font-body);
    font-size: .88rem; border-radius: var(--radius); width: 100%;
}
.rf-group input:focus, .rf-group select:focus {
    outline: 2px solid var(--accent-2); border-color: transparent;
}
</style>

<?php
    /* ── UPLOAD FORM ──────────────────────────────────────────────────── */
    ?>
<?php else: ?>

<p style="color:var(--ink-soft);margin-bottom:1.5rem">
    Upload a CSV from your spreadsheet. All rows are shown for review — nothing is written to the database until you confirm.
</p>

<div style="background:white;border:1px solid var(--rule);padding:2rem;box-shadow:var(--shadow);margin-bottom:2rem">
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="stage" value="upload">

        <div class="form-group" style="margin-bottom:1.4rem">
            <label for="import_file">CSV File</label>
            <input type="file" id="import_file" name="import_file" accept=".csv,.tsv,.txt" required
                   style="padding:.45rem 0;border:none;background:transparent">
            <span style="font-size:.78rem;color:var(--ink-soft)">
                Excel: <em>File → Save As → CSV</em>.
                Google Sheets: <em>File → Download → CSV</em>.
            </span>
        </div>

        <!-- Import mode selector -->
        <div class="form-group" style="margin-bottom:1.6rem">
            <label>Import Mode</label>
            <div class="mode-group" style="margin-top:.5rem">
                <label class="mode-btn">
                    <input type="radio" name="import_mode" value="append" checked>
                    <span>Append</span>
                </label>
                <label class="mode-btn">
                    <input type="radio" name="import_mode" value="update">
                    <span>Update</span>
                </label>
                <label class="mode-btn mode-btn--danger" id="overwrite-btn">
                    <input type="radio" name="import_mode" value="overwrite" id="overwrite-radio">
                    <span>Overwrite</span>
                </label>
            </div>
            <div class="mode-desc" id="mode-desc-append">
                Add all rows as new records. Safe default.
            </div>
            <div class="mode-desc" id="mode-desc-update" style="display:none">
                Update existing records matched by date + city + state + school name; insert anything new.
            </div>
            <div class="mode-desc mode-desc--warn" id="mode-desc-overwrite" style="display:none">
                ⚠ Deletes <strong>all existing data</strong>, then imports fresh. Cannot be undone.
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Parse File →</button>
    </form>
</div>

<script>
const descs = ['append','update','overwrite'];
document.querySelectorAll('input[name="import_mode"]').forEach(r => {
    r.addEventListener('change', () => {
        descs.forEach(m => {
            document.getElementById('mode-desc-' + m).style.display = m === r.value ? 'block' : 'none';
        });
        document.getElementById('overwrite-btn').classList.toggle('mode-btn--active-danger', r.value === 'overwrite');
    });
});
</script>

<h2 style="margin-bottom:.8rem;margin-top:2rem">Expected Column Names</h2>
<p style="font-size:.88rem;color:var(--ink-soft);margin-bottom:.8rem">
    Matched case-insensitively. Your exact headers are shown in <code>code</code>.
</p>
<div class="table-wrap">
    <table style="font-size:.82rem">
        <thead><tr><th>Field</th><th>Your header / accepted aliases</th><th>Required?</th></tr></thead>
        <tbody>
            <tr><td><strong>Date</strong></td><td><code>Date</code>, incident_date, event_date</td><td>✓</td></tr>
            <tr><td><strong>City</strong></td><td><code>City</code></td><td>—</td></tr>
            <tr><td><strong>State</strong></td><td><code>State</code> — full name or abbreviation, including territories</td><td>—</td></tr>
            <tr><td><strong>School Name</strong></td><td><code>School</code>, school_name, name</td><td>—</td></tr>
            <tr><td><strong>Deaths</strong></td><td><code>Deaths</code>, killed, fatalities</td><td>—</td></tr>
            <tr><td><strong>Injuries</strong></td><td><code>Injuries</code>, injured, wounded</td><td>—</td></tr>
            <tr><td><strong>Total</strong></td><td><code>Total</code> — skipped (computed automatically)</td><td>—</td></tr>
            <tr><td><strong>Description</strong></td><td><code>Description</code>, notes, summary</td><td>—</td></tr>
            <tr><td><strong>Trans Involvement</strong></td><td><code>Transgender Involvement</code>, trans, had_trans, transgender</td><td>—</td></tr>
            <tr><td><strong>Total Assailants</strong></td><td><code>Total # of assailants</code>, total_assailants, assailants</td><td>—</td></tr>
            <tr><td><strong># Trans Assailants</strong></td><td><code># of trans assailants</code>, trans_count, num_trans</td><td>—</td></tr>
            <tr><td><strong>Gender</strong></td><td><code>Gender</code>, genders, assailant_genders</td><td>—</td></tr>
            <tr><td><strong>Source URLs</strong></td><td><code>URLs</code>, source, source_url, url, link</td><td>—</td></tr>
        </tbody>
    </table>
</div>

<style>
.mode-group { display: flex; gap: 0; }
.mode-btn {
    display: flex; align-items: center; gap: .5rem;
    padding: .5rem 1.4rem; border: 1px solid var(--rule);
    margin-right: -1px; cursor: pointer;
    font-family: var(--font-body); font-size: .88rem; font-weight: 600;
    background: white; transition: background .15s, color .15s;
    user-select: none;
}
.mode-btn:first-child { border-radius: var(--radius) 0 0 var(--radius); }
.mode-btn:last-child  { border-radius: 0 var(--radius) var(--radius) 0; margin-right: 0; }
.mode-btn input[type=radio] { display: none; }
.mode-btn:has(input:checked) {
    background: var(--ink); color: var(--paper);
    border-color: var(--ink); z-index: 1;
}
.mode-btn--danger:has(input:checked),
.mode-btn--active-danger {
    background: var(--accent) !important; border-color: var(--accent) !important;
    color: white !important;
}
.mode-btn:hover:not(:has(input:checked)) { background: var(--paper-2); }
.mode-desc {
    margin-top: .5rem; font-size: .82rem; color: var(--ink-soft);
}
.mode-desc--warn { color: var(--accent); font-weight: 600; }
</style>

<?php endif; ?>
</div>
<?php page_footer(); ?>
