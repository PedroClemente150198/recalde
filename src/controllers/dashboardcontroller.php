<?php 

namespace Controllers;
use core\controller;
use models\ventas;
use models\inventario;
use models\pedidos;
use models\historial;
use models\dashboard;
use models\usuarios;
use models\clientes;

class DashboardController extends Controller{
    public function index(){
        // Llama a la vista del dashboard
        $this->render('dashboard/index');
    }

    public function home() {
        $this->render('dashboard/home/index', $this->buildHomePayload());
    }

    public function homeData() {
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'data' => $this->buildHomePayload()
        ], JSON_UNESCAPED_UNICODE);
    }

    private function buildHomePayload(): array {
        $dashboardModel = new Dashboard();

        $ventasMes = $dashboardModel->getVentasPorMes();
        $pedidosPorEstado = $dashboardModel->getPedidosPorEstado();

        $labelsMes = [];
        $datosVentasMes = [];
        foreach ($ventasMes as $ventaMes) {
            $labelsMes[] = 'Mes ' . (int) ($ventaMes['mes'] ?? 0);
            $datosVentasMes[] = (float) ($ventaMes['total'] ?? 0);
        }

        $pedidosEstados = [];
        foreach ($pedidosPorEstado as $pedido) {
            $estado = strtolower((string) ($pedido['estado'] ?? 'desconocido'));
            $pedidosEstados[$estado] = (int) ($pedido['cantidad'] ?? 0);
        }

        return [
            "totalVentas" => (int) $dashboardModel->getTotalVentas(),
            "ingresosTotales" => (float) $dashboardModel->getIngresosTotales(),
            "totalPedidos" => (int) $dashboardModel->getTotalPedidos(),
            "topProductos" => $dashboardModel->getProductosMasVendidos(),
            "ultimasVentas" => $dashboardModel->getUltimasVentas(),
            "labelsMes" => $labelsMes,
            "datosVentasMes" => $datosVentasMes,
            "pedidosEstados" => $pedidosEstados,
            "ultimaActualizacion" => date('Y-m-d H:i:s')
        ];
    }


    
    public function ventas(){
        $ventasModel = new Ventas();
        $listadoVentas = $ventasModel->getAllVentas();
        $pedidosDisponibles = $ventasModel->getPedidosDisponiblesParaVenta();
        $this->render('dashboard/ventas/index', [
            'ventas' => $listadoVentas,
            'pedidosDisponibles' => $pedidosDisponibles
        ]);
    }

    public function ventaCrear(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idPedido = filter_input(INPUT_POST, 'id_pedido', FILTER_VALIDATE_INT);
        $totalRaw = str_replace(',', '.', trim((string) ($_POST['total'] ?? '0')));
        $metodoPago = strtolower(trim((string) ($_POST['metodo_pago'] ?? 'efectivo')));
        $usuarioRegistro = (int) ($_SESSION['usuario']['user_id'] ?? 0);

        if (!$idPedido) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Pedido inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!is_numeric($totalRaw)) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Total inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ventasModel = new Ventas();
        $idVenta = $ventasModel->crearVentaDesdePedido(
            (int) $idPedido,
            (float) $totalRaw,
            $metodoPago,
            $usuarioRegistro > 0 ? $usuarioRegistro : null
        );

        if (!$idVenta) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => $ventasModel->getLastError() ?? 'No se pudo registrar la venta.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Venta registrada correctamente.',
            'id' => (int) $idVenta
        ], JSON_UNESCAPED_UNICODE);
    }

    public function ventaActualizar(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idVenta = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $totalRaw = str_replace(',', '.', trim((string) ($_POST['total'] ?? '0')));
        $metodoPago = strtolower(trim((string) ($_POST['metodo_pago'] ?? 'efectivo')));

        if (!$idVenta) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'ID de venta inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!is_numeric($totalRaw)) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Total inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ventasModel = new Ventas();
        $actualizada = $ventasModel->updateVenta((int) $idVenta, (float) $totalRaw, $metodoPago);

        if (!$actualizada) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => $ventasModel->getLastError() ?? 'No se pudo actualizar la venta.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Venta actualizada correctamente.',
            'id' => (int) $idVenta
        ], JSON_UNESCAPED_UNICODE);
    }

    public function ventaEliminar(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idVenta = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$idVenta) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'ID de venta inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ventasModel = new Ventas();
        $eliminada = $ventasModel->deleteVenta((int) $idVenta);

        if (!$eliminada) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => $ventasModel->getLastError() ?? 'No se pudo eliminar la venta.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Venta eliminada correctamente.',
            'id' => (int) $idVenta
        ], JSON_UNESCAPED_UNICODE);
    }

    public function perfil(){
        $usuarioActual = null;

        if (isset($_SESSION['usuario']['usuario'])) {
            $usuariosModel = new Usuarios();
            $usuarioActual = $usuariosModel->getUserByUsername($_SESSION['usuario']['usuario']);
        }

        $this->render('dashboard/perfil/index', [
            'usuario' => $usuarioActual
        ]);
    }

    public function clientes(){
        $clientesModel = new Clientes();
        $listadoClientes = $clientesModel->getClientes();

        $this->render('dashboard/clientes/index', [
            'clientes' => $listadoClientes
        ]);
    }

    public function clienteCrear(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idUsuario = (int) ($_SESSION['usuario']['user_id'] ?? 0);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $apellido = trim((string) ($_POST['apellido'] ?? ''));
        $cedula = trim((string) ($_POST['cedula'] ?? ''));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $direccion = trim((string) ($_POST['direccion'] ?? ''));
        $empresa = trim((string) ($_POST['empresa'] ?? ''));

        if ($idUsuario <= 0) {
            http_response_code(401);
            echo json_encode([
                'ok' => false,
                'message' => 'Sesión inválida. Vuelve a iniciar sesión.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $clientesModel = new Clientes();
        $idCliente = $clientesModel->crearCliente(
            $idUsuario,
            $nombre,
            $apellido,
            $cedula,
            $telefono,
            $direccion,
            $empresa
        );

        if (!$idCliente) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => $clientesModel->getLastError() ?? 'No se pudo crear el cliente.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Cliente creado correctamente.',
            'id' => $idCliente
        ], JSON_UNESCAPED_UNICODE);
    }

    public function clienteEliminar(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idCliente = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$idCliente) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'ID de cliente inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $clientesModel = new Clientes();
        $eliminado = $clientesModel->eliminarCliente((int) $idCliente);

        if (!$eliminado) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => $clientesModel->getLastError() ?? 'No se pudo eliminar el cliente.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Cliente eliminado correctamente.',
            'id' => (int) $idCliente
        ], JSON_UNESCAPED_UNICODE);
    }

    public function clienteActualizar(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idCliente = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $apellido = trim((string) ($_POST['apellido'] ?? ''));
        $cedula = trim((string) ($_POST['cedula'] ?? ''));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $direccion = trim((string) ($_POST['direccion'] ?? ''));
        $empresa = trim((string) ($_POST['empresa'] ?? ''));

        if (!$idCliente) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'ID de cliente inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $clientesModel = new Clientes();
        $actualizado = $clientesModel->actualizarCliente(
            (int) $idCliente,
            $nombre,
            $apellido,
            $cedula,
            $telefono,
            $direccion,
            $empresa
        );

        if (!$actualizado) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => $clientesModel->getLastError() ?? 'No se pudo actualizar el cliente.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Cliente actualizado correctamente.',
            'id' => (int) $idCliente
        ], JSON_UNESCAPED_UNICODE);
    }

    public function pedidos(){
        $pedidosModel = new Pedidos();
        $listadoPedidos = $pedidosModel->getPedidos();
        $clientes = $pedidosModel->getClientesParaPedido();
        $productos = $pedidosModel->getProductosParaPedido();

        $this->render('dashboard/pedidos/index', [
            'pedidos' => $listadoPedidos,
            'clientes' => $clientes,
            'productos' => $productos
        ]);
    }

    public function pedidoCrear(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idCliente = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT);
        $estado = trim((string) ($_POST['estado'] ?? 'pendiente'));
        $itemsRaw = (string) ($_POST['items'] ?? '[]');
        $items = json_decode($itemsRaw, true);

        $estadosPermitidos = ['pendiente', 'procesando', 'listo', 'entregado', 'cancelado'];
        if (!$idCliente || !in_array($estado, $estadosPermitidos, true)) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Datos inválidos para crear el pedido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!is_array($items) || count($items) === 0) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Debes agregar al menos un producto al pedido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $pedidosModel = new Pedidos();
        $idPedido = $pedidosModel->crearPedidoConDetalles((int) $idCliente, $estado, $items);

        if (!$idPedido) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => $pedidosModel->getLastError() ?? 'No se pudo crear el pedido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Pedido creado correctamente.',
            'id' => $idPedido
        ], JSON_UNESCAPED_UNICODE);
    }

    public function pedidoDetalle(){
        header('Content-Type: application/json; charset=UTF-8');

        $idPedido = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$idPedido) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'ID de pedido inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $pedidosModel = new Pedidos();
        $pedido = $pedidosModel->getPedidoById($idPedido);

        if (!$pedido) {
            http_response_code(404);
            echo json_encode([
                'ok' => false,
                'message' => 'Pedido no encontrado.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $detalles = $pedidosModel->getDetallePedido($idPedido);

        echo json_encode([
            'ok' => true,
            'pedido' => $pedido,
            'detalles' => $detalles
        ], JSON_UNESCAPED_UNICODE);
    }

    public function pedidoActualizar(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idPedido = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $estado = trim((string) ($_POST['estado'] ?? ''));

        $estadosPermitidos = ['pendiente', 'procesando', 'listo', 'entregado', 'cancelado'];
        if (!$idPedido || !in_array($estado, $estadosPermitidos, true)) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Datos inválidos para actualizar el pedido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $pedidosModel = new Pedidos();
        $pedido = $pedidosModel->getPedidoById($idPedido);
        if (!$pedido) {
            http_response_code(404);
            echo json_encode([
                'ok' => false,
                'message' => 'Pedido no encontrado.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $actualizado = $pedidosModel->actualizarEstado($idPedido, $estado);
        if (!$actualizado) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'No se pudo actualizar el estado del pedido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Pedido actualizado correctamente.',
            'id' => $idPedido,
            'estado' => $estado
        ], JSON_UNESCAPED_UNICODE);
    }

    public function historial(){
        $historialModel = new Historial();
        $listadoHistorial = $historialModel->getHistorial();
        $this->render('dashboard/historial/index', [
            'historial' => $listadoHistorial
        ]);
    }

    public function historialDetalle(){
        header('Content-Type: application/json; charset=UTF-8');

        $idHistorial = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$idHistorial) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'ID de historial inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $historialModel = new Historial();
        $detalleCompleto = $historialModel->getDetalleCompleto((int) $idHistorial);

        if (!$detalleCompleto) {
            http_response_code(404);
            echo json_encode([
                'ok' => false,
                'message' => 'Registro no encontrado.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'registro' => $detalleCompleto['registro'] ?? null,
            'detalle' => $detalleCompleto['detalle'] ?? [],
            'resumen' => $detalleCompleto['resumen'] ?? []
        ], JSON_UNESCAPED_UNICODE);
    }

    public function historialAnular(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idHistorial = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$idHistorial) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'ID inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $historialModel = new Historial();
        $registro = $historialModel->getById($idHistorial);

        if (!$registro) {
            http_response_code(404);
            echo json_encode([
                'ok' => false,
                'message' => 'Registro no encontrado.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (($registro['estado'] ?? '') === 'anulado') {
            echo json_encode([
                'ok' => true,
                'message' => 'El registro ya estaba anulado.',
                'id' => $idHistorial,
                'estado' => 'anulado'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $actualizado = $historialModel->actualizarEstado($idHistorial, 'anulado');
        if (!$actualizado) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'No se pudo anular el registro.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Registro anulado correctamente.',
            'id' => $idHistorial,
            'estado' => 'anulado'
        ], JSON_UNESCAPED_UNICODE);
    }

    public function inventario(){
        $inventarioModel = new Inventario();
        $listadoInventario = $inventarioModel->getInventario();
        $categorias = $inventarioModel->getCategoriasActivas();
        $categoriasListado = $inventarioModel->getCategorias();
        $this->render('dashboard/inventario/index',[
            'inventario' => $listadoInventario,
            'categorias' => $categorias,
            'categoriasListado' => $categoriasListado
        ]);
    }

    public function productoCrear(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idCategoriaRaw = trim((string) ($_POST['id_categoria'] ?? ''));
        $idCategoria = $idCategoriaRaw === '' ? null : filter_var($idCategoriaRaw, FILTER_VALIDATE_INT);
        $nombre = trim((string) ($_POST['nombre_producto'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $precioRaw = str_replace(',', '.', trim((string) ($_POST['precio_base'] ?? '0')));
        $estado = strtolower(trim((string) ($_POST['estado'] ?? 'activo')));

        if ($idCategoriaRaw !== '' && $idCategoria === false) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Categoría inválida.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!is_numeric($precioRaw)) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Precio base inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $inventarioModel = new Inventario();
        $idProducto = $inventarioModel->crearProducto(
            $idCategoria === false ? null : ($idCategoria === null ? null : (int) $idCategoria),
            $nombre,
            $descripcion,
            (float) $precioRaw,
            $estado
        );

        if (!$idProducto) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => $inventarioModel->getLastError() ?? 'No se pudo crear el producto.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Producto creado correctamente.',
            'id' => $idProducto
        ], JSON_UNESCAPED_UNICODE);
    }

    public function productoActualizar(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idProducto = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $idCategoriaRaw = trim((string) ($_POST['id_categoria'] ?? ''));
        $idCategoria = $idCategoriaRaw === '' ? null : filter_var($idCategoriaRaw, FILTER_VALIDATE_INT);
        $nombre = trim((string) ($_POST['nombre_producto'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $precioRaw = str_replace(',', '.', trim((string) ($_POST['precio_base'] ?? '0')));
        $estado = strtolower(trim((string) ($_POST['estado'] ?? 'activo')));

        if (!$idProducto) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'ID de producto inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($idCategoriaRaw !== '' && $idCategoria === false) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Categoría inválida.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!is_numeric($precioRaw)) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Precio base inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $inventarioModel = new Inventario();
        $actualizado = $inventarioModel->actualizarProducto(
            (int) $idProducto,
            $idCategoria === false ? null : ($idCategoria === null ? null : (int) $idCategoria),
            $nombre,
            $descripcion,
            (float) $precioRaw,
            $estado
        );

        if (!$actualizado) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => $inventarioModel->getLastError() ?? 'No se pudo actualizar el producto.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Producto actualizado correctamente.',
            'id' => (int) $idProducto
        ], JSON_UNESCAPED_UNICODE);
    }

    public function productoEliminar(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idProducto = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$idProducto) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'ID de producto inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $inventarioModel = new Inventario();
        $eliminado = $inventarioModel->eliminarProducto((int) $idProducto);

        if (!$eliminado) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => $inventarioModel->getLastError() ?? 'No se pudo eliminar el producto.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Producto eliminado correctamente.',
            'id' => (int) $idProducto
        ], JSON_UNESCAPED_UNICODE);
    }

    public function categoriaCrear(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $tipoCategoria = trim((string) ($_POST['tipo_categoria'] ?? ''));
        $estado = strtolower(trim((string) ($_POST['estado'] ?? 'activo')));

        $inventarioModel = new Inventario();
        $idCategoria = $inventarioModel->crearCategoria($tipoCategoria, $estado);

        if (!$idCategoria) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => $inventarioModel->getLastError() ?? 'No se pudo crear la categoría.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Categoría creada correctamente.',
            'id' => (int) $idCategoria
        ], JSON_UNESCAPED_UNICODE);
    }

    public function categoriaActualizar(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idCategoria = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $tipoCategoria = trim((string) ($_POST['tipo_categoria'] ?? ''));
        $estado = strtolower(trim((string) ($_POST['estado'] ?? 'activo')));

        if (!$idCategoria) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'ID de categoría inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $inventarioModel = new Inventario();
        $actualizado = $inventarioModel->actualizarCategoria((int) $idCategoria, $tipoCategoria, $estado);

        if (!$actualizado) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => $inventarioModel->getLastError() ?? 'No se pudo actualizar la categoría.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Categoría actualizada correctamente.',
            'id' => (int) $idCategoria
        ], JSON_UNESCAPED_UNICODE);
    }

    public function categoriaEliminar(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idCategoria = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$idCategoria) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'ID de categoría inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $inventarioModel = new Inventario();
        $eliminado = $inventarioModel->eliminarCategoria((int) $idCategoria);

        if (!$eliminado) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => $inventarioModel->getLastError() ?? 'No se pudo eliminar la categoría.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Categoría eliminada correctamente.',
            'id' => (int) $idCategoria
        ], JSON_UNESCAPED_UNICODE);
    }

    public function configuration(){
        $this->render('dashboard/configuration/index');
    }
}


?>
