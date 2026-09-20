ALTER TABLE reforestation_compartments
    ADD COLUMN updated_by INT UNSIGNED NULL AFTER created_by,
    ADD INDEX idx_compartments_updated_by (updated_by),
    ADD CONSTRAINT fk_compartments_updated_by
        FOREIGN KEY (updated_by) REFERENCES users_tbl (id)
        ON DELETE SET NULL;

UPDATE reforestation_compartments rc
SET rc.updated_by = COALESCE(
        (
            SELECT csh.recorded_by
            FROM compartment_status_history csh
            WHERE csh.compartment_id = rc.id
              AND csh.recorded_by IS NOT NULL
            ORDER BY csh.recorded_at DESC, csh.id DESC
            LIMIT 1
        ),
        rc.created_by
    ),
    rc.updated_at = rc.updated_at
WHERE rc.updated_by IS NULL;
