<?php
/**
 * VISTA: reporte PDF de todos los servicios (antes se armaba dentro del controlador).
 * Dibujar el PDF es presentación, igual que el HTML de las demás vistas.
 * Recibe $data de Servicios::reporte(), preparado por ServiciosModel::datosReporte():
 *   empresa, servicios (cada uno con su texto_estado).
 */
$empresa = $data['empresa'];

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
// Encabezado de la tabla
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
// Una fila por servicio; el estado en rojo si sigue en curso, en verde si finalizó
foreach ($data['servicios'] as $row) {
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
    $pdf->Cell(30, 5, $row['texto_estado'], 1, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);
}
$pdf->Output();
