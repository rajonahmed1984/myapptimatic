<style>
    #ajaxModal.hidden {
        display: none;
    }

    .is-invalid {
        border-color: rgb(248 113 113) !important;
    }

    .invalid-feedback {
        margin-top: 0.25rem;
        font-size: 0.75rem;
        color: rgb(185 28 28);
    }
</style>

<div id="ajaxModal" class="fixed inset-0 z-[70] hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-slate-900/60" data-ajax-modal-backdrop></div>
    <div class="relative mx-auto flex min-h-full max-w-3xl items-center justify-center px-4 py-8">
        <div class="w-full rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div id="ajaxModalTitle" class="text-sm font-semibold text-slate-900">Form</div>
                <button type="button" id="ajaxModalClose" class="rounded-full border border-slate-200 p-2 text-slate-500 hover:border-slate-300 hover:text-slate-700" aria-label="Close">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div id="ajaxModalBody" class="max-h-[75vh] overflow-y-auto p-5"></div>
        </div>
    </div>
</div>
