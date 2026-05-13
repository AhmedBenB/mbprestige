param(
    [Parameter(Mandatory = $true)]
    [string]$ServerHost,

    [string]$ServerProjectPath = "/var/www/mbprestige",
    [string]$LocalProjectPath = "C:\Users\AHMED\Desktop\mbprestige",

    [switch]$IncludeEnv,
    [switch]$IncludeStorage,
    [switch]$IncludeVendor
)

$ErrorActionPreference = "Stop"

function Write-Step {
    param([string]$Message)
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

if (-not (Get-Command scp -ErrorAction SilentlyContinue)) {
    throw "scp n'est pas disponible sur cette machine."
}

if (-not (Test-Path -LiteralPath $LocalProjectPath)) {
    throw "Le dossier local n'existe pas: $LocalProjectPath"
}

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$desktopDir = Split-Path -Parent $LocalProjectPath
$backupPath = Join-Path $desktopDir ("mbprestige_backup_before_sync_" + $timestamp)
$tmpPath = Join-Path $env:TEMP ("mbprestige_sync_" + $timestamp)

$items = @(
    "app",
    "bootstrap",
    "config",
    "database",
    "deploy",
    "docs",
    "public",
    "resources",
    "routes",
    "scripts",
    "tests",
    ".env.example",
    ".gitattributes",
    ".gitignore",
    "artisan",
    "composer.json",
    "composer.lock",
    "package.json",
    "package-lock.json",
    "phpunit.xml",
    "README.md",
    "vite.config.js"
)

if ($IncludeEnv) { $items += ".env" }
if ($IncludeStorage) { $items += "storage" }
if ($IncludeVendor) { $items += "vendor" }

Write-Step "Backup local => $backupPath"
Copy-Item -LiteralPath $LocalProjectPath -Destination $backupPath -Recurse -Force

Write-Step "Préparation dossier temporaire => $tmpPath"
New-Item -ItemType Directory -Path $tmpPath -Force | Out-Null

foreach ($item in $items) {
    $remote = "$ServerHost`:$ServerProjectPath/$item"
    Write-Step "Téléchargement $item"
    & scp -r $remote $tmpPath
}

Write-Step "Application des fichiers synchronisés"
foreach ($item in $items) {
    $localTarget = Join-Path $LocalProjectPath $item
    $tmpSource = Join-Path $tmpPath $item

    if (-not (Test-Path -LiteralPath $tmpSource)) {
        Write-Warning "Absent côté serveur: $item (skip)"
        continue
    }

    if (Test-Path -LiteralPath $localTarget) {
        Remove-Item -LiteralPath $localTarget -Recurse -Force
    }

    Move-Item -LiteralPath $tmpSource -Destination $localTarget -Force
}

Write-Step "Nettoyage temporaire"
Remove-Item -LiteralPath $tmpPath -Recurse -Force

Write-Step "Terminé"
Write-Host "Backup local: $backupPath" -ForegroundColor Green
Write-Host "Projet local synchronisé avec: $ServerHost`:$ServerProjectPath" -ForegroundColor Green
Write-Host ""
Write-Host "Commande suivante recommandée:" -ForegroundColor Yellow
Write-Host "Set-Location -LiteralPath `"$LocalProjectPath`"; php artisan optimize:clear; php artisan migrate --force; php artisan route:list"
