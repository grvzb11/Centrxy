# Build an offline Windows desktop installer after Setup-Desktop.ps1 succeeds.
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$project = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Set-Location $project
if (-not (Test-Path 'vendor/autoload.php')) { throw 'Composer dependencies are missing. Run .\desktop\Setup-Desktop.ps1 first.' }
if (-not (Test-Path 'config/nativephp.php')) { throw 'NativePHP has not been initialized. Run php artisan native:install first.' }
$connection = Select-String -Path '.env' -Pattern '^DB_CONNECTION=sqlite$' -Quiet
if (-not $connection) { throw 'For desktop packaging, set DB_CONNECTION=sqlite in .env (back up the original first).' }
$version = Select-String -Path '.env' -Pattern '^NATIVEPHP_APP_VERSION=' -Quiet
if (-not $version) { throw 'Set NATIVEPHP_APP_VERSION in .env and increment it for every released installer.' }
# Include the pinned, Apache-2.0 licensed qpdf distribution in the Windows installer.
# Build must fail if this dependency cannot be prepared; recipients must not
# need a separate converter installation.
& (Join-Path $PSScriptRoot 'Prepare-PdfEngine.ps1')
if ($LASTEXITCODE -ne 0) { throw 'Bundled qpdf preparation failed.' }
& php artisan certify:desktop:doctor
if ($LASTEXITCODE -ne 0) { throw 'Desktop doctor found unmet requirements; fix before building.' }
& composer check-platform-reqs --no-interaction
if ($LASTEXITCODE -ne 0) { throw 'Composer platform checks failed.' }
& php artisan native:build win x64
if ($LASTEXITCODE -ne 0) { throw 'NativePHP Windows build failed. See build logs.' }
$dist = Join-Path $project 'nativephp/electron/dist'
if (-not (Test-Path $dist)) { throw "Build completed but '$dist' is missing. Check NativePHP output and package settings." }
$installers = @(Get-ChildItem -Path $dist -Recurse -File | Where-Object { $_.Extension -in @('.exe', '.msi') })
if ($installers.Count -eq 0) { throw 'No Windows installer artifact found under nativephp/electron/dist.' }
Write-Host "Installer artifacts in: $dist" -ForegroundColor Green
$installers | ForEach-Object { Write-Host $_.FullName }
Write-Warning 'Before distributing: test one install on a clean/offline Windows VM, check PDF output and verify data survives upgrades.'
