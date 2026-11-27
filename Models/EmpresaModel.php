<?php
/**
 * Modelo de Empresa (datos de la cooperativa: RUC, nombre, contacto y logo).
 * La tabla configuracion tiene un único registro.
 */
class EmpresaModel extends Query
{
    public function __construct()
    {
        parent::__construct();
    }

    /* =========================================================
     * REGLAS DE NEGOCIO
     * ========================================================= */

    /**
     * Actualiza los datos de la cooperativa y, si se subió, el logo.
     * $archivo es el elemento de $_FILES (puede venir sin nombre si no se cambia el logo).
     */
    public function actualizar(array $d, $archivo)
    {
        // 1. Campos obligatorios
        if (empty($d['id']) || empty($d['ruc']) || empty($d['nombre']) || empty($d['telefono']) || empty($d['correo']) || empty($d['direccion'])) {
            return array('msg' => 'Todos los campos son requeridos', 'icono' => 'warning');
        }
        // 2. Correo válido y RUC solo con dígitos.
        //    El RUC se guarda como texto: si se convierte a número se pierde el 0 inicial.
        if (filter_var($d['correo'], FILTER_VALIDATE_EMAIL) === false) {
            return array('msg' => 'Ingrese un correo valido', 'icono' => 'warning');
        }
        if (!ctype_digit($d['ruc'])) {
            return array('msg' => 'El RUC solo debe contener números', 'icono' => 'warning');
        }
        // 3. El logo (si se sube) debe ser PNG. Se revisa ANTES de guardar nada.
        $logoNuevo = !empty($archivo['name']);
        if ($logoNuevo && strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION)) != 'png') {
            return array('msg' => 'El logo debe ser una imagen PNG', 'icono' => 'warning');
        }
        // 4. Guardar los datos y, si se guardaron, reemplazar el logo
        $ok = $this->modificar($d['ruc'], $d['nombre'], $d['telefono'], $d['correo'], $d['direccion'], $d['mensaje'], 'logo.png', $d['id']);
        if ($ok != 1) {
            return array('msg' => 'Error al modificar', 'icono' => 'error');
        }
        if ($logoNuevo) {
            move_uploaded_file($archivo['tmp_name'], 'Assets/img/logo.png');
        }
        return array('msg' => 'Datos modificado con éxito', 'icono' => 'success');
    }

    /* =========================================================
     * ACCESO A DATOS
     * ========================================================= */

    public function getEmpresa()
    {
        $sql = "SELECT * FROM configuracion";
        return $this->select($sql);
    }

    public function modificar(string $ruc, string $nombre, string $tel, string $correo, string $dir, string $mensaje, string $img, int $id)
    {
        $sql = "UPDATE configuracion SET ruc=?, nombre = ?, telefono =?, correo=?, direccion=?, mensaje=?, logo = ? WHERE id=?";
        return $this->save($sql, array($ruc, $nombre, $tel, $correo, $dir, $mensaje, $img, $id));
    }
}
