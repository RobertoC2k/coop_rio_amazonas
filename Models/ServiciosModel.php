<?php
/**
 * Modelo de Servicios de transporte pesado.
 * Un servicio es el trabajo o viaje que la cooperativa realiza para un cliente
 * con uno de sus vehículos, durante un periodo (fecha_inicio a fecha_fin).
 * Estados: 1 = En curso, 0 = Finalizado.
 */
require_once "Models/ReglasTransporte.php";

class ServiciosModel extends ReglasTransporte
{
    public function __construct()
    {
        parent::__construct();
    }

    /* =========================================================
     * REGLAS DE NEGOCIO
     * ========================================================= */

    /**
     * Registra un servicio aplicando todas las reglas del negocio.
     * Recibe los datos del formulario y devuelve la respuesta para la vista
     * ('msg', 'icono' y, si se registró, 'id_servicio').
     */
    public function registrar(array $d)
    {
        // 1. Campos obligatorios. El abono se revisa aparte: empty("0") es verdadero en PHP,
        //    y un abono de 0 (el cliente aún no paga nada) sí es válido.
        if (
            empty($d['id_cli']) || empty($d['id_veh']) || empty($d['select_cliente']) || empty($d['select_vehiculo'])
            || empty($d['cantidad']) || empty($d['precios']) || trim($d['abono']) === '' || empty($d['fecha'])
        ) {
            return array('msg' => 'Todos los campos con * son requeridos', 'icono' => 'warning');
        }

        // 2. Cantidad entera dentro del máximo y tarifa válida (1 = hora, 2 = día, 3 = mes)
        $error = $this->validarCantidad($d['cantidad'], $d['precios']);
        if ($error != null) {
            return array('msg' => $error, 'icono' => 'warning');
        }

        // 3. La fecha de inicio debe ser válida y no anterior a hoy
        //    (se permite hoy porque el personal registra servicios que empiezan en el día)
        if (strtotime($d['fecha']) === false || strtotime($d['fecha']) < strtotime('today')) {
            return array('msg' => 'La fecha de inicio no puede ser anterior a hoy', 'icono' => 'warning');
        }

        // 4. El cliente y el vehículo deben existir y estar activos
        if (empty($this->getClienteActivo($d['id_cli']))) {
            return array('msg' => 'El cliente no existe o está dado de baja', 'icono' => 'error');
        }
        $vehiculo = $this->getVehiculo($d['id_veh']);
        if (empty($vehiculo) || $vehiculo['estado'] == 0) {
            return array('msg' => 'El vehículo no existe', 'icono' => 'error');
        }

        // 5. Calcular precio unitario y fecha de fin según la tarifa elegida
        $tarifa = $this->calcularTarifa($vehiculo, $d['precios'], $d['cantidad'], $d['fecha']);

        // 6. El abono es un número de 0 en adelante (hasta 2 decimales) y no supera el total
        $abono = $this->normalizarMonto($d['abono']);
        if ($abono === null) {
            return array('msg' => 'El abono debe ser un número (ejemplo: 50 o 50.25)', 'icono' => 'warning');
        }
        // calcularTotal redondea a centavos (ver ReglasTransporte): sin eso, un abono
        // igual al total se rechazaba (3 x 1.15 = 3.4499999999...)
        $total = $this->calcularTotal($d['cantidad'], $tarifa['monto']);
        if ((float) $abono > $total) {
            return array('msg' => 'El abono no puede ser mayor al total del servicio ($' . number_format($total, 2) . ')', 'icono' => 'warning');
        }
        $d['abono'] = $abono;

        // 7. El vehículo no debe estar ocupado en esas fechas
        $ocupado = $this->buscarCruceDeFechas($tarifa['inicio'], $tarifa['fin'], $d['id_veh'], $d['id_cli']);
        if (!empty($ocupado)) {
            return array('msg' => 'El vehículo está ocupado: ' . $ocupado['desde'] . ' hasta ' . $ocupado['hasta'], 'icono' => 'error');
        }

        // 8. Guardar
        $id = $this->registrarServicio(
            $d['cantidad'], $d['precios'], $tarifa['monto'], $d['abono'], $tarifa['inicio'], $tarifa['fin'],
            $d['observacion'], $d['id_cli'], $d['id_veh']
        );
        if ($id > 0) {
            // El abono inicial también queda en el historial de pagos
            if ((float) $abono > 0) {
                $this->insertarAbono($id, $abono, $d['id_usuario']);
            }
            return array('msg' => 'Servicio registrado con éxito', 'icono' => 'success', 'id_servicio' => $id);
        }
        return array('msg' => 'Error al registrar el servicio', 'icono' => 'error');
    }

