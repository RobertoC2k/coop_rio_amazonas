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

    // Comprobante PDF de un servicio
    public function pdfServicio($id)
    {
        $empresa = $this->model->getEmpresa();
        $data = $this->model->getServicio($id);
        require('Libraries/fpdf/html2pdf.php');

        $pdf = new PDF_HTML('P', 'mm', array(210, 148));
        $pdf->AddPage();
        $pdf->SetMargins(10, 0, 0);
        $pdf->SetTitle('Comprobante de Servicio', true);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(135, 8, textoPdf($empresa['nombre']), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(20, 5, 'Ruc: ', 0, 0, 'L');
        $pdf->Cell(50, 5, $empresa['ruc'], 0, 0, 'L');
        $pdf->Cell(20, 5, textoPdf('Teléfono: '), 0, 0, 'L');
        $pdf->Cell(50, 5, $empresa['telefono'], 0, 1, 'L');
        $pdf->Cell(20, 5, textoPdf('Correo: '), 0, 0, 'L');
        $pdf->Cell(50, 5, textoPdf($empresa['correo']), 0, 0, 'L');
        $pdf->Cell(20, 5, textoPdf('Dirección: '), 0, 0, 'L');
        $pdf->Cell(50, 5, textoPdf($empresa['direccion']), 0, 1, 'L');
        $pdf->Cell(20, 5, 'Fecha: ', 0, 0, 'L');
        $pdf->Cell(50, 5, $data['fecha_inicio'], 0, 0, 'L');
        $estado = $this->model->textoEstado($data['estado']);
        if ($data['estado'] == 1) {
            $pdf->SetTextColor(255, 0, 0);
        } else {
            $pdf->SetTextColor(0, 0, 255);
        }
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(20, 5, 'Estado: ', 0, 0, 'L');
        $pdf->Cell(50, 5, $estado, 0, 1, 'L');
        //Encabezado
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(135, 10, 'Datos del Cliente', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(40, 5, 'DOC: ' . $data['dni'], 1, 0, 'L');
        $pdf->Cell(95, 5, 'NOMBRE: ' . textoPdf($data['nombre']), 1, 1, 'L');
        $pdf->Cell(65, 5, textoPdf('DIRECCIÓN: ' . $data['direccion']), 1, 0, 'L');
        $pdf->Cell(70, 5, textoPdf('TELÉFONO: ' . $data['telefono']), 1, 1, 'L');
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(135, 10, textoPdf('Datos del Servicio'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(65, 5, textoPdf('PLACA: ' . $data['placa']), 1, 0, 'L');
        $pdf->Cell(70, 5, textoPdf('VEHÍCULO: ' . $data['tipo']), 1, 1, 'L');
        $pdf->Cell(65, 5, textoPdf('MODELO: ' . $data['modelo']), 1, 0, 'L');
        $tipo = $this->model->textoTipoPrecio($data['tipo_precio']);
        $pdf->Cell(70, 5, textoPdf($tipo . $data['cantidad']), 1, 1, 'L');
        $pdf->Cell(65, 5, textoPdf('MONTO x ' . $tipo . $data['monto']), 1, 0, 'L');
        $pdf->Cell(70, 5, textoPdf('ABONADO: ' . $data['abono']), 1, 1, 'L');
        $pdf->Cell(65, 5, textoPdf('F. INICIO: ' . $data['fecha_inicio']), 1, 0, 'L');
        $pdf->Cell(70, 5, textoPdf('F. FIN: ' . $data['fecha_fin']), 1, 1, 'L');
        $pdf->Ln();
        $total = $this->model->calcularSaldoPendiente($data);
        $pdf->Cell(135, 5, textoPdf('PENDIENTE: ' . number_format($total, 2)), 0, 1, 'C');
        $pdf->Ln();
        $pdf->Cell(65, 5, textoPdf('_____________________________'), 0, 0, 'C');
        $pdf->Cell(65, 5, textoPdf('_____________________________'), 0, 1, 'C');
        $pdf->Cell(65, 5, textoPdf('Firma'), 0, 0, 'C');
        $pdf->Cell(65, 5, textoPdf('Huella'), 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->WriteHtml(textoPdf($empresa['mensaje']));

        $pdf->Output();
    }

    // Reporte PDF de todos los servicios
    public function reporte()
    {
        $empresa = $this->model->getEmpresa();
        $servicios = $this->model->getServicios();
        if (empty($servicios)) {
            echo 'No hay registro';
        } else {
            require('Libraries/fpdf/fpdf.php');
            $pdf = new FPDF('L', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetMargins(5, 0, 0);
            $pdf->SetTitle('Reporte de Servicios', true);
            $pdf->SetFont('Arial', '', 15);
            $pdf->Cell(280, 8, textoPdf($empresa['nombre']), 0, 1, 'C');

            $pdf->Image('Assets/img/logo.png', 250, 10, 25, 25);
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(25, 5, 'Ruc: ', 0, 0, 'L');
            $pdf->Cell(20, 5, $empresa['ruc'], 0, 1, 'L');
            $pdf->Cell(25, 5, textoPdf('Teléfono: '), 0, 0, 'L');
            $pdf->Cell(20, 5, $empresa['telefono'], 0, 1, 'L');
            $pdf->Cell(25, 5, textoPdf('Correo: '), 0, 0, 'L');
            $pdf->Cell(20, 5, textoPdf($empresa['correo']), 0, 1, 'L');
            $pdf->Cell(25, 5, textoPdf('Dirección: '), 0, 0, 'L');
            $pdf->Cell(20, 5, textoPdf($empresa['direccion']), 0, 1, 'L');
            $pdf->Ln(10);
            //Encabezado de la tabla
            $pdf->SetFillColor(0, 0, 0);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(289, 5, 'Detalle de Servicios', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 10);
            $pdf->SetFillColor(155, 155, 155);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(70, 5, 'Cliente', 1, 0, 'L', true);
            $pdf->Cell(30, 5, 'Placa', 1, 0, 'L', true);
            $pdf->Cell(50, 5, textoPdf('Vehículo'), 1, 0, 'L', true);
            $pdf->Cell(35, 5, 'F. Inicio', 1, 0, 'L', true);
            $pdf->Cell(35, 5, 'F. Fin', 1, 0, 'L', true);
            $pdf->Cell(15, 5, 'Cant', 1, 0, 'L', true);
            $pdf->Cell(24, 5, 'Monto', 1, 0, 'L', true);
            $pdf->Cell(30, 5, 'Estado', 1, 1, 'L', true);
            $pdf->SetTextColor(0, 0, 0);
            foreach ($servicios as $row) {
                $pdf->Cell(70, 5, textoPdf($row['nombre']), 1, 0, 'L');
                $pdf->Cell(30, 5, $row['placa'], 1, 0, 'L');
                $pdf->Cell(50, 5, textoPdf($row['tipo']), 1, 0, 'L');
                $pdf->Cell(35, 5, $row['fecha_inicio'], 1, 0, 'L');
                $pdf->Cell(35, 5, $row['fecha_fin'], 1, 0, 'L');
                $pdf->Cell(15, 5, $row['cantidad'], 1, 0, 'C');
                $pdf->Cell(24, 5, $row['monto'], 1, 0, 'L');
                if ($row['estado'] == 1) {
                    $pdf->SetTextColor(255, 0, 0);
                } else {
                    $pdf->SetTextColor(0, 128, 0);
                }
                $pdf->Cell(30, 5, $this->model->textoEstado($row['estado']), 1, 1, 'L');
                $pdf->SetTextColor(0, 0, 0);
            }
            $pdf->Output();
        }
    }
}
