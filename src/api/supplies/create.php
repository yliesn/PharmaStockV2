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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Méthode non autorisée'], 405);
}

$body = json_decode(file_get_contents('php://input'), true);

// Valide les champs requis
$designation = isset($body['designation']) ? trim($body['designation']) : '';

if (!$designation) {
    json_response(['error' => 'Désignation requise'], 400);
}

$conditionnement = isset($body['conditionnement']) ? trim($body['conditionnement']) : null;
$quantite_stock = isset($body['quantite_stock']) ? (int)$body['quantite_stock'] : 0;
$seuil_alerte = isset($body['seuil_alerte']) ? (int)$body['seuil_alerte'] : null;
$commande_en_cours = isset($body['commande_en_cours']) ? (bool)$body['commande_en_cours'] : false;

$pdo = getDB();

// Crée la fourniture sans référence (sera générée après insertion)
$stmt = $pdo->prepare('
    INSERT INTO FOURNITURE (reference, designation, conditionnement, quantite_stock, seuil_alerte, commande_en_cours)
    VALUES (?, ?, ?, ?, ?, ?)
');

try {
    // Insère avec une référence temporaire
    $stmt->execute([
        'TEMP',
        $designation,
        $conditionnement,
        $quantite_stock,
        $seuil_alerte,
        $commande_en_cours ? 1 : 0,
    ]);
    $id = $pdo->lastInsertId();

    // Génère la référence: PH + id sur 3 caractères avec zéros complétants
    $reference = 'PH' . str_pad($id, 3, '0', STR_PAD_LEFT);

    // Mise à jour la référence
    $stmt = $pdo->prepare('UPDATE FOURNITURE SET reference = ? WHERE id = ?');
    $stmt->execute([$reference, $id]);

    json_response([
        'message' => 'Fourniture créée avec succès',
        'data' => [
            'id'                => (int) $id,
            'reference'         => $reference,
            'designation'       => $designation,
            'conditionnement'   => $conditionnement,
            'quantite_stock'    => $quantite_stock,
            'seuil_alerte'      => $seuil_alerte,
            'commande_en_cours' => $commande_en_cours,
        ],
    ], 201);
} catch (PDOException $e) {
    json_response(['error' => 'Erreur lors de la création de la fourniture'], 500);
}
