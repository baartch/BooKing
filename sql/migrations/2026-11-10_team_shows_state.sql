-- Add a state to team shows — 2026-11-10
-- Existing shows default to provisional.

ALTER TABLE team_shows
    ADD COLUMN state ENUM('provisional', 'definitely', 'canceled') NOT NULL DEFAULT 'provisional' AFTER entrance_fee;
