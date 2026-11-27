<?php
class DashboardModel extends Query
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getDatos(string $table)
    {
        $sql = "SELECT COUNT(*) AS total FROM $table WHERE estado = 1";
        $data = $this->select($sql);
        return $data;
    }

    public function ingresosMes($desde, $hasta)
    {
        $sql = "SELECT SUM(IF(MONTH(fecha_inicio) = 1, (cantidad * monto), 0)) AS ene,
        SUM(IF(MONTH(fecha_inicio) = 2, (cantidad * monto), 0)) AS feb,
        SUM(IF(MONTH(fecha_inicio) = 3, (cantidad * monto), 0)) AS mar,
        SUM(IF(MONTH(fecha_inicio) = 4, (cantidad * monto), 0)) AS abr,
        SUM(IF(MONTH(fecha_inicio) = 5, (cantidad * monto), 0)) AS may,
        SUM(IF(MONTH(fecha_inicio) = 6, (cantidad * monto), 0)) AS jun,
        SUM(IF(MONTH(fecha_inicio) = 7, (cantidad * monto), 0)) AS jul,
        SUM(IF(MONTH(fecha_inicio) = 8, (cantidad * monto), 0)) AS ago,
        SUM(IF(MONTH(fecha_inicio) = 9, (cantidad * monto), 0)) AS sep,
        SUM(IF(MONTH(fecha_inicio) = 10, (cantidad * monto), 0)) AS oct,
        SUM(IF(MONTH(fecha_inicio) = 11, (cantidad * monto), 0)) AS nov,
        SUM(IF(MONTH(fecha_inicio) = 12, (cantidad * monto), 0)) AS dic 
        FROM servicios WHERE fecha_inicio BETWEEN ? AND ?";
        return $this->select($sql, array($desde, $hasta));
    }

    public function ingresosSemana()
    {
        $sql = "SELECT 
        DATE_FORMAT(fecha_inicio, '%Y-%m-%d') as fecha, 
        SUM(cantidad * monto) as total
        FROM servicios
        WHERE WEEK(fecha_inicio) = WEEK(CURDATE())  -- Obtener la semana actual
        GROUP BY fecha
        ORDER BY fecha";
        return $this->selectAll($sql);
    }
}
