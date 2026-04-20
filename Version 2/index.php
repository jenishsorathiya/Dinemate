<?php
declare(strict_types=1);

$distDir = __DIR__ . '/frontend/dist';
$indexFile = $distDir . '/index.html';

if (!is_file($indexFile)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Frontend build not found. Run: cd frontend && npm run build";
    exit();
}

header('Content-Type: text/html; charset=utf-8');
readfile($indexFile);
exit();

