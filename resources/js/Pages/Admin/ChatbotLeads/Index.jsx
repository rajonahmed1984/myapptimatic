import React, { useState, useMemo } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import DateTimeText from '../../../Components/DateTimeText';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';

export default function Index({
    pageTitle = 'Chatbot Leads',
    leads = [],
    filters = {},
    pagination = {},
    routes = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';
    
    const [selectedLeadId, setSelectedLeadId] = useState(leads[0]?.id || null);

    const { searchTerm, setSearchTerm, submitSearch } = useInertiaLiveSearch({
        initialValue: filters?.search ?? '',
        url: routes?.index,
    });

    const selectedLead = useMemo(() => {
        return leads.find(lead => lead.id === selectedLeadId) || leads[0] || null;
    }, [leads, selectedLeadId]);

    // Parse transcript to structure bubbles
    const parsedMessages = useMemo(() => {
        if (!selectedLead || !selectedLead.transcript) return [];
        const lines = selectedLead.transcript.split('\n');
        const messages = [];
        
        for (let line of lines) {
            line = line.trim();
            if (!line) continue;
            
            if (line.startsWith('Visitor:')) {
                messages.push({
                    sender: 'visitor',
                    text: line.substring(8).trim()
                });
            } else if (line.startsWith('Chatbot:')) {
                messages.push({
                    sender: 'chatbot',
                    text: line.substring(8).trim()
                });
            } else if (
                line.startsWith('Visitor Name:') || 
                line.startsWith('Visitor Phone:') || 
                line.startsWith('Product/Service Interest:') || 
                line.startsWith('---')
            ) {
                // Ignore metadata headers
                continue;
            } else {
                // System note or other info
                messages.push({
                    sender: 'system',
                    text: line
                });
            }
        }
        return messages;
    }, [selectedLead]);

    const handleDelete = (id) => {
        if (!window.confirm('Are you sure you want to delete this chatbot lead transcript?')) {
            return;
        }

        const destroyUrl = routes?.destroy.replace(':id', id);
        router.delete(destroyUrl, {
            preserveScroll: true,
            onSuccess: () => {
                if (selectedLeadId === id) {
                    setSelectedLeadId(null);
                }
            }
        });
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">AI Chatbot</div>
                    <h1 className="mt-2 text-2xl font-semibold text-slate-900">Chatbot History</h1>
                    <p className="mt-1 text-sm text-slate-600">Review user requirements, contact info, and complete chat transcripts.</p>
                </div>
            </div>

            <div className="flex flex-col gap-6 lg:flex-row min-h-[600px] h-[calc(100vh-250px)]">
                {/* Left Pane - Leads List */}
                <div className="flex flex-col w-full lg:w-[40%] card p-0 overflow-hidden h-full">
                    {/* Search header */}
                    <div className="p-4 border-b border-slate-200 bg-slate-50/50">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                submitSearch();
                            }}
                            className="relative"
                        >
                            <input
                                type="text"
                                name="search"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                placeholder="Search leads by name, email, product..."
                                className="w-full pl-10 pr-4 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
                            />
                            <div className="absolute left-3.5 top-3 text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </form>
                    </div>

                    {/* Leads list content */}
                    <div className="flex-1 overflow-y-auto divide-y divide-slate-100">
                        {leads.length === 0 ? (
                            <div className="p-8 text-center text-slate-500 text-sm">
                                No leads or sessions found.
                            </div>
                        ) : (
                            leads.map((lead) => {
                                const active = selectedLead?.id === lead.id;
                                return (
                                    <button
                                        key={lead.id}
                                        onClick={() => setSelectedLeadId(lead.id)}
                                        className={`w-full text-left p-4 transition-all ${
                                            active 
                                                ? 'bg-teal-50/70 border-l-4 border-teal-600' 
                                                : 'hover:bg-slate-50/60 border-l-4 border-transparent'
                                        }`}
                                    >
                                        <div className="flex justify-between items-start gap-2">
                                            <h4 className="font-semibold text-slate-900 text-sm truncate">{lead.name}</h4>
                                            <span className="text-[10px] text-slate-400 whitespace-nowrap font-medium">{lead.created_at_display}</span>
                                        </div>
                                        <div className="text-xs text-slate-500 mt-1 truncate">{lead.email}</div>
                                        <div className="flex items-center gap-2 mt-2">
                                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-teal-100 text-teal-800">
                                                {lead.product_interest}
                                            </span>
                                            {lead.phone !== 'N/A' && (
                                                <span className="text-[10px] text-slate-400 truncate">{lead.phone}</span>
                                            )}
                                        </div>
                                    </button>
                                );
                            })
                        )}
                    </div>

                    {/* Pagination */}
                    {pagination?.has_pages && (
                        <div className="p-3 border-t border-slate-200 bg-slate-50/50 flex justify-between items-center gap-2 text-xs">
                            {pagination?.previous_url ? (
                                <a href={pagination.previous_url} data-native="true" className="px-3 py-1.5 border border-slate-300 rounded-full font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                                    Previous
                                </a>
                            ) : (
                                <span className="px-3 py-1.5 border border-slate-200 rounded-full text-slate-300">Previous</span>
                            )}
                            
                            {pagination?.next_url ? (
                                <a href={pagination.next_url} data-native="true" className="px-3 py-1.5 border border-slate-300 rounded-full font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                                    Next
                                </a>
                            ) : (
                                <span className="px-3 py-1.5 border border-slate-200 rounded-full text-slate-300">Next</span>
                            )}
                        </div>
                    )}
                </div>

                {/* Right Pane - Chat transcript bubble UI */}
                <div className="flex-1 card p-0 overflow-hidden flex flex-col h-full bg-slate-950/5 border border-slate-200">
                    {selectedLead ? (
                        <>
                            {/* Contact Header Panel */}
                            <div className="p-4 border-b border-slate-200 bg-white shadow-sm flex flex-wrap items-start justify-between gap-4">
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <h3 className="font-bold text-slate-950 text-base">{selectedLead.name}</h3>
                                        <span className="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-600 text-white">
                                            {selectedLead.product_interest}
                                        </span>
                                    </div>
                                    <div className="text-xs text-slate-600 flex flex-wrap items-center gap-x-4 gap-y-1">
                                        <span className="flex items-center gap-1">
                                            <strong>Email:</strong> <a href={`mailto:${selectedLead.email}`} className="text-teal-600 hover:underline">{selectedLead.email}</a>
                                        </span>
                                        {selectedLead.phone !== 'N/A' && (
                                            <span className="flex items-center gap-1">
                                                <strong>Phone:</strong> <a href={`tel:${selectedLead.phone}`} className="text-teal-600 hover:underline">{selectedLead.phone}</a>
                                            </span>
                                        )}
                                        <span className="text-slate-400">| Sync: {selectedLead.created_at_display}</span>
                                    </div>
                                </div>
                                
                                <button
                                    type="button"
                                    onClick={() => handleDelete(selectedLead.id)}
                                    className="px-3 py-1.5 rounded-xl border border-rose-200 hover:bg-rose-50 text-xs font-semibold text-rose-600 transition"
                                >
                                    Delete Lead
                                </button>
                            </div>

                            {/* Chat messages */}
                            <div className="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50/50">
                                {parsedMessages.length === 0 ? (
                                    <div className="flex flex-col items-center justify-center h-full text-slate-400 gap-2 text-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" className="h-10 w-10 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                        No chat messages found.
                                    </div>
                                ) : (
                                    parsedMessages.map((msg, index) => {
                                        if (msg.sender === 'system') {
                                            return (
                                                <div key={index} className="flex justify-center my-2">
                                                    <span className="bg-slate-200 text-slate-600 text-[10px] font-semibold px-2 py-0.5 rounded-md">
                                                        {msg.text}
                                                    </span>
                                                </div>
                                            );
                                        }

                                        const isVisitor = msg.sender === 'visitor';
                                        return (
                                            <div
                                                key={index}
                                                className={`flex ${isVisitor ? 'justify-end' : 'justify-start'}`}
                                            >
                                                <div className={`flex items-start gap-2.5 max-w-[80%] ${isVisitor ? 'flex-row-reverse' : ''}`}>
                                                    {/* Avatar Icon */}
                                                    <div className={`h-7 w-7 rounded-lg flex items-center justify-center text-[10px] font-bold shadow-sm flex-shrink-0 ${
                                                        isVisitor ? 'bg-teal-600 text-white' : 'bg-slate-200 text-slate-700'
                                                    }`}>
                                                        {isVisitor ? 'VS' : 'AI'}
                                                    </div>
                                                    
                                                    {/* Chat Bubble */}
                                                    <div className={`rounded-2xl px-3 py-2 text-sm leading-relaxed ${
                                                        isVisitor
                                                            ? 'bg-teal-600 text-white rounded-tr-none shadow-sm'
                                                            : 'bg-white text-slate-800 rounded-tl-none border border-slate-200/80 shadow-sm'
                                                    }`}>
                                                        {msg.text}
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })
                                )}
                            </div>
                        </>
                    ) : (
                        <div className="flex-1 flex flex-col items-center justify-center p-8 text-slate-400 gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-16 w-16 opacity-25 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            <p className="text-sm font-semibold">Select a chatbot session to view</p>
                            <p className="text-xs text-slate-400">All transcripts synced from your marketing chatbot will appear here.</p>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
