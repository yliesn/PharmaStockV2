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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Méthode non autorisée'], 405);
}

$db = getDB();

// Récupérer l'ID de l'inventaire
$inventaire_id = $_GET['id'] ?? null;

if (!$inventaire_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inventaire manquant']);
    exit;
}

try {
    // Récupérer les lignes de l'inventaire
    $query = "SELECT 
                il.id,
                il.inventaire_id,
                il.fourniture_id,
                il.quantite_theorique,
                il.quantite_physique,
                il.ecart,
                il.commentaire,
                f.reference,
                f.designation,
                f.conditionnement
              FROM INVENTAIRE_LIGNE il
              JOIN FOURNITURE f ON il.fourniture_id = f.id
              WHERE il.inventaire_id = ?
              ORDER BY f.reference";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$inventaire_id]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les infos de l'inventaire
    $invStmt = $db->prepare("SELECT * FROM INVENTAIRE WHERE id = ?");
    $invStmt->execute([$inventaire_id]);
    $inventaire = $invStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'inventaire' => $inventaire,
        'lines' => $lines,
        'total' => count($lines)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
