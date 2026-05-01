# AutoMoto B2B — Marketplace Automobile Laravel

Plateforme B2B d'achat de véhicules d'occasion, inspirée d'eCarsTrade.
Architecture Laravel modulaire, complète et prête à déployer.

---

## Stack technique

- **Backend** : Laravel 10+, PHP 8.2+
- **Base de données** : MySQL 8 ou PostgreSQL 14+
- **Cache / Queue** : Redis + Laravel Horizon
- **Stockage** : S3-compatible (ou `storage/public` en local)
- **Frontend** : Blade + Alpine.js + Tailwind CSS
- **Auth** : Laravel Sanctum
- **Recherche** : Laravel Scout + Meilisearch (optionnel)

---

## Installation

```bash
# 1. Cloner et installer
git clone ...
cd automoto
composer install
npm install && npm run build

# 2. Configurer l'environnement
cp .env.example .env
php artisan key:generate

# 3. Base de données
php artisan migrate
php artisan db:seed

# 4. Lancer
php artisan serve
php artisan queue:work   # dans un second terminal
```

---

## Comptes de test

| Rôle  | Email                   | Mot de passe |
|-------|-------------------------|--------------|
| Admin | admin@automoto.test     | password     |
| Pro   | user0@automoto.test     | password     |
| Pro   | user1@automoto.test     | password     |

---

## Architecture

```
app/
├── Console/Kernel.php              # Scheduler (crons)
├── Enums/
│   ├── ListingTypeEnum.php         # auction_open, auction_blind, fixed_price, partner_stock
│   ├── PublicationStatusEnum.php   # draft → published → archived
│   ├── AuctionStatusEnum.php       # scheduled → live → ended_waiting_validation
│   └── BidStatusEnum.php           # pending → leading → outbid → accepted/rejected
├── Http/
│   ├── Controllers/
│   │   ├── Public/                 # Pages publiques
│   │   ├── App/                    # Espace client connecté
│   │   └── Admin/                  # Back-office admin
│   ├── Middleware/AdminMiddleware.php
│   └── Requests/                   # Form requests validés
├── Jobs/                           # Queue jobs
│   ├── SyncSourceJob               # Sync sources toutes les 15 min
│   ├── RefreshAuctionStatusesJob   # Toutes les minutes
│   ├── ResolveEndedAuctionsJob     # Toutes les minutes
│   ├── PublishApprovedListingsJob  # Toutes les 5 min
│   ├── ProcessListingImagesJob     # Async par listing
│   ├── NotifyOutbidUsersJob        # Async à chaque enchère
│   └── ArchiveExpiredListingsJob   # Nuit
├── Models/
│   ├── Listing.php                 # Annonce commerciale
│   ├── Vehicle.php                 # Master data véhicule
│   ├── Auction.php                 # Mécanique d'enchère
│   ├── Bid.php                     # Offre utilisateur
│   ├── Organization.php            # Société cliente
│   └── Models.php                  # Source, Favorite, SavedSearch, Images...
├── Notifications/
│   └── OutbidNotification.php
└── Services/
    ├── Auctions/
    │   ├── AuctionStateResolver.php  # Calcul état enchère en temps réel
    │   └── PlaceBidService.php       # Placement offre avec lock DB
    ├── Imports/
    │   └── ImportListingService.php  # Normalisation + dédup + media
    └── Listings/
        └── ListingPublicationService.php
```

---

## Tables principales

| Table                | Rôle |
|----------------------|------|
| `organizations`      | Sociétés clientes (trial/silver/golden) |
| `users`              | Comptes liés aux organisations |
| `sources`            | Sources d'import (API, CSV, XML) |
| `source_imports`     | Historique des syncs |
| `source_import_items`| Items bruts de chaque import |
| `vehicles`           | Master data technique véhicule |
| `listings`           | Annonces publiées |
| `auctions`           | Enchères liées aux listings |
| `bids`               | Offres des utilisateurs |
| `listing_images`     | Images avec pipeline CDN |
| `listing_documents`  | Documents (carpass, expertise…) |
| `listing_attributes` | Options/équipements groupés |
| `favorites`          | Favoris utilisateur |
| `saved_searches`     | Alertes sauvegardées |
| `user_notifications` | Centre de notifications |
| `audit_logs`         | Traçabilité de toutes les actions |

