# Installs NativePHP v2 dependencies into this Laravel project on Windows.
# Requires internet on the developer's PC for first dependency download; end-user installer works offline.
[CmdletBinding()]
param([switch]$UseDesktopEnvironment)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$project = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Set-Location $project
function Check-Command([string]$Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) { throw "Missing $Name. Install it on the developer's PC first." }
}
Check-Command php
Check-Command composer
Check-Command node
Check-Command npm
$phpVersion = (& php -r 'echo PHP_VERSION_ID;') -as [int]
if ($phpVersion -lt 80300) { throw 'NativePHP Desktop v2 requires PHP 8.3+ (use PHP 8.3/8.4 on the build PC).' }
$nodeMajor = [int]((& node --version) -replace '^v','' -replace '\..*$','')
if ($nodeMajor -lt 22) { throw 'NativePHP Desktop v2 requires Node.js 22+.' }
$ext = (& php -m) | ForEach-Object { $_.Trim().ToLowerInvariant() }
foreach ($name in @('pdo_sqlite','sqlite3','mbstring','zip','fileinfo')) {
    if ($ext -notcontains $name) { throw "PHP extension $name is not enabled in CLI php.ini." }
}
if ($UseDesktopEnvironment -and (Test-Path '.env')) {
    $timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
    $backupDir = Join-Path $env:LOCALAPPDATA "CertifyBuildBackups"
    New-Item -Path $backupDir -ItemType Directory -Force | Out-Null
    $backupPath = Join-Path $backupDir ("Certify-env-backup-$timestamp.txt")
    Copy-Item '.env' $backupPath -ErrorAction Stop
    Copy-Item '.env.desktop.example' '.env' -Force
    Write-Host "Backed up existing .env outside the package: $backupPath" -ForegroundColor Yellow
} elseif (-not (Test-Path '.env')) {
    Copy-Item '.env.desktop.example' '.env'
    Write-Host 'Created .env from .env.desktop.example.'
} else {
    $connection = Select-String -Path '.env' -Pattern '^DB_CONNECTION=sqlite$' -Quiet
    if (-not $connection) { throw 'Existing .env is not set to SQLite. Re-run with -UseDesktopEnvironment to back it up and switch, or use a separate project directory.' }
}
# Dependencies are intentionally installed from Composer repositories rather than supplied as stale vendor binaries.
& composer install --no-interaction
if ($LASTEXITCODE -ne 0) { throw 'composer install failed. Check the output.' }
$hasKey = Select-String -Path '.env' -Pattern '^APP_KEY=base64:.+' -Quiet
if (-not $hasKey) {
    & php artisan key:generate --force
    if ($LASTEXITCODE -ne 0) { throw 'APP_KEY generation failed.' }
}
& php artisan native:install
if ($LASTEXITCODE -ne 0) { throw 'native:install failed.' }
if (-not (Test-Path 'config/nativephp.php')) { throw 'NativePHP configuration was not published.' }
& php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { throw 'optimize:clear failed.' }
Write-Host 'PDF engine preparation (required before sharing a Windows installer): .\desktop\Prepare-PdfEngine.ps1'
Write-Host 'NativePHP integration installed. Run php artisan native:run to test the desktop window.' -ForegroundColor Green
Write-Host 'Run php artisan native:migrate to initialize the NativePHP development SQLite database.'
