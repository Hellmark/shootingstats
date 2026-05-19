<?php
// includes/db.php

require_once __DIR__ . "/config.php";

function get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=%s",
            DB_HOST,
            DB_NAME,
            DB_CHARSET,
        );
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log the real error for server-side debugging
            error_log("Database connection failed: " . $e->getMessage());
            http_response_code(500);
            die(
                '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Site Unavailable</title></head>' .
                    '<body style="font-family:sans-serif;padding:3rem;text-align:center;background:#f5f0e8">' .
                    '<div style="background:white;border-left:4px solid #8b1a1a;padding:1.5rem 2rem;max-width:600px;margin:auto">' .
                    '<h2 style="color:#8b1a1a;margin:0 0 .8rem">Site Temporarily Unavailable</h2>' .
                    '<p>We\'re experiencing a technical issue. Please try again later.</p>' .
                    '<p style="font-size:.85rem;color:var(#666)">If the problem persists, contact the site administrator.</p>' .
                    "</div></body></html>"
            );
        }
        run_migrations($pdo);
    }
    return $pdo;
}

/**
 * Auto-migration: add any columns that are missing from the incidents table.
 * Safe to run on every request — each ALTER only fires if the column is absent.
 */
function run_migrations(PDO $pdo): void
{
    // Fetch existing columns once
    $existing = [];
    foreach ($pdo->query("SHOW COLUMNS FROM incidents") as $row) {
        $existing[] = $row["Field"];
    }

    $migrations = [
        // column name => ALTER statement to add it
        "school_name" =>
            "ALTER TABLE incidents ADD COLUMN school_name VARCHAR(255) NULL AFTER state",
        "location" =>
            "ALTER TABLE incidents MODIFY COLUMN location VARCHAR(255) NULL",
    ];

    foreach ($migrations as $col => $sql) {
        if (!in_array($col, $existing, true)) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                // Non-fatal — log and continue; the import error will make the issue obvious
                error_log(
                    "Migration failed for column '$col': " . $e->getMessage(),
                );
            }
        }
    }

    // Ensure geocode_cache table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS geocode_cache (
        id        INT AUTO_INCREMENT PRIMARY KEY,
        city      VARCHAR(100) NOT NULL,
        state     VARCHAR(50)  NOT NULL,
        lat       DECIMAL(9,6),
        lng       DECIMAL(9,6),
        failed    TINYINT(1) NOT NULL DEFAULT 0,
        cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY city_state (city, state)
    )");

    // Ensure import_log table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS import_log (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        filename      VARCHAR(255),
        rows_imported INT,
        rows_skipped  INT,
        notes         TEXT,
        imported_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

function get_summary(): array
{
    $pdo = get_pdo();
    return $pdo->query("SELECT * FROM v_summary")->fetch();
}

