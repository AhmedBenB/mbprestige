# Matrice de Tests Sprint 1

## Objectif
Valider que le produit AutoSourcing est stable, cloisonné par organisation, exploitable côté métier, et prêt pour la phase sécurité / RGPD / préproduction.

Cette matrice couvre en priorité :
- authentification
- multi-organisations
- association admin / client
- demandes client
- recherches eCarsTrade
- visibilité des résultats
- changement / reset de mot de passe
- contrôle d'accès

---

## Règles d'exécution
- Ne pas tester "à l'impression". Toujours noter un résultat.
- Un test est `OK` seulement si le résultat obtenu correspond exactement au résultat attendu.
- Un test est `KO` si le comportement est faux, incomplet, instable ou contournable.
- Un test est `BLOQUE` si un prérequis manque ou si un bug amont empêche l'exécution.
- Toute anomalie `Critique` doit être corrigée avant d'ajouter de nouvelles fonctionnalités majeures.

### Légende gravité
- `Critique` : fuite de données, auth cassée, cloisonnement cassé, recherche inutilisable
- `Majeure` : fonctionnalité clé cassée, contournement possible, parcours bloqué
- `Moyenne` : comportement dégradé mais contournable
- `Mineure` : défaut d'affichage, texte, UX, détail non bloquant

### Légende statut
- `A tester`
- `OK`
- `KO`
- `BLOQUE`

---

## Préparation des jeux de test

### Comptes
- `SUPER_ADMIN_1`
  - rôle : `super_admin`
- `ADMIN_A`
  - rôle : `admin`
  - organisation : `Garage Alpha`
- `ADMIN_B`
  - rôle : `admin`
  - organisation : `Garage Beta`
- `CLIENT_A1`
  - rattaché à `Garage Alpha`
- `CLIENT_A2`
  - non rattaché au départ
- `CLIENT_B1`
  - rattaché à `Garage Beta`

### Données minimales
- 2 organisations partenaires actives
- 1 code admin par partenaire
- 1 lien public par partenaire
- 1 code d'association client généré par `ADMIN_A`
- 1 code d'association client généré par `ADMIN_B`
- 1 demande client créée chez `Garage Alpha`
- 1 demande client créée chez `Garage Beta`
- 1 recherche eCarsTrade large qui remonte des résultats

### Environnement
- backend Laravel démarré
- base de données propre et sauvegardée avant recette
- eCarsTrade authentifié et fonctionnel
- mailer local connu
  - en local, lien de debug visible si `MAIL_MAILER=log`

---

## Go / No-Go Sprint 1

### Go minimal
- aucun `KO` critique sur auth
- aucun `KO` critique sur cloisonnement organisationnel
- aucun `KO` critique sur création / modification de demande
- aucun `KO` critique sur affichage de résultats admin
- aucun `KO` critique sur visibilité client

### No-Go
- un admin partenaire peut voir les données d'un autre partenaire
- un client peut voir ou modifier les données d'un autre client
- les recherches eCarsTrade ne remontent plus
- les mots de passe ne peuvent pas être changés ou réinitialisés

---

## Checklist rapide de recette

- [ ] Super-admin connecté
- [ ] Partenaire créé
- [ ] Admin partenaire connecté avec code
- [ ] Code client généré
- [ ] Inscription client avec code
- [ ] Inscription client sans code
- [ ] Demande d'association envoyée
- [ ] Demande d'association acceptée
- [ ] Création de demande client
- [ ] Modification de demande client
- [ ] Recherche eCarsTrade lancée
- [ ] Résultats visibles côté admin
- [ ] Résultats filtrés correctement côté client
- [ ] Changement de mot de passe client
- [ ] Changement de mot de passe admin
- [ ] Reset password client
- [ ] Reset password admin
- [ ] Admin A isolé de Admin B
- [ ] Client isolé des autres clients
- [ ] Super-admin voit tout

---

## Matrice détaillée

