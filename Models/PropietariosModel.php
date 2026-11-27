<?php
/**
 * Modelo de Propietarios (dueños/socios de los vehículos de la cooperativa).
 * El dueño recibe por correo y WhatsApp el aviso de cada reserva aprobada de su vehículo.
 * Estados: 1 = Activo, 0 = Dado de baja.
 */
class PropietariosModel extends Query
{
    public function __construct()
    {
        parent::__construct();
    }

    /* =========================================================
     * REGLAS DE NEGOCIO
     * ========================================================= */

    /**
     * Registra un dueño nuevo (si 'id' viene vacío) o modifica uno existente.
     */
    public function guardar(array $d)
    {
        // 1. Campos obligatorios
        if (empty($d['cedula']) || empty($d['nombre']) || empty($d['correo']) || empty($d['celular'])) {
            return array('msg' => 'Todo los campos son obligatorios', 'icono' => 'warning');
        }
        // 2. Cédula (10 dígitos) o RUC (13 dígitos)
        if (!ctype_digit($d['cedula']) || !in_array(strlen($d['cedula']), array(10, 13))) {
            return array('msg' => 'La cédula debe tener 10 dígitos (o 13 si es RUC)', 'icono' => 'warning');
        }
        // 3. Correo válido (recibe la notificación con el calendario)
        if (filter_var($d['correo'], FILTER_VALIDATE_EMAIL) === false) {
            return array('msg' => 'Ingresa un correo valido', 'icono' => 'warning');
        }
        // 4. Celular: se guardan solo los dígitos; debe servir para WhatsApp
        $celular = preg_replace('/\D/', '', $d['celular']);
        if (strlen($celular) < 9 || strlen($celular) > 15) {
            return array('msg' => 'Ingresa un celular válido (ej. 0987654321)', 'icono' => 'warning');
        }
        // 5. La cédula y el correo no pueden ser de otro dueño
        $id = ($d['id'] == '') ? 0 : intval($d['id']);
        if ($this->existeCampo('cedula', $d['cedula'], $id)) {
            return array('msg' => 'Ya existe un dueño con esa cédula', 'icono' => 'warning');
        }
        if ($this->existeCampo('correo', $d['correo'], $id)) {
            return array('msg' => 'Ya existe un dueño con ese correo', 'icono' => 'warning');
        }
        // 6. Guardar
        if ($id > 0) {
            return ($this->modificarPropietario($d['cedula'], $d['nombre'], $d['correo'], $celular, $id) == 1)
                ? array('msg' => 'Dueño modificado', 'icono' => 'success')
                : array('msg' => 'Error al modificar', 'icono' => 'error');
        }
        return ($this->registrarPropietario($d['cedula'], $d['nombre'], $d['correo'], $celular) == 1)
            ? array('msg' => 'Dueño registrado con éxito', 'icono' => 'success')
            : array('msg' => 'Error al registrar', 'icono' => 'error');
    }

    /**
     * Da de baja un dueño (estado 0), solo si no tiene vehículos activos.
     */
    public function darDeBaja(int $id)
    {
        if ($this->vehiculosActivos($id) > 0) {
            return array('msg' => 'No se puede dar de baja: el dueño tiene vehículos activos', 'icono' => 'warning');
        }
        return ($this->accionPropietario(0, $id) == 1)
            ? array('msg' => 'Dueño dado de baja', 'icono' => 'success')
            : array('msg' => 'Error al eliminar', 'icono' => 'error');
    }

    /* =========================================================
     * ACCESO A DATOS
     * ========================================================= */

    public function getPropietarios(int $estado)
    {
        $sql = "SELECT p.*, (SELECT COUNT(*) FROM vehiculos v WHERE v.id_propietario = p.id AND v.estado != 0) AS vehiculos
                FROM propietarios p WHERE p.estado = ?";
        return $this->selectAll($sql, array($estado));
    }

    public function getPropietario(int $id)
    {
        $sql = "SELECT * FROM propietarios WHERE id = ?";
        return $this->select($sql, array($id));
    }

    // ¿Hay OTRO dueño (distinto de $id) con ese valor? $campo solo puede ser 'cedula' o 'correo'.
    private function existeCampo($campo, $valor, int $id)
    {
        if (!in_array($campo, array('cedula', 'correo'))) {
            return false;
        }
        $sql = "SELECT id FROM propietarios WHERE $campo = ? AND id != ? AND estado = 1";
        return !empty($this->select($sql, array($valor, $id)));
    }

    private function vehiculosActivos(int $id)
    {
        $data = $this->select("SELECT COUNT(*) AS total FROM vehiculos WHERE id_propietario = ? AND estado != 0", array($id));
        return $data['total'];
    }

    public function registrarPropietario($cedula, $nombre, $correo, $celular)
    {
        $sql = "INSERT INTO propietarios(cedula, nombre, correo, celular) VALUES (?,?,?,?)";
        return $this->save($sql, array($cedula, $nombre, $correo, $celular));
    }

    public function modificarPropietario($cedula, $nombre, $correo, $celular, int $id)
    {
        $sql = "UPDATE propietarios SET cedula = ?, nombre = ?, correo = ?, celular = ? WHERE id = ?";
        return $this->save($sql, array($cedula, $nombre, $correo, $celular, $id));
    }

    public function accionPropietario(int $estado, int $id)
    {
        $sql = "UPDATE propietarios SET estado = ? WHERE id = ?";
        return $this->save($sql, array($estado, $id));
    }
}
