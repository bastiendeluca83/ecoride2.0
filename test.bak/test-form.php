<?php
$title = "Test Formulaire Recherche – EcoRide";
include __DIR__ . '/../includes/header.php';
?>
<div class="container my-5">
  <h2 class="mb-4">Test du formulaire de recherche</h2>
  <form action="/covoiturages" method="get" class="row g-3 align-items-end">
    <div class="col-12 col-md-4">
      <label class="form-label">Départ</label>
      <input type="text" name="from" class="form-control" placeholder="Ville de départ" required>
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label">Arrivée</label>
      <input type="text" name="to" class="form-control" placeholder="Ville d'arrivée" required>
    </div>
    <div class="col-12 col-md-3">
      <label class="form-label">Date</label>
      <input type="date" name="date" class="form-control" required>
    </div>
    <div class="col-12 col-md-1 d-grid">
      <button type="submit" class="btn btn-light border-0" style="background:#0f8a46;">Rechercher</button>
    </div>
  </form>
  <p class="mt-3 text-muted">Le formulaire envoie vers <code>/covoiturages</code> (même si la page n’est pas encore implémentée).</p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
