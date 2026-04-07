-- Migration: add areas to restaurant tables
-- Description: creates table_areas and links restaurant_tables to areas with ordering
-- Date: 2026-04-03

CREATE TABLE IF NOT EXISTS table_areas (
    area_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    table_number_start INT NULL DEFAULT NULL,
    table_number_end INT NULL DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE restaurant_tables
    ADD COLUMN area_id INT NULL AFTER table_id,
    ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER capacity;

INSERT INTO table_areas (name, display_order, is_active)
SELECT 'Main Floor', 10, 1
WHERE NOT EXISTS (
    SELECT 1 FROM table_areas WHERE name = 'Main Floor'
);

UPDATE restaurant_tables
SET area_id = (SELECT area_id FROM table_areas WHERE name = 'Main Floor' ORDER BY area_id ASC LIMIT 1)
WHERE area_id IS NULL OR area_id = 0;
