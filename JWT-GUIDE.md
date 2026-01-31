# Guide JWT - Authentification complète avec Refresh Token

Ce guide explique comment utiliser l'authentification JWT dans votre application Symfony avec refresh token.

## Architecture

Votre application utilise JWT (JSON Web Tokens) pour **toutes** les authentifications :
- Routes API (`/api/*`)
- Routes Web (`/profile`, `/dashboard`, etc.)

### Firewalls configurés

1. **login** : Route `/api/login_check` - Authentification et génération du JWT
2. **refresh** : Route `/api/token/refresh` - Rafraîchissement du token
3. **api** : Routes `/api/*` - API protégée par JWT
4. **main** : Toutes les autres routes - Pages web protégées par JWT

## Flux d'authentification

```
┌─────────────┐
│   Client    │
└──────┬──────┘
       │
       │ 1. POST /api/login_check
       │    { email, password }
       ▼
┌─────────────┐
│   Symfony   │
│   Backend   │
└──────┬──────┘
       │
       │ 2. Retourne JWT + Refresh Token
       │    { token: "eyJ...", refresh_token: "abc..." }
       ▼
┌─────────────┐
│   Client    │
│  (stockage) │
└──────┬──────┘
       │
       │ 3. Requêtes avec JWT
       │    Authorization: Bearer eyJ...
       ▼
┌─────────────┐
│   Routes    │
│  protégées  │
└─────────────┘
```

## 1. Connexion

### Endpoint
```
POST /api/login_check
Content-Type: application/json
```

### Corps de la requête
```json
{
  "email": "user@example.com",
  "password": "votre_password"
}
```

### Réponse en cas de succès
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "refresh_token": "abc123def456..."
}
```

### Exemple avec cURL
```bash
curl -X POST http://localhost:8080/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}'
```

### Exemple JavaScript
```javascript
const response = await fetch('/api/login_check', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'password'
  })
});

const data = await response.json();
// Stocker les tokens
localStorage.setItem('jwt_token', data.token);
localStorage.setItem('refresh_token', data.refresh_token);
```

## 2. Utiliser le JWT pour accéder aux routes protégées

Toutes les requêtes vers des routes protégées doivent inclure le JWT dans le header `Authorization`.

### Exemple avec cURL
```bash
curl http://localhost:8080/api/profile \
  -H "Authorization: Bearer VOTRE_TOKEN_ICI"
```

### Exemple JavaScript
```javascript
const token = localStorage.getItem('jwt_token');

const response = await fetch('/api/profile', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  }
});

const data = await response.json();
console.log(data);
```

## 3. Rafraîchir le token (Refresh Token)

Les JWT expirent après 15 minutes (900 secondes par défaut). Utilisez le refresh token pour obtenir un nouveau JWT sans redemander les credentials.

### Endpoint
```
POST /api/token/refresh
Content-Type: application/json
```

### Corps de la requête
```json
{
  "refresh_token": "abc123def456..."
}
```

### Réponse
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "refresh_token": "abc123def456..."
}
```

**Note** : Le `refresh_token` retourné peut être le même que celui envoyé (il reste valide pendant 30 jours).

### Exemple avec cURL
```bash
curl -X POST http://localhost:8080/api/token/refresh \
  -H "Content-Type: application/json" \
  -d '{"refresh_token":"VOTRE_REFRESH_TOKEN_ICI"}'
```

### Exemple JavaScript
```javascript
async function refreshAccessToken() {
  const refreshToken = localStorage.getItem('refresh_token');

  const response = await fetch('/api/token/refresh', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      refresh_token: refreshToken
    })
  });

  if (!response.ok) {
    throw new Error('Échec du rafraîchissement du token');
  }

  const data = await response.json();

  // Mettre à jour les tokens
  localStorage.setItem('jwt_token', data.token);
  if (data.refresh_token) {
    localStorage.setItem('refresh_token', data.refresh_token);
  }

  return data.token;
}
```

## 4. Routes disponibles

### Routes publiques
- `POST /api/login_check` - Connexion
- `POST /api/token/refresh` - Rafraîchir le token
- `POST /register` - Inscription (si implémentée)

### Routes protégées (nécessitent un JWT valide)

#### API
- `GET /api/profile` - Récupérer les données du profil (JSON)
- `GET /api/dashboard` - Récupérer les données du dashboard (JSON)

#### Web
- `GET /profile` - Page profil utilisateur
- `GET /dashboard` - Page dashboard utilisateur

