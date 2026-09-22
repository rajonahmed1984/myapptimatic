import React, { useEffect } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';

export default function IncomeCreateModal({
    open = false,
    onClose,
    categories = [],
    storeUrl = '/admin/income',
    defaultDate,
}) {
    const today = defaultDate || new Date().toISOString().split('T')[0];

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        income_category_id: '',
        title: '',
        amount: '',
        income_date: today,
        notes: '',
        attachment: null,
    });

    useEffect(() => {
        if (!open) {
            clearErrors();
        }
    }, [open]);

    useEffect(() => {
        const handleKeyDown = (event) => {
            if (event.key === 'Escape' && open) {
                handleClose();
            }
        };

        if (open) {
            window.addEventListener('keydown', handleKeyDown);
        }

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [open]);

    const handleClose = () => {
        if (typeof onClose === 'function') {
            onClose();
        }
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        post(storeUrl, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                handleClose();
            },
        });
    };

    if (!open) {
        return null;
    }

    const categoryOptions = [
        { value: '', label: 'Select category' },
        ...categories.map((c) => ({ value: String(c.id), label: c.name })),
    ];

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"
                onClick={handleClose}
            />

            {/* Modal Dialog */}
            <div className="relative w-full max-w-xl rounded-2xl border border-slate-200 bg-white shadow-2xl overflow-hidden my-6 z-10 animate-in fade-in zoom-in-95 duration-150">
                <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <div className="text-base font-semibold text-slate-900">New Income</div>
                        <div className="text-xs text-slate-500">Record a new income entry</div>
                    </div>
                    <button
                        type="button"
                        onClick={handleClose}
                        className="rounded-full p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition"
                        aria-label="Close"
                    >
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="p-6 grid gap-4 text-sm">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label className="text-xs font-medium text-slate-700">Category *</label>
                            <SearchableSelect
                                name="income_category_id"
                                required
                                value={data.income_category_id}
                                onChange={(value) => setData('income_category_id', value)}
                                options={categoryOptions}
                                className="mt-1"
                                placeholder="Select category"
                                error={errors?.income_category_id}
                            />
                            {errors?.income_category_id && (
                                <div className="mt-1 text-xs text-rose-600">{errors.income_category_id}</div>
                            )}
                        </div>
                        <div>
                            <label className="text-xs font-medium text-slate-700">Title *</label>
                            <input
                                type="text"
                                name="title"
                                required
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                placeholder="e.g. Website development payment"
                                className="ui-input mt-1 w-full"
                            />
                            {errors?.title && (
                                <div className="mt-1 text-xs text-rose-600">{errors.title}</div>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label className="text-xs font-medium text-slate-700">Amount *</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="amount"
                                required
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value)}
                                placeholder="0.00"
                                className="ui-input mt-1 w-full"
                            />
                            {errors?.amount && (
                                <div className="mt-1 text-xs text-rose-600">{errors.amount}</div>
                            )}
                        </div>
                        <div>
                            <label className="text-xs font-medium text-slate-700">Income Date *</label>
                            <input
                                type="date"
                                name="income_date"
                                required
                                value={data.income_date}
                                onChange={(e) => setData('income_date', e.target.value)}
                                className="ui-input mt-1 w-full"
                            />
                            {errors?.income_date && (
                                <div className="mt-1 text-xs text-rose-600">{errors.income_date}</div>
                            )}
                        </div>
                    </div>

                    <div>
                        <label className="text-xs font-medium text-slate-700">Notes (optional)</label>
                        <textarea
                            name="notes"
                            rows={2}
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            placeholder="Additional details or reference notes..."
                            className="ui-input mt-1 w-full resize-none"
                        />
                        {errors?.notes && (
                            <div className="mt-1 text-xs text-rose-600">{errors.notes}</div>
                        )}
                    </div>

                    <div>
                        <label className="text-xs font-medium text-slate-700">Attachment (jpg, jpeg, png, pdf)</label>
                        <input
                            type="file"
                            name="attachment"
                            accept=".jpg,.jpeg,.png,.pdf"
                            onChange={(e) => setData('attachment', e.target.files[0] || null)}
                            className="mt-1.5 block w-full text-xs text-slate-600 file:mr-3 file:rounded-full file:border-0 file:bg-slate-100 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-slate-700 hover:file:bg-slate-200"
                        />
                        {errors?.attachment && (
                            <div className="mt-1 text-xs text-rose-600">{errors.attachment}</div>
                        )}
                    </div>

                    <div className="mt-3 flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                        <button
                            type="button"
                            onClick={handleClose}
                            className="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-slate-400 hover:text-slate-800 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-full bg-slate-900 px-5 py-2 text-xs font-semibold text-white hover:bg-slate-800 transition disabled:opacity-50 flex items-center gap-1.5"
                        >
                            {processing ? (
                                <>
                                    <svg className="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    Saving...
                                </>
                            ) : (
                                'Save Income'
                            )}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
