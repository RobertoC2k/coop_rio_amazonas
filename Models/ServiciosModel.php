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
        $abono = str_replace(',', '.', trim($d['abono']));   // acepta 10,50 o 10.50
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $abono)) {
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
            return array('msg' => 'Servicio registrado con éxito', 'icono' => 'success', 'id_servicio' => $id);
        }
        return array('msg' => 'Error al registrar el servicio', 'icono' => 'error');
    }

    // validarCantidad(), calcularTarifa() y buscarCruceDeFechas() se heredan de
    // ReglasTransporte (Models/ReglasTransporte.php): son las mismas reglas para reservas y servicios.

    // Cierra el servicio (estado 0 = Finalizado)
    public function finalizar($id)
    {
        // Solo se finaliza un servicio que existe y sigue en curso. Antes se respondía
        // "Servicio finalizado" siempre, porque save() devuelve 1 si la consulta se ejecuta,
        // aunque no haya cambiado ninguna fila.
        $servicio = $this->getEstadoServicio($id);
        if (empty($servicio)) {
            return array('msg' => 'El servicio no existe', 'icono' => 'error');
        }
        if ($servicio['estado'] != 1) {
            return array('msg' => 'El servicio ya estaba finalizado', 'icono' => 'warning');
        }
        if ($this->actualizarEstado(0, $id) == 'ok') {
            return array('msg' => 'Servicio finalizado', 'icono' => 'success');
        }
        return array('msg' => 'Error al finalizar el servicio', 'icono' => 'error');
    }

    // Lo que falta pagar: (cantidad x precio unitario) - abono. Si ya finalizó, no debe nada.
    public function calcularSaldoPendiente($servicio)
    {
        if ($servicio['estado'] == 0) {
            return 0;
        }
        return round($this->calcularTotal($servicio['cantidad'], $servicio['monto']) - $servicio['abono'], 2);
    }

    public function textoEstado($estado)
    {
        return ($estado == 1) ? 'En curso' : 'Finalizado';
    }

    /**
     * Datos listos para el comprobante PDF de un servicio (Views/servicios/pdf-comprobante.php).
     * El modelo prepara textos y cálculos; la vista solo dibuja el PDF.
     * Devuelve null si el servicio no existe.
     */
    public function datosComprobante(int $id)
    {
        $servicio = $this->getServicio($id);
        if (empty($servicio)) {
            return null;
        }
        return array(
            'empresa'     => $this->getEmpresa(),
            'servicio'    => $servicio,
            'texto_estado' => $this->textoEstado($servicio['estado']),
            'texto_tipo'  => $this->textoTipoPrecio($servicio['tipo_precio']),
            'pendiente'   => $this->calcularSaldoPendiente($servicio),
        );
    }

    /**
     * Datos listos para el reporte PDF de todos los servicios (Views/servicios/pdf-reporte.php).
     * Cada servicio lleva ya su texto de estado. 'servicios' viene vacío si no hay registros.
     */
    public function datosReporte()
    {
        $servicios = $this->getServicios();
        foreach ($servicios as $i => $row) {
            $servicios[$i]['texto_estado'] = $this->textoEstado($row['estado']);
        }
        return array('empresa' => $this->getEmpresa(), 'servicios' => $servicios);
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
    public function getEmpresa()
    {
        $sql = "SELECT * FROM configuracion";
        return $this->select($sql);
    }
    public function getServicio(int $id)
    {
        $sql = "SELECT s.*, c.dni, c.nombre, c.telefono, c.direccion, v.placa, v.modelo, t.tipo FROM servicios s
                INNER JOIN clientes c ON c.id = s.id_cliente
                INNER JOIN vehiculos v ON v.id = s.id_vehiculo
                INNER JOIN tipos t ON t.id = v.id_tipo
                WHERE s.id = ?";
        return $this->select($sql, array($id));
    }

    public function registrarServicio($cantidad, $precios, $monto, $abono, $inicio, $fin, $observacion, $id_cli, $id_veh)
    {
        $sql = "INSERT INTO servicios(cantidad, tipo_precio, monto, abono, fecha_inicio, fecha_fin, observacion, id_cliente, id_vehiculo) VALUES (?,?,?,?,?,?,?,?,?)";
        $datos = array($cantidad, $precios, $monto, $abono, $inicio, $fin, $observacion, $id_cli, $id_veh);
        return $this->insertar($sql, $datos);
    }
    public function getEstadoServicio(int $id)
    {
        return $this->select("SELECT id, estado FROM servicios WHERE id = ?", array($id));
    }

    public function actualizarEstado(int $estado, int $id)
    {
        $sql = "UPDATE servicios SET estado = ? WHERE id = ?";
        $data = $this->save($sql, array($estado, $id));
        return ($data == 1) ? 'ok' : 'error';
    }
}
