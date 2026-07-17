# Phase 6: Frontend delivery

## Goal

Reduce the JavaScript and CSS downloaded on every Inertia page while keeping
navigation responsive and preserving existing page behaviour.

## Changes

- Axios is no longer attached to `window` or loaded by the global application
  entry. The Apptimatic Email management page imports the configured client
  only when that page is requested.
- Flatpickr JavaScript and CSS are loaded on demand only when the document
  contains a compatible date input.
- Eligible same-origin Inertia links are prefetched after an 80 ms hover intent
  delay or immediately on keyboard focus. Prefetched responses are cached for
  30 seconds.
- Native, download, new-tab, hash-only, and cross-origin links are excluded
  from prefetching.
- `npm run check:frontend-performance` protects the lazy-loading rules and
  enforces a 440 KiB production main-entry budget.

## Production bundle result

| Asset | Before | After | Reduction |
| --- | ---: | ---: | ---: |
| Main JS | 470.60 KB | 419.46 KB | 51.14 KB (10.9%) |
| Main JS gzip | 150.35 KB | 135.17 KB | 15.18 KB (10.1%) |

Flatpickr is now emitted as separate on-demand assets:

- JavaScript: 50.75 KB, 14.84 KB gzip
- CSS: 15.74 KB, 3.00 KB gzip

## Verification

Run:

```bash
npm run build
npm run check:frontend-performance
```

The performance guard should be run after the build so it checks the current
manifest and compiled entry size.
