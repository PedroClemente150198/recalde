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
            $usuario = trim($_POST['usuario'] ?? '');
            $password = trim($_POST['password'] ?? '');
            
            $usuariosModel = new Usuarios();
            $user = $usuariosModel->getUserByUsername($usuario);
            
            if(!$user){
                // Manejar usuario no encontrado
                $this->render('login/index', ['error' => 'Usuario no encontrado']);
                return;
            }

            // verificar la contraseña
            if(!password_verify($password, $user['contrasena'])) {
                // Manejar contraseña incorrecta
                $this->render('login/index', ['error' => 'Contraseña incorrecta']);
                return;
            }

            $_SESSION['usuario']=[
                'id' => $user['id'],
                'usuario' => $user['usuario'],
                'rol' => $user['rol']
            ];

            //redirigir a la lista de usuarios
            //header('Location: /dashboard');
            
            // Por simplicidad, asumimos que la autenticación es exitosa
            $this->redirect('dashboard');
            exit();
        } else {
            // Si no es una solicitud POST, redirigir al formulario de login
            $this->redirect('login');
        }
    }
}
