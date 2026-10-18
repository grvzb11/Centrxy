# Certify Windows PDF engine installer preparation.
# Developer/build PC only: end users should receive qpdf inside the installer.
# Downloads qpdf 12.4.2 from the OFFICIAL v12.4.2 release and checks its
# published SHA-256 before extracting or running any program.
[CmdletBinding()]
param(
    # Optional: use a qpdf ZIP already downloaded from the official release.
    # Useful when GitHub download traffic is blocked on the build PC.
    [string]$ArchivePath = ''
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$project = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$destination = Join-Path $project 'resources\bin\win-x64\qpdf'
$version = '12.4.2'
$asset = "qpdf-$version-mingw64.zip"
$expectedSha256 = '773d2fa0c7d161e2271430734338e560a07b25d0edf11ceee8e53de435012766'
$program = Join-Path $destination 'bin\qpdf.exe'

function Test-Qpdf([string]$Executable) {
    if (-not (Test-Path -LiteralPath $Executable -PathType Leaf)) { return $false }
    try {
        $output = (& $Executable --version | Out-String)
        return ($LASTEXITCODE -eq 0 -and $output -match [regex]::Escape($version))
    } catch {
        return $false
    }
}

function Assert-VerifiedArchive([string]$PathToZip) {
    if (-not (Test-Path -LiteralPath $PathToZip -PathType Leaf)) {
        throw "qpdf ZIP not found: $PathToZip"
    }
    $actualSha256 = (Get-FileHash -LiteralPath $PathToZip -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($actualSha256 -ne $expectedSha256) {
        throw "qpdf ZIP failed SHA-256 verification. Expected $expectedSha256 but got $actualSha256. Do not execute or extract this file. A download mirror may have returned an HTML page instead of the ZIP."
    }
    $stream = [System.IO.File]::OpenRead($PathToZip)
    try {
        $signature = New-Object byte[] 4
        if ($stream.Read($signature, 0, 4) -ne 4 -or
            $signature[0] -ne 0x50 -or $signature[1] -ne 0x4b) {
            throw 'The checksum-matched download does not have a ZIP header.'
        }
    } finally {
        $stream.Dispose()
    }
}

if (Test-Qpdf $program) {
    Write-Host "Ready: qpdf $version already staged at $program" -ForegroundColor Green
    exit 0
}

$urls = @(
    "https://github.com/qpdf/qpdf/releases/download/v$version/$asset",
    "https://downloads.sourceforge.net/project/qpdf/qpdf/$version/$asset"
)
$tempRoot = Join-Path ([IO.Path]::GetTempPath()) ("certify-qpdf-" + [guid]::NewGuid().ToString('N'))
New-Item -Path $tempRoot -ItemType Directory -Force | Out-Null
$download = Join-Path $tempRoot $asset
$expanded = Join-Path $tempRoot 'extracted'
$staging = Join-Path $tempRoot 'staged'
try {
    if (-not [string]::IsNullOrWhiteSpace($ArchivePath)) {
        $archive = (Resolve-Path -LiteralPath $ArchivePath -ErrorAction Stop).Path
        Assert-VerifiedArchive $archive
        Copy-Item -LiteralPath $archive -Destination $download -Force
        Write-Host "Using verified local archive: $archive"
    } else {
        $downloadSucceeded = $false
        foreach ($url in $urls) {
            try {
                if (Test-Path -LiteralPath $download) {
                    Remove-Item -LiteralPath $download -Force
                }
                Write-Host "Downloading qpdf $version from $url ..."
                Invoke-WebRequest -Uri $url -OutFile $download -UseBasicParsing -TimeoutSec 180 -MaximumRedirection 10
                Assert-VerifiedArchive $download
                $downloadSucceeded = $true
                break
            } catch {
                Write-Warning $_.Exception.Message
            }
        }
        if (-not $downloadSucceeded) {
            throw "Could not retrieve a verified $asset. Download from https://github.com/qpdf/qpdf/releases/tag/v$version and retry: .\desktop\Prepare-PdfEngine.ps1 -ArchivePath 'C:\path\to\$asset'. The expected SHA-256 is $expectedSha256. Do not bypass checksum verification."
        }
    }

    Expand-Archive -LiteralPath $download -DestinationPath $expanded -Force
    $found = @(Get-ChildItem -LiteralPath $expanded -Filter qpdf.exe -Recurse -File |
        Where-Object { $_.Directory.Name -eq 'bin' })
    if ($found.Count -ne 1) {
        throw 'Cannot locate a unique bin/qpdf.exe in the verified qpdf release archive.'
    }

    # Always stage the whole distribution, including the relative lib/ DLL
    # dependencies and the original qpdf license / notices.
    $distribution = Split-Path $found[0].Directory.FullName -Parent
    New-Item -ItemType Directory -Path $staging -Force | Out-Null
    Copy-Item -Path (Join-Path $distribution '*') -Destination $staging -Recurse -Force
    $stagedProgram = Join-Path $staging 'bin\qpdf.exe'
    if (-not (Test-Qpdf $stagedProgram)) {
        throw 'The verified qpdf files were extracted but cannot run on this Windows PC. Check the qpdf DLLs and architecture; existing Certify resources were not replaced.'
    }

    # Do not touch a working destination before a verified archive was
    # extracted and its executable was successfully run.
    $destinationParent = Split-Path $destination -Parent
    New-Item -ItemType Directory -Path $destinationParent -Force | Out-Null
    if (Test-Path -LiteralPath $destination) {
        Remove-Item -LiteralPath $destination -Recurse -Force
    }
    Copy-Item -LiteralPath $staging -Destination $destination -Recurse -Force
    if (-not (Test-Qpdf $program)) {
        throw "qpdf files were copied but $program did not start. Do not build an installer."
    }
    Write-Host "Ready: qpdf $version staged successfully for Certify's PDF engine." -ForegroundColor Green
    Write-Host "qpdf executable: $program"
    Write-Host 'In a Laravel development session, restart Laravel and run php artisan optimize:clear.'
} finally {
    if (Test-Path -LiteralPath $tempRoot) {
        Remove-Item -LiteralPath $tempRoot -Recurse -Force -ErrorAction SilentlyContinue
    }
}
