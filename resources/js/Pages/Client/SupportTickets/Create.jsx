import React from 'react';
import { Head } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';

export default function Create({ form = {}, routes = {} }) {
    const priorityOptions = [
        { value: 'low', label: 'Low' },
        { value: 'medium', label: 'Medium' },
        { value: 'high', label: 'High' },
    ];

    return (
        <>
            <Head title="New Support Ticket" />

            <div className="card p-6">
                <div className="section-label">Support request</div>
                <h1 className="mt-2 text-2xl font-semibold text-slate-900">Open a ticket</h1>
                <p className="mt-2 text-sm text-slate-500">Describe your issue and we will respond quickly.</p>

                <form method="POST" action={routes.store} className="mt-6 space-y-5" encType="multipart/form-data" data-native="true">
                    <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content || ''} />
                    <div>
                        <label className="text-sm text-slate-600">Subject</label>
                        <input
                            name="subject"
                            defaultValue={form.subject || ''}
                            required
                            className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700"
                        />
                    </div>
                    <div>
                        <label className="text-sm text-slate-600">Priority</label>
                        <SearchableSelect
                            name="priority"
                            defaultValue={String(form.priority || 'medium')}
                            options={priorityOptions}
                            className="mt-2"
                            placeholder="Select priority"
                        />
                    </div>
                    <div>
                        <label className="text-sm text-slate-600">Message</label>
                        <textarea
                            name="message"
                            rows="6"
                            defaultValue={form.message || ''}
                            required
                            className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700"
                        />
                    </div>
                    <div>
                        <label className="text-sm text-slate-600">Attachment (image/PDF)</label>
                        <input name="attachment" type="file" accept="image/*,.pdf" className="mt-2 block w-full text-sm text-slate-600" />
                    </div>
                    <div className="flex items-center justify-between">
                        <a href={routes.index} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                            Back to tickets
                        </a>
                        <button type="submit" className="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white">
                            Submit ticket
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
