-- GREENTRACE Map V2 foundation (MariaDB 10.4 compatible)
--
-- This migration intentionally preserves the legacy forest_areas point records.
-- New Map V2 compartments use polygon boundaries and can be migrated from those
-- records later after their real surveyed boundary has been captured.

ALTER TABLE `barangays`
    ADD COLUMN `psgc_code` VARCHAR(10) NULL AFTER `id`,
    ADD COLUMN `municipality_name` VARCHAR(100) NOT NULL DEFAULT 'Morong' AFTER `name`,
    ADD COLUMN `province_name` VARCHAR(100) NOT NULL DEFAULT 'Bataan' AFTER `municipality_name`,
    ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`,
    ADD UNIQUE KEY `uq_barangays_psgc_code` (`psgc_code`);

-- Geometry is isolated from the existing barangays reference table. This lets us
-- load an authoritative GeoJSON later while existing users_tbl.barangay_id values
-- remain valid.
CREATE TABLE `barangay_boundaries` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `barangay_id` INT(11) NOT NULL,
    `boundary` MULTIPOLYGON NOT NULL COMMENT 'WGS84 longitude/latitude geometry',
    `source_name` VARCHAR(150) NOT NULL,
    `source_version` VARCHAR(100) NULL,
    `source_url` VARCHAR(500) NULL,
    `imported_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_barangay_boundaries_barangay` (`barangay_id`),
    SPATIAL KEY `idx_barangay_boundaries_geometry` (`boundary`),
    CONSTRAINT `fk_barangay_boundaries_barangay`
        FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reforestation_compartments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `legacy_forest_area_id` INT(10) UNSIGNED NULL,
    `barangay_id` INT(11) NULL,
    `name` VARCHAR(150) NOT NULL,
    `short_description` VARCHAR(255) NULL,
    `status` ENUM('draft', 'planned', 'planted', 'validated', 'monitored', 'low_survival', 'completed') NOT NULL DEFAULT 'draft',
    `planting_model` VARCHAR(100) NULL,
    `date_started` DATE NULL,
    `gross_area_ha` DECIMAL(14,4) NULL COMMENT 'Geodesic area calculated from the boundary',
    `calculated_area_sqm` DECIMAL(18,2) NULL COMMENT 'Geodesic area calculated from the boundary',
    `boundary` POLYGON NOT NULL COMMENT 'WGS84 longitude/latitude geometry',
    `created_by` INT(10) UNSIGNED NULL,
    `archived` TINYINT(1) NOT NULL DEFAULT 0,
    `archived_at` DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_compartments_legacy_area` (`legacy_forest_area_id`),
    KEY `idx_compartments_barangay_status` (`barangay_id`, `status`, `archived`),
    KEY `idx_compartments_created_by` (`created_by`),
    KEY `idx_compartments_date_started` (`date_started`),
    SPATIAL KEY `idx_compartments_boundary` (`boundary`),
    CONSTRAINT `fk_compartments_legacy_forest_area`
        FOREIGN KEY (`legacy_forest_area_id`) REFERENCES `forest_areas` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_compartments_barangay`
        FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_compartments_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users_tbl` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Surveyed vertices are kept individually for traverse auditing. The polygon above
-- remains the map's efficient rendering/query geometry.
CREATE TABLE `compartment_boundary_points` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `compartment_id` BIGINT UNSIGNED NOT NULL,
    `point_order` SMALLINT UNSIGNED NOT NULL,
    `point_label` VARCHAR(32) NULL,
    `coordinate` POINT NOT NULL COMMENT 'WGS84 longitude/latitude survey point',
    `recorded_by` INT(10) UNSIGNED NULL,
    `recorded_at` DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_compartment_boundary_point_order` (`compartment_id`, `point_order`),
    SPATIAL KEY `idx_compartment_boundary_points_coordinate` (`coordinate`),
    CONSTRAINT `fk_boundary_points_compartment`
        FOREIGN KEY (`compartment_id`) REFERENCES `reforestation_compartments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_boundary_points_recorded_by`
        FOREIGN KEY (`recorded_by`) REFERENCES `users_tbl` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The species reference is the existing tree_species table. spacing_m is a snapshot
-- of tree_species.planting_spacing at planning time so later species edits do not
-- rewrite an approved compartment plan.
CREATE TABLE `compartment_species` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `compartment_id` BIGINT UNSIGNED NOT NULL,
    `tree_species_id` INT(10) UNSIGNED NOT NULL,
    `planned_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    `planted_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    `surviving_quantity` INT UNSIGNED NULL,
    `spacing_m` DECIMAL(7,2) NOT NULL COMMENT 'Snapshot of tree_species.planting_spacing in metres',
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_compartment_species` (`compartment_id`, `tree_species_id`),
    KEY `idx_compartment_species_species` (`tree_species_id`),
    CONSTRAINT `fk_compartment_species_compartment`
        FOREIGN KEY (`compartment_id`) REFERENCES `reforestation_compartments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_compartment_species_tree_species`
        FOREIGN KEY (`tree_species_id`) REFERENCES `tree_species` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `compartment_status_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `compartment_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('draft', 'planned', 'planted', 'validated', 'monitored', 'low_survival', 'completed') NOT NULL,
    `notes` TEXT NULL,
    `recorded_by` INT(10) UNSIGNED NULL,
    `recorded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_compartment_status_history` (`compartment_id`, `recorded_at`),
    CONSTRAINT `fk_compartment_status_history_compartment`
        FOREIGN KEY (`compartment_id`) REFERENCES `reforestation_compartments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_compartment_status_history_recorded_by`
        FOREIGN KEY (`recorded_by`) REFERENCES `users_tbl` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `compartment_photos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `compartment_id` BIGINT UNSIGNED NOT NULL,
    `uploaded_by` INT(10) UNSIGNED NULL,
    `category` ENUM('planned', 'planted', 'validated', 'monitored', 'low_survival', 'other') NOT NULL DEFAULT 'other',
    `storage_path` VARCHAR(255) NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `file_size_bytes` BIGINT UNSIGNED NOT NULL,
    `captured_at` DATETIME NULL,
    `caption` VARCHAR(500) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_compartment_photos_list` (`compartment_id`, `category`, `created_at`),
    CONSTRAINT `fk_compartment_photos_compartment`
        FOREIGN KEY (`compartment_id`) REFERENCES `reforestation_compartments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_compartment_photos_uploaded_by`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users_tbl` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
