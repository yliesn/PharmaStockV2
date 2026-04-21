<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../config/helpers.php';

send_cors();

$token = get_bearer_token();
if (!$token) json_response(['error' => 'Token manquant'], 401);

$payload = jwt_verify($token);
if (!$payload) json_response(['error' => 'Token invalide ou expiré'], 401);

if (!is_admin($payload)) {
    json_response(['error' => 'Accès refusé - admin requis'], 403);
}

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET /api/admin/users.php ─────────────────────────────────
// ?page=1&limit=20&search=xxx&role=xxx&actif=true|false
if ($method === 'GET') {
    $page   = isset($_GET['page'])  ? max(1, (int)$_GET['page'])            : 1;
    $limit  = isset($_GET['limit']) ? min(100, max(5, (int)$_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $role   = isset($_GET['role'])   ? trim($_GET['role'])   : '';
    $actif  = isset($_GET['actif'])  ? $_GET['actif']        : '';

    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[]  = '(nom LIKE ? OR prenom LIKE ? OR login LIKE ?)';
        $s        = '%' . $search . '%';
        $params   = array_merge($params, [$s, $s, $s]);
    }
    if ($role !== '') {
        $where[]  = 'role = ?';
        $params[] = $role;
    }
    if ($actif !== '') {
        $where[]  = 'actif = ?';
        $params[] = $actif === 'true' ? 1 : 0;
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM UTILISATEUR $whereSQL");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetch()['total'];

    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare("
        SELECT id, nom, prenom, login, role, actif, date_derniere_connexion
        FROM UTILISATEUR
        $whereSQL
        ORDER BY id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(count($params) - 1, $limit,  PDO::PARAM_INT);
    $stmt->bindValue(count($params),     $offset, PDO::PARAM_INT);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    json_response([
        'data' => array_map(fn($u) => formatUser($u), $users),
        'pagination' => [
            'page'  => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => (int) ceil($total / $limit),
        ],
    ]);
}

// ── POST /api/admin/users.php ────────────────────────────────
// Créer un utilisateur
if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true);
    $nom    = trim($body['nom']          ?? '');
    $prenom = trim($body['prenom']       ?? '');
    $login  = trim($body['login']        ?? '');
    $mdp    = $body['mot_de_passe']      ?? '';
    $role   = strtoupper(trim($body['role'] ?? 'UTILISATEUR'));

    if (!$nom || !$prenom || !$login || !$mdp) {
        json_response(['error' => 'Tous les champs sont obligatoires (nom, prenom, login, mot_de_passe)'], 400);
    }
    if (strlen($nom) < 2)    json_response(['error' => 'Le nom doit faire au moins 2 caractères'], 400);
    if (strlen($prenom) < 2) json_response(['error' => 'Le prénom doit faire au moins 2 caractères'], 400);
    if (strlen($login) < 3)  json_response(['error' => 'Le login doit faire au moins 3 caractères'], 400);
    if (strlen($mdp) < 8)    json_response(['error' => 'Le mot de passe doit faire au moins 8 caractères'], 400);

    validateRole($role);

    $check = $pdo->prepare('SELECT id FROM UTILISATEUR WHERE login = ? LIMIT 1');
    $check->execute([$login]);
    if ($check->fetch()) json_response(['error' => 'Ce login est déjà utilisé'], 409);

    $stmt = $pdo->prepare('
        INSERT INTO UTILISATEUR (nom, prenom, login, mot_de_passe, role, actif)
        VALUES (?, ?, ?, ?, ?, 1)
    ');
    $stmt->execute([$nom, $prenom, $login, password_hash($mdp, PASSWORD_BCRYPT), $role]);
    $id = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT id, nom, prenom, login, role, actif, date_derniere_connexion FROM UTILISATEUR WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);

    json_response(['message' => 'Utilisateur créé avec succès', 'user' => formatUser($stmt->fetch())], 201);
}

// ── PUT /api/admin/users.php?id=x ───────────────────────────
// Modifier nom, prénom, login, mot_de_passe, role
if ($method === 'PUT') {
    $user_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    if (!$user_id) json_response(['error' => 'ID utilisateur requis'], 400);

    $stmt = $pdo->prepare('SELECT id FROM UTILISATEUR WHERE id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) json_response(['error' => 'Utilisateur introuvable'], 404);

    $body    = json_decode(file_get_contents('php://input'), true);
    $updates = [];
    $params  = [];

    if (isset($body['nom'])) {
        $nom = trim($body['nom']);
        if (strlen($nom) < 2) json_response(['error' => 'Le nom doit faire au moins 2 caractères'], 400);
        $updates[] = 'nom = ?';
        $params[]  = $nom;
    }
    if (isset($body['prenom'])) {
        $prenom = trim($body['prenom']);
        if (strlen($prenom) < 2) json_response(['error' => 'Le prénom doit faire au moins 2 caractères'], 400);
        $updates[] = 'prenom = ?';
        $params[]  = $prenom;
    }
    if (isset($body['login'])) {
        $login = trim($body['login']);
        if (strlen($login) < 3) json_response(['error' => 'Le login doit faire au moins 3 caractères'], 400);
        $check = $pdo->prepare('SELECT id FROM UTILISATEUR WHERE login = ? AND id != ? LIMIT 1');
        $check->execute([$login, $user_id]);
        if ($check->fetch()) json_response(['error' => 'Ce login est déjà utilisé'], 409);
        $updates[] = 'login = ?';
        $params[]  = $login;
    }
    if (isset($body['mot_de_passe'])) {
        $mdp = $body['mot_de_passe'];
        if (strlen($mdp) < 8) json_response(['error' => 'Le mot de passe doit faire au moins 8 caractères'], 400);
        $updates[] = 'mot_de_passe = ?';
        $params[]  = password_hash($mdp, PASSWORD_BCRYPT);
    }
    if (isset($body['role'])) {
        $role = strtoupper(trim($body['role']));
        validateRole($role);
        if ($user_id === (int)$payload['sub'] && $role !== 'ADMIN') {
            json_response(['error' => 'Vous ne pouvez pas retirer votre propre rôle admin'], 403);
        }
        $updates[] = 'role = ?';
        $params[]  = $role;
    }
    if (isset($body['actif'])) {
        if ($user_id === (int)$payload['sub']) {
            json_response(['error' => 'Vous ne pouvez pas modifier votre propre statut'], 403);
        }
        $updates[] = 'actif = ?';
        $params[]  = $body['actif'] ? 1 : 0;
    }

    if (empty($updates)) json_response(['error' => 'Aucun champ à mettre à jour'], 400);

    $params[] = $user_id;
    $stmt = $pdo->prepare('UPDATE UTILISATEUR SET ' . implode(', ', $updates) . ' WHERE id = ?');
    $stmt->execute($params);

    $stmt = $pdo->prepare('SELECT id, nom, prenom, login, role, actif, date_derniere_connexion FROM UTILISATEUR WHERE id = ? LIMIT 1');
    $stmt->execute([$user_id]);

    json_response(['message' => 'Utilisateur mis à jour avec succès', 'user' => formatUser($stmt->fetch())]);
}

// ── DELETE /api/admin/users.php?id=x ────────────────────────
if ($method === 'DELETE') {
    $user_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    if (!$user_id) json_response(['error' => 'ID utilisateur requis'], 400);

    if ($user_id === (int)$payload['sub']) {
        json_response(['error' => 'Vous ne pouvez pas supprimer votre propre compte'], 403);
    }

    $stmt = $pdo->prepare('SELECT id FROM UTILISATEUR WHERE id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) json_response(['error' => 'Utilisateur introuvable'], 404);

    $pdo->prepare('DELETE FROM UTILISATEUR WHERE id = ?')->execute([$user_id]);

    json_response(['message' => 'Utilisateur supprimé avec succès']);
}

json_response(['error' => 'Méthode non autorisée'], 405);

// ── Helpers locaux ───────────────────────────────────────────
function formatUser(array $u): array {
    return [
        'id'                      => (int)  $u['id'],
        'nom'                     => $u['nom'],
        'prenom'                  => $u['prenom'],
        'login'                   => $u['login'],
        'role'                    => $u['role'],
        'actif'                   => (bool) $u['actif'],
        'date_derniere_connexion' => $u['date_derniere_connexion'],
    ];
}

function validateRole(string $role): void {
    $allowed = ['UTILISATEUR', 'ADMIN', 'VISITEUR'];
    if (!in_array($role, $allowed, true)) {
        json_response(['error' => 'Rôle invalide. Autorisés : ' . implode(', ', $allowed)], 400);
    }
}