function get_incidents(
    array $filters = [],
    int $limit = 50,
    int $offset = 0,
): array {
    $pdo = get_pdo();
    $where = ["1=1"];
    $params = [];

    if (!empty($filters["state"])) {
        $where[] = "state = :state";
        $params["state"] = $filters["state"];
    }
    if (!empty($filters["year_from"])) {
        $where[] = "YEAR(incident_date) >= :year_from";
        $params["year_from"] = (int) $filters["year_from"];
    }
    if (!empty($filters["year_to"])) {
        $where[] = "YEAR(incident_date) <= :year_to";
        $params["year_to"] = (int) $filters["year_to"];
    }
    if (isset($filters["trans_only"]) && $filters["trans_only"]) {
        $where[] = "had_trans_assailant = 1";
    }

    $sql = sprintf(
        "SELECT * FROM incidents WHERE %s ORDER BY incident_date DESC LIMIT %d OFFSET %d",
        implode(" AND ", $where),
        $limit,
        $offset,
    );
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_incident_count(array $filters = []): int
{
    $pdo = get_pdo();
    $where = ["1=1"];
    $params = [];

    if (!empty($filters["state"])) {
        $where[] = "state = :state";
        $params["state"] = $filters["state"];
    }
    if (!empty($filters["year_from"])) {
        $where[] = "YEAR(incident_date) >= :year_from";
        $params["year_from"] = (int) $filters["year_from"];
    }
    if (!empty($filters["year_to"])) {
        $where[] = "YEAR(incident_date) <= :year_to";
        $params["year_to"] = (int) $filters["year_to"];
    }
    if (isset($filters["trans_only"]) && $filters["trans_only"]) {
        $where[] = "had_trans_assailant = 1";
    }

    $sql = sprintf(
        "SELECT COUNT(*) FROM incidents WHERE %s",
        implode(" AND ", $where),
    );
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function get_by_year(): array
{
    $pdo = get_pdo();
    return $pdo
        ->query(
            'SELECT YEAR(incident_date) AS yr,
                COUNT(*) AS incidents,
                SUM(deaths) AS deaths,
                SUM(injuries) AS injuries,
                SUM(had_trans_assailant) AS trans_incidents,
                SUM(CASE WHEN had_trans_assailant = 1 THEN deaths   ELSE 0 END) AS trans_deaths,
                SUM(CASE WHEN had_trans_assailant = 1 THEN injuries ELSE 0 END) AS trans_injuries,
                SUM(CASE WHEN had_trans_assailant = 0 THEN deaths   ELSE 0 END) AS non_trans_deaths,
                SUM(CASE WHEN had_trans_assailant = 0 THEN injuries ELSE 0 END) AS non_trans_injuries
         FROM incidents
         GROUP BY yr
         ORDER BY yr ASC',
        )
        ->fetchAll();
}

function get_by_state(): array
{
    $pdo = get_pdo();
    return $pdo
        ->query(
            'SELECT state,
                COUNT(*) AS incidents,
                SUM(deaths) AS deaths,
                SUM(injuries) AS injuries
         FROM incidents
         WHERE state IS NOT NULL AND state != ""
         GROUP BY state
         ORDER BY incidents DESC',
        )
        ->fetchAll();
}

function get_states(): array
{
    $pdo = get_pdo();
    return $pdo
        ->query(
            'SELECT DISTINCT state FROM incidents WHERE state IS NOT NULL AND state != "" ORDER BY state',
        )
        ->fetchAll(PDO::FETCH_COLUMN);
}

function get_incident(int $id): ?array
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare("SELECT * FROM incidents WHERE id = :id");
    $stmt->execute(["id" => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Build the human-readable location display string from components.
 * Format: "City, State — School Name" (omitting blanks gracefully)
 */
function build_location(
    string $city,
    string $state,
    string $school = "",
): string {
    $city = trim($city);
    $state = trim($state);
    $school = trim($school);

    // City, State portion
    $geo = implode(", ", array_filter([$city, $state]));

    if ($school === "") {
        return $geo;
    }

    // Strip any trailing " — STATE" or ", STATE" from school name to avoid duplication
    // e.g. if school was stored as "Heritage Middle School — ND" or "Heritage Middle School, ND"
    if ($state !== "") {
        $school = preg_replace(
            "/[\s,\-—]+" . preg_quote($state, "/") . '\s*$/i',
            "",
            $school,
        );
        $school = trim($school, " \t\n\r\0\x0B—,-");
    }

    if ($school === "") {
        return $geo;
    }
    return ($geo ? $geo . " — " : "") . $school;
}

/**
 * Returns an HTML string for display: "City, State" bold + "School Name" in lighter text below.
 */
function format_location_html(array $inc): string
{
    $geo = implode(
        ", ",
        array_filter([trim($inc["city"] ?? ""), trim($inc["state"] ?? "")]),
    );
    $school = trim($inc["school_name"] ?? "");
    $out = "";
    if ($geo) {
        $out .= "<strong>" . htmlspecialchars($geo) . "</strong>";
    }
    if ($school) {
        $out .=
            ($geo ? "<br>" : "") .
            '<span style="font-weight:300;font-size:.85em;color:var(--ink-soft)">' .
            htmlspecialchars($school) .
            "</span>";
    }
    return $out ?: htmlspecialchars($inc["location"] ?? "");
}

function upsert_incident(array $data, ?int $id = null): int
{
    $pdo = get_pdo();

    // Strip any source_url that isn't http(s) — prevents javascript: URIs
    if (isset($data["source_url"]) && !preg_match('/^https?:\/\//i', $data["source_url"])) {
        $data["source_url"] = "";
    }

    // Always recompute the display location from components
    $data["location"] = build_location(
        $data["city"] ?? "",
        $data["state"] ?? "",
        $data["school_name"] ?? "",
    );

    $fields = [
        "incident_date",
        "location",
        "city",
        "state",
        "school_name",
        "deaths",
        "injuries",
        "description",
        "had_trans_assailant",
        "trans_assailant_count",
        "assailant_genders",
        "total_assailants",
        "source_url",
    ];

    if ($id) {
        $sets = implode(", ", array_map(fn($f) => "$f = :$f", $fields));
        $sql = "UPDATE incidents SET $sets WHERE id = :id";
        $data["id"] = $id;
        $pdo->prepare($sql)->execute(
            array_intersect_key($data, array_flip([...$fields, "id"])),
        );
        return $id;
    } else {
        $cols = implode(", ", $fields);
        $vals = implode(", ", array_map(fn($f) => ":$f", $fields));
        $sql = "INSERT INTO incidents ($cols) VALUES ($vals)";
        $pdo->prepare($sql)->execute(
            array_intersect_key($data, array_flip($fields)),
        );
        return (int) $pdo->lastInsertId();
    }
}

function delete_incident(int $id): void
{
    $pdo = get_pdo();
    $pdo->prepare("DELETE FROM incidents WHERE id = :id")->execute([
        "id" => $id,
    ]);
}

function get_heatmap_data(): array
{
    $pdo = get_pdo();
    // Return all incidents joined with cached coordinates
    return $pdo
        ->query(
            'SELECT i.city, i.state, i.school_name,
                COUNT(*) AS incidents,
                SUM(i.deaths) AS deaths,
                SUM(i.injuries) AS injuries,
                SUM(i.had_trans_assailant) AS trans_incidents,
                g.lat, g.lng
         FROM incidents i
         LEFT JOIN geocode_cache g ON g.city = i.city AND g.state = i.state
         WHERE g.lat IS NOT NULL AND g.failed = 0
         GROUP BY i.city, i.state, g.lat, g.lng
         ORDER BY incidents DESC',
        )
        ->fetchAll();
}

function get_uncached_locations(): array
{
    $pdo = get_pdo();
    // Cities in incidents table that have no geocode entry yet
    return $pdo
        ->query(
            'SELECT DISTINCT i.city, i.state
         FROM incidents i
         LEFT JOIN geocode_cache g ON g.city = i.city AND g.state = i.state
         WHERE g.id IS NULL
           AND i.city != ""
           AND i.state != ""
         LIMIT 50', // process in batches to respect Nominatim rate limits
        )
        ->fetchAll();
}

function geocode_location(string $city, string $state): ?array
{
    $query = urlencode("$city, $state, USA");
    $url = "https://nominatim.openstreetmap.org/search?q={$query}&format=json&limit=1&countrycodes=us";

    $ctx = stream_context_create([
        "http" => [
            "header" => "User-Agent: SchoolShootingStats/1.0\r\n",
            "timeout" => 5,
        ],
    ]);

    $json = @file_get_contents($url, false, $ctx);
    if (!$json) {
        return null;
    }

    $data = json_decode($json, true);
    if (empty($data[0])) {
        return null;
    }

    return ["lat" => (float) $data[0]["lat"], "lng" => (float) $data[0]["lon"]];
}

function cache_geocode(string $city, string $state, ?array $coords): void
{
    $pdo = get_pdo();
    if ($coords) {
        $pdo->prepare(
            'INSERT INTO geocode_cache (city, state, lat, lng, failed)
             VALUES (?, ?, ?, ?, 0)
             ON DUPLICATE KEY UPDATE lat=VALUES(lat), lng=VALUES(lng), failed=0, cached_at=NOW()',
        )->execute([$city, $state, $coords["lat"], $coords["lng"]]);
    } else {
        $pdo->prepare(
            'INSERT INTO geocode_cache (city, state, lat, lng, failed)
             VALUES (?, ?, NULL, NULL, 1)
             ON DUPLICATE KEY UPDATE failed=1, cached_at=NOW()',
        )->execute([$city, $state]);
    }
}

function get_geocode_progress(): array
{
    $pdo = get_pdo();
    $total = (int) $pdo
        ->query(
            'SELECT COUNT(DISTINCT city, state) FROM incidents WHERE city != "" AND state != ""',
        )
        ->fetchColumn();
    $cached = (int) $pdo
        ->query("SELECT COUNT(*) FROM geocode_cache WHERE failed = 0")
        ->fetchColumn();
    $failed = (int) $pdo
        ->query("SELECT COUNT(*) FROM geocode_cache WHERE failed = 1")
        ->fetchColumn();
    return compact("total", "cached", "failed");
}

function delete_all_incidents(): void
{
    get_pdo()->exec("DELETE FROM incidents");
}

/**
 * Stream all incidents as a CSV download.
 * Call this before any output is sent.
 */
function export_csv(): void
{
    $pdo = get_pdo();
    $rows = $pdo
        ->query(
            'SELECT incident_date, city, state, school_name,
                deaths, injuries, total_harmed,
                description,
                had_trans_assailant AS `Transgender Involvement`,
                total_assailants AS `Total # of assailants`,
                trans_assailant_count AS `# of trans assailants`,
                assailant_genders AS Gender,
                source_url AS URLs
         FROM incidents
         ORDER BY incident_date ASC',
        )
        ->fetchAll();

    $filename = "school_shootings_" . date("Y-m-d") . ".csv";
    header("Content-Type: text/csv; charset=utf-8");
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header("Cache-Control: no-cache");

    $fh = fopen("php://output", "w");
    // BOM for Excel UTF-8 compatibility
    fputs($fh, "\xEF\xBB\xBF");

    // Headers matching the import format exactly
    fputcsv($fh, [
        "Date",
        "City",
        "State",
        "School",
        "Deaths",
        "Injuries",
        "Total",
        "Description",
        "Transgender Involvement",
        "Total # of assailants",
        "# of trans assailants",
        "Gender",
        "URLs",
    ]);

    foreach ($rows as $row) {
        fputcsv($fh, [
            $row["incident_date"],
            $row["city"],
            $row["state"],
            $row["school_name"],
            $row["deaths"],
            $row["injuries"],
            $row["total_harmed"],
            $row["description"],
            $row["Transgender Involvement"] ? "Yes" : "No",
            $row["Total # of assailants"],
            $row["# of trans assailants"],
            $row["Gender"],
            $row["URLs"],
        ]);
    }
    fclose($fh);
    exit();
}
