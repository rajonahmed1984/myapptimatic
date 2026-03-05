#!/usr/bin/env bash
set -euo pipefail

composer test
npm run check:react-standard
npm run build
