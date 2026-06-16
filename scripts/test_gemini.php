<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    /** @var \App\Services\GeminiService $svc */
    $svc = $app->make(\App\Services\GeminiService::class);
    // Debug KB availability
    $kbPath = env('AI_KB_PATH', 'ai/nghidinh81.txt');
    $exists = \Illuminate\Support\Facades\Storage::disk('local')->exists($kbPath);
    $fullPath = null;
    try {
        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($kbPath);
    } catch (Throwable $ex) {
        $fullPath = 'n/a';
    }

    $contentLen = 0;
    if ($exists) {
        $content = \Illuminate\Support\Facades\Storage::disk('local')->get($kbPath);
        $contentLen = strlen($content);
    }

    echo json_encode([ 'kbPathEnv' => $kbPath, 'exists' => $exists, 'resolvedPath' => $fullPath, 'content_length' => $contentLen ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

    $res = $svc->askChatbotRag('Theo Điều 3, sinh viên hộ nghèo được miễn bao nhiêu phần trăm học phí?');
    echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}