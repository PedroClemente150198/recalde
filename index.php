<?php 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__. '/src/core/autoload.php';

//echo __DIR__. '/src/models/usuarios.php';
//echo BASE_PATH;
//require_once BASE_PATH.'/src/models/usuarios.php';
//


use controllers\usuariocontroller;
use controllers\logincontroller;
use controllers\dashboardcontroller;

// Por ejemplo, ?route=usuarios
$route = $_GET['route'] ?? 'home';

//echo $route.'<br>';

switch($route) {
    case 'login':
        $controller = new LoginController();
        $controller->index();
        break;
    
    case 'validar-login':
        $controller = new LoginController();
        $controller->authenticate();
        break;
    
    case 'logout':
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
        header("Location: ?route=login");
        exit();
    
    case 'usuarios':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new UsuarioController();
        $controller->index();
        break;

    case 'dashboard':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->index();
        break;
    
    case 'ventas':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->ventas();
        break;
    
    case 'perfil':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->perfil();
        break;
    
    case 'pedidos':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->pedidos();
        break;
    
    case 'historial':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->historial();
        break;
    
    case 'inventario':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->inventario();
        break;
    
    case 'configuracion':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->configuration();
        break;

    case 'home':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->home();
        break;
    default:
        header("Location: login");
        exit();
}
/*
use models\usuarios;

$getUsers = new Usuarios();
$results = $getUsers->getAllUsers();

if(!$results){
    echo "No se encontraron usuarios.";
    return;
}
echo 'Lista de usuarios registrados:' . "<br>";
foreach ($results as $row) {
    echo "<p>Usuario ID: " . $row['id'] . "<br>Usuario: " . $row['usuario'] . "<br>Contraseña: ".$row["contrasena"]."<br>Email: " . $row['correo'] . "<br></p>";
}
*/


//require_once __DIR__. '/config/db.php';
//$usuario = new Usuarios();
//$results = $usuario->getAllUsers();
//if (!$results) {
//    echo "No se encontraron usuarios.";
//    return;
//}
//foreach ($results as $row) {
//    echo "<h1>Usuario ID: " . $row['id'] . " Nombre: " . $row['nombre'] . " Email: " . $row['email'] . "<br></h1>";
//}

//$dabase = new Database();
//$dabase->connect();
//$query = $dabase->connect()->query("SELECT * FROM roles");
//$results = $query->fetchAll(PDO::FETCH_ASSOC);
//if (!$results) {
//    echo "No se encontraron roles.";
//    return;
//}
//foreach ($results as $row) {
//    echo "<h1>Role ID: " . $row['id'] . " Rol: " . $row['rol'] . "<br></h1>";
//}
//$query->closeCursor();
//$dabase->disconnect();

?>