-- ============================================================================
-- DINEMATE BOOKING SYSTEM - TIME SLOT SQL QUERIES REFERENCE
-- ============================================================================

-- ============================================================================
-- 1. DATABASE SCHEMA MIGRATION
-- ============================================================================

-- Add time slot fields to existing bookings table
ALTER TABLE bookings 
ADD COLUMN start_time TIME NOT NULL DEFAULT '12:00:00',
ADD COLUMN end_time TIME NOT NULL DEFAULT '13:00:00';

-- Create index for faster queries
CREATE INDEX idx_bookings_date_time 
ON bookings(booking_date, start_time, end_time, table_id, status);

CREATE INDEX idx_bookings_table_status 
ON bookings(table_id, status, booking_date);


-- ============================================================================
-- 2. DETECT OVERLAPPING BOOKINGS
-- ============================================================================

-- Query to find ALL overlapping bookings for a specific table, date, and time slot
SELECT 
    booking_id,
    user_id,
    table_id,
    booking_date,
    start_time,
    end_time,
    number_of_guests,
    status
FROM bookings 
WHERE table_id = 5                    -- Replace with actual table_id
AND booking_date = '2026-04-15'      -- Replace with actual date
AND status IN ('pending', 'confirmed')
AND (
    -- Condition 1: Existing booking starts before new booking ends AND ends after new booking starts
    (start_time < '13:30' AND end_time > '12:00')
    -- Condition 2: Existing booking starts within the new booking time slot
    OR (start_time >= '12:00' AND start_time < '13:30')
    -- Condition 3: Existing booking ends within the new booking time slot
    OR (end_time > '12:00' AND end_time <= '13:30')
);

-- Example: Check if time slot 12:00-13:30 conflicts with existing bookings
-- Result: Will return all bookings that overlap with this time window


-- ============================================================================
-- 3. CHECK AVAILABILITY OF SPECIFIC TABLE
-- ============================================================================

-- Count overlapping bookings (returns 0 if available, >0 if conflicts)
SELECT COUNT(*) as overlap_count
FROM bookings 
WHERE table_id = 3
AND booking_date = '2026-04-15'
AND status IN ('pending', 'confirmed')
AND (
    (start_time < '14:00' AND end_time > '13:00')
    OR (start_time >= '13:00' AND start_time < '14:00')
    OR (end_time > '13:00' AND end_time <= '14:00')
);


-- ============================================================================
-- 4. GET AVAILABLE TABLES FOR DATE AND TIME SLOT
-- ============================================================================

-- Find all tables that don't have overlapping bookings
SELECT rt.* 
FROM restaurant_tables rt
WHERE rt.status = 'available'
AND rt.table_id NOT IN (
    SELECT DISTINCT table_id
    FROM bookings
    WHERE booking_date = '2026-04-15'
    AND status IN ('pending', 'confirmed')
    AND (
        (start_time < '15:00' AND end_time > '14:00')
        OR (start_time >= '14:00' AND start_time < '15:00')
        OR (end_time > '14:00' AND end_time <= '15:00')
    )
)
ORDER BY rt.capacity ASC;

-- Alternative: Get available tables with capacity filter
SELECT rt.* 
FROM restaurant_tables rt
WHERE rt.status = 'available'
AND rt.capacity >= 6  -- Minimum capacity for 6 guests
AND rt.table_id NOT IN (
    SELECT DISTINCT table_id
    FROM bookings
    WHERE booking_date = '2026-04-15'
    AND status IN ('pending', 'confirmed')
    AND (
        (start_time < '15:00' AND end_time > '14:00')
        OR (start_time >= '14:00' AND start_time < '15:00')
        OR (end_time > '14:00' AND end_time <= '15:00')
    )
)
ORDER BY rt.capacity ASC;


-- ============================================================================
-- 5. GET BOOKED TIME SLOTS FOR A TABLE AND DATE
-- ============================================================================

-- See all time slots taken for a specific table on a date
SELECT 
    booking_id,
    start_time,
    end_time,
    number_of_guests,
    user_id,
    status
FROM bookings
WHERE table_id = 2
AND booking_date = '2026-04-15'
AND status IN ('pending', 'confirmed')
ORDER BY start_time ASC;

-- Example Result:
-- | booking_id | start_time | end_time | number_of_guests | status    |
-- |------------|------------|----------|------------------|-----------|
-- | 1          | 12:00:00   | 13:00:00 | 4                | confirmed |
-- | 2          | 13:30:00   | 14:30:00 | 2                | pending   |
-- | 3          | 15:00:00   | 16:30:00 | 6                | confirmed |


-- ============================================================================
-- 6. INSERT NEW BOOKING WITH TIME SLOTS
-- ============================================================================

INSERT INTO bookings 
(user_id, table_id, booking_date, start_time, end_time, number_of_guests, special_request, status, created_at)
VALUES 
(15, 3, '2026-04-15', '14:00:00', '15:00:00', 4, 'Window seat preferred', 'confirmed', NOW());

-- Returns: Last inserted booking_id


-- ============================================================================
-- 7. UPDATE BOOKING TIME SLOT (for booking modifications)
-- ============================================================================

-- Update an existing booking's time slot
UPDATE bookings
SET 
    start_time = '16:00:00',
    end_time = '17:00:00',
    updated_at = NOW()
WHERE booking_id = 5
AND user_id = 15;  -- Ensure user owns the booking

-- Check for conflicts BEFORE updating
SELECT COUNT(*) as conflict_count
FROM bookings
WHERE table_id = 3
AND booking_date = '2026-04-15'
AND booking_id != 5  -- Exclude current booking from check
AND (
    (start_time < '17:00:00' AND end_time > '16:00:00')
    OR (start_time >= '16:00:00' AND start_time < '17:00:00')
    OR (end_time > '16:00:00' AND end_time <= '17:00:00')
);


