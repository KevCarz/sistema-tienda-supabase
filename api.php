<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

function requiredText(array $input, string $field, string $label): string
{
    $value = $input[$field] ?? null;
    if (!is_string($value) || trim($value) === '') {
        throw new InvalidArgumentException($label . ' es obligatorio.');
    }

    return trim($value);
}

function positiveInteger(array $input, string $field, string $label): int
{
    $value = filter_var($input[$field] ?? null, FILTER_VALIDATE_INT);
    if ($value === false || $value < 1) {
        throw new InvalidArgumentException($label . ' debe ser un entero mayor a 0.');
    }

    return $value;
}

function nonNegativeNumber(array $input, string $field, string $label): float
{
    $value = $input[$field] ?? null;
    if (!is_scalar($value) || !is_numeric((string)$value) || !is_finite((float)$value) || (float)$value < 0) {
        throw new InvalidArgumentException($label . ' debe ser un número mayor o igual a 0.');
    }

    return (float)$value;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawBody = file_get_contents('php://input');
$input = [];

if ($method === 'POST') {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $headers = is_array($headers) ? $headers : [];
    $headers = array_change_key_case($headers, CASE_LOWER);
    $csrf = $headers['x-csrf-token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    // Rechazar el POST con token inválido; regenerarlo y continuar permitiría saltarse la protección CSRF.
    if (!isset($_SESSION['csrf']) || !is_string($csrf) || !hash_equals($_SESSION['csrf'], $csrf)) {
        respond(['ok' => false, 'error' => 'La sesión expiró. Recarga la página e inténtalo de nuevo.'], 403);
        exit;
    }

    $decoded = json_decode($rawBody, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        respond(['ok' => false, 'error' => 'El cuerpo de la petición debe ser un JSON válido.'], 400);
        exit;
    }

    $input = $decoded;
}

$action = $_GET['action'] ?? '';
$writeActions = ['venta', 'compra', 'add_producto', 'add_cliente', 'add_proveedor'];

if (in_array($action, $writeActions, true) && $method !== 'POST') {
    respond(['ok' => false, 'error' => 'Esta acción requiere una petición POST.'], 405);
    exit;
}

try {
    switch ($action) {
        case 'state':
            $stmt = $pdo->query("SELECT COUNT(*) AS count, COALESCE(SUM(total), 0) AS total FROM movimientos WHERE tipo = 'venta' AND fecha_hora::date = CURRENT_DATE");
            $ventasHoy = $stmt->fetch();

            $stmt = $pdo->query("SELECT COUNT(*) AS count, COALESCE(SUM(total), 0) AS total FROM movimientos WHERE tipo = 'compra' AND fecha_hora::date = CURRENT_DATE");
            $comprasHoy = $stmt->fetch();

            $stmt = $pdo->prepare('SELECT id, nombre, stock_actual FROM productos WHERE stock_actual <= $1 ORDER BY stock_actual ASC');
            $stmt->execute([LIMITE_STOCK]);
            $stockBajo = $stmt->fetchAll();

            $stmt = $pdo->query('SELECT m.tipo, m.cantidad, m.total, m.fecha_hora, p.nombre AS producto FROM movimientos m JOIN productos p ON m.producto_id = p.id ORDER BY m.fecha_hora DESC LIMIT 5');
            $ultimas = $stmt->fetchAll();

            $topProductos = $pdo->query('SELECT * FROM vista_top_productos')->fetchAll();
            $ventasSemana = $pdo->query('SELECT * FROM vista_ventas_semana')->fetchAll();

            respond([
                'ok' => true,
                'csrf' => $_SESSION['csrf'],
                'resumen' => [
                    'ventas_hoy_count' => (int)$ventasHoy['count'],
                    'ventas_hoy_total' => (float)$ventasHoy['total'],
                    'compras_hoy_count' => (int)$comprasHoy['count'],
                    'compras_hoy_total' => (float)$comprasHoy['total'],
                    'stock_bajo' => $stockBajo,
                    'ultimas' => $ultimas,
                    'limite_stock' => LIMITE_STOCK,
                ],
                'top_productos' => $topProductos,
                'ventas_semana' => $ventasSemana,
                'clientes' => $pdo->query('SELECT id, nombre FROM clientes ORDER BY nombre')->fetchAll(),
                'proveedores' => $pdo->query('SELECT id, nombre FROM proveedores ORDER BY nombre')->fetchAll(),
                'productos' => $pdo->query('SELECT id, nombre, descripcion, precio_venta, costo_compra, stock_actual FROM productos ORDER BY nombre')->fetchAll(),
            ]);
            break;

        case 'venta':
            $productoId = positiveInteger($input, 'producto_id', 'El producto');
            $clienteId = positiveInteger($input, 'cliente_id', 'El cliente');
            $cantidad = positiveInteger($input, 'cantidad', 'La cantidad');

            $pdo->beginTransaction();
            try {
                // Bloquear el producto evita que dos ventas simultáneas descuenten el mismo stock.
                $stmt = $pdo->prepare('SELECT precio_venta, stock_actual FROM productos WHERE id = $1 FOR UPDATE');
                $stmt->execute([$productoId]);
                $producto = $stmt->fetch();

                if (!$producto || (int)$producto['stock_actual'] < $cantidad) {
                    throw new DomainException('Stock insuficiente para realizar esta venta.');
                }

                $precio = (float)$producto['precio_venta'];
                $total = $precio * $cantidad;
                $stmt = $pdo->prepare("INSERT INTO movimientos (tipo, producto_id, cliente_id, cantidad, valor_unitario, total) VALUES ('venta', $1, $2, $3, $4, $5)");
                $stmt->execute([$productoId, $clienteId, $cantidad, $precio, $total]);

                $stmt = $pdo->prepare('UPDATE productos SET stock_actual = stock_actual - $1 WHERE id = $2');
                $stmt->execute([$cantidad, $productoId]);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }

            respond(['ok' => true, 'csrf' => $_SESSION['csrf']]);
            break;

        case 'compra':
            $productoId = positiveInteger($input, 'producto_id', 'El producto');
            $proveedorId = positiveInteger($input, 'proveedor_id', 'El proveedor');
            $cantidad = positiveInteger($input, 'cantidad', 'La cantidad');
            $costo = nonNegativeNumber($input, 'costo_unitario', 'El costo unitario');

            $pdo->beginTransaction();
            try {
                $total = $costo * $cantidad;
                $stmt = $pdo->prepare("INSERT INTO movimientos (tipo, producto_id, proveedor_id, cantidad, valor_unitario, total) VALUES ('compra', $1, $2, $3, $4, $5)");
                $stmt->execute([$productoId, $proveedorId, $cantidad, $costo, $total]);

                $stmt = $pdo->prepare('UPDATE productos SET stock_actual = stock_actual + $1, costo_compra = $2 WHERE id = $3');
                $stmt->execute([$cantidad, $costo, $productoId]);
                if ($stmt->rowCount() === 0) {
                    throw new DomainException('No se encontró el producto seleccionado.');
                }

                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }

            respond(['ok' => true, 'csrf' => $_SESSION['csrf']]);
            break;

        case 'add_producto':
            $nombre = requiredText($input, 'nombre', 'El nombre del producto');
            $precioVenta = nonNegativeNumber($input, 'precio_venta', 'El precio de venta');
            $costoCompra = nonNegativeNumber($input, 'costo_compra', 'El costo de compra');
            $stock = $input['stock_actual'] ?? 0;
            $stock = filter_var($stock, FILTER_VALIDATE_INT);
            if ($stock === false || $stock < 0) {
                throw new InvalidArgumentException('El stock inicial debe ser un entero mayor o igual a 0.');
            }

            $proveedorId = null;
            if (isset($input['proveedor_id']) && $input['proveedor_id'] !== '') {
                $proveedorId = positiveInteger($input, 'proveedor_id', 'El proveedor');
            }

            $stmt = $pdo->prepare('INSERT INTO productos (nombre, descripcion, precio_venta, costo_compra, stock_actual, proveedor_id) VALUES ($1, $2, $3, $4, $5, $6)');
            $stmt->execute([
                $nombre,
                is_string($input['descripcion'] ?? null) ? trim($input['descripcion']) : '',
                $precioVenta,
                $costoCompra,
                $stock,
                $proveedorId,
            ]);

            respond(['ok' => true, 'csrf' => $_SESSION['csrf']]);
            break;

        case 'add_cliente':
            $nombre = requiredText($input, 'nombre', 'El nombre');
            $telefono = $input['telefono'] ?? '';
            $email = $input['email'] ?? '';
            if (!is_string($telefono) || !is_string($email)) {
                throw new InvalidArgumentException('El teléfono y el email deben ser texto.');
            }
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('El email no tiene un formato válido.');
            }

            $stmt = $pdo->prepare('INSERT INTO clientes (nombre, telefono, email) VALUES ($1, $2, $3)');
            $stmt->execute([$nombre, trim($telefono), trim($email)]);
            respond(['ok' => true, 'csrf' => $_SESSION['csrf']]);
            break;

        case 'add_proveedor':
            $nombre = requiredText($input, 'nombre', 'El nombre');
            $contacto = $input['contacto'] ?? '';
            $telefono = $input['telefono'] ?? '';
            if (!is_string($contacto) || !is_string($telefono)) {
                throw new InvalidArgumentException('El contacto y el teléfono deben ser texto.');
            }

            $stmt = $pdo->prepare('INSERT INTO proveedores (nombre, contacto, telefono) VALUES ($1, $2, $3)');
            $stmt->execute([$nombre, trim($contacto), trim($telefono)]);
            respond(['ok' => true, 'csrf' => $_SESSION['csrf']]);
            break;

        case 'movimientos':
            $stmt = $pdo->query(
                'SELECT m.tipo, m.cantidad, m.valor_unitario, m.total, m.fecha_hora, p.nombre AS producto, c.nombre AS cliente, pr.nombre AS proveedor
                 FROM movimientos m
                 JOIN productos p ON m.producto_id = p.id
                 LEFT JOIN clientes c ON m.cliente_id = c.id
                 LEFT JOIN proveedores pr ON m.proveedor_id = pr.id
                 ORDER BY m.fecha_hora DESC
                 LIMIT 120'
            );
            respond(['ok' => true, 'movimientos' => $stmt->fetchAll(), 'limite' => 120]);
            break;

        default:
            respond(['ok' => false, 'error' => 'Acción no válida.'], 404);
    }
} catch (InvalidArgumentException $error) {
    respond(['ok' => false, 'error' => $error->getMessage()], 422);
} catch (DomainException $error) {
    respond(['ok' => false, 'error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    error_log('Error en API (' . $action . '): ' . $error->getMessage());
    respond(['ok' => false, 'error' => 'No se pudo completar la operación. Inténtalo de nuevo.'], 500);
}
