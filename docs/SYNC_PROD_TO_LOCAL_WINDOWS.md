# Synchroniser la prod vers `C:\Users\AHMED\Desktop\mbprestige`

## 1) Prérequis

- Avoir accès SSH à la prod (exemple `root@178.105.61.0`).
- Ouvrir PowerShell.
- Vérifier:
  - `ssh -V`
  - `scp -V`

## 2) Exécuter la sync

Dans PowerShell:

```powershell
Set-Location -LiteralPath "C:\Users\AHMED\Desktop\mbprestige"
.\scripts\sync-prod-to-local.ps1 -ServerHost "root@178.105.61.0" -IncludeEnv
```

Options utiles:

- `-IncludeEnv`: récupère aussi `.env` prod.
- `-IncludeStorage`: récupère aussi `storage`.
- `-IncludeVendor`: récupère aussi `vendor` (plus lourd, souvent inutile).

Le script:

- fait un backup local automatique
- télécharge les fichiers/dossiers clés depuis `/var/www/mbprestige`
- remplace la copie locale

## 3) Vérification locale après sync

```powershell
Set-Location -LiteralPath "C:\Users\AHMED\Desktop\mbprestige"
php artisan optimize:clear
php artisan migrate --force
php artisan route:list | Select-String -Pattern "api/admin/(purchases|payments|imports/ecarstrade)"
```

## 4) Vérification rapide attendue

- `app/Http/Controllers/AdminSalesController.php` existe.
- Routes admin `purchases/payments` présentes.
- `php artisan migrate:status` affiche les migrations `purchases/payments` en `Ran`.

