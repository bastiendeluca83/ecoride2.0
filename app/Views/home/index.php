<?php
/* app/Views/home/index.php
 Cette vue est injectée dans le layout global via View::render() / BaseController::render().*/

$title = 'Accueil – EcoRide';
?>

<section class="mb-5">
  <div class="row align-items-center g-4">
    <div class="col-lg-6">
      <h1 class="display-6 fw-semibold mb-3">EcoRide — le covoiturage écoresponsable</h1>
      <p class="lead mb-3">
        Réduisez votre empreinte carbone tout en faisant des économies.
        EcoRide met en relation des conducteurs et des passagers pour des trajets en
        <strong>voiture</strong> partout en France.
      </p>
      <ul class="list-unstyled mb-0">
        <li class="mb-1">• Trajets vérifiés et notés</li>
        <li class="mb-1">• Option <em>véhicule électrique</em> pour des voyages plus verts</li>
        <li class="mb-1">• Paiement en <strong>crédits</strong> pour simplifier vos réservations</li>
      </ul>
    </div>
    <div class="col-lg-6">
      <img src="/assets/img/photo-home-page-1.jpg"
           class="img-fluid rounded shadow-sm"
           alt="Covoiturage écologique avec EcoRide">
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="card border-0 shadow-sm">
    <div class="card-body p-3 p-md-4">
      <h2 class="h5 mb-3">Rechercher un covoiturage</h2>
      <form action="/search" method="post" class="row g-3 align-items-end" novalidate>
        <div class="col-12 col-md-4">
          <label for="from_city" class="form-label">Départ</label>
          <input id="from_city" name="from_city" class="form-control" placeholder="Ville de départ" required>
        </div>
        <div class="col-12 col-md-4">
          <label for="to_city" class="form-label">Arrivée</label>
          <input id="to_city" name="to_city" class="form-control" placeholder="Ville d’arrivée" required>
        </div>
        <div class="col-12 col-md-3">
          <label for="date_start" class="form-label">Date</label>
          <input id="date_start" type="date" name="date_start" class="form-control" required>
        </div>
        <div class="col-12 col-md-1 d-grid">
          <button class="btn btn-success" type="submit">Rechercher</button>
        </div>

        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="eco_only" name="eco_only" value="1">
            <label class="form-check-label" for="eco_only">Uniquement véhicules électriques</label>
          </div>
        </div>
      </form>
    </div>
  </div>
</section>

<section class="mt-5">
  <h2 class="h5 mb-3">Pourquoi choisir EcoRide ?</h2>
  <div class="row g-3">
    <div class="col-md-4">
      <div class="card h-100 border-0 shadow-sm">
        <img src="/assets/img/photo-home-page-2.jpg" class="card-img-top" alt="Confort et sécurité en covoiturage">
        <div class="card-body">
          <h3 class="h6 fw-semibold">Confort & sécurité</h3>
          <p class="mb-0 small text-muted">Des conducteurs évalués et des trajets suivis.</p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 border-0 shadow-sm">
        <img src="/assets/img/photo-home-page-3.jpg" class="card-img-top" alt="Économies sur les trajets">
        <div class="card-body">
          <h3 class="h6 fw-semibold">Économies</h3>
          <p class="mb-0 small text-muted">Partagez les frais, voyagez à moindre coût.</p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 border-0 shadow-sm">
        <img src="/assets/img/photo-home-page-4.jpg" class="card-img-top" alt="Trajets plus écologiques">
        <div class="card-body">
          <h3 class="h6 fw-semibold">Écologie</h3>
          <p class="mb-0 small text-muted">Privilégiez les trajets en véhicule électrique.</p>
        </div>
      </div>
    </div>
  </div>
</section>
