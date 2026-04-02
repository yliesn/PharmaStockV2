<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../config/helpers.php';

send_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$body = json_decode(file_get_contents('php://input'), true);
$nom    = trim($body['nom'] ?? '');
$prenom = trim($body['prenom'] ?? '');
$login  = trim($body['login'] ?? '');
$mdp    = $body['mot_de_passe'] ?? '';
$role   = trim($body['role'] ?? 'user');

if (!$nom || !$prenom || !$login || !$mdp) {
    json_response(['error' => 'Tous les champs sont obligatoires (nom, prenom, login, mot_de_passe)'], 400);
}

if (strlen($nom) < 2) {
    json_response(['error' => 'Le nom doit faire au moins 2 caractères'], 400);
}

if (strlen($prenom) < 2) {
    json_response(['error' => 'Le prénom doit faire au moins 2 caractères'], 400);
}

if (strlen($login) < 3) {
    json_response(['error' => 'Le login doit faire au moins 3 caractères'], 400);
}

if (strlen($mdp) < 8) {
    json_response(['error' => 'Le mot de passe doit faire au moins 8 caractères'], 400);
}

// Valide les rôles autorisés
$allowed_roles = ['UTILISATEUR', 'ADMIN', 'VISITEUR'];
if (!in_array($role, $allowed_roles, true)) {
    json_response(['error' => 'Rôle invalide. Rôles autorisés: ' . implode(', ', $allowed_roles)], 400);
}

$pdo = getDB();

// Vérifier si le login existe déjà
$stmt = $pdo->prepare('SELECT id FROM UTILISATEUR WHERE login = ? LIMIT 1');
$stmt->execute([$login]);
if ($stmt->fetch()) {
    json_response(['error' => 'Ce login est déjà utilisé'], 409);
}

$hash = password_hash($mdp, PASSWORD_BCRYPT);

$stmt = $pdo->prepare('INSERT INTO UTILISATEUR (nom, prenom, login, mot_de_passe, role, actif) VALUES (?, ?, ?, ?, ?, true)');
$stmt->execute([$nom, $prenom, $login, $hash, $role]);

$new_user_id = $pdo->lastInsertId();

// Récupère le nouvel utilisateur
$stmt = $pdo->prepare('SELECT id, nom, prenom, login, role, actif FROM UTILISATEUR WHERE id = ? LIMIT 1');
$stmt->execute([$new_user_id]);
$user = $stmt->fetch();

json_response([
    'message' => 'Compte utilisateur créé avec succès',
    'user' => [
        'id'     => (int) $user['id'],
        'nom'    => $user['nom'],
        'prenom' => $user['prenom'],
        'login'  => $user['login'],
        'role'   => $user['role'],
        'actif'  => (bool) $user['actif'],
    ],
], 201);
