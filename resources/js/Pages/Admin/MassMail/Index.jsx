import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';

export default function Index({
    campaigns = {},
    counts = {},
}) {
    const { csrf_token: csrfToken = '', errors = {}, status = '' } = usePage().props || {};
    const [subject, setSubject] = useState('');
    const [targetStatus, setTargetStatus] = useState('all');
    const [body, setBody] = useState('');

    const campaignList = Array.isArray(campaigns?.data) ? campaigns.data : [];

    const getStatusBadge = (campaignStatus) => {
        switch (String(campaignStatus).toLowerCase()) {
            case 'completed':
                return 'bg-emerald-50 text-emerald-700 border-emerald-200';
            case 'sending':
                return 'bg-blue-50 text-blue-700 border-blue-200 animate-pulse';
            case 'pending':
            default:
                return 'bg-slate-50 text-slate-600 border-slate-200';
        }
    };

    return (
        <AdminLayout>
            <Head title="Mass Mail Campaigns" />

            <div className="space-y-6">
                {status && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
                        {status}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Send Campaign Form */}
                    <div className="card p-6 lg:col-span-2 space-y-4">
                        <div className="section-label">New Email Campaign</div>
                        <div className="text-xl font-bold text-slate-900">Compose Mass Notification</div>
                        <p className="text-sm text-slate-500">Send an email message to all or filtered clients instantly. Dispatched safely via the background queue.</p>

                        <form method="POST" action={route('admin.mass-mail.store')} data-native="true" className="space-y-4 pt-2">
                            <input type="hidden" name="_token" value={csrfToken} />

                            <div>
                                <label className="text-sm font-semibold text-slate-700">Subject</label>
                                <input
                                    name="subject"
                                    type="text"
                                    value={subject}
                                    onChange={(e) => setSubject(e.target.value)}
                                    placeholder="Enter email subject line"
                                    className="ui-input mt-1.5 w-full"
                                    required
                                />
                                {errors?.subject && <div className="mt-1 text-xs text-rose-600">{errors.subject}</div>}
                            </div>

                            <div>
                                <label className="text-sm font-semibold text-slate-700">Target Audience (Client Status)</label>
                                <select
                                    name="target_status"
                                    value={targetStatus}
                                    onChange={(e) => setTargetStatus(e.target.value)}
                                    className="ui-input mt-1.5 w-full bg-white"
                                    required
                                >
                                    <option value="all">All Clients ({counts.all || 0} active/inactive/suspended)</option>
                                    <option value="active">Active Clients Only ({counts.active || 0})</option>
                                    <option value="suspended">Suspended Clients Only ({counts.suspended || 0})</option>
                                    <option value="inactive">Inactive Clients Only ({counts.inactive || 0})</option>
                                </select>
                                {errors?.target_status && <div className="mt-1 text-xs text-rose-600">{errors.target_status}</div>}
                            </div>

                            <div>
                                <label className="text-sm font-semibold text-slate-700">Message Body (HTML Allowed)</label>
                                <textarea
                                    name="body"
                                    rows={8}
                                    value={body}
                                    onChange={(e) => setBody(e.target.value)}
                                    placeholder="<p>Dear Client,</p><p>We are excited to announce...</p>"
                                    className="ui-input mt-1.5 w-full font-mono text-sm"
                                    required
                                />
                                <span className="text-[11px] text-slate-400">You can use standard HTML markup. The message will be wrapped in the system's global branded template automatically.</span>
                                {errors?.body && <div className="mt-1 text-xs text-rose-600">{errors.body}</div>}
                            </div>

                            <div className="flex justify-end pt-2">
                                <button
                                    type="submit"
                                    className="rounded-full bg-teal-500 hover:bg-teal-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-all"
                                >
                                    Launch Mass Mailing
                                </button>
                            </div>
                        </form>
                    </div>

                    {/* Stats & Audience Summary */}
                    <div className="space-y-6">
                        <div className="card p-6 space-y-4">
                            <div className="section-label">Recipient Stats</div>
                            <div className="text-lg font-bold text-slate-900">Audience Segments</div>
                            
                            <div className="space-y-3 pt-2">
                                <div className="flex justify-between items-center border-b border-slate-100 pb-2">
                                    <span className="text-sm font-medium text-slate-600">Total Clients</span>
                                    <span className="text-sm font-bold text-slate-950">{counts.all || 0}</span>
                                </div>
                                <div className="flex justify-between items-center border-b border-slate-100 pb-2">
                                    <span className="text-sm font-medium text-emerald-600">Active</span>
                                    <span className="text-sm font-bold text-emerald-700">{counts.active || 0}</span>
                                </div>
                                <div className="flex justify-between items-center border-b border-slate-100 pb-2">
                                    <span className="text-sm font-medium text-amber-600">Suspended</span>
                                    <span className="text-sm font-bold text-amber-700">{counts.suspended || 0}</span>
                                </div>
                                <div className="flex justify-between items-center pb-2">
                                    <span className="text-sm font-medium text-slate-400">Inactive</span>
                                    <span className="text-sm font-bold text-slate-600">{counts.inactive || 0}</span>
                                </div>
                            </div>
                        </div>

                        <div className="card p-6 space-y-3">
                            <div className="section-label">Pro-Tips</div>
                            <div className="text-sm font-bold text-slate-800">HTML Templates</div>
                            <p className="text-xs text-slate-500 leading-relaxed">
                                You can style headings, write bullet points, or construct links inside the editor. The message body is drop-in integrated inside the standard header-footer email wrapper.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Campaign History Log */}
                <div className="card p-6">
                    <div className="section-label mb-2">Campaign Logs</div>
                    <div className="text-xl font-bold text-slate-900 mb-4">Past Mass Mailings</div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 text-xs font-bold uppercase tracking-wider text-slate-500">
                                    <th className="pb-3">Subject</th>
                                    <th className="pb-3">Target</th>
                                    <th className="pb-3 text-center">Progress / Sent</th>
                                    <th className="pb-3 text-center">Status</th>
                                    <th className="pb-3">Created By</th>
                                    <th className="pb-3">Date Dispatched</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {campaignList.length > 0 ? (
                                    campaignList.map((campaign) => {
                                        const progressPercent = campaign.total_recipients > 0
                                            ? Math.round((campaign.sent_count / campaign.total_recipients) * 100)
                                            : 0;

                                        return (
                                            <tr key={campaign.id} className="hover:bg-slate-50/50">
                                                <td className="py-3.5 font-semibold text-slate-900 max-w-xs truncate" title={campaign.subject}>
                                                    {campaign.subject}
                                                </td>
                                                <td className="py-3.5 capitalize text-slate-600">
                                                    {campaign.target_status}
                                                </td>
                                                <td className="py-3.5 text-center">
                                                    <div className="flex flex-col items-center">
                                                        <span className="text-xs font-semibold text-slate-700">
                                                            {campaign.sent_count} / {campaign.total_recipients} ({progressPercent}%)
                                                        </span>
                                                        <div className="mt-1 h-1.5 w-24 overflow-hidden rounded-full bg-slate-100">
                                                            <div
                                                                className={`h-full rounded-full transition-all ${
                                                                    campaign.status === 'completed' ? 'bg-emerald-500' : 'bg-teal-500'
                                                                }`}
                                                                style={{ width: `${progressPercent}%` }}
                                                            />
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="py-3.5 text-center">
                                                    <span className={`inline-block rounded-full border px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider ${getStatusBadge(campaign.status)}`}>
                                                        {campaign.status}
                                                    </span>
                                                </td>
                                                <td className="py-3.5 text-slate-600">
                                                    {campaign.creator_name}
                                                </td>
                                                <td className="py-3.5 text-xs text-slate-500">
                                                    {campaign.created_at_display}
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan={6} className="py-8 text-center text-slate-500">
                                            No mass mail campaigns launched yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {campaigns?.links?.length > 3 && (
                        <div className="mt-4 flex flex-wrap items-center gap-2 text-sm">
                            {campaigns.links.map((link, index) =>
                                link.url ? (
                                    <a
                                        key={`${index}-${link.label}`}
                                        href={link.url}
                                        data-native="true"
                                        className={`rounded-full border px-3 py-1 ${
                                            link.active
                                                ? 'border-slate-900 bg-slate-900 text-white'
                                                : 'border-slate-300 text-slate-700 hover:border-teal-300 hover:text-teal-600'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span
                                        key={`${index}-${link.label}`}
                                        className="rounded-full border border-slate-200 px-3 py-1 text-slate-400"
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        style={{ pointerEvents: 'none' }}
                                    />
                                )
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
