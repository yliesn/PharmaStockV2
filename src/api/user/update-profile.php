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
$nom    = isset($body['nom']) ? trim($body['nom']) : null;
$prenom = isset($body['prenom']) ? trim($body['prenom']) : null;

// Au moins un champ doit être fourni
if ($nom === null && $prenom === null) {
    json_response(['error' => 'Au moins un champ à mettre à jour est requis'], 400);
}

// Validation longueur
if ($nom !== null && strlen($nom) < 2) {
    json_response(['error' => 'Le nom doit faire au moins 2 caractères'], 400);
}

if ($prenom !== null && strlen($prenom) < 2) {
    json_response(['error' => 'Le prénom doit faire au moins 2 caractères'], 400);
}

$pdo = getDB();

// Construction de la requête UPDATE dynamique
$updates = [];
$params = [];

if ($nom !== null) {
    $updates[] = 'nom = ?';
    $params[] = $nom;
}

if ($prenom !== null) {
    $updates[] = 'prenom = ?';
    $params[] = $prenom;
}

$params[] = $payload['sub']; // id de l'utilisateur

$sql = 'UPDATE UTILISATEUR SET ' . implode(', ', $updates) . ' WHERE id = ?';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Récupère les données mises à jour
$stmt = $pdo->prepare('SELECT id, nom, prenom, login, role, actif FROM UTILISATEUR WHERE id = ? LIMIT 1');
$stmt->execute([$payload['sub']]);
$user = $stmt->fetch();

json_response([
    'message' => 'Profil mis à jour avec succès',
    'user' => [
        'id'     => $user['id'],
        'nom'    => $user['nom'],
        'prenom' => $user['prenom'],
        'login'  => $user['login'],
        'role'   => $user['role'],
        'actif'  => (bool) $user['actif'],
    ],
], 200);
