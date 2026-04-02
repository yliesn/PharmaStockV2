<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../config/helpers.php';

send_cors();

// Récupère le token depuis l'header Authorization
$token = get_bearer_token();
if (!$token) {
    json_response(['error' => 'Token manquant'], 401);
}

// Valide le token
$payload = jwt_verify($token);
if (!$payload) {
    json_response(['error' => 'Token invalide ou expiré'], 401);
}

// Récupère l'utilisateur depuis la base de données
$pdo  = getDB();
$stmt = $pdo->prepare('SELECT id, nom, prenom, login, role, actif, date_derniere_connexion FROM UTILISATEUR WHERE id = ? LIMIT 1');
$stmt->execute([$payload['sub']]);
$user = $stmt->fetch();

if (!$user) {
    json_response(['error' => 'Utilisateur introuvable'], 404);
}

json_response([
    'user' => [
        'id'                      => $user['id'],
        'nom'                     => $user['nom'],
        'prenom'                  => $user['prenom'],
        'login'                   => $user['login'],
        'role'                    => $user['role'],
        'actif'                   => $user['actif'],
        'date_derniere_connexion' => $user['date_derniere_connexion'],
    ],
]);
