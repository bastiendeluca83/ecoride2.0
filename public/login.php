<?php
header('Location: /login');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si déjà connecté, redirige vers dashboard
if (isset($_SESSION['user']['id'])) {
    header('Location: /dashboard');
    exit;
}

$error = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion — EcoRide</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="height:100vh;">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h1 class="h4 mb-4 text-center">Connexion</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="/login">
                        <div class="mb-3">
                            <label for="email" class="form-label">Adresse email</label>
                            <input type="email" name="email" id="email" class="form-control" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Mot de passe</label>
                            <input type="password" name="password" id="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Se connecter</button>
                    </form>

                    <p class="mt-3 text-center">
                        <a href="/">Retour à l'accueil</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
