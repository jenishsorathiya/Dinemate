<?php
/**
 * Booking System Helper Functions
 * Provides reusable functions for time slot validation and overlap detection
 */

// Restaurant configuration
const RESTAURANT_HOURS = [
    'open' => '10:00',
    'close' => '22:00',
    'minDuration' => 60,    // minutes
    'maxDuration' => 180    // minutes
];

/**
 * Checks if a time is within restaurant operating hours
 * 
 * @param string $time Time in HH:MM or HH:MM:SS format
 * @return bool True if time is within operating hours
 */
function isWithinRestaurantHours($time) {
    $time = date('H:i', strtotime($time));
    $open = date('H:i', strtotime(RESTAURANT_HOURS['open']));
    $close = date('H:i', strtotime(RESTAURANT_HOURS['close']));
    
    return $time >= $open && $time <= $close;
}

/**
 * Validates time slot (start time must be before end time, both within hours)
 * 
 * @param string $startTime Start time in HH:MM or HH:MM:SS format
 * @param string $endTime End time in HH:MM or HH:MM:SS format
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validateTimeSlot($startTime, $endTime) {
    // Convert to consistent format
    $startTime = date('H:i', strtotime($startTime));
    $endTime = date('H:i', strtotime($endTime));
    $restaurantOpen = date('H:i', strtotime(RESTAURANT_HOURS['open']));
    $restaurantClose = date('H:i', strtotime(RESTAURANT_HOURS['close']));
    
    // Check if within operating hours
    if ($startTime < $restaurantOpen) {
        return [
            'valid' => false,
            'error' => 'Start time cannot be before ' . RESTAURANT_HOURS['open']
        ];
    }
    
    if ($endTime > $restaurantClose) {
        return [
            'valid' => false,
            'error' => 'End time cannot be after ' . RESTAURANT_HOURS['close']
        ];
    }
    
    // Check if end time is after start time
    if ($endTime <= $startTime) {
        return [
            'valid' => false,
            'error' => 'End time must be after start time'
        ];
    }
    
    // Calculate duration
    $start = new DateTime('2000-01-01 ' . $startTime);
    $end = new DateTime('2000-01-01 ' . $endTime);
    $durationMinutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;
    
    // Check minimum duration
    if ($durationMinutes < RESTAURANT_HOURS['minDuration']) {
        return [
            'valid' => false,
            'error' => 'Booking duration must be at least ' . RESTAURANT_HOURS['minDuration'] . ' minutes'
        ];
    }
    
    // Check maximum duration
    if ($durationMinutes > RESTAURANT_HOURS['maxDuration']) {
        return [
            'valid' => false,
            'error' => 'Booking duration cannot exceed ' . RESTAURANT_HOURS['maxDuration'] . ' minutes'
        ];
    }
    
    return ['valid' => true, 'error' => null];
}

/**
 * Checks if a booking time slot conflicts with existing bookings
 * 
 * @param PDO $pdo Database connection
 * @param int $tableId Table ID
 * @param string $bookingDate Date in YYYY-MM-DD format
 * @param string $startTime Start time in HH:MM:SS format
 * @param string $endTime End time in HH:MM:SS format
 * @param int|null $excludeBookingId Optional: booking ID to exclude (for editing)
 * @return array ['conflict' => bool, 'overlappingBookings' => array]
 */
function checkBookingConflicts($pdo, $tableId, $bookingDate, $startTime, $endTime, $excludeBookingId = null) {
    $query = "
        SELECT booking_id, start_time, end_time, user_id
        FROM bookings 
        WHERE table_id = ? 
        AND booking_date = ? 
        AND status IN ('pending', 'confirmed')
        AND (
            (start_time < ? AND end_time > ?)
            OR (start_time >= ? AND start_time < ?)
            OR (end_time > ? AND end_time <= ?)
        )
    ";
    
    $params = [
        $tableId,
        $bookingDate,
        $endTime,
        $startTime,
        $startTime,
        $endTime,
        $startTime,
        $endTime
    ];
    
    if ($excludeBookingId !== null) {
        $query .= " AND booking_id != ?";
        $params[] = $excludeBookingId;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    $overlappingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'conflict' => count($overlappingBookings) > 0,
        'overlappingBookings' => $overlappingBookings
    ];
}

/**
 * Gets available tables for a specific date and time slot
 * 
 * @param PDO $pdo Database connection
 * @param string $bookingDate Date in YYYY-MM-DD format
 * @param string $startTime Start time in HH:MM:SS format
 * @param string $endTime End time in HH:MM:SS format
 * @param int|null $minCapacity Minimum table capacity (optional)
 * @param int|null $excludeBookingId Booking ID to exclude from conflict check
 * @return array List of available tables
 */
function getAvailableTables($pdo, $bookingDate, $startTime, $endTime, $minCapacity = null, $excludeBookingId = null) {
    // Get all available tables
    $query = "SELECT * FROM restaurant_tables WHERE status = 'available'";
    $params = [];
    
    if ($minCapacity !== null) {
        $query .= " AND capacity >= ?";
        $params[] = $minCapacity;
    }
    
    $query .= " ORDER BY capacity ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter out tables with conflicts
    $availableTables = [];
    foreach ($tables as $table) {
        $conflict = checkBookingConflicts($pdo, $table['table_id'], $bookingDate, $startTime, $endTime, $excludeBookingId);
        if (!$conflict['conflict']) {
            $availableTables[] = $table;
        }
    }
    
    return $availableTables;
}

/**
 * Formats time for display (12-hour format)
 * 
 * @param string $time Time in HH:MM:SS format
 * @return string Formatted time (e.g., "7:30 PM")
 */
function formatTime($time) {
    return date('g:i A', strtotime($time));
}

/**
 * Calculates booking duration in minutes
 * 
 * @param string $startTime Start time in HH:MM:SS format
 * @param string $endTime End time in HH:MM:SS format
 * @return float Duration in minutes
 */
function getBookingDuration($startTime, $endTime) {
    $start = new DateTime('2000-01-01 ' . $startTime);
    $end = new DateTime('2000-01-01 ' . $endTime);
    return ($end->getTimestamp() - $start->getTimestamp()) / 60;
}

/**
 * Converts minutes to readable format (e.g., "1 hour 30 minutes")
 * 
 * @param float $minutes Duration in minutes
 * @return string Formatted duration
 */
function formatDuration($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    $parts = [];
    if ($hours > 0) {
        $parts[] = $hours . ' hour' . ($hours > 1 ? 's' : '');
    }
    if ($mins > 0) {
        $parts[] = $mins . ' minute' . ($mins > 1 ? 's' : '');
    }
    
    return implode(' ', $parts) ?: '0 minutes';
}

?>
