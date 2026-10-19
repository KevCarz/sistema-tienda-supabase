<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Validar CSRF en todas las peticiones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headers = getallheaders();
    $csrf = $headers['X-CSRF-Token'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido']);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
                case 'state':
            // 1. Ventas de hoy
            $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total FROM movimientos WHERE tipo = 'venta' AND fecha_hora::date = CURRENT_DATE");
            $ventasHoy = $stmt->fetch();
            
            // 2. Compras de hoy (NUEVO: necesario para calcular el balance del día)
            $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total FROM movimientos WHERE tipo = 'compra' AND fecha_hora::date = CURRENT_DATE");
            $comprasHoy = $stmt->fetch();
            
            // 3. Stock bajo
            $stmt = $pdo->prepare("SELECT id, nombre, stock_actual FROM productos WHERE stock_actual <= $1 ORDER BY stock_actual ASC");
            $stmt->execute([LIMITE_STOCK]);
            $stockBajo = $stmt->fetchAll();
            
            // 4. Últimas transacciones
            $stmt = $pdo->query("SELECT m.tipo, m.cantidad, m.total, m.fecha_hora, p.nombre as producto FROM movimientos m JOIN productos p ON m.producto_id = p.id ORDER BY m.fecha_hora DESC LIMIT 5");
            $ultimas = $stmt->fetchAll();
            
            // 5. Reportes Gerenciales (Vistas SQL creadas anteriormente)
            $topProductos = $pdo->query("SELECT * FROM vista_top_productos")->fetchAll();
            $ventasSemana = $pdo->query("SELECT * FROM vista_ventas_semana")->fetchAll();
            
            // 6. Respuesta JSON al frontend
            echo json_encode([
                'ok' => true, 
                'csrf' => $_SESSION['csrf'],
                'resumen' => [
                    'ventas_hoy_count' => (int)$ventasHoy['count'],
                    'ventas_hoy_total' => (float)$ventasHoy['total'],
                    'compras_hoy_count' => (int)$comprasHoy['count'],   // ← AGREGADO
                    'compras_hoy_total' => (float)$comprasHoy['total'], // ← AGREGADO
                    'stock_bajo' => $stockBajo,
                    'ultimas' => $ultimas,
                    'limite_stock' => LIMITE_STOCK
                ],
                'top_productos' => $topProductos,
                'ventas_semana' => $ventasSemana,
                'clientes' => $pdo->query("SELECT id, nombre FROM clientes ORDER BY nombre")->fetchAll(),
                'proveedores' => $pdo->query("SELECT id, nombre FROM proveedores ORDER BY nombre")->fetchAll(),
                'productos' => $pdo->query("SELECT id, nombre, descripcion, precio_venta, costo_compra, stock_actual FROM productos ORDER BY nombre")->fetchAll()
            ]);
            break;

        case 'venta':
            $pdo->beginTransaction();
            try {
                // FOR UPDATE funciona igual en PostgreSQL y es crucial para evitar race conditions
                $stmt = $pdo->prepare("SELECT precio_venta, stock_actual FROM productos WHERE id = $1 FOR UPDATE");
                $stmt->execute([$input['producto_id']]);
                $prod = $stmt->fetch();
                
                if (!$prod || $prod['stock_actual'] < $input['cantidad']) {
                    throw new Exception('Stock insuficiente para realizar esta venta.');
                }
                
                $total = (float)$prod['precio_venta'] * (int)$input['cantidad'];
                
                $pdo->prepare("INSERT INTO movimientos (tipo, producto_id, cliente_id, cantidad, valor_unitario, total) VALUES ('venta', $1, $2, $3, $4, $5)")
                    ->execute([$input['producto_id'], $input['cliente_id'], $input['cantidad'], $prod['precio_venta'], $total]);
                
                $pdo->prepare("UPDATE productos SET stock_actual = stock_actual - $1 WHERE id = $2")
                    ->execute([$input['cantidad'], $input['producto_id']]);
                
                $pdo->commit();
                echo json_encode(['ok' => true, 'csrf' => $_SESSION['csrf']]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'compra':
            $pdo->beginTransaction();
            try {
                $total = (float)$input['costo_unitario'] * (int)$input['cantidad'];
                
                $pdo->prepare("INSERT INTO movimientos (tipo, producto_id, proveedor_id, cantidad, valor_unitario, total) VALUES ('compra', $1, $2, $3, $4, $5)")
                    ->execute([$input['producto_id'], $input['proveedor_id'], $input['cantidad'], $input['costo_unitario'], $total]);
                
                $pdo->prepare("UPDATE productos SET stock_actual = stock_actual + $1, costo_compra = $2 WHERE id = $3")
                    ->execute([$input['cantidad'], $input['costo_unitario'], $input['producto_id']]);
                
                $pdo->commit();
                echo json_encode(['ok' => true, 'csrf' => $_SESSION['csrf']]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'add_producto':
            $pdo->prepare("INSERT INTO productos (nombre, descripcion, precio_venta, costo_compra, stock_actual, proveedor_id) VALUES ($1, $2, $3, $4, $5, $6)")
                ->execute([$input['nombre'], $input['descripcion'] ?? '', $input['precio_venta'], $input['costo_compra'], $input['stock_actual'] ?? 0, $input['proveedor_id'] ?: null]);
            echo json_encode(['ok' => true, 'csrf' => $_SESSION['csrf']]);
            break;

        case 'add_cliente':
            $pdo->prepare("INSERT INTO clientes (nombre, telefono, email) VALUES ($1, $2, $3)")
                ->execute([$input['nombre'], $input['telefono'] ?? '', $input['email'] ?? '']);
            echo json_encode(['ok' => true, 'csrf' => $_SESSION['csrf']]);
            break;

        case 'add_proveedor':
            $pdo->prepare("INSERT INTO proveedores (nombre, contacto, telefono) VALUES ($1, $2, $3)")
                ->execute([$input['nombre'], $input['contacto'] ?? '', $input['telefono'] ?? '']);
            echo json_encode(['ok' => true, 'csrf' => $_SESSION['csrf']]);
            break;

        case 'movimientos':
            $stmt = $pdo->query("SELECT m.tipo, m.cantidad, m.valor_unitario, m.total, m.fecha_hora, p.nombre as producto, c.nombre as cliente, pr.nombre as proveedor 
                                 FROM movimientos m 
                                 JOIN productos p ON m.producto_id = p.id 
                                 LEFT JOIN clientes c ON m.cliente_id = c.id 
                                 LEFT JOIN proveedores pr ON m.proveedor_id = pr.id 
                                 ORDER BY m.fecha_hora DESC LIMIT 120");
            echo json_encode(['ok' => true, 'movimientos' => $stmt->fetchAll(), 'limite' => 120]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}