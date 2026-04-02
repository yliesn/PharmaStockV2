<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../config/helpers.php';

send_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
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

// Vérifie que l'utilisateur est admin
if (!is_admin($payload)) {
    json_response(['error' => 'Accès refusé - admin requis'], 403);
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$user_id) {
    json_response(['error' => 'ID utilisateur requis'], 400);
}

// Vérifie que l'admin ne change pas son propre rôle
if ($user_id === $payload['sub']) {
    json_response(['error' => 'Vous ne pouvez pas modifier votre propre rôle'], 403);
}

$body = json_decode(file_get_contents('php://input'), true);
$new_role = isset($body['role']) ? trim($body['role']) : '';

if (!$new_role) {
    json_response(['error' => 'Le rôle est requis'], 400);
}

// Valide les rôles autorisés
$allowed_roles = ['user', 'admin', 'moderator'];
if (!in_array($new_role, $allowed_roles, true)) {
    json_response(['error' => 'Rôle invalide. Rôles autorisés: ' . implode(', ', $allowed_roles)], 400);
}

$pdo = getDB();

// Vérifie que l'utilisateur existe
$stmt = $pdo->prepare('SELECT id, role FROM UTILISATEUR WHERE id = ? LIMIT 1');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    json_response(['error' => 'Utilisateur introuvable'], 404);
}

// Vérifie que le rôle change réellement
if ($user['role'] === $new_role) {
    json_response(['error' => 'Le nouvel rôle est identique à l\'ancien rôle'], 400);
}

// Change le rôle
$stmt = $pdo->prepare('UPDATE UTILISATEUR SET role = ? WHERE id = ?');
$stmt->execute([$new_role, $user_id]);

// Récupère les données mises à jour
$stmt = $pdo->prepare('SELECT id, nom, prenom, login, role, actif FROM UTILISATEUR WHERE id = ? LIMIT 1');
$stmt->execute([$user_id]);
$updated_user = $stmt->fetch();

json_response([
    'message' => 'Rôle mis à jour avec succès',
    'user' => [
        'id'     => (int) $updated_user['id'],
        'nom'    => $updated_user['nom'],
        'prenom' => $updated_user['prenom'],
        'login'  => $updated_user['login'],
        'role'   => $updated_user['role'],
        'actif'  => (bool) $updated_user['actif'],
    ],
], 200);
