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

    public function dashboardUiData() {
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
            'data' => $this->getDeveloperUiPreferences()
        ], JSON_UNESCAPED_UNICODE);
    }

    public function developer() {
        if (!$this->isDeveloperSession()) {
            $this->redirect('?route=home');
            return;
        }

        $dashboardModel = new Dashboard();
        $selectedTable = trim((string) ($_GET['table'] ?? ''));
        $panel = $this->buildDeveloperPanelData($dashboardModel, $selectedTable);

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

        $selectedTable = trim((string) ($_GET['table'] ?? ''));
        $dashboardModel = new Dashboard();
        $data = $this->buildDeveloperPanelData($dashboardModel, $selectedTable);

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
        $selectedTable = trim((string) ($_POST['table'] ?? ''));
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

                $data = $this->buildDeveloperPanelData($dashboardModel, $selectedTable);

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

                $data = $this->buildDeveloperPanelData($dashboardModel, $selectedTable);

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

            case 'vaciar-tabla':
                if ($selectedTable === '') {
                    http_response_code(400);
                    echo json_encode([
                        'ok' => false,
                        'message' => 'Debes seleccionar una tabla.'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $deletedRows = $dashboardModel->clearDeveloperTable($selectedTable);
                if ($deletedRows === null) {
                    http_response_code(400);
                    echo json_encode([
                        'ok' => false,
                        'message' => $dashboardModel->getLastError() ?? 'No se pudo vaciar la tabla.'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $logout = $this->syncDeveloperSessionAfterTableMutation($selectedTable);
                $data = $logout ? null : $this->buildDeveloperPanelData($dashboardModel, $selectedTable);

                echo json_encode([
                    'ok' => true,
                    'message' => "Tabla {$selectedTable} vaciada correctamente. Filas afectadas: {$deletedRows}.",
                    'deleted_rows' => (int) $deletedRows,
                    'logout' => $logout,
                    'redirect' => $logout ? '?route=login' : null,
                    'data' => $data
                ], JSON_UNESCAPED_UNICODE);
                return;

            case 'eliminar-fila-tabla':
                if ($selectedTable === '') {
                    http_response_code(400);
                    echo json_encode([
                        'ok' => false,
                        'message' => 'Debes seleccionar una tabla.'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $primaryKey = $this->decodeDeveloperPayload((string) ($_POST['primary_key'] ?? ''));
                if (!is_array($primaryKey) || $primaryKey === []) {
                    http_response_code(400);
                    echo json_encode([
                        'ok' => false,
                        'message' => 'Clave primaria invalida para eliminar la fila.'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $targetUserId = $selectedTable === 'usuarios'
                    ? (int) ($primaryKey['id'] ?? 0)
                    : 0;
                $sessionUserId = (int) ($_SESSION['usuario']['user_id'] ?? 0);
                if ($selectedTable === 'usuarios' && $targetUserId > 0 && $targetUserId === $sessionUserId) {
                    http_response_code(400);
                    echo json_encode([
                        'ok' => false,
                        'message' => 'No puedes eliminar tu propio usuario desde el panel Developer.'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $deleted = $dashboardModel->deleteDeveloperTableRow($selectedTable, $primaryKey);
                if (!$deleted) {
                    http_response_code(400);
                    echo json_encode([
                        'ok' => false,
                        'message' => $dashboardModel->getLastError() ?? 'No se pudo eliminar la fila.'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $logout = $this->syncDeveloperSessionAfterTableMutation($selectedTable, $primaryKey);
                $data = $logout ? null : $this->buildDeveloperPanelData($dashboardModel, $selectedTable);

                echo json_encode([
                    'ok' => true,
                    'message' => 'Fila eliminada correctamente.',
                    'logout' => $logout,
                    'redirect' => $logout ? '?route=login' : null,
                    'data' => $data
                ], JSON_UNESCAPED_UNICODE);
                return;

            case 'actualizar-fila-tabla':
                if ($selectedTable === '') {
                    http_response_code(400);
                    echo json_encode([
                        'ok' => false,
                        'message' => 'Debes seleccionar una tabla.'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $primaryKey = $this->decodeDeveloperPayload((string) ($_POST['primary_key'] ?? ''));
                $fields = $this->decodeDeveloperPayload((string) ($_POST['fields'] ?? ''));

                if (!is_array($primaryKey) || $primaryKey === []) {
                    http_response_code(400);
                    echo json_encode([
                        'ok' => false,
                        'message' => 'Clave primaria invalida para actualizar la fila.'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                if (!is_array($fields) || $fields === []) {
                    http_response_code(400);
                    echo json_encode([
                        'ok' => false,
                        'message' => 'No se recibieron datos validos para actualizar.'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $updated = $dashboardModel->updateDeveloperTableRow($selectedTable, $primaryKey, $fields);
                if (!$updated) {
                    http_response_code(400);
                    echo json_encode([
                        'ok' => false,
                        'message' => $dashboardModel->getLastError() ?? 'No se pudo actualizar la fila.'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $logout = $this->syncDeveloperSessionAfterTableMutation($selectedTable, $primaryKey);
                $data = $logout ? null : $this->buildDeveloperPanelData($dashboardModel, $selectedTable);

                echo json_encode([
                    'ok' => true,
                    'message' => 'Fila actualizada correctamente.',
                    'logout' => $logout,
                    'redirect' => $logout ? '?route=login' : null,
                    'data' => $data
                ], JSON_UNESCAPED_UNICODE);
                return;

            case 'actualizar-preferencia-ui':
                $key = strtolower(trim((string) ($_POST['key'] ?? '')));
                $rawValue = strtolower(trim((string) ($_POST['value'] ?? '')));

                if (!in_array($key, ['ventas_show_actions_column', 'historial_show_actions_column'], true)) {
                    http_response_code(400);
                    echo json_encode([
                        'ok' => false,
                        'message' => 'Preferencia UI no soportada.'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                if (!in_array($rawValue, ['1', '0', 'true', 'false'], true)) {
                    http_response_code(400);
                    echo json_encode([
                        'ok' => false,
                        'message' => 'Valor inválido para la preferencia UI.'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $enabled = in_array($rawValue, ['1', 'true'], true);
                if (!$this->setDeveloperUiPreference($key, $enabled, $dashboardModel)) {
                    http_response_code(500);
                    echo json_encode([
                        'ok' => false,
                        'message' => $dashboardModel->getLastError() ?? 'No se pudo guardar la preferencia UI.'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $data = $this->buildDeveloperPanelData($dashboardModel, $selectedTable);
                $preferenceLabel = $key === 'historial_show_actions_column'
                    ? 'La columna Acciones de historial'
                    : 'La columna Acciones de ventas';

                echo json_encode([
                    'ok' => true,
                    'message' => $enabled
                        ? $preferenceLabel . ' ahora está visible.'
                        : $preferenceLabel . ' ahora está oculta.',
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
        $ventasModel = new Ventas();
        $historialModel = new Historial();
        $periodo = $this->normalizePeriodoIngresos($periodoIngresos);

        $ventasModel->syncPedidosEstadoPorCartera();

        $serieIngresos = $dashboardModel->getIngresosSerie($periodo);
        $pedidosPorEstado = $dashboardModel->getPedidosPorEstado();
        $resumenCartera = $ventasModel->getResumenCartera();
        $clientesConDeuda = $ventasModel->getClientesConDeuda(5);
        $ultimasVentas = $ventasModel->getUltimasVentas(5);
        $resumenHistorial = $historialModel->getResumenGeneral();
        $ultimosHistorial = $historialModel->getUltimosRegistros(5);

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
            "ultimasVentas" => $ultimasVentas,
            "periodoIngresos" => $periodo,
            "labelsIngresos" => $labelsIngresos,
            "datosIngresos" => $datosIngresos,
            // Compatibilidad con estructura previa del frontend
            "labelsMes" => $labelsIngresos,
            "datosVentasMes" => $datosIngresos,
            "pedidosEstados" => $pedidosEstados,
            "carteraResumen" => $resumenCartera,
            "clientesConDeuda" => $clientesConDeuda,
            "historialResumen" => $resumenHistorial,
            "ultimosHistorial" => $ultimosHistorial,
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
        $ventasModel->syncPedidosEstadoPorCartera();
        $listadoVentas = $ventasModel->getAllVentas();
        $pedidosDisponibles = $ventasModel->getPedidosDisponiblesParaVenta();
        $resumenCartera = $ventasModel->getResumenCartera();
        $clientesConDeuda = $ventasModel->getClientesConDeuda();
        $preferences = $this->getDeveloperUiPreferences();
        $this->render('dashboard/ventas/index', [
            'ventas' => $listadoVentas,
            'pedidosDisponibles' => $pedidosDisponibles,
            'resumenCartera' => $resumenCartera,
            'clientesConDeuda' => $clientesConDeuda,
            'showActionsColumn' => (bool) ($preferences['ventasShowActionsColumn'] ?? true)
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
        $abonoInicialRaw = str_replace(',', '.', trim((string) ($_POST['abono_inicial'] ?? '')));
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

        $abonoInicial = null;
        if ($abonoInicialRaw !== '') {
            if (!is_numeric($abonoInicialRaw)) {
                http_response_code(400);
                echo json_encode([
                    'ok' => false,
                    'message' => 'Abono inicial inválido.'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            $abonoInicial = (float) $abonoInicialRaw;
        }

        $ventasModel = new Ventas();
        $idVenta = $ventasModel->crearVentaDesdePedido(
            (int) $idPedido,
            (float) $totalRaw,
            $metodoPago,
            $usuarioRegistro > 0 ? $usuarioRegistro : null,
            $abonoInicial
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

    public function ventaDetalle(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idVenta = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$idVenta) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'ID de venta inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ventasModel = new Ventas();
        $venta = $ventasModel->getVentaById((int) $idVenta);
        if (!$venta) {
            http_response_code(404);
            echo json_encode([
                'ok' => false,
                'message' => 'Venta no encontrada.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $abonos = $ventasModel->getAbonosByVenta((int) $idVenta);

        echo json_encode([
            'ok' => true,
            'venta' => $venta,
            'abonos' => $abonos
        ], JSON_UNESCAPED_UNICODE);
    }

    public function ventaAbonoCrear(){
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método no permitido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idVenta = filter_input(INPUT_POST, 'id_venta', FILTER_VALIDATE_INT);
        $montoRaw = str_replace(',', '.', trim((string) ($_POST['monto'] ?? '0')));
        $metodoPago = strtolower(trim((string) ($_POST['metodo_pago'] ?? 'efectivo')));
        $observacion = trim((string) ($_POST['observacion'] ?? ''));
        $usuarioRegistro = (int) ($_SESSION['usuario']['user_id'] ?? 0);

        if (!$idVenta) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Venta inválida para registrar abono.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!is_numeric($montoRaw)) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Monto de abono inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ventasModel = new Ventas();
        $idAbono = $ventasModel->registrarAbono(
            (int) $idVenta,
            (float) $montoRaw,
            $metodoPago,
            $observacion !== '' ? $observacion : null,
            $usuarioRegistro > 0 ? $usuarioRegistro : null
        );

        if (!$idAbono) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => $ventasModel->getLastError() ?? 'No se pudo registrar el abono.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ventaActualizada = $ventasModel->getVentaById((int) $idVenta);
        $abonos = $ventasModel->getAbonosByVenta((int) $idVenta);

        echo json_encode([
            'ok' => true,
            'message' => 'Abono registrado correctamente.',
            'id_abono' => (int) $idAbono,
            'venta' => $ventaActualizada,
            'abonos' => $abonos
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
        $ventasModel = new Ventas();
        $ventasModel->syncPedidosEstadoPorCartera();

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

        $estadosPermitidos = ['pendiente', 'procesando', 'listo', 'entregado'];
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

        $ventasModel = new Ventas();
        $ventasModel->syncPedidosEstadoPorCartera((int) $idPedido);

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
        $medidasPorDetalle = $pedidosModel->getMedidasPorPedido((int) $idPedido);

        foreach ($detalles as &$detalle) {
            $idDetalle = (int) ($detalle['id'] ?? 0);
            $detalle['medidas'] = $idDetalle > 0
                ? ($medidasPorDetalle[$idDetalle] ?? [])
                : [];
        }
        unset($detalle);

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
        $idCliente = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT);
        $estado = trim((string) ($_POST['estado'] ?? ''));
        $itemsRaw = array_key_exists('items', $_POST)
            ? (string) ($_POST['items'] ?? '[]')
            : '';
        $items = $itemsRaw !== '' ? json_decode($itemsRaw, true) : null;

        $estadosPermitidos = ['pendiente', 'procesando', 'listo', 'entregado'];
        if (!$idPedido || !in_array($estado, $estadosPermitidos, true)) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Datos inválidos para actualizar el pedido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $pedidosModel = new Pedidos();
        $ventasModel = new Ventas();
        $ventasModel->syncPedidosEstadoPorCartera((int) $idPedido);
        $pedido = $pedidosModel->getPedidoById($idPedido);
        if (!$pedido) {
            http_response_code(404);
            echo json_encode([
                'ok' => false,
                'message' => 'Pedido no encontrado.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $actualizado = false;
        $mensajeError = 'No se pudo actualizar el pedido.';

        if ($itemsRaw !== '') {
            if (!$idCliente || !is_array($items) || count($items) === 0) {
                http_response_code(400);
                echo json_encode([
                    'ok' => false,
                    'message' => 'Debes indicar cliente, estado y al menos un producto para actualizar el pedido.'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $actualizado = $pedidosModel->actualizarPedidoConDetalles((int) $idPedido, (int) $idCliente, $estado, $items);
            $mensajeError = $pedidosModel->getLastError() ?? 'No se pudo actualizar el pedido.';
        } else {
            $actualizado = $pedidosModel->actualizarEstado($idPedido, $estado);
        }

        if (!$actualizado) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => $mensajeError
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ventasModel->syncPedidosEstadoPorCartera((int) $idPedido);
        $pedidoActualizado = $pedidosModel->getPedidoById((int) $idPedido);
        $estadoActual = (string) ($pedidoActualizado['estado'] ?? $estado);

        echo json_encode([
            'ok' => true,
            'message' => 'Pedido actualizado correctamente.',
            'id' => $idPedido,
            'estado' => $estadoActual,
            'total' => (float) ($pedidoActualizado['total'] ?? 0)
        ], JSON_UNESCAPED_UNICODE);
    }

    public function pedidoEliminar(){
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
        if (!$idPedido) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'ID de pedido inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $pedidosModel = new Pedidos();
        $eliminado = $pedidosModel->eliminarPedido((int) $idPedido);

        if (!$eliminado) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => $pedidosModel->getLastError() ?? 'No se pudo eliminar el pedido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Pedido eliminado correctamente.',
            'id' => (int) $idPedido
        ], JSON_UNESCAPED_UNICODE);
    }

    public function historial(){
        $ventasModel = new Ventas();
        $ventasModel->syncPedidosEstadoPorCartera();

        $historialModel = new Historial();
        $listadoHistorial = $historialModel->getHistorial();
        $preferences = $this->getDeveloperUiPreferences();
        $this->render('dashboard/historial/index', [
            'historial' => $listadoHistorial,
            'showActionsColumn' => (bool) ($preferences['historialShowActionsColumn'] ?? true)
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
            'categoriasListado' => $categoriasListado,
            'stockColumnsEnabled' => $inventarioModel->hasRealStockColumns()
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
        $stockActualRaw = trim((string) ($_POST['stock_actual'] ?? '0'));
        $stockMinimoRaw = trim((string) ($_POST['stock_minimo'] ?? '5'));
        $estado = strtolower(trim((string) ($_POST['estado'] ?? 'activo')));
        $stockActual = filter_var($stockActualRaw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0]
        ]);
        $stockMinimo = filter_var($stockMinimoRaw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0]
        ]);

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

        if ($stockActual === false) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Stock actual inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($stockMinimo === false) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Stock mínimo inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $inventarioModel = new Inventario();
        $idProducto = $inventarioModel->crearProducto(
            $idCategoria === false ? null : ($idCategoria === null ? null : (int) $idCategoria),
            $nombre,
            $descripcion,
            (float) $precioRaw,
            $estado,
            (int) $stockActual,
            (int) $stockMinimo
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
        $stockActualRaw = trim((string) ($_POST['stock_actual'] ?? '0'));
        $stockMinimoRaw = trim((string) ($_POST['stock_minimo'] ?? '5'));
        $estado = strtolower(trim((string) ($_POST['estado'] ?? 'activo')));
        $stockActual = filter_var($stockActualRaw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0]
        ]);
        $stockMinimo = filter_var($stockMinimoRaw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0]
        ]);

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

        if ($stockActual === false) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Stock actual inválido.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($stockMinimo === false) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Stock mínimo inválido.'
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
            $estado,
            (int) $stockActual,
            (int) $stockMinimo
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

    private function buildDeveloperPanelData(Dashboard $dashboardModel, ?string $selectedTable = null): array {
        $this->migrateLegacyDeveloperUiSession($dashboardModel);
        $panel = $dashboardModel->getDeveloperOverview($selectedTable);
        $panel['rolActual'] = (string) ($_SESSION['usuario']['nombre_rol'] ?? '-');
        $panel['preferences'] = $this->getDeveloperUiPreferences($dashboardModel);
        return $panel;
    }

    private function getDeveloperUiPreferences(?Dashboard $dashboardModel = null): array {
        $model = $dashboardModel ?? new Dashboard();
        $this->migrateLegacyDeveloperUiSession($model);
        return $model->getSharedUiPreferences();
    }

    private function setDeveloperUiPreference(string $key, bool $value, ?Dashboard $dashboardModel = null): bool {
        $model = $dashboardModel ?? new Dashboard();
        $updatedBy = (int) ($_SESSION['usuario']['user_id'] ?? 0);

        $saved = $model->saveSharedUiPreference($key, $value, $updatedBy > 0 ? $updatedBy : null);
        if (!$saved) {
            return false;
        }

        if (!isset($_SESSION['developer_ui']) || !is_array($_SESSION['developer_ui'])) {
            $_SESSION['developer_ui'] = [];
        }

        if (in_array($key, ['ventas_show_actions_column', 'historial_show_actions_column'], true)) {
            $_SESSION['developer_ui'][$key] = $value;
        }

        $_SESSION['developer_ui_global_migrated'] = true;
        return true;
    }

    private function migrateLegacyDeveloperUiSession(?Dashboard $dashboardModel = null): void {
        if (!$this->isDeveloperSession()) {
            return;
        }

        if (!isset($_SESSION['developer_ui']) || !is_array($_SESSION['developer_ui'])) {
            return;
        }

        if (!empty($_SESSION['developer_ui_global_migrated'])) {
            return;
        }

        $model = $dashboardModel ?? new Dashboard();
        foreach (['ventas_show_actions_column', 'historial_show_actions_column'] as $key) {
            if (!array_key_exists($key, $_SESSION['developer_ui'])) {
                continue;
            }

            $rawValue = $_SESSION['developer_ui'][$key];
            $normalized = filter_var($rawValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $value = $normalized === null ? (bool) $rawValue : $normalized;

            $updatedBy = (int) ($_SESSION['usuario']['user_id'] ?? 0);
            $model->saveSharedUiPreference($key, $value, $updatedBy > 0 ? $updatedBy : null);
        }

        $_SESSION['developer_ui_global_migrated'] = true;
    }

    private function decodeDeveloperPayload(string $payload): ?array {
        $payload = trim($payload);
        if ($payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function syncDeveloperSessionAfterTableMutation(string $tableName, array $primaryKey = []): bool {
        $idSesion = (int) ($_SESSION['usuario']['user_id'] ?? 0);
        if ($idSesion <= 0) {
            return false;
        }

        if ($tableName === 'usuarios') {
            $targetId = (int) ($primaryKey['id'] ?? 0);
            if ($targetId > 0 && $targetId !== $idSesion) {
                return false;
            }

            $usuariosModel = new Usuarios();
            $usuario = $usuariosModel->getUserById($idSesion);
            if (!$usuario) {
                $_SESSION = [];
                session_destroy();
                return true;
            }

            unset($usuario['contrasena']);
            $_SESSION['usuario'] = $usuario;
            return false;
        }

        if ($tableName === 'roles') {
            $this->refreshSessionUser($idSesion);
        }

        return false;
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
