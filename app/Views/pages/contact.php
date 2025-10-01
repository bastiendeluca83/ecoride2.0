<?php
/* app/Views/pages/contact.php
 * Page Contact (statique avec formulaire)
 */
$title = $title ?? 'Contact – EcoRide';
?>
<div class="container py-4">
  <h1 class="h3 mb-4">Contactez-nous</h1>
  <p>
    Une question ? Besoin d’aide ? Remplissez ce formulaire ou écrivez-nous à
    <a href="mailto:ecoride.demo@gmail.com">ecoride.demo@gmail.com</a>.
  </p>

  <form method="post" action="/send-contact" class="needs-validation" novalidate>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <div class="mb-3">
      <label for="cName" class="form-label">Nom</label>
      <input type="text" class="form-control" id="cName" name="name" required>
      <div class="invalid-feedback">Nom requis.</div>
    </div>
    <div class="mb-3">
      <label for="cEmail" class="form-label">Email</label>
      <input type="email" class="form-control" id="cEmail" name="email" required>
      <div class="invalid-feedback">Email valide requis.</div>
    </div>
    <div class="mb-3">
      <label for="cMsg" class="form-label">Message</label>
      <textarea class="form-control" id="cMsg" name="message" rows="5" required></textarea>
      <div class="invalid-feedback">Message requis.</div>
    </div>
    <button type="submit" class="btn btn-success">Envoyer</button>
  </form>
</div>
