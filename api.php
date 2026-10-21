<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

function requiredText(array $data, string $field, string $label): string
{
    $value = $data[$field] ?? null;
    if (!is_string($value) || trim($value) === '') {
        throw new InvalidArgumentException($label . ' es obligatorio.');
    }

    return trim($value);
}

function optionalText(array $data, string $field, string $label): string
{
    $value = $data[$field] ?? '';
    if (!is_string($value)) {
        throw new InvalidArgumentException($label . ' debe ser texto.');
    }

    return trim($value);
}

function positiveInteger(array $data, string $field, string $label): int
{
    $rawValue = $data[$field] ?? null;
    if (!is_int($rawValue) && !is_string($rawValue)) {
        throw new InvalidArgumentException($label . ' debe ser un entero mayor a 0.');
    }

    $value = filter_var($rawValue, FILTER_VALIDATE_INT);
    if ($value === false || $value < 1) {
        throw new InvalidArgumentException($label . ' debe ser un entero mayor a 0.');
    }

    return $value;
}

function nonNegativeNumber(array $data, string $field, string $label): float
{
    $value = $data[$field] ?? null;
    if ((!is_string($value) && !is_int($value) && !is_float($value))
        || !is_numeric((string)$value)
        || !is_finite((float)$value)
        || (float)$value < 0) {
        throw new InvalidArgumentException($label . ' debe ser un número mayor o igual a 0.');
    }

    return (float)$value;
}

