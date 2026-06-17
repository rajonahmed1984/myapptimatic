{{--
  Toast container — injected once per layout.
  The toast.js script reads:
    1. Blade-rendered data-flash-message elements (traditional page loads / redirects)
    2. Inertia flash props via the 'inertia:finish' event (SPA navigation)
    3. A global window.showToast(message, type) API for manual triggers
--}}
<div id="toast-stack" aria-live="polite" aria-atomic="false"
     style="position:fixed;top:1.25rem;right:1.25rem;z-index:9999;display:flex;flex-direction:column;gap:.625rem;width:22rem;max-width:calc(100vw - 2.5rem);pointer-events:none;">
</div>
