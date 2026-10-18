# Opt-in Windows scheduled background processor; run in PowerShell as the current user.
[CmdletBinding()]
param([switch]$Remove)
$ErrorActionPreference='Stop'
$root=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$php=Join-Path (Split-Path $root -Parent) 'php\php.exe'
if (-not (Test-Path $php)) { $php='C:\xampp\php\php.exe' }
if (-not (Test-Path $php)) { throw 'Specify/install PHP at C:\xampp\php\php.exe before scheduling.' }
$name='Certify Background Certificates'
if ($Remove) { Unregister-ScheduledTask -TaskName $name -Confirm:$false -ErrorAction SilentlyContinue; Write-Host 'Task removed'; return }
$action=New-ScheduledTaskAction -Execute $php -Argument 'artisan certify:process-pending --limit=10' -WorkingDirectory $root
$trigger=New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(2) -RepetitionInterval (New-TimeSpan -Minutes 1)
$settings=New-ScheduledTaskSettingsSet -ExecutionTimeLimit (New-TimeSpan -Minutes 3) -MultipleInstances IgnoreNew
Register-ScheduledTask -TaskName $name -Action $action -Trigger $trigger -Settings $settings -Description 'Process Certify certificate PDFs even if the browser is closed.' -Force | Out-Null
Write-Host 'Installed scheduled task:' $name
Write-Host 'Use -Remove to uninstall.'
