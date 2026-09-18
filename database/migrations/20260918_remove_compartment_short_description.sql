-- The Map V2 creation workflow does not collect a free-form description.
-- The reforestation_compartments table was empty when this migration was added.
ALTER TABLE `reforestation_compartments`
    DROP COLUMN `short_description`;