## 5. Gestion automatique du refresh

Voici un exemple de fonction qui gère automatiquement le refresh du token en cas d'expiration :

```javascript
async function fetchWithAuth(url, options = {}) {
  let token = localStorage.getItem('jwt_token');

  // Ajouter le token aux headers
  options.headers = {
    ...options.headers,
    'Authorization': `Bearer ${token}`
  };

  let response = await fetch(url, options);

  // Si 401 (non autorisé), tenter de rafraîchir le token
  if (response.status === 401) {
    try {
      token = await refreshAccessToken();

      // Réessayer la requête avec le nouveau token
      options.headers['Authorization'] = `Bearer ${token}`;
      response = await fetch(url, options);
    } catch (error) {
      // Échec du refresh, rediriger vers login
      window.location.href = '/login';
      throw error;
    }
  }

  return response;
}

// Utilisation
const response = await fetchWithAuth('/api/profile');
const data = await response.json();
```

## 6. Page de test

Une page HTML complète de démonstration est disponible à :
```
http://localhost:8080/jwt-example.html
```

Cette page vous permet de :
- ✓ Tester la connexion
- ✓ Voir votre profil
- ✓ Accéder au dashboard
- ✓ Rafraîchir le token
- ✓ Se déconnecter

## 7. Sécurité et bonnes pratiques

### Stockage des tokens

**Option 1 : localStorage (Simple, pour débuter)**
```javascript
localStorage.setItem('jwt_token', token);
```
⚠️ Vulnérable aux attaques XSS

**Option 2 : Cookie HttpOnly (Recommandé en production)**
Le backend devrait définir un cookie HttpOnly, inaccessible depuis JavaScript.

**Option 3 : SessionStorage (Meilleur que localStorage)**
```javascript
sessionStorage.setItem('jwt_token', token);
```
Le token est supprimé à la fermeture du navigateur.

### Durée de vie des tokens

Par défaut dans votre configuration :
- **JWT** : 15 minutes (900 secondes)
- **Refresh Token** : Plus long (configurable dans `config/packages/gesdinet_jwt_refresh_token.yaml`)

### HTTPS en production
⚠️ **TOUJOURS utiliser HTTPS en production** pour protéger les tokens en transit.

## 8. Créer un utilisateur de test

Pour tester l'authentification, créez un utilisateur :

```bash
# Se connecter au conteneur
docker-compose exec app bash

# Créer un utilisateur via la console Symfony
php bin/console security:hash-password

# Ou créer une fixture / un script
```

Ou créez un endpoint d'inscription :

```php
#[Route('/register', name: 'app_register', methods: ['POST'])]
public function register(
    Request $request,
    UserPasswordHasherInterface $passwordHasher,
    EntityManagerInterface $em
): JsonResponse {
    $data = json_decode($request->getContent(), true);

    $user = new User();
    $user->setEmail($data['email']);
    $user->setPassword(
        $passwordHasher->hashPassword($user, $data['password'])
    );
    $user->setRoles(['ROLE_USER']);

    $em->persist($user);
    $em->flush();

    return new JsonResponse(['message' => 'User created'], 201);
}
```

## 9. Débogage

### Vérifier la configuration JWT
```bash
docker-compose exec app php bin/console debug:config lexik_jwt_authentication
```

### Vérifier les routes
```bash
docker-compose exec app php bin/console debug:router
```

### Vérifier les firewalls
```bash
docker-compose exec app php bin/console debug:firewall
```

### Décoder un JWT
Utilisez [jwt.io](https://jwt.io) pour décoder et inspecter vos tokens.

## 10. Codes d'erreur courants

| Code | Signification | Solution |
|------|---------------|----------|
| 401  | Token invalide ou expiré | Rafraîchir le token ou se reconnecter |
| 403  | Accès interdit (rôle insuffisant) | Vérifier les rôles requis |
| 400  | Requête invalide | Vérifier le format JSON |

## Configuration des fichiers

### security.yaml
Votre configuration actuelle utilise :
- Firewall `login` pour `/login`
- Firewall `refresh` pour `/token/refresh`
- Firewall `api` et `main` avec JWT activé

### .env
```env
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=votre_passphrase
```

Les clés RSA sont stockées dans `config/jwt/`.

## Support

En cas de problème :
1. Vérifiez les logs : `docker-compose logs -f app`
2. Vérifiez que les clés JWT existent : `ls -la back/config/jwt/`
3. Testez avec la page d'exemple : `http://localhost:8080/jwt-example.html`
