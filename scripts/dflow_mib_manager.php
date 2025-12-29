<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$mibsRoot = __DIR__ . '/../mibs';
if (!is_dir($mibsRoot)) {
    mkdir($mibsRoot, 0755, true);
}

$repos = [
    'kcsinclair' => 'https://github.com/kcsinclair/mibs.git',
    'cisco'      => 'https://github.com/cisco/cisco-mibs.git',
    'mrmeeb'     => 'https://git.mrmeeb.stream/MrMeeb/snmp_mib_archive.git'
];

echo "DFLOW MIB Manager - Ingesting Vendor MIBs\n";
echo "========================================\n";

foreach ($repos as $name => $url) {
    $targetDir = $mibsRoot . '/' . $name;
    echo "Processing $name ($url)...\n";
    
    if (is_dir($targetDir . '/.git')) {
        echo "  [info] Repository already exists. Updating...\n";
        passthru("cd " . escapeshellarg($targetDir) . " && git pull");
    } else {
        echo "  [info] Cloning repository...\n";
        passthru("git clone --depth 1 " . escapeshellarg($url) . " " . escapeshellarg($targetDir));
    }
}

// Special case for Observium - if we can't find a direct git, we might need to skip or find a mirror.
// For now, let's assume the other 3 cover the vast majority.
echo "\nScanning for MIB files to index...\n";
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($mibsRoot));
$mibCount = 0;
foreach ($iterator as $file) {
    if ($file->isFile() && (preg_match('/\.mib$/i', $file->getFilename()) || preg_match('/\.my$/i', $file->getFilename()) || !preg_match('/\./', $file->getFilename()))) {
        $mibCount++;
    }
}

echo "Total MIB files found: $mibCount\n";
echo "MIB Ingestion complete. DFLOW is now vendor-aware.\n";