function rollbackIfActive(PDO $pdo): void
{
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';
$writeActions = ['venta', 'compra', 'add_producto', 'add_cliente', 'add_proveedor'];
$readActions = ['state', 'movimientos'];

if (!is_string($action) || $action === '') {
    respond(['ok' => false, 'error' => 'Debes indicar una acción válida.'], 400);
    exit;
}

// Evita responder con HTTP 200 y un cuerpo vacío ante acciones no implementadas.
if (!in_array($action, array_merge($writeActions, $readActions), true)) {
    respond(['ok' => false, 'error' => 'Debes indicar una acción válida.'], 400);
    exit;
}

if (!in_array($method, ['GET', 'POST'], true)
    || (in_array($action, $writeActions, true) && $method !== 'POST')
    || (in_array($action, $readActions, true) && $method !== 'GET')) {
    respond(['ok' => false, 'error' => 'Método HTTP no permitido para esta acción.'], 405);
    exit;
}

$data = [];
if ($method === 'POST') {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $headers = is_array($headers) ? array_change_key_case($headers, CASE_LOWER) : [];
    $csrf = $headers['x-csrf-token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (!isset($_SESSION['csrf']) || !is_string($csrf) || !hash_equals($_SESSION['csrf'], $csrf)) {
        respond(['ok' => false, 'error' => 'La sesión expiró. Recarga la página e inténtalo de nuevo.'], 403);
        exit;
    }

    $rawBody = file_get_contents('php://input');
    $decoded = json_decode($rawBody, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        respond(['ok' => false, 'error' => 'El cuerpo de la petición debe ser un JSON válido.'], 400);
        exit;
    }
    $data = $decoded;
}

try {
    switch ($action) {
        case 'state':
            $ventasHoy = $pdo->query("SELECT COUNT(*) AS count, COALESCE(SUM(total), 0) AS total FROM movimientos WHERE tipo = 'venta' AND fecha_hora::date = CURRENT_DATE")->fetch();
            $comprasHoy = $pdo->query("SELECT COUNT(*) AS count, COALESCE(SUM(total), 0) AS total FROM movimientos WHERE tipo = 'compra' AND fecha_hora::date = CURRENT_DATE")->fetch();

            $stmt = $pdo->prepare('SELECT id, nombre, stock_actual FROM productos WHERE stock_actual <= ? ORDER BY stock_actual ASC');
            $stmt->execute([LIMITE_STOCK]);
            $stockBajo = $stmt->fetchAll();

            $ultimas = $pdo->query('SELECT m.tipo, m.cantidad, m.total, m.fecha_hora, p.nombre AS producto FROM movimientos m JOIN productos p ON m.producto_id = p.id ORDER BY m.fecha_hora DESC LIMIT 5')->fetchAll();

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
                'top_productos' => $pdo->query('SELECT * FROM vista_top_productos')->fetchAll(),
                'ventas_semana' => $pdo->query('SELECT * FROM vista_ventas_semana')->fetchAll(),
                'clientes' => $pdo->query('SELECT id, nombre FROM clientes ORDER BY nombre')->fetchAll(),
                'proveedores' => $pdo->query('SELECT id, nombre FROM proveedores ORDER BY nombre')->fetchAll(),
                'productos' => $pdo->query('SELECT id, nombre, descripcion, precio_venta, costo_compra, stock_actual FROM productos ORDER BY nombre')->fetchAll(),
            ]);
            break;

        case 'add_cliente':
            $nombre = requiredText($data, 'nombre', 'El nombre');
            $telefono = optionalText($data, 'telefono', 'El teléfono');
            $email = optionalText($data, 'email', 'El email');
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('El email no tiene un formato válido.');
            }

            $stmt = $pdo->prepare('INSERT INTO clientes (nombre, telefono, email) VALUES (?, ?, ?)');
            $stmt->execute([$nombre, $telefono, $email]);
            respond(['ok' => true, 'csrf' => $_SESSION['csrf']]);
            break;

        case 'add_proveedor':
            $stmt = $pdo->prepare('INSERT INTO proveedores (nombre, contacto, telefono) VALUES (?, ?, ?)');
            $stmt->execute([
                requiredText($data, 'nombre', 'El nombre'),
                optionalText($data, 'contacto', 'El contacto'),
                optionalText($data, 'telefono', 'El teléfono'),
            ]);
            respond(['ok' => true, 'csrf' => $_SESSION['csrf']]);
            break;

        case 'add_producto':
            $nombre = requiredText($data, 'nombre', 'El nombre del producto');
            $precioVenta = nonNegativeNumber($data, 'precio_venta', 'El precio de venta');
            $costoCompra = nonNegativeNumber($data, 'costo_compra', 'El costo de compra');
            $rawStock = $data['stock_actual'] ?? 0;
            $stock = (is_int($rawStock) || is_string($rawStock))
                ? filter_var($rawStock, FILTER_VALIDATE_INT)
                : false;
            if ($stock === false || $stock < 0) {
                throw new InvalidArgumentException('El stock inicial debe ser un entero mayor o igual a 0.');
            }

            $proveedorId = null;
            if (isset($data['proveedor_id']) && $data['proveedor_id'] !== '') {
                $proveedorId = positiveInteger($data, 'proveedor_id', 'El proveedor');
            }

            $stmt = $pdo->prepare('INSERT INTO productos (nombre, descripcion, precio_venta, costo_compra, stock_actual, proveedor_id) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $nombre,
                optionalText($data, 'descripcion', 'La descripción'),
                $precioVenta,
                $costoCompra,
                $stock,
                $proveedorId,
            ]);
            respond(['ok' => true, 'csrf' => $_SESSION['csrf']]);
            break;

        case 'venta':
            $productoId = positiveInteger($data, 'producto_id', 'El producto');
            $clienteId = positiveInteger($data, 'cliente_id', 'El cliente');
            $cantidad = positiveInteger($data, 'cantidad', 'La cantidad');

            $pdo->beginTransaction();
            try {
                // El bloqueo evita que ventas simultáneas descuenten las mismas unidades.
                $stmt = $pdo->prepare('SELECT stock_actual, precio_venta FROM productos WHERE id = ? FOR UPDATE');
                $stmt->execute([$productoId]);
                $producto = $stmt->fetch();

                if (!$producto || (int)$producto['stock_actual'] < $cantidad) {
                    throw new DomainException('No se encontró el producto o no hay stock suficiente.');
                }

                $precio = (float)$producto['precio_venta'];
                $total = round($cantidad * $precio, 2);
                if (!is_finite($total)) {
                    throw new InvalidArgumentException('El total de la venta excede el valor permitido.');
                }
                $stmt = $pdo->prepare('UPDATE productos SET stock_actual = stock_actual - ? WHERE id = ?');
                $stmt->execute([$cantidad, $productoId]);

                $stmt = $pdo->prepare("INSERT INTO movimientos (tipo, producto_id, cliente_id, cantidad, valor_unitario, total) VALUES ('venta', ?, ?, ?, ?, ?)");
                $stmt->execute([$productoId, $clienteId, $cantidad, $precio, $total]);
                $pdo->commit();
            } catch (Throwable $error) {
                rollbackIfActive($pdo);
                throw $error;
            }

            respond(['ok' => true, 'csrf' => $_SESSION['csrf']]);
            break;

        case 'compra':
            $productoId = positiveInteger($data, 'producto_id', 'El producto');
            $proveedorId = positiveInteger($data, 'proveedor_id', 'El proveedor');
            $cantidad = positiveInteger($data, 'cantidad', 'La cantidad');
            $costoUnitario = nonNegativeNumber($data, 'costo_unitario', 'El costo unitario');

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('SELECT id FROM productos WHERE id = ? FOR UPDATE');
                $stmt->execute([$productoId]);
                if (!$stmt->fetch()) {
                    throw new DomainException('No se encontró el producto seleccionado.');
                }

                $total = round($cantidad * $costoUnitario, 2);
                if (!is_finite($total)) {
                    throw new InvalidArgumentException('El total de la compra excede el valor permitido.');
                }
                $stmt = $pdo->prepare('UPDATE productos SET stock_actual = stock_actual + ?, costo_compra = ? WHERE id = ?');
                $stmt->execute([$cantidad, $costoUnitario, $productoId]);

                $stmt = $pdo->prepare("INSERT INTO movimientos (tipo, producto_id, proveedor_id, cantidad, valor_unitario, total) VALUES ('compra', ?, ?, ?, ?, ?)");
                $stmt->execute([$productoId, $proveedorId, $cantidad, $costoUnitario, $total]);
                $pdo->commit();
            } catch (Throwable $error) {
                rollbackIfActive($pdo);
                throw $error;
            }

            respond(['ok' => true, 'csrf' => $_SESSION['csrf']]);
            break;

        case 'movimientos':
            // ── Parámetros de filtro (todos opcionales) ──
            foreach (['search', 'tipo', 'fecha_inicio', 'fecha_fin'] as $filter) {
                if (isset($_GET[$filter]) && !is_string($_GET[$filter])) {
                    throw new InvalidArgumentException('Los filtros de búsqueda deben ser texto.');
                }
            }

            $search = trim($_GET['search'] ?? '');
            $tipo = trim($_GET['tipo'] ?? '');
            $fechaInicio = trim($_GET['fecha_inicio'] ?? '');
            $fechaFin = trim($_GET['fecha_fin'] ?? '');

            if ($tipo !== '' && !in_array($tipo, ['venta', 'compra'], true)) {
                throw new InvalidArgumentException('El tipo debe ser venta o compra.');
            }

            foreach ([$fechaInicio, $fechaFin] as $dateValue) {
                if ($dateValue === '') {
                    continue;
                }

                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
                if (!$date || $date->format('Y-m-d') !== $dateValue) {
                    throw new InvalidArgumentException('Las fechas deben tener el formato AAAA-MM-DD.');
                }
            }
            if ($fechaInicio !== '' && $fechaFin !== '' && $fechaInicio > $fechaFin) {
                throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la fecha final.');
            }

            $limite = isset($_GET['limite']) ? filter_var($_GET['limite'], FILTER_VALIDATE_INT) : 120;
            
            // Validar límite (máximo 500 para no saturar)
            if ($limite === false || $limite < 1 || $limite > 500) {
                $limite = 120;
            }

            // ─ Construcción dinámica de la consulta ─
            $sql = 'SELECT m.id, m.tipo, m.cantidad, m.valor_unitario, m.total, m.fecha_hora, 
                           p.nombre AS producto, c.nombre AS cliente, pr.nombre AS proveedor
                    FROM movimientos m
                    JOIN productos p ON m.producto_id = p.id
                    LEFT JOIN clientes c ON m.cliente_id = c.id
                    LEFT JOIN proveedores pr ON m.proveedor_id = pr.id
                    WHERE 1=1';
            
            $params = [];
            $paramIndex = 1;

            // Filtro por tipo (venta o compra)
            if ($tipo !== '') {
                $sql .= " AND m.tipo = ?";
                $params[] = $tipo;
            }

            // Filtro por rango de fechas
            if ($fechaInicio !== '') {
                $sql .= " AND m.fecha_hora::date >= ?";
                $params[] = $fechaInicio;
            }
            if ($fechaFin !== '') {
                $sql .= " AND m.fecha_hora::date <= ?";
                $params[] = $fechaFin;
            }

            // Filtro por texto (busca en producto, cliente o proveedor)
            if ($search !== '') {
                $sql .= " AND (LOWER(p.nombre) LIKE ? OR LOWER(c.nombre) LIKE ? OR LOWER(pr.nombre) LIKE ?)";
                $searchLower = '%' . strtolower($search) . '%';
                $params[] = $searchLower;
                $params[] = $searchLower;
                $params[] = $searchLower;
            }

            // Ordenamiento y límite
            $sql .= " ORDER BY m.fecha_hora DESC LIMIT ?";
            $params[] = $limite;

            // ─ Ejecución segura con prepared statement ──
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $movimientos = $stmt->fetchAll();

            // ── Respuesta incluyendo los filtros aplicados ─
            respond([
                'ok' => true,
                'movimientos' => $movimientos,
                'filtros_aplicados' => [
                    'search' => $search,
                    'tipo' => $tipo,
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin,
                    'limite' => $limite
                ],
                'total_resultados' => count($movimientos)
            ]);
            break;
    }
} catch (InvalidArgumentException | DomainException $error) {
    respond(['ok' => false, 'error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    error_log('Error en API (' . $action . '): ' . $error->getMessage());
    respond(['ok' => false, 'error' => 'No se pudo completar la operación. Inténtalo de nuevo.'], 500);
}
