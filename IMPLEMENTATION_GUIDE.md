# DineMate Booking System - Time Slot Feature Implementation Guide

## Overview
This guide documents the complete implementation of time slot booking functionality for the DineMate restaurant reservation system. The feature allows users to select specific start and end times for their reservations, with automatic conflict detection and validation.

---

## 📋 Implementation Checklist

- [x] Database schema migration (add start_time and end_time columns)
- [x] Frontend HTML form with time inputs
- [x] Frontend JavaScript validation
- [x] Backend PHP validation and conflict checking
- [x] Helper functions library
- [x] SQL query reference documentation

---

## 🗄️ 1. Database Migration

### Files Modified/Created
- `database/migrations/add_time_slots.sql`

### Step 1: Run the Migration
Execute this SQL in your phpMyAdmin or MySQL client:

```sql
ALTER TABLE bookings ADD COLUMN start_time TIME NOT NULL DEFAULT '12:00:00';
ALTER TABLE bookings ADD COLUMN end_time TIME NOT NULL DEFAULT '13:00:00';
CREATE INDEX idx_bookings_date_time ON bookings(booking_date, start_time, end_time, table_id, status);
```

### Updated Database Schema
```
Table: bookings
┌──────────────────┬──────────────┬─────────────────────────┐
│ Column Name      │ Data Type    │ Purpose                 │
├──────────────────┼──────────────┼─────────────────────────┤
│ booking_id       │ INT (PK)     │ Primary Key             │
│ user_id          │ INT (FK)     │ Customer Reference      │
│ table_id         │ INT (FK)     │ Table Reference         │
│ booking_date     │ DATE         │ Reservation Date        │
│ start_time       │ TIME (NEW)   │ Reservation Start Time  │
│ end_time         │ TIME (NEW)   │ Reservation End Time    │
│ number_of_guests │ INT          │ Guest Count             │
│ special_request  │ TEXT         │ Special Requirements    │
│ status           │ ENUM         │ pending/confirmed/etc   │
└──────────────────┴──────────────┴─────────────────────────┘
```

---

## 🎨 2. Frontend Implementation

### Files Modified
- `bookings/book-table.php`

### Features Added

#### 2.1 HTML Time Inputs
**Location:** Lines where old dropdown was replaced

**New Form Fields:**
```html
<!-- Start Time Input -->
<div class="col-md-6 mb-4">
    <label class="form-label">
        <i class="fa fa-clock"></i> Start Time
    </label>
    <input
        type="time"
        name="start_time"
        class="form-control modern-input"
        required
        id="start-time"
        min="10:00"
        max="22:00"
        value="12:00"
    >
    <small class="form-text text-muted">Restaurant hours: 10:00 - 22:00</small>
    <div id="start-time-error" class="validation-message"></div>
</div>

<!-- End Time Input -->
<div class="col-md-6 mb-4">
    <label class="form-label">
        <i class="fa fa-hourglass-end"></i> End Time
    </label>
    <input
        type="time"
        name="end_time"
        class="form-control modern-input"
        required
        id="end-time"
        min="10:00"
        max="22:00"
        value="13:00"
    >
    <small class="form-text text-muted">Booking duration: 60 - 180 minutes</small>
    <div id="end-time-error" class="validation-message"></div>
</div>
```

**UI Features:**
- Native HTML5 time pickers
- Bootstrap responsive layout (2-column on medium+ screens)
- Real-time validation feedback
- Restaurant hours displayed as helper text
- Booking duration constraints shown

#### 2.2 JavaScript Validation

**Configuration:**
```javascript
const RESTAURANT_HOURS = {
    open: '10:00',
    close: '22:00',
    minDuration: 60,      // minutes
    maxDuration: 180      // minutes
};
```

**Validation Rules:**
1. ✅ Start time cannot be before restaurant opening (10:00)
2. ✅ End time cannot be after restaurant closing (22:00)
3. ✅ End time must be after start time
4. ✅ Booking duration must be at least 60 minutes
5. ✅ Booking duration cannot exceed 180 minutes

**Events Handled:**
- `change` on start_time: Real-time validation with error messages
- `change` on end_time: Real-time validation with error messages
- `submit` on form: Comprehensive validation before submission

---

## 🔧 3. Backend Implementation

### Files Modified/Created
- `bookings/process-booking.php` - Updated with time validation & conflict checking
- `bookings/booking-helpers.php` - New helper functions library

### 3.1 Backend Validation Flow

**process-booking.php includes:**

