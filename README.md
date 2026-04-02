# API Authentification - Login

## Vue d'ensemble

Ce projet fournit un système complet d'authentification avec JWT (JSON Web Tokens). Il inclut une interface de connexion HTML et deux endpoints API.

---

## API Endpoints

### 1. `POST /api/login.php` - Connexion

**Description**: Authentifie un utilisateur avec email et mot de passe, retourne un JWT.

**Méthode HTTP**: `POST`

**Headers requis**:
```
Content-Type: application/json
```

**Body (JSON)**:
```json
{
  "email": "user@example.com",
  "password": "motdepasse123"
}
```

**Réponse en cas de succès** (200):
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user": {
    "id": 1,
    "username": "john_doe",
    "email": "user@example.com",
    "role": "admin"
  }
}
```

**Réponses d'erreur**:
- `400` - Champs manquants (email ou password vide)
- `401` - Identifiants invalides (email inexistant ou mot de passe incorrect)
- `405` - Méthode non autorisée (doit être POST)

**Logique**:
1. Vérifie que email et password sont fournis
2. Recherche l'utilisateur par email dans la BD
3. Valide le mot de passe avec `password_verify()`
4. Génère un JWT valide 1h
5. Retourne le token et les infos utilisateur

---

### 2. `GET /api/me.php` - Récupérer mon profil

**Description**: Retourne les infos de l'utilisateur actuel en utilisant son JWT valide.

**Méthode HTTP**: `GET`

**Headers requis**:
```
Authorization: Bearer <JWT_TOKEN>
```

**Réponse en cas de succès** (200):
```json
{
  "user": {
    "id": 1,
    "username": "john_doe",
    "email": "user@example.com",
    "role": "admin",
    "created_at": "2026-03-15 10:30:00"
  }
}
```

**Réponses d'erreur**:
- `401` - Token manquant
- `401` - Token invalide ou expiré
- `404` - Utilisateur introuvable en BD
- `405` - Méthode non autorisée (doit être GET)

**Logique**:
1. Récupère le JWT depuis le header `Authorization`
2. Valide la signature et l'expiration du JWT
3. Extrait l'ID utilisateur du payload du token
4. Récupère les infos fraîches depuis la BD
5. Retourne les données utilisateur

---

## Flux d'authentification côté client

```
1. Utilisateur remplit form email/password → login.html
2. POST vers /api/login.php
3. Reçoit token + user data
4. Stocke token dans localStorage['jwt']
5. Redirige vers dashboard.html
6. Dashboard charge → GET /api/me.php avec token
7. Affiche les infos utilisateur
```

---

## Configuration

- **JWT Secret**: Défini dans `config/jwt.php` (ligne `JWT_SECRET`)
- **Durée JWT**: 3600 secondes (1 heure) - défini dans `config/jwt.php` (ligne `JWT_EXPIRY`)
- **Base de données**: `.php/config/db.php`

---

## Fichiers

- `src/api/login.php` - Endpoint de connexion
- `src/api/me.php` - Endpoint profil utilisateur
- `src/config/jwt.php` - Fonctions JWT et configuration
- `src/config/db.php` - Connexion base de données
- `src/config/helpers.php` - Fonctions utilitaires
- `src/views/login.html` - Page de connexion
- `src/views/dashboard.html` - Page dashboard (protégée)
