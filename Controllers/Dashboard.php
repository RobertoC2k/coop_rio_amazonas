<?php

class Dashboard extends Controller
{
    public function __construct()
    {
        session_start();
        if (empty($_SESSION['id_usuario'])) {
            header("location: " . base_url);
            exit;
        }
        // El panel y los reportes de ingresos son solo para el personal (tipo 1).
        // Los clientes van a su pantalla de reservas.
        if ($_SESSION['tipo'] != 1) {
            header("location: " . base_url . 'reservas');
            exit;
        }
        parent::__construct();
    }
    public function index()
    {
        $data['usuarios'] = $this->model->getDatos('usuarios');
        $data['clientes'] = $this->model->getDatos('clientes');
        $data['vehiculos'] = $this->model->getDatos('vehiculos');
        $data['tipos'] = $this->model->getDatos('tipos');
        $data['marcas'] = $this->model->getDatos('marcas');
        $this->views->getView('dashboard/home', $data);
    }
    
    public function ingresosMes()
    {
        $desde = date('Y') . '-01-01 00:00:00';
        $hasta = date('Y') . '-12-31 23:59:59';
        $data['ingresos'] = $this->model->ingresosMes($desde, $hasta);
        echo json_encode($data);
        die();
    }

    public function ingresosSemana()
    {
        $data = $this->model->ingresosSemana();
        echo json_encode($data);
        die();
    }
}
