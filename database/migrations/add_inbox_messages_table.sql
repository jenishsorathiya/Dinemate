-- ============================================================================
-- Inbox messages table — unified store for all admin inbox items
-- (new booking requests, booking changes, cancellation requests,
--  function enquiries, and free-form guest messages)
-- ============================================================================

CREATE TABLE IF NOT EXISTS inbox_messages (
    inbox_id        INT             NOT NULL AUTO_INCREMENT,
    type            ENUM('new_booking','booking_change','cancellation','function_enquiry','guest_message') NOT NULL,
    folder          ENUM('requests','unassigned','waitlist','archived') NOT NULL DEFAULT 'requests',
    status          ENUM('open','waiting','confirmed','declined','resolved') NOT NULL DEFAULT 'open',
    booking_id      INT             DEFAULT NULL,
    user_id         INT             DEFAULT NULL,
    guest_name      VARCHAR(100)    DEFAULT NULL,
    guest_email     VARCHAR(150)    DEFAULT NULL,
    guest_phone     VARCHAR(30)     DEFAULT NULL,
    party_size      INT             DEFAULT NULL,
    subject         VARCHAR(160)    DEFAULT NULL,
    preview         VARCHAR(240)    DEFAULT NULL,
    message         TEXT            DEFAULT NULL,
    staff_notes     TEXT            DEFAULT NULL,
    is_read         TINYINT(1)      NOT NULL DEFAULT 0,
    received_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_action_at  TIMESTAMP       NULL     DEFAULT NULL,
    PRIMARY KEY (inbox_id),
    INDEX idx_inbox_folder_received (folder, status, received_at),
    INDEX idx_inbox_booking (booking_id),
    INDEX idx_inbox_user (user_id),
    CONSTRAINT fk_inbox_booking FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
    CONSTRAINT fk_inbox_user    FOREIGN KEY (user_id)    REFERENCES users(user_id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
