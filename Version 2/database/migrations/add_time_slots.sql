-- Migration: Add time slot fields to bookings table
-- Description: Adds start_time and end_time fields to support time slot bookings
-- Date: 2026-03-30

ALTER TABLE bookings ADD COLUMN start_time TIME NOT NULL DEFAULT '12:00:00';
ALTER TABLE bookings ADD COLUMN end_time TIME NOT NULL DEFAULT '13:00:00';

-- Optional: Create index for faster overlap detection queries
CREATE INDEX idx_bookings_date_time ON bookings(booking_date, start_time, end_time, table_id, status);

-- Verify the changes
DESCRIBE bookings;
