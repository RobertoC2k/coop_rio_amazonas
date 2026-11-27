<?php
/**
 * Modelo de Servicios de transporte pesado.
 * Un servicio es el trabajo o viaje que la cooperativa realiza para un cliente
 * con uno de sus vehículos, durante un periodo (fecha_inicio a fecha_fin).
 * Estados: 1 = En curso, 0 = Finalizado.
 */
class ServiciosModel extends Query
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
        // 1. Campos obligatorios
        if (
            empty($d['id_cli']) || empty($d['id_veh']) || empty($d['select_cliente']) || empty($d['select_vehiculo'])
            || empty($d['cantidad']) || empty($d['precios']) || empty($d['abono']) || empty($d['fecha'])
        ) {
            return array('msg' => 'Todo los campos con * son requeridos', 'icono' => 'warning');
        }

        // 2. El vehículo debe existir
        $vehiculo = $this->getVehiculo($d['id_veh']);
        if (empty($vehiculo)) {
            return array('msg' => 'El vehículo no existe', 'icono' => 'error');
        }

        // 3. Calcular precio unitario y fecha de fin según la tarifa elegida
        $tarifa = $this->calcularTarifa($vehiculo, $d['precios'], $d['cantidad'], $d['fecha']);

        // 4. El vehículo no debe estar ocupado en esas fechas
        $ocupado = $this->buscarCruceDeFechas($tarifa['inicio'], $tarifa['fin'], $d['id_veh'], $d['id_cli']);
        if (!empty($ocupado)) {
            return array('msg' => 'El vehículo está ocupado: ' . $ocupado['desde'] . ' hasta ' . $ocupado['hasta'], 'icono' => 'error');
        }

        // 5. Guardar
        $id = $this->registrarServicio(
            $d['cantidad'], $d['precios'], $tarifa['monto'], $d['abono'], $tarifa['inicio'], $tarifa['fin'],
            $d['observacion'], $d['id_cli'], $d['id_veh']
        );
        if ($id > 0) {
            return array('msg' => 'Servicio registrado con éxito', 'icono' => 'success', 'id_servicio' => $id);
        }
        return array('msg' => 'Error al registrar el servicio', 'icono' => 'error');
    }

    /**
     * Tarifa: 1 = por hora, 2 = por día, 3 = por mes.
     * Devuelve el precio unitario (monto) y las fechas de inicio y fin.
     */
    public function calcularTarifa($vehiculo, $tipoPrecio, $cantidad, $fecha)
    {
        if ($tipoPrecio == 1) {
            $suma = '+' . $cantidad . ' hours';
            $monto = $vehiculo['precio_hora'];
        } else if ($tipoPrecio == 2) {
            $suma = '+' . $cantidad . ' days';
            $monto = $vehiculo['precio_dia'];
        } else {
            $suma = '+' . $cantidad . ' month';
            $monto = $vehiculo['precio_mes'];
        }
        $inicio = date("Y-m-d H:i:s", strtotime($fecha));
        $fin = date("Y-m-d H:i:s", strtotime($fecha . ' ' . $suma));
        return array('monto' => $monto, 'inicio' => $inicio, 'fin' => $fin);
    }

    /**
     * Dos periodos se cruzan si uno empieza antes de que el otro termine
     * y termina después de que el otro empiece.
     * Se revisan:
     *  - las reservas de OTROS clientes que no fueron rechazadas (la del mismo cliente es la que se está concretando),
     *  - los servicios que siguen en curso (estado 1).
     * Devuelve el primer cruce encontrado o false si el vehículo está libre.
     */
    public function buscarCruceDeFechas($desde, $hasta, $id_veh, $id_cli)
    {
        $sql = "SELECT f_inicio AS desde, f_fin AS hasta FROM reservas
                WHERE id_vehiculo = ? AND id_cliente != ? AND estado != 2
                AND f_inicio < ? AND f_fin > ?";
        $reserva = $this->select($sql, array($id_veh, $id_cli, $hasta, $desde));
        if (!empty($reserva)) {
            return $reserva;
        }

        $sql = "SELECT fecha_inicio AS desde, fecha_fin AS hasta FROM servicios
                WHERE id_vehiculo = ? AND estado = 1
                AND fecha_inicio < ? AND fecha_fin > ?";
        return $this->select($sql, array($id_veh, $hasta, $desde));
    }

    // Cierra el servicio (estado 0 = Finalizado)
    public function finalizar($id)
    {
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
        return ($servicio['cantidad'] * $servicio['monto']) - $servicio['abono'];
    }

    public function textoTipoPrecio($tipo)
    {
        if ($tipo == 1) {
            return 'HORAS: ';
        } else if ($tipo == 2) {
            return 'DIAS: ';
        }
        return 'MESES: ';
    }

    public function textoEstado($estado)
    {
        return ($estado == 1) ? 'En curso' : 'Finalizado';
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
    public function actualizarEstado(int $estado, int $id)
    {
        $sql = "UPDATE servicios SET estado = ? WHERE id = ?";
        $data = $this->save($sql, array($estado, $id));
        return ($data == 1) ? 'ok' : 'error';
    }
}
