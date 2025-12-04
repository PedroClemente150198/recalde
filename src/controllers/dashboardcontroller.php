<?php 

namespace Controllers;
use core\controller;

class DashboardController extends Controller{
    public function index(){
        // Llama a la vista del dashboard
        $this->render('dashboard/index');
    }

    public function home(){
        // Llama a la vista del dashboard
        $this->render('dashboard/home/index');
    }
    
    public function ventas(){
        $this->render('dashboard/ventas/index');
    }

    public function perfil(){
        $this->render('dashboard/perfil/index');
    }

    public function pedidos(){
        $this->render('dashboard/pedidos/index');
    }

    public function historial(){
        $this->render('dashboard/historial/index');
    }

    public function inventario(){
        $this->render('dashboard/inventario/index');
    }

    public function configuration(){
        $this->render('dashboard/configuration/index');
    }
}


?>