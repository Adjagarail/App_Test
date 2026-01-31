# Test du flux complet JWT avec Refresh Token

Ce document contient des commandes cURL prêtes à l'emploi pour tester l'authentification JWT complète.

## Prérequis

1. Lancer le projet : `docker-compose up -d`
2. Créer un utilisateur de test

## Étape 1 : Créer un utilisateur de test

```bash
curl -X POST http://localhost:8080/register/test
```

Cela créera un utilisateur avec :
- Email : `test@example.com`
- Password : `password`

## Étape 2 : Se connecter et obtenir les tokens

```bash
curl -X POST http://localhost:8080/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'
```

**Résultat attendu :**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "refresh_token": "e8bc8ba8e46da2683631d4cfda5e472873e14ef928e510d9fef26464f4add3ef..."
}
```

⚠️ **Copiez le `token` et le `refresh_token` pour les étapes suivantes**

## Étape 3 : Accéder à une route protégée avec le JWT

Remplacez `VOTRE_TOKEN_ICI` par le token obtenu à l'étape 2 :

```bash
curl -H "Authorization: Bearer VOTRE_TOKEN_ICI" \
  http://localhost:8080/api/profile
```

**Résultat attendu :**
```json
{
  "email": "test@example.com",
  "roles": ["ROLE_USER"]
}
```

## Étape 4 : Rafraîchir le token

Remplacez `VOTRE_REFRESH_TOKEN_ICI` par le refresh_token obtenu à l'étape 2 :

```bash
curl -X POST http://localhost:8080/api/token/refresh \
  -H "Content-Type: application/json" \
  -d '{"refresh_token":"VOTRE_REFRESH_TOKEN_ICI"}'
```

**Résultat attendu :**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9... (nouveau token)",
  "refresh_token": "e8bc8ba8e46da2683631d4cfda5e472873e14ef928e510d9fef26464f4add3ef... (même token)"
}
```

## Étape 5 : Utiliser le nouveau token

Testez que le nouveau token fonctionne :

```bash
curl -H "Authorization: Bearer NOUVEAU_TOKEN_ICI" \
  http://localhost:8080/api/profile
```

## Flux complet en une seule session

```bash
# 1. Créer un utilisateur de test
curl -X POST http://localhost:8080/register/test

# 2. Se connecter
LOGIN_RESPONSE=$(curl -s -X POST http://localhost:8080/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}')

# Afficher la réponse
echo "Login Response:"
echo $LOGIN_RESPONSE

# 3. Extraire le token (nécessite jq - si vous ne l'avez pas, copiez manuellement)
# TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.token')
# REFRESH_TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.refresh_token')

# 4. Accéder au profil (remplacez TOKEN par votre token réel)
# curl -H "Authorization: Bearer $TOKEN" http://localhost:8080/api/profile

# 5. Rafraîchir le token (remplacez REFRESH_TOKEN par votre refresh_token réel)
# REFRESH_RESPONSE=$(curl -s -X POST http://localhost:8080/api/token/refresh \
#   -H "Content-Type: application/json" \
#   -d "{\"refresh_token\":\"$REFRESH_TOKEN\"}")

# echo "Refresh Response:"
# echo $REFRESH_RESPONSE
```

## Tests additionnels

### Tester le dashboard

```bash
curl -H "Authorization: Bearer VOTRE_TOKEN_ICI" \
  http://localhost:8080/api/dashboard
```

### Tester avec un token invalide (doit retourner 401)

```bash
curl -H "Authorization: Bearer token_invalide" \
  http://localhost:8080/api/profile
```

**Résultat attendu :** Code 401 (Unauthorized)

### Tester sans token (doit retourner 401)

```bash
curl http://localhost:8080/api/profile
```

**Résultat attendu :** Code 401 (Unauthorized)

## Page de test interactive

Pour un test plus visuel, ouvrez dans votre navigateur :

```
http://localhost:8080/jwt-example.html
```

Cette page vous permet de :
- ✅ Tester la connexion
- ✅ Voir votre profil
- ✅ Accéder au dashboard
- ✅ Rafraîchir le token automatiquement
- ✅ Se déconnecter

## Durée de vie des tokens

- **JWT Token** : 15 minutes (900 secondes)
- **Refresh Token** : 30 jours (2592000 secondes)

Après 15 minutes, le JWT expire et vous devez utiliser le refresh token pour en obtenir un nouveau.

## En cas d'erreur

### Erreur 401 sur /api/login_check
- Vérifiez que l'utilisateur existe
- Vérifiez que le mot de passe est correct
- Relancez la création de l'utilisateur de test

### Erreur 401 sur les routes protégées
- Vérifiez que le token est bien dans le header `Authorization: Bearer ...`
- Vérifiez que le token n'a pas expiré (15 min)
- Utilisez le refresh token pour obtenir un nouveau JWT

### Erreur 401 sur /api/token/refresh
- Vérifiez que le refresh_token est correct
- Le refresh token a peut-être expiré (30 jours)
- Reconnectez-vous avec /api/login_check

### Erreur 500
- Vérifiez les logs : `docker-compose logs -f app`
- Vérifiez que le cache est clean : `docker-compose exec app rm -rf var/cache/*`
- Redémarrez : `docker-compose restart app`
