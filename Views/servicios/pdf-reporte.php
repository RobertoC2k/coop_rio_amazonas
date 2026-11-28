<?php
/**
 * VISTA: reporte PDF de todos los servicios (antes se armaba dentro del controlador).
 * Dibujar el PDF es presentación, igual que el HTML de las demás vistas.
 * Recibe $data de Servicios::reporte(), preparado por ServiciosModel::datosReporte():
 *   empresa, servicios (cada uno con texto_estado, total y pendiente), totales y abonos.
 */
$empresa = $data['empresa'];
$totales = $data['totales'];

require_once 'Libraries/fpdf/fpdf.php';
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

/* ---------- Detalle de servicios ---------- */
$pdf->SetFillColor(0, 0, 0);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(287, 5, 'Detalle de Servicios', 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 9);
$pdf->SetFillColor(155, 155, 155);
// Anchos de columna: suman 287 mm, el ancho útil de la hoja A4 horizontal
$pdf->Cell(55, 5, 'Cliente', 1, 0, 'L', true);
$pdf->Cell(22, 5, 'Placa', 1, 0, 'L', true);
$pdf->Cell(40, 5, textoPdf('Vehículo'), 1, 0, 'L', true);
$pdf->Cell(33, 5, 'F. Inicio', 1, 0, 'L', true);
$pdf->Cell(33, 5, 'F. Fin', 1, 0, 'L', true);
$pdf->Cell(12, 5, 'Cant', 1, 0, 'C', true);
$pdf->Cell(20, 5, 'Precio', 1, 0, 'R', true);
$pdf->Cell(22, 5, 'Total', 1, 0, 'R', true);
$pdf->Cell(22, 5, 'Pagado', 1, 0, 'R', true);
$pdf->Cell(28, 5, 'Estado', 1, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);
// Una fila por servicio; el estado en rojo si sigue en curso, en verde si finalizó
foreach ($data['servicios'] as $row) {
    $pdf->Cell(55, 5, textoPdf($row['nombre']), 1, 0, 'L');
    $pdf->Cell(22, 5, $row['placa'], 1, 0, 'L');
    $pdf->Cell(40, 5, textoPdf($row['tipo']), 1, 0, 'L');
    $pdf->Cell(33, 5, $row['fecha_inicio'], 1, 0, 'L');
    $pdf->Cell(33, 5, $row['fecha_fin'], 1, 0, 'L');
    $pdf->Cell(12, 5, $row['cantidad'], 1, 0, 'C');
    $pdf->Cell(20, 5, number_format($row['monto'], 2), 1, 0, 'R');
    $pdf->Cell(22, 5, number_format($row['total'], 2), 1, 0, 'R');
    $pdf->Cell(22, 5, number_format($row['abono'], 2), 1, 0, 'R');
    if ($row['estado'] == 1) {
        $pdf->SetTextColor(255, 0, 0);
    } else {
        $pdf->SetTextColor(0, 128, 0);
    }
    $pdf->Cell(28, 5, $row['texto_estado'], 1, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);
}
// Totales: lo facturado, lo realmente pagado (suma de abonos) y lo que falta cobrar
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(215, 5, 'TOTALES', 1, 0, 'R');
$pdf->Cell(22, 5, number_format($totales['total'], 2), 1, 0, 'R');
$pdf->Cell(22, 5, number_format($totales['pagado'], 2), 1, 0, 'R');
$pdf->Cell(28, 5, '', 1, 1, 'L');
$pdf->Cell(237, 5, 'PENDIENTE POR COBRAR', 1, 0, 'R');
$pdf->Cell(22, 5, number_format($totales['pendiente'], 2), 1, 0, 'R');
$pdf->Cell(28, 5, '', 1, 1, 'L');

/* ---------- Detalle de abonos (cada pago recibido) ---------- */
$pdf->Ln(8);
$pdf->SetFillColor(0, 0, 0);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(287, 5, 'Detalle de Abonos', 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 9);
$pdf->SetFillColor(155, 155, 155);
$pdf->Cell(45, 5, 'Fecha', 1, 0, 'L', true);
$pdf->Cell(25, 5, 'Servicio', 1, 0, 'C', true);
$pdf->Cell(110, 5, 'Cliente', 1, 0, 'L', true);
$pdf->Cell(70, 5, 'Cobrado por', 1, 0, 'L', true);
$pdf->Cell(37, 5, 'Monto', 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);
if (empty($data['abonos'])) {
    $pdf->Cell(287, 5, 'Sin abonos registrados', 1, 1, 'C');
}
foreach ($data['abonos'] as $abono) {
    $pdf->Cell(45, 5, $abono['fecha'], 1, 0, 'L');
    $pdf->Cell(25, 5, '#' . $abono['id_servicio'], 1, 0, 'C');
    $pdf->Cell(110, 5, textoPdf($abono['cliente']), 1, 0, 'L');
    $pdf->Cell(70, 5, textoPdf($abono['usuario']), 1, 0, 'L');
    $pdf->Cell(37, 5, number_format($abono['monto'], 2), 1, 1, 'R');
}
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(250, 5, 'TOTAL PAGADO', 1, 0, 'R');
$pdf->Cell(37, 5, number_format($totales['pagado'], 2), 1, 1, 'R');

$pdf->Output();
