<?php
$title = "Test complet US 1 – EcoRide";
include __DIR__ . '/../includes/header.php';
?>

<div class="container my-5">

  <!-- Présentation -->
  <section class="mb-5">
    <div class="row align-items-center g-4">
      <div class="col-lg-6">
        <h2 class="mb-3">Présentation d’EcoRide</h2>
        <p class="lead mb-0">
          EcoRide est la plateforme de covoiturage écologique qui vous permet de partager vos trajets
          tout en réduisant votre empreinte carbone.
        </p>
      </div>
      <div class="col-lg-6 text-center">
        <!-- Astuce : évite les espaces dans les noms de fichiers -->
        <img src="/assets/img/photo-home-page-1.jpg"
             class="img-fluid rounded shadow-sm img-demo mb-3"
             alt="Illustration covoiturage écologique 1">
        <img src="/assets/img/photo-home-page-2.jpg"
             class="img-fluid rounded shadow-sm img-demo"
             alt="Illustration covoiturage écologique 2">
      </div>
    </div>
  </section>

  <!-- Formulaire de recherche -->
  <section class="bg-light p-4 rounded shadow-sm">
    <h3 class="mb-4">Rechercher un itinéraire</h3>
    <form action="/covoiturages" method="get" class="row g-3 align-items-end">
      <div class="col-12 col-md-4">
        <label class="form-label" for="from">Départ</label>
        <input id="from" type="text" name="from" class="form-control" placeholder="Ville de départ" required>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label" for="to">Arrivée</label>
        <input id="to" type="text" name="to" class="form-control" placeholder="Ville d'arrivée" required>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label" for="date">Date</label>
        <input id="date" type="date" name="date" class="form-control" required>
      </div>
      <div class="col-12 col-md-1 d-grid">
        <button type="submit" class="btn btn-success">Go</button>
      </div>
    </form>
    <p class="mt-3 text-muted">Les données sont envoyées vers <code>/covoiturages</code>.</p>
  </section>

  <!-- Trajets phares -->
  <section class="my-5">
    <?php
      $file = __DIR__ . '/../includes/trajets-phares.php';
      if (is_file($file)) {
        include $file;
      } else {
        echo '<div class="alert alert-warning mb-0">Le bloc "Trajets phares" est momentanément indisponible.</div>';
      }
    ?>
  </section>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
