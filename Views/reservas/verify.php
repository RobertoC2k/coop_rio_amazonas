<?php cargarHeader($_SESSION['tipo'], $notificaciones); ?>

<div class="card">
    <div class="card-body">
        <div class="row">
            <input type="hidden" id="idreserva" value="<?php echo escapar($data['reserva']['id']); ?>">
            <div class="col-md-6">
                <ul class="list-group">
                    <li class="list-group-item"><b>Vehículo: </b> <?php echo escapar($data['vehiculo']['tipo']); ?></li>
                    <li class="list-group-item"><b>Marca: </b> <?php echo escapar($data['vehiculo']['marca']); ?></li>
                    <li class="list-group-item"><img class="img-thumbnail" src="<?php echo base_url . 'Assets/img/vehiculos/' . $data['vehiculo']['foto']; ?>" alt=""></li>
                </ul>
            </div>
            <div class="col-md-6">
                <ul class="list-group mb-3">
                    <li class="list-group-item"><b>Cliente:</b> <?php echo escapar($data['reserva']['nombre']); ?></li>
                    <li class="list-group-item"><b>Fecha de inicio:</b> <?php echo escapar($data['reserva']['f_inicio']); ?></li>
                    <li class="list-group-item"><b>Fecha de fin:</b> <?php echo escapar($data['reserva']['f_fin']); ?></li>
                    <li class="list-group-item"><b>Duración:</b> <?php echo escapar($data['duracion']); ?></li>
                    <li class="list-group-item"><b>Punto de trabajo:</b> <?php echo htmlspecialchars((string) $data['reserva']['direccion_trabajo']); ?></li>
                    <li class="list-group-item"><b>Observaciones:</b> <?php echo escapar($data['reserva']['observacion']); ?></li>
                    <li class="list-group-item"><b>Dueño del vehículo:</b>
                        <?php echo $data['reserva']['prop_nombre']
                            ? htmlspecialchars($data['reserva']['prop_nombre'] . ' - ' . $data['reserva']['prop_celular'])
                            : '<span class="text-danger">Sin dueño asignado (asígnalo en Vehículos)</span>'; ?>
                    </li>
                    <li class="list-group-item"><b><?php echo $data['texto_tipo']; ?></b><?php echo escapar($data['reserva']['monto']); ?></li>
                    <li class="list-group-item"><b>Total:</b> <?php echo number_format($data['total'], 2); ?></li>
                </ul>
                <?php if ($data['reserva']['estado'] == 0) { ?>
                    <div class="text-end">
                        <button class="btn btn-danger" type="button" id="btnRechazar">Rechazar</button>
                        <button class="btn btn-primary" type="button" id="btnAprobar">Aprobar</button>
                    </div>
                <?php } else if ($data['reserva']['estado'] == 1) { ?>
                    <div class="alert alert-success">Reserva <b>aprobada</b>.</div>
                    <?php if (!empty($data['servicio'])) {
                        $servicio = $data['servicio']; ?>
                        <!-- Estado del servicio que se creó al aprobar: la reserva sigue "aprobada",
                             la ejecución y los pagos los lleva el servicio -->
                        <div class="alert <?php echo $servicio['estado'] == 1 ? 'alert-warning' : 'alert-secondary'; ?>">
                            <b>Servicio #<?php echo (int) $servicio['id']; ?>:</b> <?php echo escapar($servicio['texto_estado']); ?>
                            &middot; Abonado <?php echo number_format($servicio['abonado'], 2); ?> de <?php echo number_format($servicio['total'], 2); ?>
                            <?php if ($servicio['pendiente'] > 0) { ?>
                                &middot; <span class="text-danger">Pendiente <?php echo number_format($servicio['pendiente'], 2); ?></span>
                            <?php } ?>
                            &middot; <a href="<?php echo base_url . 'servicios/pdfServicio/' . (int) $servicio['id']; ?>" target="_blank">Ver comprobante PDF</a>
                        </div>
                    <?php } ?>
                    <?php if ($data['whatsapp'] != '') { ?>
                        <div class="text-end">
                            <a class="btn btn-success" href="<?php echo htmlspecialchars($data['whatsapp']); ?>" target="_blank">
                                <i class="fab fa-whatsapp"></i> Enviar WhatsApp al dueño
                            </a>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="alert alert-secondary mb-0">
                        Reserva <b>rechazada</b>.<br>
                        <b>Motivo:</b> <?php echo htmlspecialchars($data['reserva']['motivo_rechazo']); ?>
                    </div>
                <?php } ?>
            </div>

        </div>

    </div>
</div>

<!-- Ventana para indicar el motivo del rechazo -->
<div class="modal fade" id="modalRechazo" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" role="dialog" aria-labelledby="tituloRechazo" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tituloRechazo">Rechazar reserva</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="frmRechazo" autocomplete="off">
                <div class="modal-body">
                    <div class="form-floating mb-3">
                        <select class="form-control" id="motivo" name="motivo">
                            <option value="">Seleccione...</option>
                            <?php foreach ($data['motivos'] as $motivo) { ?>
                                <option value="<?php echo $motivo; ?>"><?php echo $motivo; ?></option>
                            <?php } ?>
                        </select>
                        <label for="motivo">Motivo <span class="text-danger fw-bold">*</span></label>
                    </div>
                    <div class="form-floating">
                        <textarea class="form-control" id="detalle" name="detalle" style="height: 100px" maxlength="200" placeholder="Detalle"></textarea>
                        <label for="detalle" id="lblDetalle">Detalle (opcional)</label>
                    </div>
                    <small class="text-muted">Se enviará un correo al cliente con el motivo.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" id="btnConfirmarRechazo">Rechazar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php cargarFooter($_SESSION['tipo']); ?>

<script src="<?php echo base_url . 'Assets/js/pages/verify.js'; ?>"></script>

</body>

</html>