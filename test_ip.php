<?php
function ip_in_range($ip, $range) {
    if (strpos($range, '/') === false) $range .= '/32';
    list($range, $netmask) = explode('/', $range, 2);
    
    // Se o IP for um bloco (ex: 132.255.220.0/22), extraímos o primeiro IP
    if (strpos($ip, '/') !== false) {
        list($ip, ) = explode('/', $ip, 2);
    }
    
    $range_dec = ip2long($range);
    $ip_dec = ip2long($ip);
    if ($ip_dec === false || $range_dec === false) return false;
    
    $wildcard_dec = pow(2, (32 - (int)$netmask)) - 1;
    $netmask_dec = ~ $wildcard_dec;
    return (($ip_dec & $netmask_dec) == ($range_dec & $netmask_dec));
}

$targetIp = '132.255.220.0/22';
$block = '132.255.220.0/22';
var_dump(ip_in_range($targetIp, $block)); // Deve ser true agora
?>