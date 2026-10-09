<?php

function send_cors(): void {
    header('Access-Control-Allow-Origin: *'); // Restreindre en prod
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function json_response(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

const ROLE_ADMIN       = 'ADMIN';
const ROLE_UTILISATEUR = 'UTILISATEUR';
const ROLE_VISITEUR    = 'VISITEUR';
const ALLOWED_ROLES    = [ROLE_UTILISATEUR, ROLE_ADMIN, ROLE_VISITEUR];

function is_admin(mixed $payload): bool {
    return isset($payload['role']) && $payload['role'] === ROLE_ADMIN;
}

/** Normalise un rôle reçu du client (casse, espaces) vers sa forme stockée en base. */
function normalize_role(string $role): string {
    return strtoupper(trim($role));
}

/** Arrête la requête avec une 400 si le rôle n'est pas autorisé. */
function validate_role(string $role): void {
    if (!in_array($role, ALLOWED_ROLES, true)) {
        json_response(['error' => 'Rôle invalide. Rôles autorisés : ' . implode(', ', ALLOWED_ROLES)], 400);
    }
}
