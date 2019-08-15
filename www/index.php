<?php

require __DIR__ . '/../src/bootstrap.php';

try {
    \Helio\Panel\App::getApp('app')->run();
} catch (\Throwable $e) {
    header('Content-Type: application/json', 500);
    echo json_encode([
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'prev' => $e->getTrace(),
        'stacktrace' => 'PROD' === \Helio\Panel\Utility\ServerUtility::get('SITE_ENV') ? null : $e->getTrace(),
    ]);
}
