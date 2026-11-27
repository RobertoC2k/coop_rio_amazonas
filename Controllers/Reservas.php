<?php
/**
 * Controlador de Reservas (solicitud de turno).
 * Solo recibe datos de la vista, llama al modelo (ReservasModel) y responde.
 */
class Reservas extends Controller
{
    public function __construct()
    {
        session_start();
        // exit detiene el código: sin él, la acción se ejecutaba igual después de redirigir.
        if (empty($_SESSION['id_usuario'])) {
            header("location: " . base_url . 'login');
            exit;
        }
        parent::__construct();
    }

    public function index()
    {
        $data['vehiculos'] = $this->model->getVehiculos();
        $this->views->getView("reservas/index", $data);
    }

    public function listar($id_vehiculo)
    {
        // El modelo decide qué nombre ve cada usuario y el color según el estado
        $data = $this->model->getEventosCalendario($id_vehiculo, $_SESSION['tipo'], $_SESSION['id_usuario']);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function getVehiculo(int $id)
    {
        $data = $this->model->getVehiculo($id);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function solicitar()
    {
        // 1. Recibir datos de la vista
        $datos = array(
            'id_cli'      => $_SESSION['id_usuario'],
            'id_veh'      => strClean($_POST['idVehiculo']),
            'cantidad'    => strClean($_POST['cantidad']),
            'precios'     => strClean($_POST['precios']),
            'fecha'       => strClean($_POST['fecha']),
            'hora'        => strClean($_POST['hora']),
            'observacion' => strClean($_POST['observacion'])
        );
        // 2. El modelo valida, calcula la tarifa, verifica disponibilidad y guarda
        $msg = $this->model->solicitar($datos);
        // 3. Responder a la vista
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function verify($id)
    {
        if ($_SESSION['tipo'] != 1) {
            header("location: " . base_url . 'login');
            exit;
        }
        $data['reserva'] = $this->model->getReserva($id);
        if (empty($data['reserva'])) {
            header("location: " . base_url . 'errors');
            exit;
        }
        $data['vehiculo'] = $this->model->getVehiculo($data['reserva']['id_vehiculo']);
        $data['motivos'] = $this->model->motivosRechazo();
        $this->views->getView("reservas/verify", $data);
    }

    public function rechazar($idReserva)
    {
        // Permiso: solo el administrador rechaza
        if ($_SESSION['tipo'] != 1) {
            $msg = array('msg' => 'No tienes permisos', 'icono' => 'warning');
        } else {
            // El modelo valida el motivo, guarda el rechazo y avisa al cliente
            $msg = $this->model->rechazar(
                intval($idReserva),
                strClean($_POST['motivo']),
                strClean($_POST['detalle'])
            );
        }
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function aprobar($idReserva)
    {
        // Permiso: solo el administrador aprueba
        if ($_SESSION['tipo'] != 1) {
            $msg = array('msg' => 'No tienes permisos', 'icono' => 'warning');
        } else {
            // El modelo cambia el estado y notifica al cliente por correo
            $msg = $this->model->aprobar($idReserva);
        }
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }
}