-- ============================================================================
-- 8. CANCEL BOOKING AND FREE UP TIME SLOT
-- ============================================================================

UPDATE bookings
SET status = 'cancelled'
WHERE booking_id = 5
AND booking_date >= CURDATE();  -- Only cancel future bookings


-- ============================================================================
-- 9. ANALYTICS: BOOKING UTILIZATION BY TIME SLOT
-- ============================================================================

-- See which time slots are most booked
SELECT 
    booking_date,
    start_time,
    COUNT(*) as bookings_count,
    SUM(number_of_guests) as total_guests,
    COUNT(DISTINCT table_id) as tables_used
FROM bookings
WHERE status IN ('pending', 'confirmed')
AND booking_date >= '2026-04-01'
GROUP BY booking_date, start_time
ORDER BY booking_date, start_time;


-- ============================================================================
-- 10. ANALYTICS: TABLE UTILIZATION
-- ============================================================================

-- See which tables are most booked
SELECT 
    rt.table_id,
    rt.table_number,
    rt.capacity,
    COUNT(b.booking_id) as total_bookings,
    ROUND(COUNT(b.booking_id) * 100.0 / 
        (SELECT COUNT(*) FROM bookings WHERE status IN ('pending', 'confirmed')), 2) as utilization_percent
FROM restaurant_tables rt
LEFT JOIN bookings b ON rt.table_id = b.table_id 
    AND b.status IN ('pending', 'confirmed')
    AND b.booking_date >= '2026-04-01'
GROUP BY rt.table_id
ORDER BY total_bookings DESC;


-- ============================================================================
-- 11. ANALYTICS: PEAK HOURS
-- ============================================================================

-- Identify busiest time slots
SELECT 
    TIME_FORMAT(start_time, '%H:00') as hour,
    COUNT(*) as booking_count,
    SUM(number_of_guests) as total_guests
FROM bookings
WHERE status IN ('pending', 'confirmed')
AND booking_date >= '2026-04-01'
GROUP BY TIME_FORMAT(start_time, '%H:00')
ORDER BY booking_count DESC;


-- ============================================================================
-- 12. GET CONFLICTS FOR USER DASHBOARD
-- ============================================================================

-- Show all bookings with potential conflicts
SELECT 
    b1.booking_id,
    b1.table_id,
    b1.booking_date,
    b1.start_time,
    b1.end_time,
    b1.number_of_guests,
    COUNT(b2.booking_id) as conflicting_bookings
FROM bookings b1
LEFT JOIN bookings b2 ON 
    b1.table_id = b2.table_id
    AND b1.booking_date = b2.booking_date
    AND b1.booking_id != b2.booking_id
    AND b2.status IN ('pending', 'confirmed')
    AND (
        (b2.start_time < b1.end_time AND b2.end_time > b1.start_time)
        OR (b2.start_time >= b1.start_time AND b2.start_time < b1.end_time)
        OR (b2.end_time > b1.start_time AND b2.end_time <= b1.end_time)
    )
WHERE b1.status IN ('pending', 'confirmed')
GROUP BY b1.booking_id
HAVING conflicting_bookings > 0;


-- ============================================================================
-- 13. TIME SLOT WIDTH CALCULATION (for JavaScript validation)
-- ============================================================================

-- Get booking duration in minutes
SELECT 
    booking_id,
    start_time,
    end_time,
    TIMESTAMPDIFF(MINUTE, start_time, end_time) as duration_minutes
FROM bookings
WHERE booking_date = '2026-04-15'
ORDER BY start_time;


-- ============================================================================
-- 14. VALIDATION: Check Bookings Outside Operating Hours
-- ============================================================================

-- Find bookings outside restaurant hours (should be 0)
SELECT * 
FROM bookings
WHERE start_time < '10:00:00' 
OR end_time > '22:00:00'
OR status IN ('pending', 'confirmed');


-- ============================================================================
-- 15. MAINTENANCE: Identify Orphaned or Invalid Bookings
-- ============================================================================

-- Find bookings with end_time before start_time (data integrity check)
SELECT *
FROM bookings
WHERE end_time <= start_time;

-- Find bookings with excessive duration (>3 hours)
SELECT *
FROM bookings
WHERE TIMESTAMPDIFF(MINUTE, start_time, end_time) > 180;

-- Find bookings with insufficient minimum duration (< 1 hour)
SELECT *
FROM bookings
WHERE TIMESTAMPDIFF(MINUTE, start_time, end_time) < 60;


-- ============================================================================
-- SQL OVERLAP DETECTION EXPLANATION
-- ============================================================================

-- The overlap detection logic uses these conditions:
-- For two time intervals to NOT overlap, one must end before the other starts.
-- Therefore, to find overlaps, we check when at least one of these is true:
--
-- Condition 1: (existing.start < new.end) AND (existing.end > new.start)
--   → Standard overlap check: existing starts before new ends AND existing ends after new starts
--
-- Condition 2: (existing.start >= new.start) AND (existing.start < new.end)
--   → Existing booking starts during new booking time
--
-- Condition 3: (existing.end > new.start) AND (existing.end <= new.end)
--   → Existing booking ends during new booking time
--
-- Example:
-- New booking: 14:00-15:00
-- Existing:    13:00-14:00 → NO conflict (ends exactly when new starts)
-- Existing:    14:00-15:00 → CONFLICT (exact match)
-- Existing:    14:30-15:30 → CONFLICT (overlaps in middle)
-- Existing:    13:00-15:30 → CONFLICT (encompasses new booking)
-- Existing:    15:00-16:00 → NO conflict (starts exactly when new ends)

?>
