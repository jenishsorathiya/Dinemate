-- Migration: Make legacy `booking_time` column nullable with default NULL
-- Date: 2026-04-07
-- This script is safe to run on any environment. It will detect if the
-- bookings.booking_time column exists and, if it is NOT NULL or has no
-- default, will alter the column to allow NULL and set DEFAULT NULL.

-- NOTE: This migration uses INFORMATION_SCHEMA to determine the existing
-- column type and emits an ALTER with that exact type. No manual edits
-- should be necessary.

SELECT
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
INTO @coltype, @is_nullable, @coldefault
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'bookings'
  AND COLUMN_NAME = 'booking_time'
LIMIT 1;

-- If the column doesn't exist, @coltype will be NULL; we build a no-op SELECT in that case
SET @need = IF(@coltype IS NOT NULL AND (@is_nullable = 'NO' OR @coldefault IS NULL), 1, 0);
SET @sql = IF(@need = 1, CONCAT('ALTER TABLE bookings MODIFY COLUMN booking_time ', @coltype, ' NULL DEFAULT NULL;'), 'SELECT 1;');

PREPARE _stmt FROM @sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- End of migration
