<?php

/**
 * Thu thập siêu dữ liệu môi trường thực thi kiểm thử (Phần C & H — Đồ án Kiểm thử phần mềm).
 * Ghi nhận Commit SHA, Branch, Tag, PHP Version, OS và trạng thái Docker K16 Testcontainers.
 */
$outputPath = $argv[1] ?? __DIR__.'/../storage/logs/test-reports/environment_metadata.json';
$dir = dirname($outputPath);
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$nullSink = PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null';
$execTrim = static function (string $cmd) use ($nullSink): string {
    $out = @shell_exec("{$cmd} {$nullSink}");

    return (is_string($out) && trim($out) !== '') ? trim($out) : 'none';
};

$mysqlProofPath = $dir.'/mysql_container_proof.json';
$mysqlProof = null;
if (file_exists($mysqlProofPath)) {
    $decoded = json_decode((string) file_get_contents($mysqlProofPath), true);
    if (is_array($decoded)) {
        $mysqlProof = $decoded;
    }
}

$metadata = [
    'student' => 'Nguyễn Thành Minh (MSSV: 2312691)',
    'partition' => 'Phân vùng 4: Users, RBAC, UserPolicy, FMCS & Auth/API Integration',
    'technique' => 'K16 Ephemeral Integration (Testcontainers MySQL 8.0.36)',
    'git_branch' => $execTrim('git rev-parse --abbrev-ref HEAD'),
    'git_commit_sha' => $execTrim('git rev-parse HEAD'),
    'git_commit_short' => $execTrim('git rev-parse --short HEAD'),
    'git_tag' => $execTrim('git describe --tags --exact-match'),
    'php_version' => PHP_VERSION,
    'os_platform' => PHP_OS_FAMILY.' ('.php_uname('s').' '.php_uname('r').')',
    'execution_mode' => $mysqlProof ? 'K16 Testcontainers (Docker MySQL '.($mysqlProof['mysql_server_version'] ?? '8.0.36').')' : 'Local PHPUnit',
    'mysql_container_proof' => $mysqlProof,
    'captured_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
];

file_put_contents(
    $outputPath,
    json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL
);

fwrite(STDOUT, "Captured environment metadata to: {$outputPath}\n");