    /**
     * Convierte el texto del monto a número con punto decimal ("10,50" -> "10.50").
     * Devuelve null si no es un número de 0 en adelante con hasta 2 decimales.
     */
    private function normalizarMonto($texto)
    {
        $monto = str_replace(',', '.', trim((string) $texto));
        return preg_match('/^\d+(\.\d{1,2})?$/', $monto) ? $monto : null;
    }

    /**
     * Registra un pago parcial (abono) de un servicio.
     * Reglas: el servicio existe y todavía debe algo; el monto es mayor que 0
     * y no supera lo que falta pagar. Se guarda quién lo cobró ($idUsuario).
     * No se exige que esté "En curso": los servicios finalizados antes de esta regla
     * pudieron quedar con saldo, y así se pueden terminar de cobrar.
     */
    public function registrarAbono($idServicio, $montoTexto, $idUsuario)
    {
        $servicio = $this->getServicio((int) $idServicio);
        if (empty($servicio)) {
            return array('msg' => 'El servicio no existe', 'icono' => 'error');
        }
        $pendiente = $this->calcularSaldoPendiente($servicio);
        if ($pendiente <= 0) {
            return array('msg' => 'El servicio ya está pagado en su totalidad', 'icono' => 'warning');
        }
        $monto = $this->normalizarMonto($montoTexto);
        if ($monto === null || (float) $monto <= 0) {
            return array('msg' => 'El abono debe ser un número mayor que 0 (ejemplo: 50 o 50.25)', 'icono' => 'warning');
        }
        if ((float) $monto > $pendiente) {
            return array('msg' => 'El abono no puede ser mayor a lo pendiente ($' . number_format($pendiente, 2) . ')', 'icono' => 'warning');
        }
        if ($this->insertarAbono($servicio['id'], $monto, $idUsuario) == 0) {
            return array('msg' => 'Error al registrar el abono', 'icono' => 'error');
        }
        return array('msg' => 'Abono registrado', 'icono' => 'success');
    }

    /**
     * Datos para la ventana de abonos: totales del servicio y el historial de pagos.
     * Devuelve null si el servicio no existe.
     */
    public function datosAbonos($idServicio)
    {
        $servicio = $this->getServicio((int) $idServicio);
        if (empty($servicio)) {
            return null;
        }
        return array(
            'id'        => $servicio['id'],
            'estado'    => $servicio['estado'],
            'total'     => $this->calcularTotal($servicio['cantidad'], $servicio['monto']),
            'abonado'   => (float) $servicio['abono'],
            'pendiente' => $this->calcularSaldoPendiente($servicio),
            'abonos'    => $this->getAbonos($servicio['id']),
        );
    }

    // validarCantidad(), calcularTarifa() y buscarCruceDeFechas() se heredan de
    // ReglasTransporte (Models/ReglasTransporte.php): son las mismas reglas para reservas y servicios.

    // Cierra el servicio (estado 0 = Finalizado)
    public function finalizar($id)
    {
        // Solo se finaliza un servicio que existe y sigue en curso. Antes se respondía
        // "Servicio finalizado" siempre, porque save() devuelve 1 si la consulta se ejecuta,
        // aunque no haya cambiado ninguna fila.
        $servicio = $this->getServicio((int) $id);
        if (empty($servicio)) {
            return array('msg' => 'El servicio no existe', 'icono' => 'error');
        }
        if ($servicio['estado'] != 1) {
            return array('msg' => 'El servicio ya estaba finalizado', 'icono' => 'warning');
        }
        // Solo se finaliza un servicio pagado en su totalidad
        $pendiente = $this->calcularSaldoPendiente($servicio);
        if ($pendiente > 0) {
            return array('msg' => 'No se puede finalizar: falta pagar $' . number_format($pendiente, 2) . '. Registra el abono primero', 'icono' => 'warning');
        }
        if ($this->actualizarEstado(0, $id) == 'ok') {
            return array('msg' => 'Servicio finalizado', 'icono' => 'success');
        }
        return array('msg' => 'Error al finalizar el servicio', 'icono' => 'error');
    }

