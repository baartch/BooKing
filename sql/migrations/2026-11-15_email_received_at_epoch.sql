-- Preserve the source instant independently from the timezone-less received_at value.

ALTER TABLE email_messages
    ADD COLUMN received_at_epoch BIGINT DEFAULT NULL AFTER received_at;
