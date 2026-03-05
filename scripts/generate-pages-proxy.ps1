$ErrorActionPreference = 'Stop'

Write-Warning 'Deprecated script: generate-pages-proxy.ps1'
Write-Output 'The project now uses standard paths under resources/js/Pages.'
Write-Output 'Legacy proxy generation from resources/js/react/Pages is no longer supported.'
Write-Output 'Use "npm run check:react-standard" to validate structure.'
