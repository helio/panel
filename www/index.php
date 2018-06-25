<?php

require __DIR__ . '/../src/bootstrap.php';

try {
    session_start();
    Helio\Panel\App::run('app');
} catch (Exception $e) {
    header('Content-Type: application/json', 500);
    echo json_encode([
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'prev' => $e->getTrace(),
        'stacktrace' => SITE_ENV === 'PROD' ? null : $e->getTrace()
    ]);
}