import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Confirm({
    pageTitle = 'Ownership Transfer',
    tokenValid = false,
    token = null,
    canReview = false,
    transfer = {},
    routes = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    return (
        <>
            <Head title={pageTitle} />

            <div className="mx-auto max-w-xl">
                <div className="card p-6">
                    <div className="text-2xl font-semibold text-slate-900">Project Transfer Request</div>

                    {!tokenValid ? (
                        <p className="mt-4 text-sm text-rose-600">
                            This transfer link is invalid or has expired. Please ask the sender to resend the invite.
                        </p>
                    ) : (
                        <>
                            <div className="mt-4 space-y-2 text-sm text-slate-700">
                                <div>
                                    <span className="font-semibold text-slate-900">Item:</span> {transfer?.project_name}
                                </div>
                                <div>
                                    <span className="font-semibold text-slate-900">From:</span> {transfer?.from_customer_name}
                                </div>
                                <div>
                                    <span className="font-semibold text-slate-900">Status:</span> {transfer?.status_label}
                                </div>
                                {transfer?.reason ? (
                                    <div>
                                        <span className="font-semibold text-slate-900">Note:</span> {transfer.reason}
                                    </div>
                                ) : null}
                            </div>

                            {canReview ? (
                                <div className="mt-6 flex items-center gap-3">
                                    <form action={routes?.accept} method="POST" data-native="true">
                                        <input type="hidden" name="_token" value={csrf} />
                                        <input type="hidden" name="token" value={token || ''} />
                                        <button
                                            type="submit"
                                            className="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                                            onClick={(e) => {
                                                if (!confirm('Accept this project transfer? It will become part of your account.')) {
                                                    e.preventDefault();
                                                }
                                            }}
                                        >
                                            Accept Transfer
                                        </button>
                                    </form>
                                    <form action={routes?.reject} method="POST" data-native="true">
                                        <input type="hidden" name="_token" value={csrf} />
                                        <input type="hidden" name="token" value={token || ''} />
                                        <button
                                            type="submit"
                                            className="rounded-full border border-rose-300 px-5 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50"
                                            onClick={(e) => {
                                                if (!confirm('Reject this project transfer?')) {
                                                    e.preventDefault();
                                                }
                                            }}
                                        >
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            ) : (
                                <p className="mt-6 text-sm text-slate-500">
                                    This transfer has already been resolved and can no longer be accepted or rejected.
                                </p>
                            )}
                        </>
                    )}
                </div>
            </div>
        </>
    );
}