```
1. Input Sanitization
   ├─ Validate all required fields present
   ├─ Convert times to consistent format
   └─ Validate guest count

2. Restaurant Hours Validation
   ├─ Check start_time >= 10:00
   ├─ Check end_time <= 22:00
   └─ Check end_time > start_time

3. Booking Duration Validation
   ├─ Calculate duration in minutes
   ├─ Check duration >= 60 minutes
   └─ Check duration <= 180 minutes

4. Table Capacity Validation
   ├─ Fetch table from database
   ├─ Verify table exists
   └─ Check guests <= capacity

5. Conflict Detection
   ├─ Query database for overlapping bookings
   ├─ Return error if conflicts found
   └─ Only check 'pending' and 'confirmed' bookings

6. Insert Booking (if all validations pass)
   ├─ Execute INSERT statement
   ├─ Get booking_id from lastInsertId()
   └─ Redirect to confirmation page
```

### 3.2 Conflict Detection SQL Query

**The Core Logic:**
```php
$stmt = $pdo->prepare("
    SELECT COUNT(*) as overlap_count 
    FROM bookings 
    WHERE table_id = ? 
    AND booking_date = ? 
    AND status IN ('pending', 'confirmed')
    AND (
        (start_time < ? AND end_time > ?)          -- Condition 1: Standard overlap
        OR (start_time >= ? AND start_time < ?)   -- Condition 2: Start during new slot
        OR (end_time > ? AND end_time <= ?)       -- Condition 3: End during new slot
    )
");

// Example: Check if 14:00-15:00 conflicts on table 3 on 2026-04-15
$stmt->execute([
    3,                    // table_id
    '2026-04-15',        // booking_date
    '15:00',             // new end_time
    '14:00',             // new start_time
    '14:00',             // new start_time
    '15:00',             // new end_time
    '14:00',             // new start_time
    '15:00'              // new end_time
]);
```

**Overlap Detection Examples:**
```
New Booking: 14:00-15:00

Existing: 12:00-13:00 → ✅ NO CONFLICT (ends before new starts)
Existing: 14:00-15:00 → ❌ CONFLICT (exact match)
Existing: 13:30-14:30 → ❌ CONFLICT (overlaps start)
Existing: 14:30-15:30 → ❌ CONFLICT (overlaps end)
Existing: 13:00-16:00 → ❌ CONFLICT (encompasses new)
Existing: 13:30-14:20 → ❌ CONFLICT (fully contained)
Existing: 15:00-16:00 → ✅ NO CONFLICT (starts when new ends)
```

---

## 📚 4. Helper Functions Library

### File
- `bookings/booking-helpers.php`

### Available Functions

#### 4.1 `validateTimeSlot($startTime, $endTime)`
Validates a time slot for:
- Operating hours compliance
- Time order (end > start)
- Minimum/maximum duration

```php
$result = validateTimeSlot('14:00', '15:30');
// Returns: ['valid' => true, 'error' => null]

$result = validateTimeSlot('14:00', '14:00');
// Returns: ['valid' => false, 'error' => 'End time must be after start time']
```

#### 4.2 `checkBookingConflicts($pdo, $tableId, $date, $startTime, $endTime, $excludeBookingId)`
Checks for overlapping bookings

```php
$conflicts = checkBookingConflicts($pdo, 3, '2026-04-15', '14:00:00', '15:00:00');
// Returns: ['conflict' => false, 'overlappingBookings' => []]

$conflicts = checkBookingConflicts($pdo, 3, '2026-04-15', '14:00:00', '15:00:00', 5);
// Excludes booking_id 5 from check (useful for modifications)
```

#### 4.3 `getAvailableTables($pdo, $date, $startTime, $endTime, $minCapacity)`
Gets all available tables for a specific time slot

```php
$tables = getAvailableTables($pdo, '2026-04-15', '14:00:00', '15:00:00', 4);
// Returns array of tables with capacity >= 4 that have no conflicts
```

#### 4.4 `getBookingDuration($startTime, $endTime)`
Calculates duration in minutes

```php
$minutes = getBookingDuration('14:00:00', '15:30:00');
// Returns: 90
```

#### 4.5 `formatTime($time)`
Formats time for display (12-hour format)

```php
echo formatTime('14:30:00');
// Output: "2:30 PM"
```

---

## 🧪 5. Testing the Implementation

### Test Case 1: Valid Booking
**Scenario:** User books Table 3 for April 15, 2026 from 14:00-15:00

**Expected Result:**
- ✅ All validations pass
- ✅ Booking inserted into database
- ✅ User redirected to confirmation page
- ✅ New booking visible in my-bookings page

### Test Case 2: Overlapping Time Slot
**Scenario:** Table 3 already has booking 14:30-15:30, user tries to book 14:00-15:00

**Expected Result:**
- ❌ Backend detects conflict
- ❌ Error message shown: "This table is already booked for the selected time slot..."
- ❌ Booking NOT inserted

### Test Case 3: Invalid Time Range
**Scenario:** User selects start_time: 15:00, end_time: 14:00

**Expected Result:**
- ❌ JavaScript validation triggered
- ❌ Error message: "End time must be after start time"
- ❌ Form submission prevented

### Test Case 4: Duration Validation
**Scenario:** User selects 14:00-14:30 (30 minutes)

