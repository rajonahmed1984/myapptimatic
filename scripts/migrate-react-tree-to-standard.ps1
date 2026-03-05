$ErrorActionPreference = 'Stop'

function Normalize-RelativeImportPath([string]$path) {
    $normalized = $path.Replace('\\', '/').Replace('\', '/')
    if ($normalized.StartsWith('./') -or $normalized.StartsWith('../')) {
        return $normalized
    }
    if ($normalized.StartsWith('.')) {
        return './' + $normalized.Substring(1)
    }
    return './' + $normalized
}

function Has-DefaultExport([string]$filePath) {
    $content = Get-Content -LiteralPath $filePath -Raw
    if ($content -match '\bexport\s+default\b') { return $true }
    if ($content -match 'export\s*\{\s*default') { return $true }
    return $false
}

function Copy-And-Proxy([string]$sourceRoot, [string]$destRoot) {
    if (!(Test-Path $sourceRoot)) {
        throw "Source root not found: $sourceRoot"
    }

    New-Item -ItemType Directory -Path $destRoot -Force | Out-Null

    $files = Get-ChildItem -Path $sourceRoot -Recurse -File -Include *.js,*.jsx,*.ts,*.tsx

    foreach ($source in $files) {
        $relative = $source.FullName.Substring($sourceRoot.Length).TrimStart([char[]]'\/')
        $dest = Join-Path $destRoot $relative
        $destDir = Split-Path -Path $dest -Parent
        New-Item -ItemType Directory -Path $destDir -Force | Out-Null
        Copy-Item -LiteralPath $source.FullName -Destination $dest -Force
    }

    foreach ($source in $files) {
        $relative = $source.FullName.Substring($sourceRoot.Length).TrimStart([char[]]'\/')
        $dest = Join-Path $destRoot $relative

        Push-Location (Split-Path -Path $source.FullName -Parent)
        $importPath = [string](Resolve-Path -LiteralPath $dest -Relative | Select-Object -First 1)
        Pop-Location

        $importPath = Normalize-RelativeImportPath $importPath
        $hasDefault = Has-DefaultExport $dest

        $lines = @("export * from '$importPath';")
        if ($hasDefault) {
            $lines += "export { default } from '$importPath';"
        }

        Set-Content -LiteralPath $source.FullName -Value (($lines -join "`n") + "`n") -Encoding UTF8
    }

    Write-Output ("Processed {0}: {1} files" -f $sourceRoot, $files.Count)
}

$base = (Get-Location).Path
Copy-And-Proxy (Join-Path $base 'resources/js/react/Components') (Join-Path $base 'resources/js/Components')
Copy-And-Proxy (Join-Path $base 'resources/js/react/Layouts') (Join-Path $base 'resources/js/Layouts')
Copy-And-Proxy (Join-Path $base 'resources/js/react/utils') (Join-Path $base 'resources/js/utils')
Copy-And-Proxy (Join-Path $base 'resources/js/react/Pages') (Join-Path $base 'resources/js/Pages')

Write-Output 'Migration script completed.'
