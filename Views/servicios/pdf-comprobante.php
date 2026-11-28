<?php
/**
 * VISTA: comprobante PDF de un servicio (antes se armaba dentro del controlador).
 * Dibujar el PDF es presentación, igual que el HTML de las demás vistas.
 * Recibe $data de Servicios::pdfServicio(), preparado por ServiciosModel::datosComprobante():
 *   empresa, servicio, texto_estado, texto_tipo, pendiente.
 */
$empresa  = $data['empresa'];
$servicio = $data['servicio'];
$tipo     = $data['texto_tipo'];

require_once 'Libraries/fpdf/html2pdf.php';

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
$pdf->Cell(50, 5, $servicio['fecha_inicio'], 0, 0, 'L');
// Estado en rojo si sigue en curso, en azul si ya finalizó
if ($servicio['estado'] == 1) {
    $pdf->SetTextColor(255, 0, 0);
} else {
    $pdf->SetTextColor(0, 0, 255);
}
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(20, 5, 'Estado: ', 0, 0, 'L');
$pdf->Cell(50, 5, $data['texto_estado'], 0, 1, 'L');
// Datos del cliente
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(135, 10, 'Datos del Cliente', 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(40, 5, 'DOC: ' . $servicio['dni'], 1, 0, 'L');
$pdf->Cell(95, 5, 'NOMBRE: ' . textoPdf($servicio['nombre']), 1, 1, 'L');
$pdf->Cell(65, 5, textoPdf('DIRECCIÓN: ' . $servicio['direccion']), 1, 0, 'L');
$pdf->Cell(70, 5, textoPdf('TELÉFONO: ' . $servicio['telefono']), 1, 1, 'L');
// Datos del servicio
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(135, 10, textoPdf('Datos del Servicio'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(65, 5, textoPdf('PLACA: ' . $servicio['placa']), 1, 0, 'L');
$pdf->Cell(70, 5, textoPdf('VEHÍCULO: ' . $servicio['tipo']), 1, 1, 'L');
$pdf->Cell(65, 5, textoPdf('MODELO: ' . $servicio['modelo']), 1, 0, 'L');
$pdf->Cell(70, 5, textoPdf($tipo . $servicio['cantidad']), 1, 1, 'L');
$pdf->Cell(65, 5, textoPdf('MONTO x ' . $tipo . $servicio['monto']), 1, 0, 'L');
$pdf->Cell(70, 5, textoPdf('ABONADO: ' . $servicio['abono']), 1, 1, 'L');
$pdf->Cell(65, 5, textoPdf('F. INICIO: ' . $servicio['fecha_inicio']), 1, 0, 'L');
$pdf->Cell(70, 5, textoPdf('F. FIN: ' . $servicio['fecha_fin']), 1, 1, 'L');
// Historial de abonos (cada pago parcial del cliente)
if (!empty($data['abonos'])) {
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(135, 10, 'Abonos', 0, 1, 'C');
    $pdf->Cell(45, 5, 'FECHA', 1, 0, 'L');
    $pdf->Cell(30, 5, 'MONTO', 1, 0, 'L');
    $pdf->Cell(60, 5, 'COBRADO POR', 1, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    foreach ($data['abonos'] as $abono) {
        $pdf->Cell(45, 5, $abono['fecha'], 1, 0, 'L');
        $pdf->Cell(30, 5, number_format($abono['monto'], 2), 1, 0, 'L');
        $pdf->Cell(60, 5, textoPdf($abono['usuario']), 1, 1, 'L');
    }
}
$pdf->Ln();
$pdf->Cell(135, 5, textoPdf('PENDIENTE: ' . number_format($data['pendiente'], 2)), 0, 1, 'C');
// Firmas
$pdf->Ln();
$pdf->Cell(65, 5, textoPdf('_____________________________'), 0, 0, 'C');
$pdf->Cell(65, 5, textoPdf('_____________________________'), 0, 1, 'C');
$pdf->Cell(65, 5, textoPdf('Firma'), 0, 0, 'C');
$pdf->Cell(65, 5, textoPdf('Huella'), 0, 1, 'C');
$pdf->Ln(2);
$pdf->WriteHtml(textoPdf($empresa['mensaje']));

// Output('S') devuelve el PDF como texto (para adjuntarlo al correo, ver pdfComoTexto en Helpers.php);
// Output() sin parámetros lo muestra en el navegador.
if (!empty($data['como_texto'])) {
    $pdfTexto = $pdf->Output('S');
} else {
    $pdf->Output();
}
