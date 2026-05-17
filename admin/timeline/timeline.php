<?php
require_once __DIR__ . '/../../includes/functions.php';

startAppSession();
redirect(appPath('admin/pages/admin_home.php'));
