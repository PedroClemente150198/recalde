<?php
namespace Controllers;

use core\controller;
use models\usuarios;

class LoginController extends Controller {
    public function index() {
        // Renderizar la vista login
        $this->render('login/index');
    }

    public function authenticate(){
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            
            // Iniciar sesión SOLO UNA VEZ en toda la app
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $usuario = trim($_POST['usuario'] ?? '');
            $password = trim($_POST['password'] ?? '');
            
            #echo "Usuario: $usuario, Password: $password";

            $usuariosModel = new Usuarios();
            $user = $usuariosModel->getUserByUsername($usuario);

            /*
            foreach($user as $key => $value){
                echo "$key : $value<br>";
            }
            */

            if(!$user){
                // Manejar usuario no encontrado
                $this->render('login/index', ['error' => 'Usuario no encontrado']);
                return;
            };

            if (strtolower((string) ($user['estado'] ?? '')) !== 'activo') {
                $this->render('login/index', ['error' => 'Tu usuario está inactivo. Contacta al administrador.']);
                return;
            }
            
            // verificar la contraseña
            $storedPassword = (string) ($user['contrasena'] ?? '');
            if (!$usuariosModel->verifyPassword($password, $storedPassword)) {
                // Manejar contraseña incorrecta
                $this->render('login/index', ['error' => 'Contraseña incorrecta']);
                return;
            }

            $userId = (int) ($user['user_id'] ?? $user['id'] ?? 0);
            if ($userId > 0 && (
                $usuariosModel->needsPasswordMigration($storedPassword) ||
                $usuariosModel->passwordNeedsRehash($storedPassword)
            )) {
                $usuariosModel->upgradePasswordHash($userId, $password);
            }

            /*$_SESSION['usuario']=[
                'id' => $user['id'],
                'usuario' => $user['usuario'],
                'rol' => $user['rol'],
                'correo' => $user['correo']
            ];*/

            // ELIMINAR la contraseña antes de poner en sesión
            unset($user['contrasena']);

            // GUARDAR *TODO* EL REGISTRO en sesión
            $_SESSION['usuario'] = $user;


            //redirigir a la lista de usuarios
            //header('Location: /dashboard');
            
            // Por simplicidad, asumimos que la autenticación es exitosa
            $this->redirect('?route=dashboard');
            return;
        } else {
            // Si no es una solicitud POST, redirigir al formulario de login
            $this->redirect('?route=login');
        }
    }
}
