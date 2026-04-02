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

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    json_response(['error' => 'Méthode non autorisée'], 405);
}

$supply_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$supply_id) {
    json_response(['error' => 'ID fourniture requis'], 400);
}

$pdo = getDB();

// Vérifie que la fourniture existe
$stmt = $pdo->prepare('SELECT id, reference FROM FOURNITURE WHERE id = ? LIMIT 1');
$stmt->execute([$supply_id]);
$supply = $stmt->fetch();

if (!$supply) {
    json_response(['error' => 'Fourniture introuvable'], 404);
}

try {
    // Supprime d'abord les mouvements de stock
    $stmt = $pdo->prepare('DELETE FROM MOUVEMENT_STOCK WHERE id_fourniture = ?');
    $stmt->execute([$supply_id]);

    // Supprime les peremptions
    $stmt = $pdo->prepare('DELETE FROM PEREMPTION WHERE fourniture_id = ?');
    $stmt->execute([$supply_id]);

    // Supprime les approbations
    $stmt = $pdo->prepare('DELETE FROM APPROBATION WHERE supply_id = ?');
    $stmt->execute([$supply_id]);

    // Supprime les lignes d'inventaire
    $stmt = $pdo->prepare('DELETE FROM INVENTAIRE_LIGNE WHERE fourniture_id = ?');
    $stmt->execute([$supply_id]);

    // Supprime la fourniture
    $stmt = $pdo->prepare('DELETE FROM FOURNITURE WHERE id = ?');
    $stmt->execute([$supply_id]);

    json_response([
        'message' => 'Fourniture supprimée avec succès',
        'reference' => $supply['reference'],
    ], 200);
} catch (PDOException $e) {
    json_response(['error' => 'Erreur lors de la suppression de la fourniture'], 500);
}
