ALTER TABLE bookings
    ADD COLUMN booking_type ENUM('normal', 'trivia', 'function') NOT NULL DEFAULT 'normal' AFTER special_request;
