<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../config/helpers.php';

// Vérifier le JWT
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

// Récupérer les données POST
$data = json_decode(file_get_contents('php://input'), true);

$commentaire = $data['commentaire'] ?? null;
$user_id = $data['utilisateur_id'];

$db = getDB();

try {
    // Créer l'inventaire
    $query = "INSERT INTO INVENTAIRE (utilisateur_id, commentaire, date_inventaire) 
              VALUES (?, ?, NOW())";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $commentaire]);

    $inventaire_id = $db->lastInsertId();

    // Récupérer tous les produits
    $products = $db->query("SELECT id, reference, designation, quantite_stock FROM FOURNITURE ORDER BY reference");
    
    // Créer les lignes d'inventaire
    $insertStmt = $db->prepare("INSERT INTO INVENTAIRE_LIGNE 
                               (inventaire_id, fourniture_id, quantite_theorique, quantite_physique) 
                               VALUES (?, ?, ?, 0)");

    foreach ($products as $product) {
        $insertStmt->execute([
            $inventaire_id,
            $product['id'],
            $product['quantite_stock']
        ]);
    }

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'inventaire_id' => $inventaire_id    
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
