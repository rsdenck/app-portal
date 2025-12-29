<?php
require 'includes/bootstrap.php';
$name = 'proxmox';
$config = json_encode([
    'url' => 'https://proxmox.example.com:8006/api2/json',
    'username' => 'root@pam',
    'password' => 'password123',
    'ignore_ssl' => true
]);
$stmt = $pdo->prepare("UPDATE plugins SET is_active = 1, config = ? WHERE name = ?");
$stmt->execute([$config, $name]);
echo "Plugin $name activated and configured.\n";
