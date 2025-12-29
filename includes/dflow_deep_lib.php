<?php
declare(strict_types=1);

/**
 * Finds the tshark executable path.
 */
function find_tshark(): ?string {
    $paths = [
        'C:\Program Files\Wireshark\tshark.exe',
        'C:\Program Files (x86)\Wireshark\tshark.exe',
        '/usr/bin/tshark',
        '/usr/local/bin/tshark'
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) return $path;
    }
    
    // Try system path
    $cmd = stripos(PHP_OS, 'WIN') === 0 ? 'where tshark' : 'which tshark';
    $out = shell_exec($cmd);
    if ($out) {
        $lines = explode("\n", trim($out));
        if (!empty($lines[0]) && file_exists(trim($lines[0]))) {
            return trim($lines[0]);
        }
    }
    
    return null;
}

/**
 * Performs deep analysis for a specific IP using TShark.
 */
function deep_analyze_ip(PDO $pdo, string $ip, int $durationSeconds = 10): array {
    $tshark = find_tshark();
    if (!$tshark) {
        return ['success' => false, 'error' => 'TShark not found. Please install Wireshark.'];
    }

    $tempPcap = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "dflow_deep_" . time() . "_" . str_replace('.', '_', $ip) . ".pcap";
    
    // Step 1: Capture packets for the specific IP
    $captureCmd = escapeshellarg($tshark) . " -a duration:$durationSeconds -f \"host $ip\" -w " . escapeshellarg($tempPcap);
    exec($captureCmd);

    if (!file_exists($tempPcap) || filesize($tempPcap) === 0) {
        if (file_exists($tempPcap)) unlink($tempPcap);
        return ['success' => false, 'error' => 'No packets captured for this IP in the given timeframe.'];
    }

    // Step 2: Analyze with TShark (extracting HTTP, TLS, DNS info)
    $fields = [
        'frame.number', 'frame.time', 'ip.src', 'ip.dst', 'ip.proto',
        'http.host', 'http.user_agent', 'http.request.uri',
        'tls.handshake.extensions_server_name', 'tls.handshake.certificate_reports',
        'dns.qry.name', 'dns.a'
    ];
    
    $analyzeCmd = escapeshellarg($tshark) . " -r " . escapeshellarg($tempPcap) . " -T json -e " . implode(' -e ', $fields);
    $output = [];
    exec($analyzeCmd, $output);
    $json = json_decode(implode('', $output), true);

    if (!$json) {
        unlink($tempPcap);
        return ['success' => false, 'error' => 'Analysis returned no JSON data.'];
    }

    $stmt = $pdo->prepare("INSERT INTO plugin_dflow_deep_analysis 
        (ip_address, protocol, analysis_type, detail_key, detail_value, severity) 
        VALUES (?, ?, ?, ?, ?, ?)");

    $count = 0;
    foreach ($json as $packet) {
        $layers = $packet['_source']['layers'] ?? [];
        $proto = $layers['ip.proto'][0] ?? 'Unknown';

        // Extract HTTP
        if (isset($layers['http.host'])) {
            $stmt->execute([$ip, $proto, 'HTTP', 'host', $layers['http.host'][0], 'info']);
            if (isset($layers['http.user_agent'])) {
                $stmt->execute([$ip, $proto, 'HTTP', 'user_agent', $layers['http.user_agent'][0], 'info']);
            }
            if (isset($layers['http.request.uri'])) {
                $stmt->execute([$ip, $proto, 'HTTP', 'uri', $layers['http.request.uri'][0], 'info']);
            }
            $count++;
        }

        // Extract TLS
        if (isset($layers['tls.handshake.extensions_server_name'])) {
            $stmt->execute([$ip, $proto, 'TLS', 'sni', $layers['tls.handshake.extensions_server_name'][0], 'info']);
            $count++;
        }

        // Extract DNS
        if (isset($layers['dns.qry.name'])) {
            $stmt->execute([$ip, $proto, 'DNS', 'query', $layers['dns.qry.name'][0], 'info']);
            $count++;
        }
    }

    unlink($tempPcap);
    return ['success' => true, 'count' => $count];
}
