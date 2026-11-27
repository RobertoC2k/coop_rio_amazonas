<?php include "Views/Templates/header1.php"; ?>

<?php include "Views/Templates/portada.php"; ?>

<section class="ftco-section ftco-about">
  <div class="container">
    <div class="row no-gutters">
      <div class="col-md-6 p-md-5 img img-2 d-flex justify-content-center align-items-center";>
      </div>
      <div class="col-md-6 wrap-about ftco-animate">
        <div class="heading-section heading-section-white ">
          <span class="subheading">Sobre nosotros</span>
          <h2 class="mb-4">Bienvenidos a Cooperativa Rio Amazonas</h2>
          <h2 class="mb-4">Misión</h2>
          <p>Brindar servicios de transporte pesado de alta calidad a nivel provincial y nacional, garantizando eficiencia, seguridad y puntualidad en cada traslado. Nos comprometemos a satisfacer las necesidades de nuestros clientes con soluciones logísticas confiables, respaldadas por un equipo de conductores profesionales y una flota moderna y bien mantenida.</p>
          <h2 class="mb-4">Visión</h2>
          <p>Ser la cooperativa de transporte pesado líder en la provincia, reconocida por su excelencia en el servicio, innovación tecnológica y compromiso con el desarrollo sostenible. Buscamos consolidarnos como la opción preferida en el sector, generando confianza y contribuyendo al crecimiento del comercio y la industria a nivel nacional.</p>
          <p><a href="<?php echo base_url . 'cars'; ?>" class="btn btn-primary py-3 px-4">Buscar maquinaria</a></p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="ftco-section ftco-intro" style="background-image: url(<?php echo base_url; ?>Assets/major/images/bg_3.jpg);">
  <div class="overlay"></div>
  <div class="container">
    <div class="row justify-content-end">
      <div class="col-md-6 heading-section heading-section-white ftco-animate">
      <h2 class="mb-3">¿Quieres afiliarte a nuestra cooperativa?</h2>
      <a href="<?php echo base_url . 'contact'; ?>" class="btn btn-primary btn-lg">Se un socio</a>
      </div>
    </div>
  </div>
</section>

<?php include "Views/Templates/footer1.php"; ?>

</body>

</html>