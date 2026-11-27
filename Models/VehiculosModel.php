<?php
/**
 * Modelo de Vehículos (flota de transporte pesado de la cooperativa).
 * Estados: 1 = Activo, 2 = (en uso / no disponible), 0 = Dado de baja.
 */
class VehiculosModel extends Query
{
    private $carpeta = 'Assets/img/vehiculos/';

    public function __construct()
    {
        parent::__construct();
    }

    /* =========================================================
     * REGLAS DE NEGOCIO
     * ========================================================= */

    /**
     * Registra un vehículo nuevo (si 'id' viene vacío) o modifica uno existente.
     * $archivo es el elemento de $_FILES con la foto (puede venir sin nombre).
     * $conservarFoto indica si el formulario mantiene la foto actual
     * (si el usuario la quitó, el vehículo vuelve a la imagen por defecto).
     */
    public function guardar(array $d, $archivo, $conservarFoto)
    {
        // 1. Campos obligatorios
        $obligatorios = array('propietario', 'placa', 'marca', 'tipo', 'modelo', 'precio_hora', 'precio_dia', 'precio_mes',
            'kilometraje', 'transmision', 'asientos', 'peso_carga_max', 'combustible');
        foreach ($obligatorios as $campo) {
            if (empty($d[$campo])) {
                return array('msg' => 'Todos los campos son obligatorios', 'icono' => 'warning');
            }
        }
        // El dueño debe existir y estar activo (recibe los avisos de las reservas)
        if (!$this->propietarioActivo($d['propietario'])) {
            return array('msg' => 'Selecciona un dueño válido', 'icono' => 'warning');
        }

        // 2. Precios mayores a 0 y asientos como número entero positivo
        foreach (array('precio_hora', 'precio_dia', 'precio_mes') as $campo) {
            if (!is_numeric($d[$campo]) || $d[$campo] <= 0) {
                return array('msg' => 'Los precios deben ser números mayores a 0', 'icono' => 'warning');
            }
        }
        if (!ctype_digit((string) $d['asientos']) || $d['asientos'] <= 0) {
            return array('msg' => 'Los asientos deben ser un número entero', 'icono' => 'warning');
        }

        // 3. La placa identifica al vehículo: no puede repetirse (tampoco al modificar)
        $id = ($d['id'] == '') ? 0 : intval($d['id']);
        if ($this->existePlaca($d['placa'], $id)) {
            return array('msg' => 'El vehículo ya existe', 'icono' => 'warning');
        }

        // 4. Decidir qué foto queda
        $anterior = ($id > 0) ? $this->getVehiculo($id) : null;
        if ($id > 0 && empty($anterior)) {
            return array('msg' => 'El vehículo no existe', 'icono' => 'error');
        }
        $fotoNueva = !empty($archivo['name']);
        if ($fotoNueva) {
            // Se revisa el contenido del archivo, no solo su nombre (ver validarImagen)
            $imagen = validarImagen($archivo, array('png', 'jpg'));
            if ($imagen['error'] != null) {
                return array('msg' => $imagen['error'], 'icono' => 'warning');
            }
            $foto = date("YmdHis") . '.' . $imagen['extension'];
        } else if ($id > 0 && $conservarFoto) {
            $foto = $anterior['foto'];          // se mantiene la que ya tenía
        } else {
            $foto = 'default.png';              // vehículo nuevo sin foto, o se quitó la foto
        }

        // 5. Guardar en la base de datos
        $valores = array(
            $d['placa'], $d['precio_hora'], $d['precio_dia'], $d['precio_mes'], $d['modelo'], $d['kilometraje'],
            $d['transmision'], $d['asientos'], $d['peso_carga_max'], $d['combustible'], $foto, $d['tipo'], $d['marca'],
            $d['propietario']
        );
        $ok = ($id > 0) ? $this->modificarVehiculo($valores, $id) : $this->registrarVehiculo($valores);
        if ($ok != 1) {
            return array('msg' => ($id > 0) ? 'Error al modificar' : 'Error al registrar', 'icono' => 'error');
        }

        // 6. Solo si se guardó: subir la foto nueva y borrar la anterior si ya no se usa
        if ($fotoNueva) {
            if (!file_exists($this->carpeta)) {
                mkdir($this->carpeta);
            }
            move_uploaded_file($archivo['tmp_name'], $this->carpeta . $foto);
        }
        if ($id > 0 && $anterior['foto'] != $foto) {
            $this->borrarFoto($anterior['foto']);
        }
        return array('msg' => ($id > 0) ? 'Vehículo modificado' : 'Vehículo registrado con éxito', 'icono' => 'success');
    }

