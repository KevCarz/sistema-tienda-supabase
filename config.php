<?php
declare(strict_types=1);
session_start();

define('APP_NAME', 'Mi Tienda App');
define('CURRENCY', 'Bs. ');
define('LIMITE_STOCK', 5);

// Leer credenciales desde variables de entorno (Render) o usar valores por defecto (local)
$db_host = getenv('DB_HOST') ?: 'db.kiodhnyvbbawjpiljykw.supabase.co';
$db_port = getenv('DB_PORT') ?: '5432';
$db_name = getenv('DB_NAME') ?: 'postgres';
$db_user = getenv('DB_USER') ?: 'postgres';
$db_pass = getenv('DB_PASS') ?: '44.MBH@@PGzac2K';

try {
    $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name;";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die(json_encode(['ok' => false, 'error' => 'Error de conexión: ' . $e->getMessage()]));
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}