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

// Vérifie que l'admin ne désactive pas lui-même
if ($user_id === $payload['sub']) {
    json_response(['error' => 'Vous ne pouvez pas modifier votre propre statut'], 403);
}

$body = json_decode(file_get_contents('php://input'), true);
$action = isset($body['action']) ? trim($body['action']) : '';

if (!$action) {
    json_response(['error' => 'L\'action est requise (activate ou deactivate)'], 400);
}

$allowed_actions = ['activate', 'deactivate'];
if (!in_array($action, $allowed_actions, true)) {
    json_response(['error' => 'Action invalide. Valeurs autorisées: ' . implode(', ', $allowed_actions)], 400);
}

$pdo = getDB();

// Vérifie que l'utilisateur existe
$stmt = $pdo->prepare('SELECT id, actif FROM UTILISATEUR WHERE id = ? LIMIT 1');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    json_response(['error' => 'Utilisateur introuvable'], 404);
}

$new_status = $action === 'activate' ? 1 : 0;

// Vérifie que le statut change réellement
if ((bool)$user['actif'] === (bool)$new_status) {
    $status_text = $action === 'activate' ? 'déjà activé' : 'déjà désactivé';
    json_response(['error' => 'L\'utilisateur est ' . $status_text], 400);
}

// Change le statut
$stmt = $pdo->prepare('UPDATE UTILISATEUR SET actif = ? WHERE id = ?');
$stmt->execute([$new_status, $user_id]);

// Récupère les données mises à jour
$stmt = $pdo->prepare('SELECT id, nom, prenom, login, role, actif FROM UTILISATEUR WHERE id = ? LIMIT 1');
$stmt->execute([$user_id]);
$updated_user = $stmt->fetch();

$status_text = $action === 'activate' ? 'activé' : 'désactivé';

json_response([
    'message' => 'Utilisateur ' . $status_text . ' avec succès',
    'user' => [
        'id'     => (int) $updated_user['id'],
        'nom'    => $updated_user['nom'],
        'prenom' => $updated_user['prenom'],
        'login'  => $updated_user['login'],
        'role'   => $updated_user['role'],
        'actif'  => (bool) $updated_user['actif'],
    ],
], 200);
