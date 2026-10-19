<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// ─ Leer el body UNA SOLA VEZ ──
$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true) ?: [];

// ── Validar CSRF (modo permisivo para desarrollo) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headers = getallheaders();
    $csrf = $headers['X-CSRF-Token'] ?? '';
    if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

$action = $_GET['action'] ?? '';

switch ($action) {

    // ── ESTADO INICIAL (Dashboard) ──
    case 'state':
        $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total FROM movimientos WHERE tipo = 'venta' AND fecha_hora::date = CURRENT_DATE");
        $ventasHoy = $stmt->fetch();
        
        $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total FROM movimientos WHERE tipo = 'compra' AND fecha_hora::date = CURRENT_DATE");
        $comprasHoy = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT id, nombre, stock_actual FROM productos WHERE stock_actual <= $1 ORDER BY stock_actual ASC");
        $stmt->execute([LIMITE_STOCK]);
        $stockBajo = $stmt->fetchAll();
        
        $stmt = $pdo->query("SELECT m.tipo, m.cantidad, m.total, m.fecha_hora, p.nombre as producto FROM movimientos m JOIN productos p ON m.producto_id = p.id ORDER BY m.fecha_hora DESC LIMIT 5");
        $ultimas = $stmt->fetchAll();
        
        $topProductos = $pdo->query("SELECT * FROM vista_top_productos")->fetchAll();
        $ventasSemana = $pdo->query("SELECT * FROM vista_ventas_semana")->fetchAll();
        
        echo json_encode([
            'ok' => true, 
            'csrf' => $_SESSION['csrf'],
            'resumen' => [
                'ventas_hoy_count' => (int)$ventasHoy['count'],
                'ventas_hoy_total' => (float)$ventasHoy['total'],
                'compras_hoy_count' => (int)$comprasHoy['count'],
                'compras_hoy_total' => (float)$comprasHoy['total'],
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

    // ── AGREGAR PROVEEDOR ──
    case 'add_proveedor':
        if (empty($input['nombre'])) {
            echo json_encode(['ok' => false, 'error' => 'El nombre es obligatorio']);
            break;
        }
        $stmt = $pdo->prepare("INSERT INTO proveedores (nombre, contacto, telefono) VALUES ($1, $2, $3)");
        $stmt->execute([
            $input['nombre'], 
            $input['contacto'] ?? '', 
            $input['telefono'] ?? ''
        ]);
        echo json_encode(['ok' => true, 'csrf' => $_SESSION['csrf']]);
        break;

    // ── AGREGAR CLIENTE ──
    case 'add_cliente':
        if (empty($input['nombre'])) {
            echo json_encode(['ok' => false, 'error' => 'El nombre es obligatorio']);
            break;
        }
        $stmt = $pdo->prepare("INSERT INTO clientes (nombre, telefono, email) VALUES ($1, $2, $3)");
        $stmt->execute([
            $input['nombre'], 
            $input['telefono'] ?? '', 
            $input['email'] ?? ''
        ]);
        echo json_encode(['ok' => true, 'csrf' => $_SESSION['csrf']]);
        break;

    // ── AGREGAR PRODUCTO ─
    case 'add_producto':
        if (empty($input['nombre'])) {
            echo json_encode(['ok' => false, 'error' => 'El nombre del producto es obligatorio']);
            break;
        }
        $stmt = $pdo->prepare("INSERT INTO productos (nombre, descripcion, precio_venta, costo_compra, stock_actual, proveedor_id) VALUES ($1, $2, $3, $4, $5, $6)");
        $stmt->execute([
            $input['nombre'],
            $input['descripcion'] ?? '',
            (float)($input['precio_venta'] ?? 0),
            (float)($input['costo_compra'] ?? 0),
            (int)($input['stock_actual'] ?? 0),
            !empty($input['proveedor_id']) ? (int)$input['proveedor_id'] : null
        ]);
        echo json_encode(['ok' => true, 'csrf' => $_SESSION['csrf']]);
        break;

    // ── REGISTRAR VENTA ──
    case 'venta':
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("SELECT stock_actual, precio_venta FROM productos WHERE id = $1 FOR UPDATE");
            $stmt->execute([(int)$input['producto_id']]);
            $producto = $stmt->fetch();
            
            if (!$producto) {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'Producto no encontrado']);
                break;
            }
            
            $cantidad = (int)$input['cantidad'];
            if ($producto['stock_actual'] < $cantidad) {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'Stock insuficiente']);
                break;
            }
            
            $total = $cantidad * (float)$producto['precio_venta'];
            
            $stmt = $pdo->prepare("UPDATE productos SET stock_actual = stock_actual - $1 WHERE id = $2");
            $stmt->execute([$cantidad, (int)$input['producto_id']]);
            
            $stmt = $pdo->prepare("INSERT INTO movimientos (tipo, producto_id, cliente_id, cantidad, valor_unitario, total) VALUES ('venta', $1, $2, $3, $4, $5)");
            $stmt->execute([
                (int)$input['producto_id'],
                !empty($input['cliente_id']) ? (int)$input['cliente_id'] : null,
                $cantidad,
                (float)$producto['precio_venta'],
                $total
            ]);
            
            $pdo->commit();
            echo json_encode(['ok' => true, 'csrf' => $_SESSION['csrf']]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ── REGISTRAR COMPRA ──
    case 'compra':
        try {
            $pdo->beginTransaction();
            
            $cantidad = (int)$input['cantidad'];
            $valorUnitario = (float)$input['valor_unitario'];
            $total = $cantidad * $valorUnitario;
            
            $stmt = $pdo->prepare("UPDATE productos SET stock_actual = stock_actual + $1 WHERE id = $2");
            $stmt->execute([$cantidad, (int)$input['producto_id']]);
            
            $stmt = $pdo->prepare("INSERT INTO movimientos (tipo, producto_id, proveedor_id, cantidad, valor_unitario, total) VALUES ('compra', $1, $2, $3, $4, $5)");
            $stmt->execute([
                (int)$input['producto_id'],
                !empty($input['proveedor_id']) ? (int)$input['proveedor_id'] : null,
                $cantidad,
                $valorUnitario,
                $total
            ]);
            
            $pdo->commit();
            echo json_encode(['ok' => true, 'csrf' => $_SESSION['csrf']]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Acción no válida']);
        break;
}