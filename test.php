<?php
require_once 'config.php';

try {
    // Intentamos hacer una consulta simple a Supabase
    $stmt = $pdo->query("SELECT version()");
    $result = $stmt->fetch();
    echo "<h1>✅ ¡Conexión Exitosa!</h1>";
    echo "<p>Versión de PostgreSQL: " . htmlspecialchars($result['version']) . "</p>";
    echo "<p><a href='index.php'>Ir a la App</a></p>";
} catch (PDOException $e) {
    echo "<h1>❌ Error de Conexión</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}