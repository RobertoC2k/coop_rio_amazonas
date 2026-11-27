<?php
/**
 * Controlador de Vehículos.
 * Solo recibe datos de la vista, llama al modelo (VehiculosModel) y responde.
 */
class Vehiculos extends Controller
{
    public function __construct()
    {
        session_start();
        // Solo el personal (tipo 1) puede entrar a este módulo.
        if (empty($_SESSION['id_usuario']) || $_SESSION['tipo'] != 1) {
            header("location: " . base_url . 'login');
            exit;
        }
        parent::__construct();
    }

    public function index()
    {
        $data['marcas'] = $this->model->getDatos('marcas');
        $data['tipos'] = $this->model->getDatos('tipos');
        $this->views->getView("vehiculos/index", $data);
    }

    public function listar()
    {
        $data = $this->model->vehiculos();
        $date = date('Y-m-d');
        // Aquí solo se arma el HTML de la tabla (presentación)
        for ($i = 0; $i < count($data); $i++) {
            $data[$i]['date'] = $date;
            $data[$i]['imagen'] = '<img class="img-thumbnail" src="' . base_url . "Assets/img/vehiculos/" . $data[$i]['foto'] . '" width="50">';
            $data[$i]['editar'] = '<button class="btn btn-outline-primary" type="button" onclick="btnEditarVeh(' . $data[$i]['id'] . ');"><i class="fas fa-edit"></i></button>';
            $data[$i]['eliminar'] = '<button class="btn btn-outline-danger" type="button" onclick="btnEliminarVeh(' . $data[$i]['id'] . ');"><i class="fas fa-trash-alt"></i></button>';
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function registrar()
    {
        // 1. Recibir datos de la vista
        $campos = array('id', 'placa', 'marca', 'tipo', 'modelo', 'precio_hora', 'precio_dia', 'precio_mes',
            'kilometraje', 'transmision', 'asientos', 'peso_carga_max', 'combustible');
        $datos = array();
        foreach ($campos as $campo) {
            $datos[$campo] = strClean($_POST[$campo]);
        }
        // Si el formulario aún tiene la foto actual, se conserva; si se quitó, queda vacío
        $conservarFoto = !empty($_POST['foto_actual']);
        // 2. El modelo valida, decide la foto y registra o modifica
        $msg = $this->model->guardar($datos, $_FILES['imagen'], $conservarFoto);
        // 3. Responder a la vista
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function editar(int $id)
    {
        $data = $this->model->getVehiculo($id);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function eliminar(int $id)
    {
        $msg = $this->model->darDeBaja($id);
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function buscarVehiculo()
    {
        $texto = isset($_GET['veh']) ? strClean($_GET['veh']) : '';
        echo json_encode($this->model->sugerencias($texto), JSON_UNESCAPED_UNICODE);
        die();
    }
}
