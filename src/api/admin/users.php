<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../config/helpers.php';

send_cors();

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

$pdo = getDB();

// GET - Lister tous les utilisateurs avec pagination
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(5, (int)$_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;

    // Compte le total
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM UTILISATEUR');
    $stmt->execute();
    $total = $stmt->fetch()['total'];

    // Récupère les utilisateurs
    $stmt = $pdo->prepare('
        SELECT id, nom, prenom, login, role, actif, date_derniere_connexion
        FROM UTILISATEUR
        ORDER BY id DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();

    json_response([
        'data' => array_map(function($user) {
            return [
                'id'                      => (int) $user['id'],
                'nom'                     => $user['nom'],
                'prenom'                  => $user['prenom'],
                'login'                   => $user['login'],
                'role'                    => $user['role'],
                'actif'                   => (bool) $user['actif'],
                'date_derniere_connexion' => $user['date_derniere_connexion'],
            ];
        }, $users),
        'pagination' => [
            'page'       => $page,
            'limit'      => $limit,
            'total'      => $total,
            'pages'      => ceil($total / $limit),
        ],
    ], 200);
}

// PUT - Modifier un utilisateur (par ID en query param)
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $user_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if (!$user_id) {
        json_response(['error' => 'ID utilisateur requis'], 400);
    }

    // Vérifie que l'utilisateur ne se supprime pas lui-même
    if ($user_id === $payload['sub']) {
        json_response(['error' => 'Vous ne pouvez pas modifier votre propre compte via cette API'], 403);
    }

    $body = json_decode(file_get_contents('php://input'), true);
    $nom    = isset($body['nom']) ? trim($body['nom']) : null;
    $prenom = isset($body['prenom']) ? trim($body['prenom']) : null;

    if ($nom === null && $prenom === null) {
        json_response(['error' => 'Au moins un champ à mettre à jour est requis'], 400);
    }

    // Vérifie que l'utilisateur existe
    $stmt = $pdo->prepare('SELECT id FROM UTILISATEUR WHERE id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        json_response(['error' => 'Utilisateur introuvable'], 404);
    }

    // Construction de la requête UPDATE
    $updates = [];
    $params = [];

    if ($nom !== null) {
        if (strlen($nom) < 2) {
            json_response(['error' => 'Le nom doit faire au moins 2 caractères'], 400);
        }
        $updates[] = 'nom = ?';
        $params[] = $nom;
    }

    if ($prenom !== null) {
        if (strlen($prenom) < 2) {
            json_response(['error' => 'Le prénom doit faire au moins 2 caractères'], 400);
        }
        $updates[] = 'prenom = ?';
        $params[] = $prenom;
    }

    $params[] = $user_id;

    $sql = 'UPDATE UTILISATEUR SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Récupère les données mises à jour
    $stmt = $pdo->prepare('SELECT id, nom, prenom, login, role, actif FROM UTILISATEUR WHERE id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    json_response([
        'message' => 'Utilisateur mis à jour avec succès',
        'user' => [
            'id'     => (int) $user['id'],
            'nom'    => $user['nom'],
            'prenom' => $user['prenom'],
            'login'  => $user['login'],
            'role'   => $user['role'],
            'actif'  => (bool) $user['actif'],
        ],
    ], 200);
}

// DELETE - Supprimer un utilisateur (par ID en query param)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $user_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if (!$user_id) {
        json_response(['error' => 'ID utilisateur requis'], 400);
    }

    // Vérifie que l'admin ne se supprime pas lui-même
    if ($user_id === $payload['sub']) {
        json_response(['error' => 'Vous ne pouvez pas supprimer votre propre compte'], 403);
    }

    // Vérifie que l'utilisateur existe
    $stmt = $pdo->prepare('SELECT id FROM UTILISATEUR WHERE id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        json_response(['error' => 'Utilisateur introuvable'], 404);
    }

    // Supprime l'utilisateur
    $stmt = $pdo->prepare('DELETE FROM UTILISATEUR WHERE id = ?');
    $stmt->execute([$user_id]);

    json_response(['message' => 'Utilisateur supprimé avec succès'], 200);
}

json_response(['error' => 'Méthode non autorisée'], 405);
