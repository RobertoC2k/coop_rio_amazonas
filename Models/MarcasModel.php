<?php
/**
 * Modelo de Marcas de vehículos (catálogo de apoyo de la flota).
 * Estados: 1 = Activa, 0 = Dada de baja.
 */
class MarcasModel extends Query
{
    public function __construct()
    {
        parent::__construct();
    }

    /* =========================================================
     * REGLAS DE NEGOCIO
     * ========================================================= */

    /**
     * Registra una marca nueva (si 'id' viene vacío) o modifica una existente.
     */
    public function guardar(array $d)
    {
        // 1. Campo obligatorio
        if (empty($d['nombre'])) {
            return array('msg' => 'El nombre es requerido', 'icono' => 'warning');
        }
        // 2. No puede haber dos marcas con el mismo nombre (tampoco al modificar)
        $id = ($d['id'] == '') ? 0 : intval($d['id']);
        if ($this->existeMarca($d['nombre'], $id)) {
            return array('msg' => 'La marca ya existe', 'icono' => 'warning');
        }
        // 3. Guardar
        if ($id > 0) {
            return ($this->modificarMarca($d['nombre'], $id) == 1)
                ? array('msg' => 'Marca modificada', 'icono' => 'success')
                : array('msg' => 'Error al modificar', 'icono' => 'error');
        }
        return ($this->registrarMarca($d['nombre']) == 1)
            ? array('msg' => 'Marca registrada con éxito', 'icono' => 'success')
            : array('msg' => 'Error al registrar', 'icono' => 'error');
    }

    /**
     * Da de baja una marca (estado 0), solo si ningún vehículo activo la usa.
     */
    public function darDeBaja(int $id)
    {
        if ($this->vehiculosActivos($id) > 0) {
            return array('msg' => 'No se puede dar de baja: hay vehículos activos de esta marca', 'icono' => 'warning');
        }
        return ($this->accionMarca(0, $id) == 1)
            ? array('msg' => 'Marca dada de baja', 'icono' => 'success')
            : array('msg' => 'Error al eliminar', 'icono' => 'error');
    }

    /* =========================================================
     * ACCESO A DATOS
     * ========================================================= */

    public function getMarcas(int $estado)
    {
        $sql = "SELECT * FROM marcas WHERE estado = ?";
        return $this->selectAll($sql, array($estado));
    }

    public function getMarca(int $id)
    {
        $sql = "SELECT * FROM marcas WHERE id = ?";
        return $this->select($sql, array($id));
    }

    // ¿Hay OTRA marca activa (distinta de $id) con ese nombre?
    private function existeMarca($marca, int $id)
    {
        $sql = "SELECT id FROM marcas WHERE marca = ? AND id != ? AND estado = 1";
        return !empty($this->select($sql, array($marca, $id)));
    }

    // Cantidad de vehículos que no están dados de baja y usan esta marca
    private function vehiculosActivos(int $id)
    {
        $data = $this->select("SELECT COUNT(*) AS total FROM vehiculos WHERE id_marca = ? AND estado != 0", array($id));
        return $data['total'];
    }

    public function registrarMarca(string $marca)
    {
        $sql = "INSERT INTO marcas(marca) VALUES (?)";
        return $this->save($sql, array($marca));
    }

    public function modificarMarca(string $marca, int $id)
    {
        $sql = "UPDATE marcas SET marca = ? WHERE id = ?";
        return $this->save($sql, array($marca, $id));
    }

    public function accionMarca(int $estado, int $id)
    {
        $sql = "UPDATE marcas SET estado = ? WHERE id = ?";
        return $this->save($sql, array($estado, $id));
    }
}
