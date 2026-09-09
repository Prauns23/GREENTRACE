-- Existing species and AR measurements are preserved. MariaDB 10.4+.
ALTER TABLE tree_species
    ADD COLUMN IF NOT EXISTS archived TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL,
    ADD INDEX IF NOT EXISTS idx_species_archived_name (archived, name);
