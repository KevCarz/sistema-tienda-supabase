<?php
declare(strict_types=1);
session_start();

define('APP_NAME', 'Mi Tienda App');
define('CURRENCY', 'Bs. ');
define('LIMITE_STOCK', 5);

// Reemplaza estos valores con tus credenciales reales de Supabase
$db_host = 'TU_HOST_SUPABASE.supabase.co';
$db_port = '5432';
$db_name = 'postgres';
$db_user = 'postgres.TU_PROYECTO_ID';
$db_pass = 'TU_CONTRASEÑA';

try {
    $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name;";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die(json_encode(['ok' => false, 'error' => 'Error de conexión']));
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}