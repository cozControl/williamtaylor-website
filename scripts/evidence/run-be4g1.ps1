param(
    [int] $Port = 8131
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
Set-Location $projectRoot

if (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue) {
    throw "Dedicated BE-4G.1 evidence port $Port is already occupied."
}

& powershell -ExecutionPolicy Bypass -File scripts/validation/willy-preservation.ps1 -Mode begin
$before = (Get-FileHash -LiteralPath willy -Algorithm SHA256).Hash.ToLowerInvariant()
$database = & powershell -ExecutionPolicy Bypass -File scripts/validation/prepare-isolated-database.ps1 -Path 'storage/app/evidence/be-4g-1/evidence.sqlite'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $database
$env:DB_URL = ''
$env:CACHE_STORE = 'array'
$env:SESSION_DRIVER = 'file'
$env:QUEUE_CONNECTION = 'sync'
$env:BE4G_EVIDENCE_PASSWORD = ([Guid]::NewGuid().ToString('N') + 'Aa1!')
$env:BE4G_BASE_URL = "http://127.0.0.1:$Port"
$env:WILLY_HASH_BEFORE = $before
$router = Join-Path $projectRoot 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
$publicDirectory = Join-Path $projectRoot 'public'

try {
    php artisan view:clear
    if ($LASTEXITCODE -ne 0) { throw 'BE-4G.1 isolated view cache clearing failed.' }
    php artisan migrate:fresh --seed --force
    $env:BE4G_FIXTURE_JSON = php scripts/evidence/be4g-fixture.php
    if ($LASTEXITCODE -ne 0) { throw 'BE-4G.1 fixture failed.' }
    $server = Start-Process php -ArgumentList @('artisan', 'serve', '--host=127.0.0.1', "--port=$Port", '--no-reload') -PassThru -WindowStyle Hidden
    try {
        $ready = $false
        for ($attempt = 0; $attempt -lt 40; $attempt++) {
            try {
                $client = New-Object System.Net.Sockets.TcpClient
                $client.Connect('127.0.0.1', $Port)
                $client.Dispose()
                $ready = $true
                break
            } catch {
                Start-Sleep -Milliseconds 250
            }
        }
        if (-not $ready) {
            throw 'Isolated BE-4G.1 server did not become ready.'
        }
        node scripts/evidence/be4g-site-content.mjs
        if ($LASTEXITCODE -ne 0) { throw 'BE-4G.1 browser evidence failed.' }
    } finally {
        if ($server -and -not $server.HasExited) {
            Stop-Process -Id $server.Id -Force
            Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'php.exe' -and $_.CommandLine -like "*127.0.0.1:$Port*" } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
        }
    }
} finally {
    Remove-Item Env:DB_CONNECTION -ErrorAction SilentlyContinue
    Remove-Item Env:DB_DATABASE -ErrorAction SilentlyContinue
    Remove-Item Env:DB_URL -ErrorAction SilentlyContinue
    Remove-Item Env:CACHE_STORE -ErrorAction SilentlyContinue
    Remove-Item Env:SESSION_DRIVER -ErrorAction SilentlyContinue
    Remove-Item Env:QUEUE_CONNECTION -ErrorAction SilentlyContinue
    Remove-Item Env:BE4G_EVIDENCE_PASSWORD -ErrorAction SilentlyContinue
    Remove-Item Env:BE4G_BASE_URL -ErrorAction SilentlyContinue
    Remove-Item Env:BE4G_FIXTURE_JSON -ErrorAction SilentlyContinue
    Remove-Item Env:WILLY_HASH_BEFORE -ErrorAction SilentlyContinue
    & powershell -ExecutionPolicy Bypass -File scripts/validation/willy-preservation.ps1 -Mode end
}
