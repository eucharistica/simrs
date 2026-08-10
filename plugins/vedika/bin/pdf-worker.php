<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Worker PDF Vedika hanya boleh dijalankan melalui PHP CLI.\n");
}

$baseDir = realpath(__DIR__ . '/../../..');
if ($baseDir === false) {
    fwrite(STDERR, "Root instalasi mLITE tidak ditemukan.\n");
    exit(1);
}

chdir($baseDir);
define('BASE_DIR', $baseDir);

// mPDF, base64 PDF INACBG, dan proses merge dapat melewati default CLI 128 MB.
// Nilai ini bisa dioverride dari Supervisor lewat MLITE_WORKER_MEMORY_LIMIT.
$workerMemoryLimit = getenv('MLITE_WORKER_MEMORY_LIMIT') ?: '512M';
ini_set('memory_limit', $workerMemoryLimit);

$templateCacheDir = BASE_DIR . '/tmp';
$templateCacheFile = $templateCacheDir . '/pdfklaim_generate.html';
if (!is_dir($templateCacheDir) || !is_writable($templateCacheDir) ||
    (file_exists($templateCacheFile) && !is_writable($templateCacheFile))) {
    fwrite(STDERR, "Folder cache template tidak writable: {$templateCacheDir}. "
        . "Sesuaikan owner/permission dengan Run User Supervisor.\n");
    exit(1);
}

$sessionDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR . 'mlite-vedika-worker';
if (!is_dir($sessionDir) && !mkdir($sessionDir, 0770, true) && !is_dir($sessionDir)) {
    fwrite(STDERR, "Folder session worker tidak dapat dibuat: {$sessionDir}\n");
    exit(1);
}

ini_set('session.save_path', $sessionDir);
$_SERVER['SCRIPT_NAME'] = '/vedika-pdf-worker.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['HTTP_HOST'] = getenv('MLITE_WORKER_HOST') ?: 'localhost';
$_SERVER['SERVER_PORT'] = getenv('MLITE_WORKER_HTTPS') === '1' ? 443 : 80;
$_SERVER['HTTPS'] = getenv('MLITE_WORKER_HTTPS') === '1' ? 'on' : 'off';

require BASE_DIR . '/config.php';
require BASE_DIR . '/systems/lib/Autoloader.php';

$core = new Systems\Admin();
$vedika = new Plugins\Vedika\Admin($core);
$vedika->init();

$options = getopt('', ['once', 'sleep::', 'max-jobs::']);
$runOnce = array_key_exists('once', $options);
$idleSleep = max(1, (int) (isset($options['sleep']) ? $options['sleep'] : 2));
$maxJobs = max(0, (int) (isset($options['max-jobs']) ? $options['max-jobs'] : 100));
$processed = 0;
$workerId = php_uname('n') . ':' . getmypid();
$activeQueueCall = false;
$fatalMemoryReserve = str_repeat('R', 2 * 1024 * 1024);

register_shutdown_function(function () use (
    &$activeQueueCall,
    &$fatalMemoryReserve,
    $core,
    $workerId
) {
    if (!$activeQueueCall) {
        return;
    }

    $lastError = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!$lastError || !in_array($lastError['type'], $fatalTypes, true)) {
        return;
    }

    // Bebaskan cadangan agar UPDATE status tetap punya ruang saat terjadi OOM.
    $fatalMemoryReserve = null;

    try {
        $pdo = $core->db()->pdo();
        $processingMessage = 'Diproses oleh ' . substr($workerId, 0, 120);
        $message = 'Worker berhenti karena fatal error: ' . $lastError['message'];
        $recover = $pdo->prepare("UPDATE mlite_vedika_pdf_queue
            SET status = CASE WHEN attempts >= 3 THEN 'failed' ELSE 'queued' END,
                message = ?,
                started_at = CASE WHEN attempts >= 3 THEN started_at ELSE NULL END,
                finished_at = CASE WHEN attempts >= 3 THEN NOW() ELSE NULL END,
                heartbeat_at = NOW()
            WHERE status = 'processing' AND message = ?");
        $recover->execute([substr($message, 0, 65000), $processingMessage]);
    } catch (Throwable $shutdownError) {
        fwrite(STDERR, 'Gagal memulihkan job setelah fatal error: '
            . $shutdownError->getMessage() . PHP_EOL);
    }
});

do {
    try {
        $activeQueueCall = true;
        $result = $vedika->processPDFQueueOnce($workerId);
        $activeQueueCall = false;
    } catch (Throwable $e) {
        $activeQueueCall = false;
        $result = [
            'status' => false,
            'idle' => false,
            'message' => $e->getMessage()
        ];
    }

    $log = [
        'time' => date('Y-m-d H:i:s'),
        'worker' => $workerId,
        'status' => !empty($result['status']),
        'idle' => !empty($result['idle']),
        'job_id' => isset($result['job_id']) ? $result['job_id'] : null,
        'no_rawat' => isset($result['no_rawat']) ? $result['no_rawat'] : null,
        'message' => isset($result['message']) ? $result['message'] : ''
    ];
    // Jangan memenuhi log Supervisor setiap beberapa detik ketika antrean kosong.
    if (empty($result['idle']) || $runOnce) {
        fwrite(STDOUT, json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    if (empty($result['idle'])) {
        $processed++;
    }

    if ($runOnce || ($maxJobs > 0 && $processed >= $maxJobs)) {
        break;
    }

    if (!empty($result['idle'])) {
        sleep($idleSleep);
    }
} while (true);

exit(0);
