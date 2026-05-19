<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/layout.php";

require_auth();

$id = (int) ($_GET["id"] ?? 0);
$inc = $id ? get_incident($id) : null;
$is_new = !$inc;

$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    $data = [
        "incident_date" => $_POST["incident_date"] ?? "",
        "city" => trim($_POST["city"] ?? ""),
        "state" => trim($_POST["state"] ?? ""),
        "school_name" => trim($_POST["school_name"] ?? ""),
        "deaths" => max(0, (int) ($_POST["deaths"] ?? 0)),
        "injuries" => max(0, (int) ($_POST["injuries"] ?? 0)),
        "description" => trim($_POST["description"] ?? ""),
        "had_trans_assailant" => isset($_POST["had_trans_assailant"]) ? 1 : 0,
        "trans_assailant_count" => max(
            0,
            (int) ($_POST["trans_assailant_count"] ?? 0),
        ),
        "assailant_genders" => trim($_POST["assailant_genders"] ?? ""),
        "total_assailants" => max(1, (int) ($_POST["total_assailants"] ?? 1)),
        "source_url" => trim($_POST["source_url"] ?? ""),
    ];

    if (empty($data["incident_date"])) {
        $errors[] = "Date is required.";
    }
    if (empty($data["city"]) && empty($data["state"])) {
        $errors[] = "At least a city or state is required.";
    }

    if (empty($errors)) {
        $saved_id = upsert_incident($data, $id ?: null);
        if ($is_new) {
            header("Location: edit.php?id={$saved_id}&saved=1");
            exit();
        }
        $success = "Incident updated successfully.";
        $inc = get_incident($id); // refresh
    }
}

$v = $inc ?? ($_POST ?? []);
$title = $is_new ? "Add Incident" : "Edit Incident";

page_header($title, true);
?>
<div class="container">

    <p style="margin-bottom:1rem"><a href="<?= $is_new
        ? "index.php"
        : "list.php" ?>">← Back</a></p>

    <div class="page-intro">
        <div class="eyebrow">Administration</div>
        <h1><?= $title ?></h1>
    </div>

    <?php if (!empty($_GET["saved"])):
        flash("Incident saved successfully.");
    endif; ?>
    <?php if ($success):
        flash($success);
    endif; ?>
    <?php foreach ($errors as $e):
        flash($e, "error");
    endforeach; ?>

    <form method="post" class="admin-form">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="form-group">
                <label for="incident_date">Date *</label>
                <input type="date" id="incident_date" name="incident_date"
                       value="<?= htmlspecialchars(
                           $v["incident_date"] ?? "",
                       ) ?>" required>
            </div>
            <div class="form-group">
                <label for="school_name">School Name</label>
                <input type="text" id="school_name" name="school_name"
                       value="<?= htmlspecialchars($v["school_name"] ?? "") ?>"
                       placeholder="e.g. Columbine High School">
            </div>
            <div class="form-group">
                <label for="city">City</label>
                <input type="text" id="city" name="city" value="<?= htmlspecialchars(
                    $v["city"] ?? "",
                ) ?>">
            </div>
            <div class="form-group">
                <label for="state">State / Territory</label>
                <select id="state" name="state">
                    <option value="">— Select —</option>
                    <?php
                    $us_states = [
                        "AL" => "Alabama",
                        "AK" => "Alaska",
                        "AZ" => "Arizona",
                        "AR" => "Arkansas",
                        "CA" => "California",
                        "CO" => "Colorado",
                        "CT" => "Connecticut",
                        "DE" => "Delaware",
                        "FL" => "Florida",
                        "GA" => "Georgia",
                        "HI" => "Hawaii",
                        "ID" => "Idaho",
                        "IL" => "Illinois",
                        "IN" => "Indiana",
                        "IA" => "Iowa",
                        "KS" => "Kansas",
                        "KY" => "Kentucky",
                        "LA" => "Louisiana",
                        "ME" => "Maine",
                        "MD" => "Maryland",
                        "MA" => "Massachusetts",
                        "MI" => "Michigan",
                        "MN" => "Minnesota",
                        "MS" => "Mississippi",
                        "MO" => "Missouri",
                        "MT" => "Montana",
                        "NE" => "Nebraska",
                        "NV" => "Nevada",
                        "NH" => "New Hampshire",
                        "NJ" => "New Jersey",
                        "NM" => "New Mexico",
                        "NY" => "New York",
                        "NC" => "North Carolina",
                        "ND" => "North Dakota",
                        "OH" => "Ohio",
                        "OK" => "Oklahoma",
                        "OR" => "Oregon",
                        "PA" => "Pennsylvania",
                        "RI" => "Rhode Island",
                        "SC" => "South Carolina",
                        "SD" => "South Dakota",
                        "TN" => "Tennessee",
                        "TX" => "Texas",
                        "UT" => "Utah",
                        "VT" => "Vermont",
                        "VA" => "Virginia",
                        "WA" => "Washington",
                        "WV" => "West Virginia",
                        "WI" => "Wisconsin",
                        "WY" => "Wyoming",
                        "DC" => "Washington D.C.",
                        "PR" => "Puerto Rico",
                        "VI" => "U.S. Virgin Islands",
                        "GU" => "Guam",
                        "AS" => "American Samoa",
                        "MP" => "Northern Mariana Islands",
                    ];
                    foreach ($us_states as $abbr => $name): ?>
                    <option value="<?= $abbr ?>" <?= ($v["state"] ?? "") ===
