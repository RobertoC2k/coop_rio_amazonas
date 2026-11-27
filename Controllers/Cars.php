<?php
class Cars extends Controller
{
    public function __construct() {
        session_start();
        parent::__construct();
    }
    public function index()
    {
        $data['active'] = 'cars';
        $data['title'] = 'Maquinaria';
        $data['detail'] = 'Maquinaria Disponible';
        $data['vehiculos'] = $this->model->getVehiculos(0);
        $this->views->getView("cars", $data);
    }

    public function single($valor)
    {
        $id = $valor;
        $data['active'] = 'cars';
        $data['vehiculo'] = $this->model->getVehiculo($id);
        // Vehículo inexistente (ej. cars/single/999): página de error en vez de avisos de PHP
        if (empty($data['vehiculo'])) {
            header('Location: ' . base_url . 'errors');
            exit;
        }
        $data['title'] = 'Detalle';
        $data['detail'] = $data['vehiculo']['tipo'];
        $this->views->getView("car-single", $data);
    }
}
