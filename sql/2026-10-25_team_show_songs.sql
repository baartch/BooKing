-- Show setlist (Songs on Shows) — 2026-10-25
-- Standalone migration for existing installs. New installs: sql/schema.sql already covers this.
-- The parent ALTERs make (team_id, id) unique so the composite FKs below can
-- enforce that a show only references songs from its own team.

ALTER TABLE team_shows
    DROP INDEX idx_team_shows_team,
    ADD UNIQUE KEY uniq_team_shows_team (team_id, id);

ALTER TABLE team_songs
    DROP INDEX idx_team_songs_team,
    ADD UNIQUE KEY uniq_team_songs_team (team_id, id);

CREATE TABLE team_show_songs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    show_id INT NOT NULL,
    team_id INT NOT NULL,
    song_id INT NOT NULL,
    position INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_team_show_songs_show_song (show_id, song_id),
    UNIQUE KEY uniq_team_show_songs_show_position (show_id, position),
    INDEX idx_team_show_songs_song (song_id),
    INDEX idx_team_show_songs_team (team_id),
    FOREIGN KEY (team_id, show_id) REFERENCES team_shows(team_id, id) ON DELETE CASCADE,
    FOREIGN KEY (team_id, song_id) REFERENCES team_songs(team_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
