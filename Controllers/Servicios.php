<?php
/**
 * Controlador de Servicios de transporte pesado.
 * Solo recibe datos de la vista, llama al modelo (ServiciosModel) y responde.
 */
class Servicios extends Controller
{
    public function __construct()
    {
        session_start();
        // Solo el personal (tipo 1) puede entrar a este módulo.
        // exit detiene el código: sin él, la acción se ejecutaba igual después de redirigir.
        if (empty($_SESSION['id_usuario']) || $_SESSION['tipo'] != 1) {
            header("location: " . base_url . 'login');
            exit;
        }
        parent::__construct();
    }

    public function index()
    {
        $data['vehiculos'] = $this->model->getVehiculos();
        $this->views->getView("servicios/index", $data);
    }

    public function registrar()
    {
        // 1. Recibir datos de la vista
        $datos = array(
            'id_cli'          => strClean($_POST['id_cli']),
            'id_veh'          => strClean($_POST['id_veh']),
            'select_cliente'  => strClean($_POST['select_cliente']),
            'select_vehiculo' => strClean($_POST['select_vehiculo']),
            'cantidad'        => strClean($_POST['cantidad']),
            'precios'         => strClean($_POST['precios']),
            'abono'           => strClean($_POST['abono']),
            'fecha'           => strClean($_POST['fecha']),
            'observacion'     => strClean($_POST['observacion'])
        );
        // 2. El modelo valida, calcula, verifica disponibilidad y guarda
        $msg = $this->model->registrar($datos);
        // 3. Responder a la vista
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function listar()
    {
        $data = escaparFilas($this->model->getServicios());
        // Aquí solo se arma el HTML de la tabla (presentación)
        for ($i = 0; $i < count($data); $i++) {
            $id = $data[$i]['id'];
            $estado = $this->model->textoEstado($data[$i]['estado']);
            $data[$i]['f_inicio'] = '<span class="badge bg-primary">' . $data[$i]['fecha_inicio'] . '</span>';
            $data[$i]['f_fin'] = '<span class="badge bg-info">' . $data[$i]['fecha_fin'] . '</span>';
            $data[$i]['accion'] = '<a class="btn btn-outline-danger" target="_blank" href="' . base_url . 'servicios/pdfServicio/' . $id . '"><i class="fas fa-file-pdf"></i></a>';
            if ($data[$i]['estado'] == 1) {
                $data[$i]['finalizar'] = '<button class="btn btn-outline-primary" type="button" title="Finalizar servicio" onclick="verFinalizar(' . $id . ');"><i class="fas fa-check"></i></button>';
                $data[$i]['estatus'] = '<span class="badge bg-warning">' . $estado . '</span>';
            } else {
                $data[$i]['finalizar'] = '';
                $data[$i]['estatus'] = '<span class="badge bg-success">' . $estado . '</span>';
            }
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function ver(int $id)
    {
        $data = $this->model->getServicio($id);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function finalizar(int $id)
    {
        $msg = $this->model->finalizar($id);
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }

    // Comprobante PDF de un servicio.
    // El modelo prepara los datos y la vista Views/servicios/pdf-comprobante.php dibuja el PDF.
    public function pdfServicio($id)
    {
        $data = $this->model->datosComprobante((int) $id);
        // Servicio inexistente (ej. servicios/pdfServicio/999): página de error en vez de un PDF roto
        if (empty($data)) {
            header('Location: ' . base_url . 'errors');
            exit;
        }
        $this->views->getView("servicios/pdf-comprobante", $data);
    }

    // Reporte PDF de todos los servicios.
    // El modelo prepara los datos y la vista Views/servicios/pdf-reporte.php dibuja el PDF.
    public function reporte()
    {
        $data = $this->model->datosReporte();
        if (empty($data['servicios'])) {
            echo 'No hay registro';
            return;
        }
        $this->views->getView("servicios/pdf-reporte", $data);
    }
}