**Expected Result:**
- ❌ Frontend/Backend validation fails
- ❌ Error message: "Booking duration must be at least 60 minutes"

### Test Case 5: Hours Validation
**Scenario:** User tries to book 08:00-09:00 (before opening)

**Expected Result:**
- ❌ Frontend validation: "Start time cannot be before 10:00"
- ❌ Or backend validation if JS bypassed

---

## 🔍 6. Database Queries for Management

### View All Upcoming Bookings with Times
```sql
SELECT 
    b.booking_id,
    u.customer_name,
    b.table_id,
    b.booking_date,
    b.start_time,
    b.end_time,
    b.number_of_guests,
    b.status
FROM bookings b
JOIN users u ON b.user_id = u.user_id
WHERE b.booking_date >= CURDATE()
ORDER BY b.booking_date, b.start_time;
```

### Find Conflicts for Specific Table and Date
```sql
SELECT * FROM bookings
WHERE table_id = 3
AND booking_date = '2026-04-15'
AND status IN ('pending', 'confirmed')
ORDER BY start_time;
```

### Check Table Utilization
```sql
SELECT 
    t.table_number,
    COUNT(b.booking_id) as bookings,
    SUM(TIMESTAMPDIFF(MINUTE, b.start_time, b.end_time)) as total_minutes_booked
FROM restaurant_tables t
LEFT JOIN bookings b ON t.table_id = b.table_id AND b.status = 'confirmed'
GROUP BY t.table_id
ORDER BY bookings DESC;
```

---

## 📝 7. Configuration Reference

### Restaurant Hours (Can be customized in process-booking.php)
```php
$restaurantOpen = '10:00';     // Opening time
$restaurantClose = '22:00';    // Closing time
$minDuration = 60;             // Minimum booking duration (minutes)
$maxDuration = 180;            // Maximum booking duration (minutes)
```

### Update these constants in:
1. `bookings/book-table.php` - PHP variables (~line 15)
2. `bookings/book-table.php` - JavaScript RESTAURANT_HOURS (~line 280)
3. `bookings/process-booking.php` - PHP variables (~line 20)
4. `bookings/booking-helpers.php` - RESTAURANT_HOURS constant (~line 6)

---

## 🚀 8. Future Enhancements

### Phase 2: Advanced Features
1. ✨ Dynamic availability calendar (show booked times)
2. ✨ Booking modification (change time slots)
3. ✨ Partial cancellations (if oversold)
4. ✨ Peak hour pricing
5. ✨ Waitlist management

### Phase 3: Analytics
1. 📊 Peak hours report
2. 📊 Table utilization metrics
3. 📊 Revenue by time slot
4. 📊 Customer booking patterns

---

## 📞 Support & Troubleshooting

### Common Issues

**Issue:** "This table is already booked for the selected time slot"
- **Cause:** Overlapping booking exists in database
- **Solution:** Select a different time, table, or date
- **Verify:** Run overlap query for specific table/date

**Issue:** JavaScript validation fires but backend allows booking
- **Cause:** JS validation bypassed (e.g., browser dev tools)
- **Solution:** Backend validation catches this too
- **Verify:** Both frontend and backend validations active

**Issue:** Bookings not appearing in database
- **Cause:** Foreign key constraint or validation failure
- **Solution:** Check error logs, verify user_id and table_id exist
- **Verify:** Run INSERT statement manually

---

## 📄 File Structure Summary

```
Dinemate/
├── bookings/
│   ├── book-table.php              (Updated - Frontend form & JS)
│   ├── process-booking.php          (Updated - Backend validation)
│   ├── booking-helpers.php          (NEW - Helper functions)
│   ├── booking-confirmation.php     (May need updates for display)
│   └── my-bookings.php              (May need updates for display)
├── database/
│   ├── migrations/
│   │   └── add_time_slots.sql       (NEW - Migration script)
│   └── SQL_REFERENCE_GUIDE.sql      (NEW - Query reference)
└── includes/
    └── functions.php                (May need sanitize() for times)
```

---

## ✅ Deployment Checklist

- [ ] Back up database before running migration
- [ ] Run SQL migration script
- [ ] Update configuration constants if customizing hours
- [ ] Test all 5 test cases above
- [ ] Verify error messages display correctly
- [ ] Test on mobile device (time picker behavior)
- [ ] Check database indexes are created
- [ ] Review error logs for any issues
- [ ] Update admin booking view if exists
- [ ] Inform users of new time slot feature

---

## 🔐 Security Notes

✅ **Implemented:**
- SQL injection prevention (prepared statements)
- Input validation on both frontend and backend
- Conflict detection prevents overbooking
- User authentication required
- Hours validation prevents invalid times

⚠️ **Consider for Production:**
- Rate limiting on booking endpoint
- CSRF token protection on forms
- Audit logging for bookings
- Email notifications
- Maximum bookings per user per day

---

End of Implementation Guide
