$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$workerPath = Join-Path $projectRoot 'worker\network_monitor.py'
$pythonCommand = Get-Command python -ErrorAction Stop
$apiUrl = Read-Host 'Worker API URL [http://127.0.0.1/mini-network-monitor/api/worker.php]'
if ([string]::IsNullOrWhiteSpace($apiUrl)) {
    $apiUrl = 'http://127.0.0.1/mini-network-monitor/api/worker.php'
}

$parsedUrl = $null
if (-not [Uri]::TryCreate($apiUrl, [UriKind]::Absolute, [ref]$parsedUrl) -or $parsedUrl.Scheme -notin @('http', 'https')) {
    throw 'API URL must be an absolute HTTP or HTTPS URL.'
}
$isLoopback = $parsedUrl.Host -eq 'localhost'
$loopbackAddress = $null
if (-not $isLoopback -and [Net.IPAddress]::TryParse($parsedUrl.Host.Trim('[', ']'), [ref]$loopbackAddress)) {
    $isLoopback = $loopbackAddress.Equals([Net.IPAddress]::Loopback) -or $loopbackAddress.Equals([Net.IPAddress]::IPv6Loopback)
}
if ($parsedUrl.Scheme -ne 'https' -and -not $isLoopback) {
    throw 'Use HTTPS when the Worker API is on another computer.'
}

$secureToken = Read-Host 'Worker token (input is hidden)' -AsSecureString
$bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureToken)
try {
    $plainToken = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
}
if ($plainToken.Length -lt 32) {
    throw 'Worker token must contain at least 32 characters.'
}

$dataDirectory = Join-Path $env:LOCALAPPDATA 'MiniNetworkMonitor'
New-Item -ItemType Directory -Path $dataDirectory -Force | Out-Null
$userSid = [Security.Principal.WindowsIdentity]::GetCurrent().User
$directoryAcl = New-Object Security.AccessControl.DirectorySecurity
$directoryAcl.SetAccessRuleProtection($true, $false)
$directoryAcl.AddAccessRule((New-Object Security.AccessControl.FileSystemAccessRule(
    $userSid,
    [Security.AccessControl.FileSystemRights]::FullControl,
    [Security.AccessControl.InheritanceFlags]'ContainerInherit, ObjectInherit',
    [Security.AccessControl.PropagationFlags]::None,
    [Security.AccessControl.AccessControlType]::Allow
)))
Set-Acl -LiteralPath $dataDirectory -AclObject $directoryAcl

$tokenPath = Join-Path $dataDirectory 'worker-token.dpapi'
$configPath = Join-Path $dataDirectory 'worker-config.json'
$secureToken | ConvertFrom-SecureString | Set-Content -LiteralPath $tokenPath -Encoding ASCII
@{
    apiUrl = $apiUrl
    pythonPath = $pythonCommand.Source
    workerPath = $workerPath
} | ConvertTo-Json | Set-Content -LiteralPath $configPath -Encoding UTF8

$runnerPath = Join-Path $PSScriptRoot 'run_worker_task.ps1'
$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$runnerPath`""
$trigger = New-ScheduledTaskTrigger -AtLogOn -User "$env:USERDOMAIN\$env:USERNAME"
$principal = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" -LogonType Interactive -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit ([TimeSpan]::Zero)
Register-ScheduledTask -TaskName 'MiniNetworkMonitorWorker' -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Description 'ICMP monitor for registered Mini Network Monitor devices' -Force | Out-Null

$plainToken = $null
$secureToken.Dispose()
Write-Output 'Scheduled task created. It starts when this Windows user signs in.'
Write-Output "Protected token and configuration are stored under $dataDirectory."
