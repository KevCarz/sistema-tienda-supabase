<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// ═══════════════════════════════════════════════════════════════
// PASO 1: Leer el body de forma segura
// ═══════════════════════════════════════════════════════════════
$rawBody = file_get_contents('php://input');

// Si el body está vacío Y es una petición POST, devolver error
if (empty($rawBody) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    echo json_encode(['ok' => false, 'error' => 'No se recibieron datos en el body']);
    exit;
}

$data = json_decode($rawBody, true);

// Si el JSON es inválido, registrar el cuerpo exacto en los logs y devolver error
if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    $errorMsg = json_last_error_msg();
    // Esto escribirá el contenido exacto que está fallando en los logs de Render
    error_log("=== ERROR JSON === Mensaje: " . $errorMsg . " | Cuerpo recibido: [" . $rawBody . "]");
    
    echo json_encode([
        'ok' => false, 
        'error' => "JSON inválido ($errorMsg). Revisa los logs del servidor para ver el dato corrupto."
    ]);
    exit;
}

if (!is_array($data)) {
    $data = [];
}

// ═══════════════════════════════════════════════════════════════
// PASO 2: Validar CSRF (modo permisivo para desarrollo)
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headers = getallheaders();
    $csrf = $headers['X-CSRF-Token'] ?? '';
    
    if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
        // Regenerar token en lugar de rechazar
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

$action = $_GET['action'] ?? '';

// ═══════════════════════════════════════════════════════════════
// PASO 3: Switch de acciones
// ═══════════════════════════════════════════════════════════════
switch ($action) {

    // ── ESTADO INICIAL (Dashboard) ──
    case 'state':
        $ventasHoy = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total FROM movimientos WHERE tipo = 'venta' AND fecha_hora::date = CURRENT_DATE")->fetch();
        $comprasHoy = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total FROM movimientos WHERE tipo = 'compra' AND fecha_hora::date = CURRENT_DATE")->fetch();
        
        $stmt = $pdo->prepare("SELECT id, nombre, stock_actual FROM productos WHERE stock_actual <= ? ORDER BY stock_actual ASC");
        $stmt->execute([LIMITE_STOCK]);
        $stockBajo = $stmt->fetchAll();
        
        $ultimas = $pdo->query("SELECT m.tipo, m.cantidad, m.total, m.fecha_hora, p.nombre as producto FROM movimientos m JOIN productos p ON m.producto_id = p.id ORDER BY m.fecha_hora DESC LIMIT 5")->fetchAll();
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
        // Validación estricta
        if (!isset($data['nombre']) || trim($data['nombre']) === '') {
            echo json_encode(['ok' => false, 'error' => 'El nombre es obligatorio. Datos recibidos: ' . json_encode($data)]);
            break;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO proveedores (nombre, contacto, telefono) VALUES (?, ?, ?)");
            $stmt->execute([
                trim($data['nombre']), 
                trim($data['contacto'] ?? ''), 
                trim($data['telefono'] ?? '')
            ]);
            echo json_encode(['ok' => true, 'csrf' => $_SESSION['csrf']]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'error' => 'Error de BD: ' . $e->getMessage()]);
        }
        break;

    // ── AGREGAR CLIENTE ─
    case 'add_cliente':
        if (!isset($data['nombre']) || trim($data['nombre']) === '') {
            echo json_encode(['ok' => false, 'error' => 'El nombre es obligatorio']);
            break;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO clientes (nombre, telefono, email) VALUES (?, ?, ?)");
            $stmt->execute([
                trim($data['nombre']), 
                trim($data['telefono'] ?? ''), 
                trim($data['email'] ?? '')
            ]);
            echo json_encode(['ok' => true, 'csrf' => $_SESSION['csrf']]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'error' => 'Error de BD: ' . $e->getMessage()]);
        }
        break;

    // ── AGREGAR PRODUCTO ──
    case 'add_producto':
        if (!isset($data['nombre']) || trim($data['nombre']) === '') {
            echo json_encode(['ok' => false, 'error' => 'El nombre del producto es obligatorio']);
            break;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO productos (nombre, descripcion, precio_venta, costo_compra, stock_actual, proveedor_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                trim($data['nombre']),
                trim($data['descripcion'] ?? ''),
                (float)($data['precio_venta'] ?? 0),
                (float)($data['costo_compra'] ?? 0),
                (int)($data['stock_actual'] ?? 0),
                !empty($data['proveedor_id']) ? (int)$data['proveedor_id'] : null
            ]);
            echo json_encode(['ok' => true, 'csrf' => $_SESSION['csrf']]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'error' => 'Error de BD: ' . $e->getMessage()]);
        }
        break;

    // ── REGISTRAR VENTA ──
    case 'venta':
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("SELECT stock_actual, precio_venta FROM productos WHERE id = ? FOR UPDATE");
            $stmt->execute([(int)$data['producto_id']]);
            $producto = $stmt->fetch();
            
            if (!$producto) {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'Producto no encontrado']);
                break;
            }
            
            $cantidad = (int)$data['cantidad'];
            if ($producto['stock_actual'] < $cantidad) {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'Stock insuficiente']);
                break;
            }
            
            $total = $cantidad * (float)$producto['precio_venta'];
            
            $stmt = $pdo->prepare("UPDATE productos SET stock_actual = stock_actual - ? WHERE id = ?");
            $stmt->execute([$cantidad, (int)$data['producto_id']]);
            
            $stmt = $pdo->prepare("INSERT INTO movimientos (tipo, producto_id, cliente_id, cantidad, valor_unitario, total) VALUES ('venta', ?, ?, ?, ?, ?)");
            $stmt->execute([
                (int)$data['producto_id'],
                !empty($data['cliente_id']) ? (int)$data['cliente_id'] : null,
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
            
            $cantidad = (int)$data['cantidad'];
            $valorUnitario = (float)$data['valor_unitario'];
            $total = $cantidad * $valorUnitario;
            
            $stmt = $pdo->prepare("UPDATE productos SET stock_actual = stock_actual + ? WHERE id = ?");
            $stmt->execute([$cantidad, (int)$data['producto_id']]);
            
            $stmt = $pdo->prepare("INSERT INTO movimientos (tipo, producto_id, proveedor_id, cantidad, valor_unitario, total) VALUES ('compra', ?, ?, ?, ?, ?)");
            $stmt->execute([
                (int)$data['producto_id'],
                !empty($data['proveedor_id']) ? (int)$data['proveedor_id'] : null,
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
        echo json_encode(['ok' => false, 'error' => 'Acción no válida: ' . $action]);
        break;
}