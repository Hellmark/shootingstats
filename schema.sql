-- School Shooting Statistics Database Schema
-- Run this once to initialize the database

CREATE DATABASE IF NOT EXISTS school_shootings CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE school_shootings;

CREATE TABLE IF NOT EXISTS incidents (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    incident_date   DATE NOT NULL,
    city            VARCHAR(100),
    state           VARCHAR(100)  COMMENT 'State or territory, abbreviation or full name',
    school_name     VARCHAR(255)  COMMENT 'Name of the school',
    location        VARCHAR(255)  COMMENT 'Computed display: City, State — School Name',
    deaths          INT NOT NULL DEFAULT 0,
    injuries        INT NOT NULL DEFAULT 0,
    total_harmed    INT GENERATED ALWAYS AS (deaths + injuries) STORED,
    description     TEXT,
    had_trans_assailant   TINYINT(1) NOT NULL DEFAULT 0,
    trans_assailant_count INT NOT NULL DEFAULT 0,
    assailant_genders     VARCHAR(255) COMMENT 'Comma-separated genders of all assailants',
    total_assailants      INT NOT NULL DEFAULT 1,
    source_url        VARCHAR(500),
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date (incident_date),
    INDEX idx_state (state),
    INDEX idx_trans (had_trans_assailant)
);

-- If upgrading from an earlier version, run these ALTER statements:
-- ALTER TABLE incidents ADD COLUMN school_name VARCHAR(255) AFTER state;
-- ALTER TABLE incidents MODIFY COLUMN state VARCHAR(100);
-- ALTER TABLE incidents MODIFY COLUMN location VARCHAR(255) NULL;

-- Summary view for quick stats
CREATE OR REPLACE VIEW v_summary AS
SELECT
    COUNT(*) AS total_incidents,
    SUM(deaths) AS total_deaths,
    SUM(injuries) AS total_injuries,
    SUM(total_harmed) AS total_harmed,
    SUM(had_trans_assailant) AS incidents_with_trans_assailant,
    SUM(trans_assailant_count) AS total_trans_assailants,
    SUM(total_assailants) AS total_assailants,
    MIN(incident_date) AS earliest_incident,
    MAX(incident_date) AS latest_incident
FROM incidents;

CREATE TABLE IF NOT EXISTS geocode_cache (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    city        VARCHAR(100) NOT NULL,
    state       VARCHAR(50)  NOT NULL,
    lat         DECIMAL(9,6),
    lng         DECIMAL(9,6),
    failed      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if geocoding returned no result',
    cached_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY city_state (city, state)
);
    id           INT AUTO_INCREMENT PRIMARY KEY,
    filename     VARCHAR(255),
    rows_imported INT,
    rows_skipped  INT,
    notes        TEXT,
    imported_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