| ID | Domaine | Scénario | Prérequis | Action | Résultat attendu | Résultat obtenu | Statut | Gravité | Notes |
|---|---|---|---|---|---|---|---|---|---|
| AUTH-01 | Auth admin | Connexion super-admin valide | `SUPER_ADMIN_1` existe et actif | Se connecter avec email + mot de passe | Accès au dashboard admin global |  | A tester | Critique |  |
| AUTH-02 | Auth admin | Connexion super-admin mot de passe faux | `SUPER_ADMIN_1` existe | Tenter connexion avec mauvais mot de passe | Refus propre, aucun accès, message neutre |  | A tester | Critique |  |
| AUTH-03 | Auth admin | Connexion admin partenaire valide | `ADMIN_A` actif + code admin connu | Se connecter avec email + mot de passe + code admin | Accès au dashboard partenaire Alpha uniquement |  | A tester | Critique |  |
| AUTH-04 | Auth admin | Connexion admin partenaire sans code | `ADMIN_A` actif | Se connecter sans code admin | Refus propre |  | A tester | Critique |  |
| AUTH-05 | Auth admin | Connexion admin partenaire avec mauvais code | `ADMIN_A` actif | Se connecter avec mauvais code admin | Refus propre |  | A tester | Critique |  |
| AUTH-06 | Auth client | Connexion client valide | `CLIENT_A1` existe et actif | Se connecter via page client | Accès au dashboard client personnel |  | A tester | Critique |  |
| AUTH-07 | Auth client | Connexion client mot de passe faux | `CLIENT_A1` existe | Tenter connexion avec mauvais mot de passe | Refus propre, aucun accès |  | A tester | Critique |  |
| AUTH-08 | Auth client | Redirection session cliente persistante | `CLIENT_A1` connecté au moins une fois | Revenir sur la page client | Retour au dashboard si session valide |  | A tester | Majeure |  |
| PART-01 | Partenaires | Création d'un partenaire par super-admin | `SUPER_ADMIN_1` connecté | Créer `Garage Gamma` | Organisation + admin partenaire + codes créés |  | A tester | Critique |  |
| PART-02 | Partenaires | Affichage des codes partenaire | Partenaire créé | Ouvrir paramètres | Code admin, code client et lien public visibles |  | A tester | Majeure |  |
| PART-03 | Partenaires | Lien public partenaire | `Garage Alpha` existe | Ouvrir le lien public Alpha | Le formulaire reconnaît le bon partenaire |  | A tester | Majeure |  |
| ASSOC-01 | Association | Inscription client avec code | Code client Alpha valide | Créer `CLIENT_A2` avec code | Client lié automatiquement à `Garage Alpha` |  | A tester | Critique |  |
| ASSOC-02 | Association | Inscription client sans code | Aucun rattachement initial | Créer un client sans code | Compte créé, non rattaché, onboarding visible |  | A tester | Majeure |  |
| ASSOC-03 | Association | Demande d'association depuis annuaire garages | Client non rattaché connecté | Envoyer demande vers `Garage Alpha` | Demande `pending` créée |  | A tester | Majeure |  |
| ASSOC-04 | Association | Acceptation d'une demande par admin | Demande `pending` existe pour Alpha | `ADMIN_A` accepte | Client rattaché à Alpha |  | A tester | Critique |  |
| ASSOC-05 | Association | Refus d'une demande par admin | Demande `pending` existe | `ADMIN_A` refuse | Demande passe `rejected`, client non rattaché |  | A tester | Majeure |  |
| ASSOC-06 | Association | Client déjà rattaché à un autre admin | `CLIENT_B1` déjà lié à Beta | Tenter rattachement à Alpha | Blocage propre, aucun mélange |  | A tester | Critique |  |
| ASSOC-07 | Association | Code d'association invalide | Client non rattaché connecté | Saisir un faux code | Refus propre, aucun rattachement |  | A tester | Majeure |  |
| SEARCH-01 | Demandes | Création d'une demande client | `CLIENT_A1` connecté | Créer une demande complète | Demande sauvegardée et visible côté client/admin Alpha |  | A tester | Critique |  |
| SEARCH-02 | Demandes | Modification d'une demande client | Une demande existe | Modifier budget / année / commentaire | Données mises à jour, nouveau run lancé si prévu |  | A tester | Majeure |  |
| SEARCH-03 | Demandes | Demande visible uniquement dans la bonne organisation | Une demande Alpha existe | Ouvrir dashboard `ADMIN_A`, puis `ADMIN_B` | Visible chez Alpha, absente chez Beta |  | A tester | Critique |  |
| SEARCH-04 | eCarsTrade | Lancement manuel d'une recherche depuis l'admin | Une demande existe | Cliquer `Relancer` | Run créé, statut cohérent |  | A tester | Critique |  |
| SEARCH-05 | eCarsTrade | Recherche large retourne des annonces | Connecteur eCarsTrade valide | Utiliser critères larges connus | Des résultats remontent dans la fiche admin |  | A tester | Critique |  |
| SEARCH-06 | eCarsTrade | Fiche candidat affiche les annonces correspondantes | Résultats existants | Ouvrir fiche candidat | Liste d'annonces complète affichée |  | A tester | Majeure |  |
| SEARCH-07 | eCarsTrade | Tri enchères puis prix fixes | Résultats mixtes | Ouvrir fiche candidat | Enchères d'abord, prix fixes ensuite |  | A tester | Moyenne |  |
| SEARCH-08 | Budget | Filtre budget cohérent avec règle métier | Budget client connu | Vérifier des annonces bien sous budget | Les annonces inférieures au plafond passent |  | A tester | Critique |  |
| SEARCH-09 | Résultats | Résultat visible côté client seulement si autorisé | Résultats avec statuts variés | Ouvrir dashboard client | Le client ne voit que les résultats partagés / autorisés |  | A tester | Critique |  |
| SEARCH-10 | Historique | Historique récent des runs | Plusieurs runs existent | Ouvrir dashboard client/admin | Historique visible et cohérent |  | A tester | Moyenne |  |
| ACL-01 | Cloisonnement | Admin Alpha ne voit jamais Beta | `ADMIN_A`, `ADMIN_B` et données distinctes | Lister demandes, résultats, clients | Alpha ne voit rien de Beta |  | A tester | Critique |  |
| ACL-02 | Cloisonnement | Admin Beta ne voit jamais Alpha | idem | Lister demandes, résultats, clients | Beta ne voit rien d'Alpha |  | A tester | Critique |  |
| ACL-03 | Cloisonnement | Tentative d'accès direct à une ressource étrangère par ID | Deux organisations avec données | Appeler une route avec un ID d'une autre org | Refus ou 404/403 côté serveur |  | A tester | Critique |  |
| ACL-04 | Cloisonnement | Client ne voit que ses propres demandes | Deux clients différents | Ouvrir dashboard client A puis B | Chacun ne voit que ses demandes |  | A tester | Critique |  |
| ACL-05 | Cloisonnement | Client ne peut pas modifier une demande étrangère | Deux clients différents | Forcer un `PATCH` sur une autre demande | Refus côté serveur |  | A tester | Critique |  |
| ACL-06 | Cloisonnement | Super-admin voit toutes les organisations | `SUPER_ADMIN_1` connecté | Ouvrir listes / partenaires / demandes | Vue globale correcte |  | A tester | Majeure |  |
| PASS-01 | Sécurité compte | Client change son mot de passe depuis son espace | `CLIENT_A1` connecté | Renseigner ancien + nouveau mot de passe | Changement accepté, message OK |  | A tester | Majeure |  |
| PASS-02 | Sécurité compte | Client saisit un mauvais mot de passe actuel | `CLIENT_A1` connecté | Tenter changement avec ancien mot de passe faux | Refus propre |  | A tester | Majeure |  |
| PASS-03 | Sécurité compte | Admin change son mot de passe depuis paramètres | `ADMIN_A` connecté | Renseigner ancien + nouveau mot de passe | Changement accepté |  | A tester | Majeure |  |
| PASS-04 | Sécurité compte | Admin saisit un mauvais mot de passe actuel | `ADMIN_A` connecté | Tenter changement avec ancien mot de passe faux | Refus propre |  | A tester | Majeure |  |
| RESET-01 | Reset password | Client demande un lien de reset | `CLIENT_A1` existe | Utiliser `Mot de passe oublie` côté client | Message neutre + lien debug/mail local |  | A tester | Majeure |  |
| RESET-02 | Reset password | Client réinitialise son mot de passe avec token valide | Token client valide | Ouvrir lien + saisir nouveau mot de passe | Mot de passe mis à jour |  | A tester | Critique |  |
| RESET-03 | Reset password | Client reset avec token invalide | Aucun token valide | Utiliser faux token | Refus propre |  | A tester | Majeure |  |
| RESET-04 | Reset password | Admin demande un lien de reset | `ADMIN_A` existe | Utiliser `Mot de passe oublie` côté admin | Message neutre + lien debug/mail local |  | A tester | Majeure |  |
| RESET-05 | Reset password | Admin réinitialise son mot de passe avec token valide | Token admin valide | Ouvrir lien + saisir nouveau mot de passe | Mot de passe mis à jour |  | A tester | Critique |  |
| RESET-06 | Reset password | Admin reset avec token invalide | Aucun token valide | Utiliser faux token | Refus propre |  | A tester | Majeure |  |
| UI-01 | UX | Page client sans page blanche | Front client disponible | Ouvrir connexion client | La page se charge proprement |  | A tester | Majeure |  |
| UI-02 | UX | Page admin sans erreur JS bloquante | Front admin disponible | Ouvrir dashboard admin | La page se charge proprement |  | A tester | Majeure |  |
| UI-03 | UX | Messages d'erreur compréhensibles | Erreurs contrôlées testées | Déclencher erreurs login/reset/code | Messages lisibles et non techniques |  | A tester | Moyenne |  |
| PERF-01 | Robustesse | Double clic sur actions sensibles | Formulaires actifs | Double cliquer sur login / save / reset | Pas de doublon gênant |  | A tester | Moyenne |  |

