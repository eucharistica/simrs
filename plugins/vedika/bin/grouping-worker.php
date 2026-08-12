<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Worker grouping Vedika hanya boleh dijalankan melalui PHP CLI.\n");
}

$baseDir = realpath(__DIR__ . '/../../..');
if ($baseDir === false) {
    fwrite(STDERR, "Root instalasi mLITE tidak ditemukan.\n");
    exit(1);
}

chdir($baseDir);
define('BASE_DIR', $baseDir);
ini_set('memory_limit', getenv('MLITE_GROUPING_MEMORY_LIMIT') ?: '512M');
set_time_limit(0);

$sessionDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR . 'mlite-vedika-grouping-worker';
if (!is_dir($sessionDir) && !mkdir($sessionDir, 0770, true) && !is_dir($sessionDir)) {
    fwrite(STDERR, "Folder session worker tidak dapat dibuat: {$sessionDir}\n");
    exit(1);
}

ini_set('session.save_path', $sessionDir);
$_SERVER['SCRIPT_NAME'] = '/vedika-grouping-worker.php';
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

    $fatalMemoryReserve = null;
    try {
        $pdo = $core->db()->pdo();
        $processingMessage = 'Diproses oleh ' . substr($workerId, 0, 120);
        $fatalMessage = substr('Worker berhenti: ' . $lastError['message'], 0, 65000);
        $recover = $pdo->prepare(
            "UPDATE mlite_vedika_grouping_queue
             SET status = CASE WHEN attempts >= 3 THEN 'failed' ELSE 'queued' END,
                 last_step = 'worker', message = ?,
                 started_at = CASE WHEN attempts >= 3 THEN started_at ELSE NULL END,
                 finished_at = CASE WHEN attempts >= 3 THEN NOW() ELSE NULL END,
                 heartbeat_at = NOW()
             WHERE status = 'processing' AND message = ?"
        );
        $recover->execute([$fatalMessage, $processingMessage]);

        $rollback = $pdo->prepare(
            "DELETE v FROM mlite_vedika v
             INNER JOIN mlite_vedika_grouping_queue q
                ON q.no_rawat = v.no_rawat AND q.nosep = v.nosep
             WHERE q.status = 'failed' AND q.message = ?
               AND v.status = q.target_status"
        );
        $rollback->execute([$fatalMessage]);
    } catch (Throwable $shutdownError) {
        fwrite(STDERR, 'Gagal memulihkan job grouping: '
            . $shutdownError->getMessage() . PHP_EOL);
    }
});

do {
    try {
        $activeQueueCall = true;
        $result = $vedika->processGroupingQueueOnce($workerId);
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
