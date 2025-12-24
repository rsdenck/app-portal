<?php

function db(array $config): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $drivers = PDO::getAvailableDrivers();
    if (!in_array('mysql', $drivers, true)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        $ini = php_ini_loaded_file() ?: '(php.ini não detectado)';
        $scanned = php_ini_scanned_files() ?: '(nenhum arquivo .ini adicional)';
        exit(
            "PDO MySQL driver ausente.\n\n" .
            "Como corrigir no Windows:\n" .
            "1) Abra o php.ini carregado:\n" .
            "   - {$ini}\n" .
            "2) Habilite (remova ';'):\n" .
            "   - extension=pdo_mysql\n" .
            "   - extension=mysqli\n" .
            "3) Reinicie o serviço que está servindo o PHP (Apache/Nginx/PHP-FPM/IIS).\n\n" .
            "Diagnóstico:\n" .
            " - PHP: " . PHP_VERSION . "\n" .
            " - php.ini: {$ini}\n" .
            " - scanned .ini: {$scanned}\n" .
            " - drivers PDO: " . implode(',', $drivers) . "\n"
        );
    }

    try {
        $pdo = new PDO(
            $config['db']['dsn'],
            $config['db']['user'],
            $config['db']['pass'],
            $config['db']['options'] ?? []
        );
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        error_log("Erro de conexão PDO: " . $e->getMessage());
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        exit("Erro interno ao conectar no banco de dados. O administrador foi notificado.");
    }
    return $pdo;
}

