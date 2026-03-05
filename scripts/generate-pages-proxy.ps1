$ErrorActionPreference = 'Stop'
$sourceRoot = Join-Path (Get-Location) 'resources/js/react/Pages'
$targetRoot = Join-Path (Get-Location) 'resources/js/Pages'
if (!(Test-Path $sourceRoot)) { throw 'Source pages directory not found.' }
if (Test-Path $targetRoot) { Remove-Item -Path $targetRoot -Recurse -Force }
New-Item -ItemType Directory -Path $targetRoot -Force | Out-Null
$extensions = @('*.jsx','*.js','*.tsx','*.ts')
$files = Get-ChildItem -Path $sourceRoot -Recurse -File -Include $extensions
foreach ($source in $files) {
    $relative = $source.FullName.Substring($sourceRoot.Length).TrimStart([char[]]'\\/')
    $target = Join-Path $targetRoot $relative
    $targetDir = Split-Path -Path $target -Parent
    New-Item -ItemType Directory -Path $targetDir -Force | Out-Null

    Push-Location $targetDir
    $importPath = [string](Resolve-Path -LiteralPath $source.FullName -Relative | Select-Object -First 1)
    Pop-Location

    if ([string]::IsNullOrWhiteSpace($importPath)) {
        throw "Could not resolve relative import path for $($source.FullName)"
    }

    $importPath = $importPath.Replace('\','/')
    if ($importPath.StartsWith('./')) {
        $importPath = $importPath
    } elseif ($importPath.StartsWith('../')) {
        $importPath = $importPath
    } elseif ($importPath.StartsWith('.')) {
        $importPath = './' + $importPath.Substring(1)
    } else {
        $importPath = './' + $importPath
    }

    $content = @(
        "export * from '$importPath';",
        "export { default } from '$importPath';",
        ''
    ) -join "`n"

    Set-Content -Path $target -Value $content -Encoding UTF8
}
Write-Output "Generated $($files.Count) page proxy files under resources/js/Pages."
