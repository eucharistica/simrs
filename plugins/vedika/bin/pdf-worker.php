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

$options = getopt('', ['once', 'sleep::', 'max-jobs::', 'job-timeout::']);
$runOnce = array_key_exists('once', $options);
$idleSleep = max(1, (int) (isset($options['sleep']) ? $options['sleep'] : 2));
$maxJobs = max(0, (int) (isset($options['max-jobs']) ? $options['max-jobs'] : 100));
$jobTimeout = max(60, (int) (isset($options['job-timeout']) ? $options['job-timeout'] : (getenv('MLITE_PDF_JOB_TIMEOUT') ?: 1200)));
$processed = 0;
$workerId = php_uname('n') . ':' . getmypid();
$activeQueueCall = false;
$stopRequested = false;
$watchdogTriggered = false;
$fatalMemoryReserve = str_repeat('R', 2 * 1024 * 1024);

// Supervisor stop/restart mengirim SIGTERM. Tangkap sinyal agar job aktif
// diselesaikan dulu, lalu worker keluar dengan rapi sebelum Supervisor start lagi.
if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    $signalHandler = function ($signal) use (&$stopRequested) {
        $stopRequested = true;
        fwrite(STDOUT, json_encode([
            'time' => date('Y-m-d H:i:s'),
            'status' => true,
            'message' => 'Sinyal stop diterima; menyelesaikan job aktif sebelum keluar',
            'signal' => $signal
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    };
    pcntl_signal(SIGTERM, $signalHandler);
    pcntl_signal(SIGINT, $signalHandler);

    // Watchdog per job. Jika mPDF/HTTP/merge tidak kembali dalam batas waktu,
    // lempar exception agar job dikembalikan ke queue dan worker keluar.
    if (defined('SIGALRM')) {
        pcntl_signal(SIGALRM, function () use (&$watchdogTriggered, &$stopRequested, $jobTimeout) {
            $watchdogTriggered = true;
            $stopRequested = true;
            throw new RuntimeException('VEDIKA_WATCHDOG_TIMEOUT: proses PDF melebihi ' . $jobTimeout . ' detik');
        });
    }
}

register_shutdown_function(function () use (
    &$activeQueueCall,
    &$fatalMemoryReserve,
    $core,
    $vedika,
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
        $pdo = $vedika->getVedikaLogPdo();
        $processingMessage = 'Diproses oleh ' . substr($workerId, 0, 120);
        $message = 'Worker berhenti karena fatal error: ' . $lastError['message'];
        $recover = $pdo->prepare("UPDATE mlite_vedika_pdf_queue_log
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
    $watchdogTriggered = false;
    try {
        $activeQueueCall = true;
        if (function_exists('pcntl_alarm') && defined('SIGALRM')) {
            pcntl_alarm($jobTimeout);
        }
        $result = $vedika->processPDFQueueOnce($workerId);
    } catch (Throwable $e) {
        $result = [
            'status' => false,
            'idle' => false,
            'message' => $e->getMessage()
        ];
    } finally {
        if (function_exists('pcntl_alarm') && defined('SIGALRM')) {
            pcntl_alarm(0);
        }
        $activeQueueCall = false;
    }

    if ($watchdogTriggered) {
        // Setelah timeout, jangan pakai proses PHP yang sama lagi. Supervisor
        // akan menjalankan worker baru sehingga state mPDF/Ghostscript bersih.
        $stopRequested = true;
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

    if ($stopRequested || $runOnce || ($maxJobs > 0 && $processed >= $maxJobs)) {
        break;
    }

    if (!empty($result['idle'])) {
        sleep($idleSleep);
    }
} while (true);

exit(0);
