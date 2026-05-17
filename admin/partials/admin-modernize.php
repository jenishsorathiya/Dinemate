<?php
$adminModernizeCssVersion = (string) (@filemtime(__DIR__ . '/../../assets/css/admin-modernize.css') ?: time());
$adminRedesignCssVersion = (string) (@filemtime(__DIR__ . '/../../assets/css/admin-redesign.css') ?: time());
?>
    <link href="../../assets/css/admin-modernize.css?v=<?= htmlspecialchars($adminModernizeCssVersion, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="../../assets/css/admin-redesign.css?v=<?= htmlspecialchars($adminRedesignCssVersion, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
