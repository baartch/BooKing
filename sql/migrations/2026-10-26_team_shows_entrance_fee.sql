-- Entrance fee on team shows — 2026-10-26
-- Standalone migration for existing installs. New installs: sql/schema.sql already covers this.

ALTER TABLE team_shows
    ADD COLUMN entrance_fee DECIMAL(10,2) DEFAULT NULL AFTER artist_fee;
