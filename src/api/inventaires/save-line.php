<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../config/helpers.php';

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

$db = getDB();

// Récupérer les données POST
$data = json_decode(file_get_contents('php://input'), true);

$line_id = $data['line_id'] ?? null;
$quantite_physique = $data['quantite_physique'] ?? null;
$commentaire = $data['commentaire'] ?? null;

if (!$line_id || $quantite_physique === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Données manquantes']);
    exit;
}

try {
    // Mettre à jour la ligne
    $query = "UPDATE INVENTAIRE_LIGNE 
              SET quantite_physique = ?, commentaire = ?
              WHERE id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$quantite_physique, $commentaire, $line_id]);

    // Récupérer la ligne mise à jour avec l'écart calculé
    $selectStmt = $db->prepare("SELECT * FROM INVENTAIRE_LIGNE WHERE id = ?");
    $selectStmt->execute([$line_id]);
    $updatedLine = $selectStmt->fetch(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'line' => $updatedLine
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
