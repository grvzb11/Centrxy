# Safely backs up a Laravel/SQLite Certify project. Does not delete or change the source.
[CmdletBinding()]
param([string]$LegacyProject = (Join-Path $PSScriptRoot '..'), [string]$BackupFolder = '')
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$legacy = (Resolve-Path $LegacyProject).Path
if (-not $BackupFolder) {
    $BackupFolder = Join-Path (Join-Path $env:LOCALAPPDATA 'CertifyBuildBackups') ('certify-backup-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
}
New-Item -Path $BackupFolder -ItemType Directory -Force | Out-Null
foreach ($file in @('.env', 'database/database.sqlite')) {
    $src = Join-Path $legacy $file
    if (Test-Path $src) {
        $dest = Join-Path $BackupFolder $file
        New-Item -Path (Split-Path $dest -Parent) -ItemType Directory -Force | Out-Null
        Copy-Item $src $dest -Force
    } else { Write-Warning "Not found: $src" }
}
$storage = Join-Path $legacy 'storage/app/private'
if (Test-Path $storage) {
    $dest = Join-Path $BackupFolder 'storage/app/private'
    New-Item -Path $dest -ItemType Directory -Force | Out-Null
    Copy-Item -Path (Join-Path $storage '*') -Destination $dest -Recurse -Force -ErrorAction SilentlyContinue
}
Write-Host "Backup written to $BackupFolder" -ForegroundColor Green
Write-Warning 'Keep .env private: it contains the application encryption key. This only backs up SQLite; MySQL requires an additional database export.'
