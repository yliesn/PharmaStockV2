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

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    json_response(['error' => 'Méthode non autorisée'], 405);
}

$supply_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$supply_id) {
    json_response(['error' => 'ID fourniture requis'], 400);
}

$pdo = getDB();

// Vérifie que la fourniture existe
$stmt = $pdo->prepare('SELECT id FROM FOURNITURE WHERE id = ? LIMIT 1');
$stmt->execute([$supply_id]);
if (!$stmt->fetch()) {
    json_response(['error' => 'Fourniture introuvable'], 404);
}

$body = json_decode(file_get_contents('php://input'), true);

$updates = [];
$params = [];

// Référence
if (isset($body['reference'])) {
    $reference = trim($body['reference']);
    if (!$reference) {
        json_response(['error' => 'Référence ne peut pas être vide'], 400);
    }
    // Vérifie que la référence est unique
    $stmt = $pdo->prepare('SELECT id FROM FOURNITURE WHERE reference = ? AND id != ? LIMIT 1');
    $stmt->execute([$reference, $supply_id]);
    if ($stmt->fetch()) {
        json_response(['error' => 'Cette référence existe déjà'], 409);
    }
    $updates[] = 'reference = ?';
    $params[] = $reference;
}

// Designation
if (isset($body['designation'])) {
    $designation = trim($body['designation']);
    if (!$designation) {
        json_response(['error' => 'Désignation ne peut pas être vide'], 400);
    }
    $updates[] = 'designation = ?';
    $params[] = $designation;
}

// Conditionnement
if (isset($body['conditionnement'])) {
    $updates[] = 'conditionnement = ?';
    $params[] = $body['conditionnement'] ? trim($body['conditionnement']) : null;
}

// Quantité stock
if (isset($body['quantite_stock'])) {
    $quantite = (int)$body['quantite_stock'];
    if ($quantite < 0) {
        json_response(['error' => 'La quantité ne peut pas être négative'], 400);
    }
    $updates[] = 'quantite_stock = ?';
    $params[] = $quantite;
}

// Seuil alerte
if (isset($body['seuil_alerte'])) {
    $updates[] = 'seuil_alerte = ?';
    $params[] = $body['seuil_alerte'] ? (int)$body['seuil_alerte'] : null;
}

// Commande en cours
if (isset($body['commande_en_cours'])) {
    $updates[] = 'commande_en_cours = ?';
    $params[] = $body['commande_en_cours'] ? 1 : 0;
}

// Ordre de passage à l'inventaire (entier >= 0, ou null / vide pour le retirer)
if (array_key_exists('ordre', $body ?? [])) {
    $ordre = $body['ordre'];
    if ($ordre === null || $ordre === '') {
        $ordre = null;
    } elseif (filter_var($ordre, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
        json_response(['error' => "L'ordre doit être un entier positif ou vide"], 400);
    } else {
        $ordre = (int) $ordre;
    }
    $updates[] = 'ordre = ?';
    $params[] = $ordre;
}

// Actif / inactif (tout utilisateur connecté). Rien n'est supprimé : l'historique est conservé.
if (array_key_exists('actif', $body ?? [])) {
    $updates[] = 'actif = ?';
    $params[] = $body['actif'] ? 1 : 0;
}

if (empty($updates)) {
    json_response(['error' => 'Aucun champ à mettre à jour'], 400);
}

$params[] = $supply_id;
$query = 'UPDATE FOURNITURE SET ' . implode(', ', $updates) . ' WHERE id = ?';

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    // Récupère la fourniture mise à jour
    $stmt = $pdo->prepare('
        SELECT ' . SUPPLY_COLUMNS . '
        FROM FOURNITURE
        WHERE id = ?
        LIMIT 1
    ');
    $stmt->execute([$supply_id]);
    $supply = $stmt->fetch();

    json_response([
        'message' => 'Fourniture mise à jour avec succès',
        'data' => format_supply($supply),
    ], 200);
} catch (PDOException $e) {
    json_response(['error' => 'Erreur lors de la mise à jour de la fourniture'], 500);
}
