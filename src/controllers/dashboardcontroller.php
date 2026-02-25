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
        $periodo = trim((string) ($_GET['periodo'] ?? 'mes'));
        $this->render('dashboard/home/index', $this->buildHomePayload($periodo));
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

        $periodo = trim((string) ($_GET['periodo'] ?? 'mes'));

        echo json_encode([
            'ok' => true,
            'data' => $this->buildHomePayload($periodo)
        ], JSON_UNESCAPED_UNICODE);
    }

    public function developer() {
        if (!$this->isDeveloperSession()) {
            $this->redirect('?route=home');
            return;
        }

        $dashboardModel = new Dashboard();
        $panel = $dashboardModel->getDeveloperOverview();
        $panel['rolActual'] = (string) ($_SESSION['usuario']['nombre_rol'] ?? '-');

        $this->render('dashboard/developer/index', $panel);
    }

    public function developerData() {
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!$this->isDeveloperSession()) {
            http_response_code(403);
            echo json_encode([
                'ok' => false,
                'message' => 'No tienes permisos para este módulo.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $dashboardModel = new Dashboard();
        $data = $dashboardModel->getDeveloperOverview();
        $data['rolActual'] = (string) ($_SESSION['usuario']['nombre_rol'] ?? '-');

        echo json_encode([
            'ok' => true,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    }

    public function developerAction() {
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!$this->isDeveloperSession()) {
            http_response_code(403);
            echo json_encode([
                'ok' => false,
                'message' => 'No tienes permisos para ejecutar esta acción.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $action = strtolower(trim((string) ($_POST['action'] ?? '')));
        $dashboardModel = new Dashboard();

        switch ($action) {
            case 'recalcular-totales-pedidos':
                $updated = $dashboardModel->recalculatePedidosTotals();

                if ($updated === null) {
                    http_response_code(500);
                    echo json_encode([
                        'ok' => false,
                        'message' => 'No se pudo recalcular los totales de pedidos.'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $data = $dashboardModel->getDeveloperOverview();
                $data['rolActual'] = (string) ($_SESSION['usuario']['nombre_rol'] ?? '-');

                echo json_encode([
                    'ok' => true,
                    'message' => "Recalculación completada. Filas afectadas: {$updated}.",
                    'updated' => (int) $updated,
                    'data' => $data
                ], JSON_UNESCAPED_UNICODE);
                return;

            case 'resetear-contrasena-usuario':
                $idUsuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
                if (!$idUsuario) {
                    http_response_code(400);
                    echo json_encode([
                        'ok' => false,
                        'message' => 'Usuario inválido para resetear contraseña.'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $usuariosModel = new Usuarios();
                $result = $usuariosModel->forceResetPassword((int) $idUsuario);
                if (!$result) {
                    http_response_code(400);
                    echo json_encode([
                        'ok' => false,
                        'message' => $usuariosModel->getLastError() ?? 'No se pudo resetear la contraseña.'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $data = $dashboardModel->getDeveloperOverview();
                $data['rolActual'] = (string) ($_SESSION['usuario']['nombre_rol'] ?? '-');

                $message = sprintf(
                    "Contraseña temporal de %s: %s",
                    (string) ($result['usuario'] ?? 'usuario'),
                    (string) ($result['temporaryPassword'] ?? '')
                );

                if (empty($result['forceChangeEnabled'])) {
                    $message .= '. Aviso: tu esquema actual no soporta forzar cambio al próximo login.';
                }

                echo json_encode([
                    'ok' => true,
                    'message' => $message,
                    'temporary_password' => (string) ($result['temporaryPassword'] ?? ''),
                    'target_user' => (string) ($result['usuario'] ?? ''),
                    'force_change_enabled' => (bool) ($result['forceChangeEnabled'] ?? false),
                    'data' => $data
                ], JSON_UNESCAPED_UNICODE);
                return;
        }

        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'message' => 'Acción no válida.'
        ], JSON_UNESCAPED_UNICODE);
    }

    private function buildHomePayload(string $periodoIngresos = 'mes'): array {
        $dashboardModel = new Dashboard();
        $periodo = $this->normalizePeriodoIngresos($periodoIngresos);

        $serieIngresos = $dashboardModel->getIngresosSerie($periodo);
        $pedidosPorEstado = $dashboardModel->getPedidosPorEstado();

        $labelsIngresos = [];
        $datosIngresos = [];
        foreach ($serieIngresos as $registro) {
            $labelsIngresos[] = $this->buildIngresoLabel($registro, $periodo);
            $datosIngresos[] = (float) ($registro['total'] ?? 0);
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
            "periodoIngresos" => $periodo,
            "labelsIngresos" => $labelsIngresos,
            "datosIngresos" => $datosIngresos,
            // Compatibilidad con estructura previa del frontend
            "labelsMes" => $labelsIngresos,
            "datosVentasMes" => $datosIngresos,
            "pedidosEstados" => $pedidosEstados,
            "ultimaActualizacion" => date('Y-m-d H:i:s')
        ];
    }

    private function normalizePeriodoIngresos(string $periodo): string {
        $periodo = strtolower(trim($periodo));
        return in_array($periodo, ['dia', 'semana', 'mes'], true) ? $periodo : 'mes';
    }

    private function buildIngresoLabel(array $registro, string $periodo): string {
        if ($periodo === 'dia') {
            $bucket = (string) ($registro['bucket'] ?? '');
            if ($bucket !== '' && strtotime($bucket) !== false) {
                return date('d/m/Y', strtotime($bucket));
            }
            return $bucket !== '' ? $bucket : 'Día';
        }

        if ($periodo === 'semana') {
            $anio = (int) ($registro['anio'] ?? 0);
            $semana = (int) ($registro['semana'] ?? 0);
            return sprintf('%d - Sem %02d', $anio, $semana);
        }

        $anio = (int) ($registro['anio'] ?? 0);
        $mes = (int) ($registro['mes'] ?? 0);
        return sprintf('%02d/%d', $mes, $anio);
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
        $usuariosModel = new Usuarios();
        $idUsuario = (int) ($_SESSION['usuario']['user_id'] ?? 0);

        $usuarioActual = $idUsuario > 0
            ? $usuariosModel->getUserById($idUsuario)
            : null;

        if (!$usuarioActual && isset($_SESSION['usuario']['usuario'])) {
            $usuarioActual = $usuariosModel->getUserByUsername((string) $_SESSION['usuario']['usuario']);
        }

        $isAdmin = $this->isAdminSession();
        $isLastActiveAdmin = $idUsuario > 0 ? $usuariosModel->isLastActiveAdmin($idUsuario) : false;
        $canDeactivateSelf = !($isAdmin && $isLastActiveAdmin);

        $this->render('dashboard/perfil/index', [
            'usuario' => $usuarioActual,
            'isAdmin' => $isAdmin,
            'canDeactivateSelf' => $canDeactivateSelf,
            'usuarios' => $isAdmin ? $usuariosModel->getAllUsers() : [],
            'roles' => $isAdmin ? $usuariosModel->getRoles() : []
        ]);
    }

    public function perfilCrear(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!$this->isAdminSession()) {
            http_response_code(403);
            echo json_encode([
                'ok' => false,
                'message' => 'No tienes permisos para crear perfiles.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idRol = filter_input(INPUT_POST, 'id_rol', FILTER_VALIDATE_INT);
        $usuario = trim((string) ($_POST['usuario'] ?? ''));
        $correo = trim((string) ($_POST['correo'] ?? ''));
        $contrasena = trim((string) ($_POST['contrasena'] ?? ''));
        $estado = strtolower(trim((string) ($_POST['estado'] ?? 'activo')));

        $usuariosModel = new Usuarios();
        $idCreado = $usuariosModel->createUser(
            (int) $idRol,
            $usuario,
            $correo,
            $contrasena,
            $estado
        );

        if (!$idCreado) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => $usuariosModel->getLastError() ?? 'No se pudo crear el perfil.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Perfil creado correctamente.',
            'id' => (int) $idCreado
        ], JSON_UNESCAPED_UNICODE);
    }

    public function perfilActualizar(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idSesion = (int) ($_SESSION['usuario']['user_id'] ?? 0);
        if ($idSesion <= 0) {
            http_response_code(401);
            echo json_encode([
                'ok' => false,
                'message' => 'Sesión inválida.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $isAdmin = $this->isAdminSession();
        $idSolicitado = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $idObjetivo = $isAdmin
            ? (int) ($idSolicitado ?: $idSesion)
            : $idSesion;

        $usuario = trim((string) ($_POST['usuario'] ?? ''));
        $correo = trim((string) ($_POST['correo'] ?? ''));
        $contrasenaRaw = trim((string) ($_POST['contrasena'] ?? ''));
        $contrasena = $contrasenaRaw === '' ? null : $contrasenaRaw;
        $mustChangePassword = (int) ($_SESSION['usuario']['debe_cambiar_contrasena'] ?? 0) === 1;

        $idRolRaw = filter_input(INPUT_POST, 'id_rol', FILTER_VALIDATE_INT);
        $estadoRaw = strtolower(trim((string) ($_POST['estado'] ?? '')));
        $idRol = $isAdmin ? ($idRolRaw ?: null) : null;
        $estado = $isAdmin && $estadoRaw !== '' ? $estadoRaw : null;

        $usuariosModel = new Usuarios();
        $usuarioObjetivo = $usuariosModel->getUserById($idObjetivo);
        if (!$usuarioObjetivo) {
            http_response_code(404);
            echo json_encode([
                'ok' => false,
                'message' => 'Usuario no encontrado.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($isAdmin) {
            $rolActual = strtolower(trim((string) ($usuarioObjetivo['nombre_rol'] ?? '')));
            $estadoActual = strtolower(trim((string) ($usuarioObjetivo['estado'] ?? '')));

            $rolObjetivo = $rolActual;
            if ($idRol !== null) {
                $rolActualizado = $usuariosModel->getRoleNameById((int) $idRol);
                if ($rolActualizado !== null && $rolActualizado !== '') {
                    $rolObjetivo = $rolActualizado;
                }
            }

            $estadoObjetivo = $estado !== null
                ? strtolower(trim((string) $estado))
                : $estadoActual;

            $pierdeAdminActivo = $rolActual === 'admin'
                && $estadoActual === 'activo'
                && !($rolObjetivo === 'admin' && $estadoObjetivo === 'activo');

            if ($pierdeAdminActivo && $usuariosModel->isLastActiveAdmin($idObjetivo)) {
                http_response_code(400);
                echo json_encode([
                    'ok' => false,
                    'message' => 'No puedes dejar al sistema sin administradores activos.'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
        }

        if ($idObjetivo === $idSesion && $mustChangePassword && ($contrasena === null || $contrasena === '')) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Debes establecer una nueva contraseña para continuar.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $actualizado = $usuariosModel->updateUser(
            $idObjetivo,
            $usuario,
            $correo,
            $contrasena,
            $idRol,
            $estado
        );

        if (!$actualizado) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => $usuariosModel->getLastError() ?? 'No se pudo actualizar el perfil.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($idObjetivo === $idSesion) {
            $this->refreshSessionUser($idSesion);
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Perfil actualizado correctamente.',
            'id' => (int) $idObjetivo
        ], JSON_UNESCAPED_UNICODE);
    }

    public function perfilEliminar(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idSesion = (int) ($_SESSION['usuario']['user_id'] ?? 0);
        if ($idSesion <= 0) {
            http_response_code(401);
            echo json_encode([
                'ok' => false,
                'message' => 'Sesión inválida.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $isAdmin = $this->isAdminSession();
        $idSolicitado = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $idObjetivo = $isAdmin
            ? (int) ($idSolicitado ?: $idSesion)
            : $idSesion;

        if (!$isAdmin && $idObjetivo !== $idSesion) {
            http_response_code(403);
            echo json_encode([
                'ok' => false,
                'message' => 'No tienes permisos para eliminar este perfil.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $usuariosModel = new Usuarios();

        if ($usuariosModel->isLastActiveAdmin($idObjetivo)) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'No puedes desactivar el último administrador activo.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $eliminado = $usuariosModel->deleteUser($idObjetivo);

        if (!$eliminado) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => $usuariosModel->getLastError() ?? 'No se pudo eliminar el perfil.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($idObjetivo === $idSesion) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION = [];
            session_destroy();

            echo json_encode([
                'ok' => true,
                'message' => 'Tu perfil fue desactivado correctamente.',
                'id' => (int) $idObjetivo,
                'logout' => true
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Perfil desactivado correctamente.',
            'id' => (int) $idObjetivo,
            'logout' => false
        ], JSON_UNESCAPED_UNICODE);
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

    private function isAdminSession(): bool {
        $rol = strtolower(trim((string) ($_SESSION['usuario']['nombre_rol'] ?? '')));
        return $rol === 'admin';
    }

    private function isDeveloperSession(): bool {
        $rol = strtolower(trim((string) ($_SESSION['usuario']['nombre_rol'] ?? '')));
        return in_array($rol, ['desarrollador', 'developer'], true);
    }

    private function refreshSessionUser(int $idUsuario): void {
        if ($idUsuario <= 0) {
            return;
        }

        $usuariosModel = new Usuarios();
        $usuario = $usuariosModel->getUserById($idUsuario);
        if (!$usuario) {
            return;
        }

        unset($usuario['contrasena']);
        $_SESSION['usuario'] = $usuario;
    }

    public function configuration(){
        $this->render('dashboard/configuration/index');
    }
}


?>
