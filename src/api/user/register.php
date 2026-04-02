<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

send_cors();

// Cette API est désactivée - les comptes sont créés par l'admin uniquement
// Voir: /api/admin/create-user.php

json_response(['error' => 'Création de compte désactivée pour les utilisateurs. Veuillez contacter un administrateur.'], 403);