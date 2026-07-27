param(
    [string] $Path = 'storage/app/evidence/be-4g-1/validation.sqlite'
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$database = Join-Path $projectRoot $Path
$tracked = (Resolve-Path (Join-Path $projectRoot 'willy')).Path

if ([System.IO.Path]::GetFullPath($database) -eq $tracked) {
    throw 'Disposable validation database resolves to tracked willy.'
}

$directory = Split-Path $database
New-Item -ItemType Directory -Force -Path $directory | Out-Null
if (Test-Path -LiteralPath $database) {
    Remove-Item -LiteralPath $database -Force
}
New-Item -ItemType File -Path $database | Out-Null

Write-Output ([System.IO.Path]::GetFullPath($database))
