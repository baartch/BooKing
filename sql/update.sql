ALTER TABLE email_conversations
    MODIFY mailbox_id INT DEFAULT NULL,
    MODIFY team_id INT DEFAULT NULL,
    ADD COLUMN user_id INT DEFAULT NULL AFTER team_id,
    DROP FOREIGN KEY email_conversations_ibfk_1,
    ADD CONSTRAINT email_conversations_ibfk_1 FOREIGN KEY (mailbox_id) REFERENCES mailboxes(id) ON DELETE SET NULL,
    ADD CONSTRAINT email_conversations_ibfk_3 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    ADD INDEX idx_email_conversations_team (team_id, last_activity_at),
    ADD INDEX idx_email_conversations_user (user_id, last_activity_at),
    ADD UNIQUE KEY uniq_email_conversation_team (team_id, subject_normalized, participant_key, is_closed),
    ADD UNIQUE KEY uniq_email_conversation_user (user_id, subject_normalized, participant_key, is_closed);

ALTER TABLE mailboxes
    ADD COLUMN display_name VARCHAR(120) DEFAULT NULL AFTER name;

ALTER TABLE email_messages
    MODIFY team_id INT DEFAULT NULL,
    ADD COLUMN user_id INT DEFAULT NULL AFTER team_id,
    ADD INDEX idx_email_messages_team (team_id),
    ADD INDEX idx_email_messages_user (user_id),
    DROP FOREIGN KEY email_messages_ibfk_3,
    ADD CONSTRAINT email_messages_ibfk_3 FOREIGN KEY (conversation_id) REFERENCES email_conversations(id) ON DELETE SET NULL,
    ADD CONSTRAINT email_messages_ibfk_5 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE email_messages
    ADD COLUMN body_html TEXT DEFAULT NULL AFTER body;

ALTER TABLE email_conversations
    DROP INDEX uniq_email_conversation,
    ADD INDEX idx_email_conversations_mailbox (mailbox_id);
