-- The Map V2 workflow no longer uses "validated" as a compartment status.
-- Existing validated records become monitored so no status information is lost.
UPDATE `reforestation_compartments`
SET `status` = 'monitored'
WHERE `status` = 'validated';

UPDATE `compartment_status_history`
SET `status` = 'monitored'
WHERE `status` = 'validated';

-- Photos use "other" because "monitored" is a valid photo category but does
-- not always describe an older validated upload.
UPDATE `compartment_photos`
SET `category` = 'other'
WHERE `category` = 'validated';

ALTER TABLE `reforestation_compartments`
    MODIFY `status` ENUM('draft', 'planned', 'planted', 'monitored', 'low_survival', 'completed')
    NOT NULL DEFAULT 'draft';

ALTER TABLE `compartment_status_history`
    MODIFY `status` ENUM('draft', 'planned', 'planted', 'monitored', 'low_survival', 'completed')
    NOT NULL;

ALTER TABLE `compartment_photos`
    MODIFY `category` ENUM('planned', 'planted', 'monitored', 'low_survival', 'other')
    NOT NULL DEFAULT 'other';
