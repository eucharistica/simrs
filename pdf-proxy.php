<?php
// pdf-proxy.php — proxy sederhana agar PDF tampil same-origin (bypass X-Frame-Options dari host sumber)

// 1) Ambil param 'u' (URL PDF asli), validasi whitelist domain
$u = isset($_GET['u']) ? $_GET['u'] : '';
if (!$u) {
    http_response_code(400);
    echo 'Missing param u';
    exit;
}

// Whitelist domain sumber (sesuaikan jika perlu)
$allowed_hosts = [
    'rsudmatraman.my.id',
    'rsudmatraman.jakarta.go.id'
];
// Bolehkan juga path yang memang memakai subpath /webapps/berkasrawat/
$parsed = parse_url($u);
$host   = $parsed['host'] ?? '';
$path   = $parsed['path'] ?? '';

if (!in_array($host, $allowed_hosts, true) || stripos($path, '/webapps/berkasrawat/') === false) {
    http_response_code(403);
    echo 'Forbidden source';
    exit;
}

// 2) Ambil file dari sumber
$ch = curl_init($u);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_USERAGENT      => 'SIMRS-PDF-Proxy',
    // Jika host sumber butuh header tertentu, set di sini:
    // CURLOPT_HTTPHEADER     => ['Accept: application/pdf'],
]);
$data = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($code !== 200 || !$data) {
    http_response_code(404);
    echo 'PDF not found';
    exit;
}

// 3) Header respons agar bisa di-embed & dirender inline
if (function_exists('header_remove')) {
    header_remove('X-Frame-Options');
}
header('X-Frame-Options:'); // kosongkan jika ada injeksi global
header("Content-Security-Policy: frame-ancestors 'self' https://simrs.rsudmatraman.my.id https://rsudmatraman.my.id https://rsudmatraman.jakarta.go.id");

// Konten harus application/pdf + inline
header('Content-Type: application/pdf');
$filename = basename($path);
header('Content-Disposition: inline; filename="'.$filename.'"');
// (opsional) caching
// header('Cache-Control: public, max-age=3600');

echo $data;
