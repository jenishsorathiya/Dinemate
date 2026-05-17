<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Dinemate/config/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Dinemate/includes/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Dinemate/includes/session-check.php';

requireAdmin();
redirect(appPath('admin/pages/admin_home.php'));
