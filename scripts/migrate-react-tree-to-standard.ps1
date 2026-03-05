$ErrorActionPreference = 'Stop'

Write-Warning 'Deprecated script: migrate-react-tree-to-standard.ps1'
Write-Output 'The React tree migration has already been completed.'
Write-Output 'Standard structure is now: resources/js/{Pages,Components,Layouts,utils}.'
Write-Output 'Use "npm run check:react-standard" to enforce this structure.'
