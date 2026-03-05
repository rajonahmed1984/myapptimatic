$ErrorActionPreference = "Stop"

composer test
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

npm run check:react-standard
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

npm run build
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}
