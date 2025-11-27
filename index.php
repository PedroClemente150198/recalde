<?php 

require_once __DIR__. '/src/core/autoload.php';

//echo __DIR__. '/src/models/usuarios.php';
//echo BASE_PATH;
//require_once BASE_PATH.'/src/models/usuarios.php';
//


use controllers\usuariocontroller;
use controllers\logincontroller;

// Por ejemplo, ?route=usuarios
$route = $_GET['route'] ?? 'home';

echo $route;

switch($route) {
    case 'login':
        $controller = new LoginController();
        $controller->index();
        break;
    case 'usuarios':
        $controller = new UsuarioController();
        $controller->index();
        break;

    default:
        echo "<h1>Bienvenido a RECLALDE</h1>";
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