    // Da de baja un vehículo (estado 0)
    public function darDeBaja(int $id)
    {
        return ($this->accionVeh(0, $id) == 1)
            ? array('msg' => 'Vehículo dado de baja', 'icono' => 'success')
            : array('msg' => 'Error al eliminar el Vehiculo', 'icono' => 'error');
    }

    /**
     * Sugerencias para el buscador de vehículos (autocompletar del módulo Servicios),
     * con sus tarifas por hora, día y mes.
     */
    public function sugerencias(string $texto)
    {
        $lista = array();
        foreach ($this->buscarVehiculo($texto) as $row) {
            $descripcion = $row['placa'] . ' - ' . $row['marca'] . ' - ' . $row['tipo'];
            $lista[] = array(
                'id'    => $row['id'],
                'label' => $descripcion,
                'value' => $descripcion,
                'hora'  => $row['precio_hora'],
                'dia'   => $row['precio_dia'],
                'mes'   => $row['precio_mes']
            );
        }
        return $lista;
    }

    // Borra el archivo de una foto, excepto la imagen por defecto
    private function borrarFoto($foto)
    {
        $ruta = $this->carpeta . $foto;
        if ($foto != 'default.png' && $foto != '' && file_exists($ruta)) {
            unlink($ruta);
        }
    }

    /* =========================================================
     * ACCESO A DATOS
     * ========================================================= */

    public function getDatos(string $table)
    {
        // El nombre de una tabla no puede ir en un "?", por eso solo se aceptan
        // las tablas de esta lista (lista blanca).
        if (!in_array($table, array('marcas', 'tipos', 'propietarios'))) {
            return array();
        }
        $sql = "SELECT * FROM $table WHERE estado = 1";
        return $this->selectAll($sql);
    }

    public function getVehiculos(int $estado)
    {
        $sql = "SELECT v.*, m.marca, t.tipo FROM vehiculos v INNER JOIN marcas m ON v.id_marca = m.id INNER JOIN tipos t ON v.id_tipo = t.id WHERE v.estado = ?";
        return $this->selectAll($sql, array($estado));
    }

    public function vehiculos()
    {
        $sql = "SELECT v.*, m.marca, t.tipo FROM vehiculos v INNER JOIN marcas m ON v.id_marca = m.id INNER JOIN tipos t ON v.id_tipo = t.id WHERE v.estado = 1 OR v.estado = 2";
        return $this->selectAll($sql);
    }

    public function getVehiculo(int $id)
    {
        $sql = "SELECT * FROM vehiculos WHERE id = ?";
        return $this->select($sql, array($id));
    }

    // ¿Hay OTRO vehículo (distinto de $id) con esa placa?
    private function existePlaca($placa, int $id)
    {
        $sql = "SELECT id FROM vehiculos WHERE placa = ? AND id != ?";
        return !empty($this->select($sql, array($placa, $id)));
    }

    private function propietarioActivo($id)
    {
        return !empty($this->select("SELECT id FROM propietarios WHERE id = ? AND estado = 1", array($id)));
    }

    // $valores: placa, precios (hora, día, mes), modelo, kilometraje, transmisión,
    // asientos, peso de carga máx., combustible, foto, id_tipo, id_marca, id_propietario
    public function registrarVehiculo(array $valores)
    {
        $sql = "INSERT INTO vehiculos(placa, precio_hora, precio_dia, precio_mes, modelo, kilometraje, transmision,
                asientos, peso_carga_max, combustible, foto, id_tipo, id_marca, id_propietario) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        return $this->save($sql, $valores);
    }

    public function modificarVehiculo(array $valores, int $id)
    {
        $sql = "UPDATE vehiculos SET placa=?, precio_hora=?, precio_dia=?, precio_mes=?, modelo=?, kilometraje=?, transmision=?,
                asientos=?, peso_carga_max=?, combustible=?, foto=?, id_tipo=?, id_marca=?, id_propietario=? WHERE id = ?";
        $valores[] = $id;
        return $this->save($sql, $valores);
    }

    public function accionVeh(int $estado, int $id)
    {
        $sql = "UPDATE vehiculos SET estado = ? WHERE id = ?";
        return $this->save($sql, array($estado, $id));
    }

    public function buscarVehiculo(string $valor)
    {
        // Consulta preparada: busca el texto en placa, tipo o marca, solo en vehículos activos.
        // Cada "?" recibe el mismo valor con % para buscar coincidencias parciales.
        $sql = "SELECT v.*, t.tipo, m.marca FROM vehiculos v
                INNER JOIN tipos t ON t.id = v.id_tipo
                INNER JOIN marcas m ON m.id = v.id_marca
                WHERE v.estado = 1 AND (v.placa LIKE ? OR t.tipo LIKE ? OR m.marca LIKE ?)";
        $buscar = '%' . $valor . '%';
        return $this->selectAll($sql, array($buscar, $buscar, $buscar));
    }
}
