$ErrorActionPreference = 'Stop'
$dataDirectory = Join-Path $env:LOCALAPPDATA 'MiniNetworkMonitor'
$tokenPath = Join-Path $dataDirectory 'worker-token.dpapi'
$configPath = Join-Path $dataDirectory 'worker-config.json'

if (-not (Test-Path -LiteralPath $tokenPath) -or -not (Test-Path -LiteralPath $configPath)) {
    throw 'Worker credentials or configuration are missing. Run scripts\install_worker_task.ps1 again.'
}

$config = Get-Content -LiteralPath $configPath -Raw | ConvertFrom-Json
$secureToken = Get-Content -LiteralPath $tokenPath -Raw | ConvertTo-SecureString
$bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureToken)
try {
    $env:MNM_WORKER_TOKEN = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
    $secureToken.Dispose()
}

$env:MNM_API_URL = [string] $config.apiUrl
try {
    & ([string] $config.pythonPath) ([string] $config.workerPath)
    exit $LASTEXITCODE
} finally {
    Remove-Item Env:\MNM_WORKER_TOKEN -ErrorAction SilentlyContinue
    Remove-Item Env:\MNM_API_URL -ErrorAction SilentlyContinue
}