    // calcularSaldoPendiente(), textoEstado() y datosComprobante() se heredan de
    // ReglasTransporte: ReservasModel también arma el comprobante para enviarlo por correo.

    /**
     * Datos listos para el reporte PDF de todos los servicios (Views/servicios/pdf-reporte.php).
     * Cada servicio lleva ya su texto de estado. 'servicios' viene vacío si no hay registros.
     */
    public function datosReporte()
    {
        $servicios = $this->getServicios();
        $totales = array('total' => 0, 'pagado' => 0, 'pendiente' => 0);
        foreach ($servicios as $i => $row) {
            $servicios[$i]['texto_estado'] = $this->textoEstado($row['estado']);
            $servicios[$i]['total'] = $this->calcularTotal($row['cantidad'], $row['monto']);
            $servicios[$i]['pendiente'] = $this->calcularSaldoPendiente($row);
            $totales['total'] += $servicios[$i]['total'];
            $totales['pagado'] += (float) $row['abono'];
            $totales['pendiente'] += $servicios[$i]['pendiente'];
        }
        return array(
            'empresa'   => $this->getEmpresa(),
            'servicios' => $servicios,
            'totales'   => array_map(function ($v) { return round($v, 2); }, $totales),
            'abonos'    => $this->getTodosLosAbonos(),
        );
    }

    // Todos los pagos registrados, con el servicio y el cliente (detalle del reporte PDF)
    public function getTodosLosAbonos()
    {
        $sql = "SELECT a.fecha, a.monto, a.id_servicio, c.nombre AS cliente, IFNULL(u.nombre, '-') AS usuario
                FROM abonos a
                INNER JOIN servicios s ON s.id = a.id_servicio
                INNER JOIN clientes c ON c.id = s.id_cliente
                LEFT JOIN usuarios u ON u.id = a.id_usuario
                ORDER BY a.fecha, a.id";
        return $this->selectAll($sql);
    }

    /* =========================================================
     * ACCESO A DATOS
     * ========================================================= */

    public function getServicios()
    {
        $sql = "SELECT s.*, c.nombre, v.placa, v.modelo, t.tipo FROM servicios s
                INNER JOIN clientes c ON c.id = s.id_cliente
                INNER JOIN vehiculos v ON v.id = s.id_vehiculo
                INNER JOIN tipos t ON t.id = v.id_tipo";
        return $this->selectAll($sql);
    }
    public function getVehiculos()
    {
        $sql = "SELECT v.id, v.placa, v.id_tipo, v.id_marca, v.estado, t.id, t.tipo, m.id, m.marca FROM vehiculos v INNER JOIN tipos t ON t.id = v.id_tipo INNER JOIN marcas m ON m.id = v.id_marca WHERE v.estado = 1";
        return $this->selectAll($sql);
    }
    public function getClienteActivo($id)
    {
        $sql = "SELECT id FROM clientes WHERE id = ? AND estado = 1";
        return $this->select($sql, array($id));
    }
    public function getVehiculo($id)
    {
        $sql = "SELECT * FROM vehiculos WHERE id = ?";
        return $this->select($sql, array($id));
    }
    // getEmpresa(), getServicio() y getAbonos() se heredan de ReglasTransporte.

    /**
     * Guarda un pago en el historial y actualiza servicios.abono con la SUMA de los pagos.
     * Se recalcula la suma (en vez de sumar al valor anterior) para que nunca queden
     * descuadrados. Devuelve el id del abono (0 si falló).
     */
    public function insertarAbono($idServicio, $monto, $idUsuario)
    {
        $id = $this->insertar("INSERT INTO abonos (monto, id_servicio, id_usuario) VALUES (?,?,?)",
            array($monto, $idServicio, $idUsuario));
        if ($id > 0) {
            $this->save("UPDATE servicios SET abono = (SELECT IFNULL(SUM(monto), 0) FROM abonos WHERE id_servicio = ?) WHERE id = ?",
                array($idServicio, $idServicio));
        }
        return $id;
    }

    // registrarServicio() se hereda de ReglasTransporte: también lo usa ReservasModel al aprobar.

    public function actualizarEstado(int $estado, int $id)
    {
        $sql = "UPDATE servicios SET estado = ? WHERE id = ?";
        $data = $this->save($sql, array($estado, $id));
        return ($data == 1) ? 'ok' : 'error';
    }
}
