ALTER TABLE bookings
    MODIFY COLUMN user_id INT NULL,
    ADD COLUMN customer_name VARCHAR(100) NULL AFTER user_id,
    ADD COLUMN customer_phone VARCHAR(30) NULL AFTER customer_name,
    ADD COLUMN customer_email VARCHAR(100) NULL AFTER customer_phone,
    ADD COLUMN guest_access_token VARCHAR(64) NULL AFTER customer_email;

CREATE UNIQUE INDEX idx_bookings_guest_access_token ON bookings (guest_access_token);

UPDATE bookings b
LEFT JOIN users u ON b.user_id = u.user_id
SET b.customer_name = COALESCE(NULLIF(b.customer_name, ''), NULLIF(b.customer_name_override, ''), u.name),
    b.customer_phone = COALESCE(NULLIF(b.customer_phone, ''), u.phone),
    b.customer_email = COALESCE(NULLIF(b.customer_email, ''), u.email)
WHERE b.customer_name IS NULL
   OR b.customer_name = ''
   OR b.customer_phone IS NULL
   OR b.customer_phone = ''
   OR b.customer_email IS NULL
   OR b.customer_email = '';
