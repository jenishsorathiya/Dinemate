<?php
// Notification provider configuration.
// Copy this file to set live values when deploying.

if (!defined('NOTIFICATION_EMAIL_FROM')) {
    define('NOTIFICATION_EMAIL_FROM', 'no-reply@dinemate.local');
}

// Twilio configuration for SMS delivery.
// To enable SMS, set these values to valid credentials.
if (!defined('TWILIO_ACCOUNT_SID')) {
    define('TWILIO_ACCOUNT_SID', '');
}

if (!defined('TWILIO_AUTH_TOKEN')) {
    define('TWILIO_AUTH_TOKEN', '');
}

if (!defined('TWILIO_FROM_NUMBER')) {
    define('TWILIO_FROM_NUMBER', '');
}