---

## Cas API / sécurité à exécuter manuellement

Ces cas doivent être testés soit via navigateur, soit via Postman/Insomnia/cURL.

| ID | Route / zone | Test | Résultat attendu | Statut | Gravité | Notes |
|---|---|---|---|---|---|---|
| API-01 | `/api/admin/requests/{id}` | Admin A tente d'ouvrir une demande de B | Refus ou non trouvé | A tester | Critique |  |
| API-02 | `/api/admin/matches/{id}` | Admin A tente d'ouvrir un résultat de B | Refus ou non trouvé | A tester | Critique |  |
| API-03 | `/api/client/searches/{id}` | Client A tente d'ouvrir une demande de Client B | Refus ou non trouvé | A tester | Critique |  |
| API-04 | `/api/client/searches/{id}` | Client A tente de modifier une demande de Client B | Refus côté serveur | A tester | Critique |  |
| API-05 | `/api/admin/partners` | Admin partenaire tente d'accéder à une route réservée super-admin | Refus côté serveur si non autorisé | A tester | Critique |  |
| API-06 | `/api/client/account/password` | Requête sans token | Refus 401 | A tester | Majeure |  |
| API-07 | `/api/admin/account/password` | Requête sans token | Refus 401 | A tester | Majeure |  |
| API-08 | `/api/client/reset-password` | Token invalide | Refus 422 | A tester | Majeure |  |
| API-09 | `/api/admin/reset-password` | Token invalide | Refus 422 | A tester | Majeure |  |

---

## Journal de bugs

| Bug ID | Date | Domaine | Description | Gravité | Reproduit ? | Corrigé ? | Vérifié ? |
|---|---|---|---|---|---|---|---|
| BUG-001 |  |  |  |  |  |  |  |
| BUG-002 |  |  |  |  |  |  |  |
| BUG-003 |  |  |  |  |  |  |  |

---

## Synthèse de fin de recette

### Résumé
- Tests exécutés :
- OK :
- KO :
- BLOQUE :

### Critiques ouvertes
- 

### Majeures ouvertes
- 

### Décision
- [ ] GO pour Sprint 2
- [ ] GO conditionnel après corrections
- [ ] NO-GO

### Observations
- 

