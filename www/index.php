<?php

require __DIR__ . '/../src/bootstrap.php';

try {
    \Helio\Invest\App::getApp('app')->run();
} catch (Exception $e) {
    header('Content-Type: application/json', 500);
    echo json_encode([
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'prev' => $e->getTrace(),
        'stacktrace' => \Helio\Invest\Utility\ServerUtility::get('SITE_ENV') === 'PROD' ? null : $e->getTrace()
    ]);
}
