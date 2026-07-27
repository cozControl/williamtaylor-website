$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$baselinePath = Join-Path $PSScriptRoot 'protected-php-baseline.json'
$baseline = Get-Content -LiteralPath $baselinePath -Raw | ConvertFrom-Json
$protectedRoot = Join-Path $projectRoot $baseline.root
$expected = @($baseline.files.PSObject.Properties.Name | Sort-Object)
$actual = @(
    Get-ChildItem -LiteralPath $protectedRoot -Recurse -Filter '*.php' -File |
        ForEach-Object {
            $_.FullName.Substring($protectedRoot.Length).TrimStart('\').Replace('\', '/')
        } |
        Sort-Object
)

if (Compare-Object -ReferenceObject $expected -DifferenceObject $actual) {
    throw 'Protected Factory PHP file set differs from the approved baseline.'
}

foreach ($relativePath in $expected) {
    $definition = $baseline.files.$relativePath
    $path = Join-Path $protectedRoot $relativePath
    $file = Get-Item -LiteralPath $path
    $hash = (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.ToLowerInvariant()

    if ($file.Length -ne $definition.bytes -or $hash -ne $definition.sha256) {
        throw "Protected Factory PHP file differs from baseline: $relativePath"
    }
}

Write-Output "PROTECTED_FACTORY_PHP_PRESERVED=$($expected.Count)/$($expected.Count)"
