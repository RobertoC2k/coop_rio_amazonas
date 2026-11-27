<?php
class Major extends Controller
{
    public function __construct()
    {
        session_start();
        parent::__construct();
    }
    public function index()
    {
        $data['active'] = 'inicio';
        $this->views->getView("index", $data);
    }

    public function verify()
    {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $res = ['msg' => 'Error desconocido', 'icono' => 'error'];
        } else {
            // 1. Recibir datos de la vista
            $datos = array(
                'dni'       => strClean($_POST['dni'] ?? ''),
                'nombre'    => strClean($_POST['nombre']),
                'codphone'  => strClean($_POST['codphone']),
                'telefono'  => strClean($_POST['telefono']),
                'correo'    => strClean($_POST['correo']),
                'clave'     => ($_POST['clave'] ?? ''),
                'direccion' => strClean($_POST['direccion'])
            );
            // 2. El modelo valida, identifica al cliente por su cédula y registra (o completa su registro)
            $res = $this->model->registrarCliente($datos);
        }
        // 3. Responder a la vista
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        die();
    }
}
