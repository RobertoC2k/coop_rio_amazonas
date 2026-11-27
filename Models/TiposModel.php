<?php
/**
 * Modelo de Tipos de vehículo (catálogo de apoyo de la flota: volqueta, cama baja, etc.).
 * Estados: 1 = Activo, 0 = Dado de baja.
 */
class TiposModel extends Query
{
    public function __construct()
    {
        parent::__construct();
    }

    /* =========================================================
     * REGLAS DE NEGOCIO
     * ========================================================= */

    /**
     * Registra un tipo nuevo (si 'id' viene vacío) o modifica uno existente.
     */
    public function guardar(array $d)
    {
        // 1. Campo obligatorio
        if (empty($d['nombre'])) {
            return array('msg' => 'El nombre es requerido', 'icono' => 'warning');
        }
        // 2. No puede haber dos tipos con el mismo nombre (tampoco al modificar)
        $id = ($d['id'] == '') ? 0 : intval($d['id']);
        if ($this->existeTipo($d['nombre'], $id)) {
            return array('msg' => 'El tipo ya existe', 'icono' => 'warning');
        }
        // 3. Guardar
        if ($id > 0) {
            return ($this->modificarTipo($d['nombre'], $id) == 1)
                ? array('msg' => 'Tipo modificado', 'icono' => 'success')
                : array('msg' => 'Error al modificar', 'icono' => 'error');
        }
        return ($this->registrarTipo($d['nombre']) == 1)
            ? array('msg' => 'Tipo registrado con éxito', 'icono' => 'success')
            : array('msg' => 'Error al registrar', 'icono' => 'error');
    }

    /**
     * Da de baja un tipo (estado 0), solo si ningún vehículo activo lo usa.
     */
    public function darDeBaja(int $id)
    {
        if ($this->vehiculosActivos($id) > 0) {
            return array('msg' => 'No se puede dar de baja: hay vehículos activos de este tipo', 'icono' => 'warning');
        }
        return ($this->accionTipo(0, $id) == 1)
            ? array('msg' => 'Tipo dado de baja', 'icono' => 'success')
            : array('msg' => 'Error al eliminar', 'icono' => 'error');
    }

    /* =========================================================
     * ACCESO A DATOS
     * ========================================================= */

    public function getTipos(int $estado)
    {
        $sql = "SELECT * FROM tipos WHERE estado = ?";
        return $this->selectAll($sql, array($estado));
    }

    public function getTipo(int $id)
    {
        $sql = "SELECT * FROM tipos WHERE id = ?";
        return $this->select($sql, array($id));
    }

    // ¿Hay OTRO tipo activo (distinto de $id) con ese nombre?
    private function existeTipo($tipo, int $id)
    {
        $sql = "SELECT id FROM tipos WHERE tipo = ? AND id != ? AND estado = 1";
        return !empty($this->select($sql, array($tipo, $id)));
    }

    // Cantidad de vehículos que no están dados de baja y son de este tipo
    private function vehiculosActivos(int $id)
    {
        $data = $this->select("SELECT COUNT(*) AS total FROM vehiculos WHERE id_tipo = ? AND estado != 0", array($id));
        return $data['total'];
    }

    public function registrarTipo(string $tipo)
    {
        $sql = "INSERT INTO tipos(tipo) VALUES (?)";
        return $this->save($sql, array($tipo));
    }

    public function modificarTipo(string $tipo, int $id)
    {
        $sql = "UPDATE tipos SET tipo = ? WHERE id = ?";
        return $this->save($sql, array($tipo, $id));
    }

    public function accionTipo(int $estado, int $id)
    {
        $sql = "UPDATE tipos SET estado = ? WHERE id = ?";
        return $this->save($sql, array($estado, $id));
    }
}
