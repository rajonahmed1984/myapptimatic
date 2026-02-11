@extends('layouts.client')

@section('title', 'New Support Ticket')
@section('page-title', 'New Ticket')

@section('content')
    <div class="card p-6">
        <div class="section-label">Support request</div>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Open a ticket</h1>
        <p class="mt-2 text-sm text-slate-500">Describe your issue and we will respond quickly.</p>

        <form method="POST" action="{{ route('client.support-tickets.store') }}" class="mt-6 space-y-5" enctype="multipart/form-data">
            @csrf
            <div>
                <label class="text-sm text-slate-600">Subject</label>
                <input name="subject" value="{{ old('subject') }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Priority</label>
                <select name="priority" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                    <option value="low" @selected(old('priority') === 'low')>Low</option>
                    <option value="medium" @selected(old('priority', 'medium') === 'medium')>Medium</option>
                    <option value="high" @selected(old('priority') === 'high')>High</option>
                </select>
            </div>
            <div>
                <label class="text-sm text-slate-600">Message</label>
                <textarea name="message" rows="6" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">{{ old('message') }}</textarea>
            </div>
            <div>
                <label class="text-sm text-slate-600">Attachment (image/PDF)</label>
                <input name="attachment" type="file" accept="image/*,.pdf" class="mt-2 block w-full text-sm text-slate-600" />
                @error('attachment')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div class="flex items-center justify-between">
                <a href="{{ route('client.support-tickets.index') }}" class="text-sm text-slate-500 hover:text-teal-600" hx-boost="false">Back to tickets</a>
                <button type="submit" class="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white">Submit ticket</button>
            </div>
        </form>
    </div>
@endsection
