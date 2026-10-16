# API REST v1

API JSON pour brancher d'autres outils (ERP, tableaux de bord, automatisations).

- **URL de base** : `https://votre-domaine/api/v1`
- **Authentification** : jeton `Bearer` créé dans *Administration → API & webhooks*.
  Le jeton agit au nom d'un utilisateur : ses rôles et ses modules (litiges, clients, relances) sont respectés.
  Un jeton « lecture seule » ne peut pas utiliser les routes `POST`.
- **Limites** : 120 requêtes par minute et par jeton (`429` + `Retry-After` au-delà).
- **Format** : JSON (`Content-Type: application/json`) ; les formulaires classiques sont aussi acceptés.
- **Mises à jour** : par `POST` (pas de `PUT`/`PATCH`).
- **Erreurs** : `{"error": {"code": "...", "message": "..."}}` avec le code HTTP adapté
  (`401` jeton absent/invalide, `403` droits insuffisants, `404`, `409` doublon ou workflow, `422` données invalides, `429`).

```bash
curl -H "Authorization: Bearer lit_xxxxxxxx" https://votre-domaine/api/v1/litiges?etat=ouverts
```

## Litiges

| Méthode | Route | Description |
|---|---|---|
| `GET` | `/litiges` | Liste paginée. Filtres : `q`, `client_id`, `statut_id`, `agence_id`, `priorite`, `etat` (`ouverts`/`clotures`), `tri`, `page`, `par_page` (≤ 100). |
| `GET` | `/litiges/{id}` | Détail avec paiements, échéances et relances. |
| `POST` | `/litiges` | Création (jeton en écriture). |
| `POST` | `/litiges/{id}/statut` | Changement de statut (le workflow configuré est appliqué). |
| `POST` | `/litiges/{id}/paiements` | Enregistrer un paiement. |
| `POST` | `/litiges/{id}/relances` | Ajouter une relance (module « relances » requis). |

### Création d'un litige

```json
{
  "client_siren": "123456789",
  "objet": "Impayé facture F-2025-0042",
  "montant_principal": 1250.00,
  "numero_facture": "F-2025-0042",
  "date_facture": "2025-02-10",
  "date_echeance": "2025-03-10",
  "priorite": "haute",
  "forcer_doublon": false
}
```

Le client est désigné par `client_id`, `client_siren` ou `client_code_erp`. `statut_id` (par défaut : « Nouveau »)
et `date_ouverture` (par défaut : aujourd'hui) sont facultatifs. Si un litige existe déjà pour la même facture,
la réponse est `409 doublon` sauf si `forcer_doublon` vaut `true`.

### Enregistrer un paiement

```json
{ "date_paiement": "2025-04-02", "montant": 500.00, "mode": "virement", "reference": "VIR-889", "reference_externe": "BLG-REG-5521" }
```

`reference_externe` rend l'appel **idempotent** : rejouer la même requête ne crée pas de doublon
(réponse `200` avec `"deja_enregistre": true`). Modes : `virement`, `cheque`, `cb`, `prelevement`, `especes`, `compensation`, `autre`.

### Changer le statut

```json
{ "statut_id": 4, "finalite_id": null, "commentaire": "Mise en demeure envoyée" }
```

## Clients

| Méthode | Route | Description |
|---|---|---|
| `GET` | `/clients` | Recherche (`q`) paginée. |
| `GET` | `/clients/{id}` | Fiche et encours. |

## Webhooks (événements sortants)

Configurés dans *Administration → API & webhooks*. Chaque événement est envoyé en `POST` JSON :

```json
{ "evenement": "paiement.enregistre", "date": "2025-04-02T10:15:00+02:00", "donnees": { "litige_id": 12, "reference": "LIT-2025-0012", "montant": 500, "encours": 750 } }
```

Événements : `litige.cree`, `litige.modifie`, `litige.statut_change`, `litige.cloture`, `paiement.enregistre`,
`relance.ajoutee`, `echeance.manquee`, `procedure_collective.detectee`.

### Vérifier la signature

En-têtes envoyés : `X-Litiges-Event`, `X-Litiges-Delivery`, `X-Litiges-Signature: t=<horodatage>,v1=<signature>`.
La signature est `HMAC-SHA256(horodatage + "." + corps_brut, secret)` en hexadécimal. Rejetez les requêtes dont
l'horodatage a plus de 5 minutes.

```php
[$t, $v1] = array_map(fn($p) => explode('=', $p, 2)[1], explode(',', $_SERVER['HTTP_X_LITIGES_SIGNATURE']));
$ok = hash_equals(hash_hmac('sha256', $t . '.' . file_get_contents('php://input'), $secret), $v1)
      && abs(time() - (int) $t) < 300;
```

Une réponse `2xx` valide la livraison. Les échecs sont rejoués (1 min, 5 min, 30 min, 2 h, 12 h) par `cron/webhooks.php`.
Après 20 échecs consécutifs le webhook est désactivé. Les adresses du réseau interne sont refusées.
