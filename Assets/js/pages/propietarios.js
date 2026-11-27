let tblPropietarios;
document.addEventListener('DOMContentLoaded', function () {
    tblPropietarios = $('#tblPropietarios').DataTable({
        responsive: true,
        processing: true,
        serverSide: false,
        ajax: {
            url: base_url + "propietarios/listar",
            dataSrc: ''
        },
        columns: [{ 'data': 'id' },
        { 'data': 'cedula' },
        { 'data': 'nombre' },
        { 'data': 'correo' },
        { 'data': 'celular' },
        { 'data': 'vehiculos' },
        { 'data': 'estado' },
        { 'data': 'editar' },
        { 'data': 'eliminar' }
        ],
        language: {
            "url": "//cdn.datatables.net/plug-ins/1.10.11/i18n/Spanish.json"
        },
        dom,
        buttons,
        bDestroy: true,
        iDisplayLength: 10,
        order: [
            [0, "desc"]
        ]
    });
})

function frmPropietario() {
    document.getElementById("title").textContent = "Nuevo dueño";
    document.getElementById("btnAccion").textContent = "Registrar";
    document.getElementById("formulario").reset();
    document.getElementById("id").value = "";
    myModal.show();
}

function registrarProp(e) {
    e.preventDefault();
    const frm = document.getElementById("formulario");
    if (frm.cedula.value == '' || frm.nombre.value == '' || frm.correo.value == '' || frm.celular.value == '') {
        alertify.error("Todo los campos son requeridos");
        return;
    }
    const http = new XMLHttpRequest();
    http.open("POST", base_url + 'propietarios/registrar', true);
    http.send(new FormData(frm));
    http.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            const res = JSON.parse(this.responseText);
            if (res.icono == 'success') {
                alertify.success(res.msg);
                frm.reset();
                myModal.hide();
                tblPropietarios.ajax.reload();
            } else {
                alertify.error(res.msg);
            }
        }
    }
}

function btnEditarProp(id) {
    document.getElementById("title").textContent = "Actualizar dueño";
    document.getElementById("btnAccion").textContent = "Modificar";
    const http = new XMLHttpRequest();
    http.open("GET", base_url + "propietarios/editar/" + id, true);
    http.send();
    http.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            const res = JSON.parse(this.responseText);
            const frm = document.getElementById("formulario");
            frm.id.value = res.id;
            frm.cedula.value = res.cedula;
            frm.nombre.value = res.nombre;
            frm.correo.value = res.correo;
            frm.celular.value = res.celular;
            myModal.show();
        }
    }
}

function btnEliminarProp(id) {
    Swal.fire({
        title: 'Esta seguro de eliminar?',
        text: "El dueño no se eliminará de forma permanente, solo cambiará el estado a inactivo!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Si!',
        cancelButtonText: 'No'
    }).then((result) => {
        if (result.isConfirmed) {
            const http = new XMLHttpRequest();
            http.open("GET", base_url + "propietarios/eliminar/" + id, true);
            http.send();
            http.onreadystatechange = function () {
                if (this.readyState == 4 && this.status == 200) {
                    const res = JSON.parse(this.responseText);
                    if (res.icono == 'success') {
                        alertify.success(res.msg);
                        tblPropietarios.ajax.reload();
                    } else {
                        alertify.error(res.msg);
                    }
                }
            }
        }
    })
}
