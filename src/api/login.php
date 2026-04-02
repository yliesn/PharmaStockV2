<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/helpers.php';

send_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Méthode non autorisée'], 405);
}

$body = json_decode(file_get_contents('php://input'), true);
$login    = trim($body['login'] ?? '');
$password = $body['password'] ?? '';

if (!$login || !$password) {
    json_response(['error' => 'Champs manquants'], 400);
}

$pdo  = getDB();
$stmt = $pdo->prepare('SELECT id, nom, prenom, login, mot_de_passe, role, actif FROM UTILISATEUR WHERE login = ? LIMIT 1');
$stmt->execute([$login]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['mot_de_passe'])) {
    json_response(['error' => 'Identifiants invalides'], 401);
}

if (!$user['actif']) {
    json_response(['error' => 'Compte désactivé'], 403);
}

$token = jwt_create([
    'sub'   => $user['id'],
    'login' => $user['login'],
    'role'  => $user['role'],
]);

json_response([
    'token' => $token,
    'user'  => [
        'id'     => $user['id'],
        'nom'    => $user['nom'],
        'prenom' => $user['prenom'],
        'login'  => $user['login'],
        'role'   => $user['role'],
    ],
]);
