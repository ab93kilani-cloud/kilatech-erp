<?php

// Manuel cache cleaner for Hostinger
$cacheFile = __DIR__ . '/backend/bootstrap/cache/config.php';

if (file_exists($cacheFile)) {
    if (unlink($cacheFile)) {
        echo "<h1>✅ Succès ! Le cache de configuration a été supprimé.</h1>";
        echo "<p>Laravel va maintenant lire vos nouvelles clés .env.</p>";
    } else {
        echo "<h1>❌ Erreur : Impossible de supprimer le fichier de cache.</h1>";
        echo "<p>Veuillez vérifier les permissions du dossier backend/bootstrap/cache/.</p>";
    }
} else {
    echo "<h1>ℹ️ Info : Aucun fichier de cache trouvé.</h1>";
    echo "<p>Le serveur devrait déjà lire vos clés .env normalement.</p>";
}

echo "<br><a href='/admin/marketing'>Retour au tableau de bord</a>";
