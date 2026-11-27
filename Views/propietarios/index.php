<?php cargarHeader($_SESSION['tipo']); ?>

<button class="btn btn-outline-primary mb-2" type="button" onclick="frmPropietario();"><i class="fas fa-plus"></i></button>

<div class="card">
    <div class="card-body">
        <h5 class="card-title">Dueños de vehículos</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-hover display responsive nowrap" id="tblPropietarios" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Id</th>
                        <th>Cédula</th>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Celular</th>
                        <th>Vehículos</th>
                        <th>Estado</th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="modal fade" id="myModal" tabindex="-1" aria-labelledby="Label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formulario" onsubmit="registrarProp(event);" autocomplete="off">
                <div class="modal-body">
                    <div class="alert alert-info" role="alert">
                        Todo los campos con <span class="text-danger fw-bold">*</span> son obligatorio.
                        El dueño recibirá por correo y WhatsApp el aviso de cada reserva aprobada de su vehículo.
                    </div>
                    <div class="form-floating mb-3">
                        <input type="hidden" id="id" name="id">
                        <input id="cedula" class="form-control" type="text" name="cedula" maxlength="13" placeholder="Cédula">
                        <label for="cedula"><i class="fas fa-id-card"></i> Cédula / RUC <span class="text-danger fw-bold">*</span></label>
                    </div>
                    <div class="form-floating mb-3">
                        <input id="nombre" class="form-control" type="text" name="nombre" placeholder="Nombre" required>
                        <label for="nombre"><i class="fas fa-user"></i> Nombre <span class="text-danger fw-bold">*</span></label>
                    </div>
                    <div class="form-floating mb-3">
                        <input id="correo" class="form-control" type="email" name="correo" placeholder="Correo" required>
                        <label for="correo"><i class="fas fa-envelope"></i> Correo <span class="text-danger fw-bold">*</span></label>
                    </div>
                    <div class="form-floating mb-3">
                        <input id="celular" class="form-control" type="text" name="celular" placeholder="Celular" required>
                        <label for="celular"><i class="fab fa-whatsapp"></i> Celular (WhatsApp) <span class="text-danger fw-bold">*</span></label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-primary" type="submit" id="btnAccion">Registrar</button>
                    <button class="btn btn-outline-danger" type="button" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php cargarFooter($_SESSION['tipo']); ?>

<script src="<?php echo base_url . 'Assets/js/pages/propietarios.js'; ?>"></script>

</body>

</html>
