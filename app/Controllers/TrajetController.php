<?php
namespace App\Controllers;

/**
 * TrajetController
 * - Contrôleur minimaliste pour afficher la page "trajet".
 * - Ici je capture le rendu d'un fichier PHP placé dans /public (trajet.php).
 * -  Note MVC : idéalement, je passerais par une vue dans app/Views + BaseController::render().
 *   Je laisse le code tel quel pour ne rien casser, mais je note la refacto possible.
 *
 * Exemple MVC recommandé (pour plus tard) :
 * ---------------------------------------------------------
 * // class TrajetController extends BaseController {
 * //     public function show(): void {
 * //         $this->render('pages/trajet', [
 * //             'title' => 'Trajet',
 * //         ]);
 * //     }
 * // }
 * // Et je déplacerais /public/trajet.php vers app/Views/pages/trajet.php
 * ---------------------------------------------------------
 */
class TrajetController
{
    /**
     * Affiche la page "trajet".
     * - J'utilise un tampon de sortie (ob_start) pour capturer l'HTML généré par trajet.php,
     *   puis je renvoie la chaîne (cela permet d'insérer ce rendu dans un layout si besoin).
     * - Le include pointe vers /public/trajet.php (chemin absolu construit proprement).
     */
    public function show(): string
    {
        // Je démarre le buffer pour récupérer tout l'HTML produit par le include.
        ob_start();

        // Je charge le fichier de vue "brut". (À migrer vers app/Views/pages/trajet.php à terme)
        include __DIR__ . '/../../public/trajet.php';

        // Je renvoie le contenu capturé. (Pas d'echo direct ici)
        return ob_get_clean();
    }
}
