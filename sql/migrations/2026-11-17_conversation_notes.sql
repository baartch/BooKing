CREATE TABLE conversation_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT NOT NULL,
    body TEXT NOT NULL,
    occurred_at DATETIME NOT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES email_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_conversation_notes_timeline (conversation_id, occurred_at)
);

CREATE TABLE conversation_note_participants (
    note_id INT NOT NULL,
    contact_id INT NOT NULL,
    PRIMARY KEY (note_id, contact_id),
    FOREIGN KEY (note_id) REFERENCES conversation_notes(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
);
