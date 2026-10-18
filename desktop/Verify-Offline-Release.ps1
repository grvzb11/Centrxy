# Manual verification on clean target PC: machine should be disconnected before running.
[CmdletBinding()]
param([Parameter(Mandatory=$true)][string]$InstallerPath)
$ErrorActionPreference = 'Stop'
$installer=(Resolve-Path $InstallerPath).Path
if ([IO.Path]::GetExtension($installer) -notin @('.exe','.msi')) { throw 'Expected an .exe or .msi Windows installer.' }
Write-Host 'Verify Wi-Fi and Ethernet are disconnected on this test PC.'
Write-Host "Install using: $installer"
Write-Host 'Checklist:'
@(
 '1. App opens from Start menu without XAMPP, PHP, Composer or internet.',
 '2. Upload PNG, JPG, a PDF 1.4, and a PDF 1.7 with compressed object streams.',
 '3. Add fields and preview a long recipient name and paragraph.',
 '4. Install a local TTF font and save the edited layout.',
 '5. Generate 25 certificates and export a ZIP.',
 '6. Verify Unicode recipient filenames and optional certificate numbers.',
 '7. Archive/restore a template; reopen History.',
 '8. Close/reopen the app; all data and files survive.',
 '9. Install an upgraded version; data still survives.',
 '10. Confirm the app makes no external requests during normal operation.',
 '11. PDF outputs preserve page dimensions and embedded fonts.',
 '12. On the disconnected Windows PC, verify qpdf normalization of a PDF 1.7 object-stream template without Ghostscript, PATH changes, or internet.'
) | ForEach-Object { Write-Host $_ }
