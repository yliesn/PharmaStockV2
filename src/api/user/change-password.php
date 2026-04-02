<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../config/helpers.php';

send_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    json_response(['error' => 'Méthode non autorisée'], 405);
}

// Vérifie le token
$token = get_bearer_token();
if (!$token) {
    json_response(['error' => 'Token manquant'], 401);
}

$payload = jwt_verify($token);
if (!$payload) {
    json_response(['error' => 'Token invalide ou expiré'], 401);
}

$body = json_decode(file_get_contents('php://input'), true);
$ancien_mdp = $body['ancien_mot_de_passe'] ?? '';
$nouveau_mdp = $body['nouveau_mot_de_passe'] ?? '';

if (!$ancien_mdp || !$nouveau_mdp) {
    json_response(['error' => 'Tous les champs sont obligatoires'], 400);
}

if (strlen($nouveau_mdp) < 8) {
    json_response(['error' => 'Le nouveau mot de passe doit faire au moins 8 caractères'], 400);
}

if ($ancien_mdp === $nouveau_mdp) {
    json_response(['error' => 'Le nouveau mot de passe ne peut pas être identique à l\'ancien'], 400);
}

$pdo = getDB();

// Récupère l'utilisateur
$stmt = $pdo->prepare('SELECT id, mot_de_passe FROM UTILISATEUR WHERE id = ? LIMIT 1');
$stmt->execute([$payload['sub']]);
$user = $stmt->fetch();

if (!$user) {
    json_response(['error' => 'Utilisateur introuvable'], 404);
}

// Vérifie l'ancien mot de passe
if (!password_verify($ancien_mdp, $user['mot_de_passe'])) {
    json_response(['error' => 'L\'ancien mot de passe est incorrect'], 401);
}

// Hash et met à jour le nouveau mot de passe
$hash = password_hash($nouveau_mdp, PASSWORD_BCRYPT);
$stmt = $pdo->prepare('UPDATE UTILISATEUR SET mot_de_passe = ? WHERE id = ?');
$stmt->execute([$hash, $payload['sub']]);

json_response(['message' => 'Mot de passe changé avec succès'], 200);