---

## Routes

### Publiques
```
GET  /                        → Homepage
GET  /catalogue               → Catalogue avec filtres
GET  /encheres                → Enchères uniquement
GET  /prix-fixes              → Prix fixes
GET  /stock                   → Stock partenaire
GET  /vehicules/{slug}        → Fiche véhicule
GET  /marques/{make}/{model}  → Page SEO marque/modèle
GET  /comment-ca-marche       → Explication parcours
GET  /frais                   → Frais et commissions
GET  /faq                     → FAQ
```

### Espace client (auth requis)
```
GET  /app                     → Dashboard
GET  /app/offres              → Mes offres
POST /app/vehicules/{id}/offres → Placer une offre
GET  /app/favoris             → Mes favoris
GET  /app/alertes             → Mes alertes
GET  /app/profil              → Mon profil
```

### Admin
```
GET  /admin                   → Dashboard admin
GET  /admin/listings          → Gestion annonces
POST /admin/listings/{id}/approve → Approuver
POST /admin/listings/{id}/publish → Publier
GET  /admin/sources           → Sources d'import
POST /admin/sources/{id}/sync → Lancer sync manuelle
GET  /admin/imports           → Historique imports
GET  /admin/auctions          → Monitor enchères
```

---

## Logique d'enchère

### Open Auction
- L'utilisateur voit la meilleure offre en cours
- Doit surenchérir d'au moins `minimum_increment`
- **Offre non annulable**
- Soft-close : si offre dans les 120 dernières secondes → prolongation de 120s

### Blind Auction
- Offre soumise sans voir les autres
- **Modifiable tant que l'enchère est active**
- À la clôture → meilleure offre désignée winner

### Achat immédiat (Fixed Price / Buy Now)
- Lock pessimiste → premier arrivé, premier servi
- Passage en `sold_pending_payment`
- Timeout si paiement non reçu

---

## Chronomètre front (Alpine.js)

```javascript
Alpine.data('auctionTimer', (config) => ({
    // Synchronisation avec horloge serveur
    // Calcul offset client/serveur
    // États : scheduled → live → ending_soon → ended_waiting_validation
    // Affichage : 2j 04h / 04h 18m / 18m 12s / 12s
}))
```

---

## Pipeline d'import

```
Source (API/CSV/XML)
    → SourceImportItem (raw payload)
    → ImportListingService (normalise + déduplique)
    → Vehicle (upsert par VIN ou hash)
    → Listing (upsert par source_external_id)
    → ProcessListingImagesJob (télécharge + CDN)
    → publication_status: imported → enriched → ready_for_review → approved → published
```

---

## Scheduler (crons)

| Job | Fréquence |
|-----|-----------|
| SyncSourceJob | Toutes les 15 min |
| RefreshAuctionStatusesJob | Chaque minute |
| ResolveEndedAuctionsJob | Chaque minute |
| PublishApprovedListingsJob | Toutes les 5 min |
| ArchiveExpiredListingsJob | Chaque nuit à 02h00 |

---

## Niveaux utilisateur (Organisation)

| Tier | Offres actives max | Délai paiement | Parking gratuit |
|------|--------------------|----------------|-----------------|
| Trial | 5 | Standard | — |
| Silver | 50 | Standard | — |
| Golden | Illimité | 7 jours | 15 jours |

---

## Prochaines étapes recommandées

1. **Connexion auth** : Implémenter les controllers `AuthController` (login, register, logout)
2. **Moteur de recherche** : Intégrer Laravel Scout + Meilisearch sur le modèle `Listing`
3. **API connecteur** : Ajouter un vrai connecteur source (CSV upload ou API REST)
4. **Pages SEO** : Générer `/marques/{make}/{model}` avec contenu structuré
5. **Paiement** : Intégrer Stripe ou virement bancaire dans le tunnel post-achat
6. **PWA** : Ajouter manifest + service worker pour l'installation web-app
7. **Tests** : Ajouter PHPUnit pour `PlaceBidService` et `AuctionStateResolver`