$abbr
    ? "selected"
    : "" ?>><?= $abbr ?> — <?= $name ?></option>
                    <?php endforeach;
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="deaths">Deaths</label>
                <input type="number" id="deaths" name="deaths" min="0" value="<?= (int) ($v[
                    "deaths"
                ] ?? 0) ?>">
            </div>
            <div class="form-group">
                <label for="injuries">Injuries</label>
                <input type="number" id="injuries" name="injuries" min="0" value="<?= (int) ($v[
                    "injuries"
                ] ?? 0) ?>">
            </div>
            <div class="form-group">
                <label for="total_assailants">Total Assailants</label>
                <input type="number" id="total_assailants" name="total_assailants" min="1" value="<?= (int) ($v[
                    "total_assailants"
                ] ?? 1) ?>">
            </div>
            <div class="form-group">
                <label for="assailant_genders">Assailant Gender(s)</label>
                <input type="text" id="assailant_genders" name="assailant_genders"
                       value="<?= htmlspecialchars(
                           $v["assailant_genders"] ?? "",
                       ) ?>"
                       placeholder="e.g. Male, Male, Female">
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;text-transform:none;letter-spacing:0;font-size:.9rem;font-weight:600">
                    <input type="checkbox" name="had_trans_assailant" value="1"
                           <?= !empty($v["had_trans_assailant"])
                               ? "checked"
                               : "" ?>
                           style="width:1rem;height:1rem;border:1px solid var(--rule)">
                    Had Transgender Assailant
                </label>
            </div>
            <div class="form-group">
                <label for="trans_assailant_count">Number of Trans Assailants</label>
                <input type="number" id="trans_assailant_count" name="trans_assailant_count" min="0"
                       value="<?= (int) ($v["trans_assailant_count"] ?? 0) ?>">
            </div>
            <div class="form-group full">
                <label for="source_url">Source URL</label>
                <input type="url" id="source_url" name="source_url"
                       value="<?= htmlspecialchars($v["source_url"] ?? "") ?>"
                       placeholder="https://...">
            </div>
            <div class="form-group full">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5"><?= htmlspecialchars(
                    $v["description"] ?? "",
                ) ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Incident</button>
            <a href="<?= $is_new
                ? "index.php"
                : "list.php" ?>" class="btn btn-ghost">Cancel</a>
        </div>
    </form>

</div>
<?php page_footer(); ?>
