# Optional ONE-TIME migration, never run while either Certify desktop or web is open.
# Requires the actual NativePHP appdata directory resolved on the target machine.
[CmdletBinding(SupportsShouldProcess=$true, ConfirmImpact='High')]
param(
    [Parameter(Mandatory=$true)][string]$LegacyProject,
    [Parameter(Mandatory=$true)][string]$DesktopDataRoot
)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$legacy = (Resolve-Path $LegacyProject).Path
$target = (Resolve-Path $DesktopDataRoot).Path
$sourceDb = Join-Path $legacy 'database/database.sqlite'
$sourceFiles = Join-Path $legacy 'storage/app/private'
$destDb = Join-Path $target 'database/database.sqlite'
$destFiles = Join-Path $target 'storage/app/private'
if ($legacy -eq $target) { throw 'Source and destination cannot be the same.' }
if (-not (Test-Path $sourceDb)) { throw "SQLite database not found: $sourceDb. MySQL data must be exported separately." }
if (-not (Test-Path $sourceFiles)) { throw "Original private storage missing: $sourceFiles" }
if (-not (Test-Path (Join-Path $target 'database'))) { throw 'Desktop appdata/database directory not found. Launch NativePHP desktop once, close it, then provide its exact appdata root.' }
if (Test-Path $destDb) {
    if (-not (Get-Command php -ErrorAction SilentlyContinue)) { throw 'PHP CLI is needed to safely check the target database before import.' }
    & php (Join-Path $PSScriptRoot 'check-empty-sqlite.php') $destDb
    if ($LASTEXITCODE -ne 0) { throw 'Destination contains records or cannot be inspected. Import blocked.' }
}
if (Test-Path $destFiles) {
    $existing = @(Get-ChildItem -Path $destFiles -Force -ErrorAction SilentlyContinue | Where-Object { $_.Name -ne '.gitkeep' })
    if ($existing.Count -gt 0) { throw 'Destination already has private files; merge not supported. Import into a fresh profile only.' }
}
if ($PSCmdlet.ShouldProcess($target, 'Copy legacy database and private files to NEW/EMPTY NativePHP appdata')) {
    if (Test-Path $destDb) {
        $backup = $destDb + '.before-legacy-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.bak'
        Copy-Item $destDb $backup -ErrorAction Stop
        Write-Host "Backed up empty desktop database to $backup"
    }
    Copy-Item $sourceDb $destDb -Force -ErrorAction Stop
    New-Item -Path $destFiles -ItemType Directory -Force | Out-Null
    Copy-Item -Path (Join-Path $sourceFiles '*') -Destination $destFiles -Recurse -Force -ErrorAction Stop
    Write-Host 'Legacy files copied; do not delete the source or backup.' -ForegroundColor Green
    Write-Warning 'You must still confirm migrated schema, certificate history and installed font references in the running desktop app. Do not run migrate:fresh.'
}
