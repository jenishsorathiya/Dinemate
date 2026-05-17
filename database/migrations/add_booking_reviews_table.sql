CREATE TABLE IF NOT EXISTS booking_reviews (
    review_id INT NOT NULL AUTO_INCREMENT,
    booking_id INT NOT NULL,
    review_rating TINYINT NOT NULL,
    review_comment TEXT DEFAULT NULL,
    reviewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (review_id),
    UNIQUE KEY uq_booking_reviews_booking_id (booking_id),
    KEY idx_booking_reviews_rating (review_rating),
    KEY idx_booking_reviews_reviewed_at (reviewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
