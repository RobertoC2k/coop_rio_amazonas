const btnAprobar = document.querySelector('#btnAprobar');
const idreserva = document.querySelector('#idreserva');
document.addEventListener('DOMContentLoaded', function () {
    if (btnAprobar) {
        btnAprobar.addEventListener('click', function () {
            if (idreserva.value == '') {
                alertify.error("Error desconocido");
            } else {
                alertify.confirm("Esta seguro de aprobar", function (e) {
                    if (e) {
                        const url = base_url + 'reservas/aprobar/' + idreserva.value;
                        const http = new XMLHttpRequest();
                        http.open("GET", url, true);
                        http.send();
                        http.onreadystatechange = function () {
                            if (this.readyState == 4 && this.status == 200) {
                                const res = JSON.parse(this.responseText);
                                if (res.icono == 'success') {
                                    alertify.success(res.msg);
                                    if (res.whatsapp) {
                                        // El clic en "Aceptar" permite abrir WhatsApp sin que el navegador lo bloquee
                                        alertify.confirm("Reserva aprobada. ¿Enviar ahora el WhatsApp al dueño del vehículo?", function (ok) {
                                            if (ok) {
                                                window.open(res.whatsapp, '_blank');
                                            }
                                            window.location.reload();
                                        });
                                    } else {
                                        setTimeout(() => {
                                            window.location.reload();
                                        }, 1500);
                                    }
                                } else {
                                    alertify.error(res.msg);
                                }
                            }
                        }
                    }
                });
                return false;
            }


        })
    }

    // ----- Rechazar reserva con motivo -----
    const btnRechazar = document.querySelector('#btnRechazar');
    const frmRechazo = document.querySelector('#frmRechazo');
    const motivo = document.querySelector('#motivo');
    const lblDetalle = document.querySelector('#lblDetalle');
    if (btnRechazar) {
        btnRechazar.addEventListener('click', function () {
            frmRechazo.reset();
            lblDetalle.textContent = 'Detalle (opcional)';
            $('#modalRechazo').modal('show');
        });
        // Si el motivo es "Otro", el detalle pasa a ser obligatorio
        motivo.addEventListener('change', function () {
            lblDetalle.textContent = (motivo.value == 'Otro') ? 'Describa el motivo *' : 'Detalle (opcional)';
        });
        frmRechazo.addEventListener('submit', function (e) {
            e.preventDefault();
            if (motivo.value == '') {
                alertify.error('Selecciona un motivo');
                return;
            }
            const url = base_url + 'reservas/rechazar/' + idreserva.value;
            const http = new XMLHttpRequest();
            http.open("POST", url, true);
            http.send(new FormData(frmRechazo));
            http.onreadystatechange = function () {
                if (this.readyState == 4 && this.status == 200) {
                    const res = JSON.parse(this.responseText);
                    if (res.icono == 'success') {
                        $('#modalRechazo').modal('hide');
                        alertify.success(res.msg);
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        alertify.error(res.msg);
                    }
                }
            }
        });
    }

})