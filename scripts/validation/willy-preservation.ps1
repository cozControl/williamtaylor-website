param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('verify', 'begin', 'end')]
    [string] $Mode
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$trackedPath = Join-Path $projectRoot 'willy'
$baselinePath = Join-Path $PSScriptRoot 'willy-baseline.json'
$sessionPath = Join-Path $projectRoot 'storage/app/evidence/willy-validation-baseline.json'
$baseline = Get-Content -LiteralPath $baselinePath -Raw | ConvertFrom-Json
$file = Get-Item -LiteralPath $trackedPath
$current = [ordered]@{
    path = 'willy'
    sha256 = (Get-FileHash -LiteralPath $trackedPath -Algorithm SHA256).Hash.ToLowerInvariant()
    bytes = $file.Length
}

if ($current.sha256 -ne $baseline.sha256 -or $current.bytes -ne $baseline.bytes) {
    throw "Tracked willy database differs from the accepted BE-4G.1 baseline."
}

if ($Mode -eq 'begin') {
    $directory = Split-Path $sessionPath
    New-Item -ItemType Directory -Force -Path $directory | Out-Null
    $current | ConvertTo-Json | Set-Content -LiteralPath $sessionPath -Encoding utf8
}

if ($Mode -eq 'end') {
    if (-not (Test-Path -LiteralPath $sessionPath)) {
        throw 'No preservation session baseline exists. Run begin first.'
    }
    $session = Get-Content -LiteralPath $sessionPath -Raw | ConvertFrom-Json
    if ($current.sha256 -ne $session.sha256 -or $current.bytes -ne $session.bytes) {
        throw 'Tracked willy database changed during validation.'
    }
}

Write-Output "WILLY_PRESERVED=$($current.sha256):$($current.bytes)"
