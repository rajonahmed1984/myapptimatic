@php
    $tabs = [
        ['key' => 'summary', 'label' => 'Summary', 'href' => route('admin.customers.show', $customer)],
        ['key' => 'profile', 'label' => 'Profile', 'href' => route('admin.customers.edit', $customer)],
        ['key' => 'services', 'label' => 'Products/Services', 'href' => route('admin.customers.show', ['customer' => $customer, 'tab' => 'services'])],
        ['key' => 'invoices', 'label' => 'Invoices', 'href' => route('admin.customers.show', ['customer' => $customer, 'tab' => 'invoices'])],
        ['key' => 'tickets', 'label' => 'Tickets', 'href' => route('admin.customers.show', ['customer' => $customer, 'tab' => 'tickets'])],
        ['key' => 'emails', 'label' => 'Emails', 'href' => route('admin.customers.show', ['customer' => $customer, 'tab' => 'emails'])],
        ['key' => 'log', 'label' => 'Log', 'href' => route('admin.customers.show', ['customer' => $customer, 'tab' => 'log'])],
    ];
@endphp

<div class="mt-6 flex flex-wrap gap-2 text-sm">
    @foreach($tabs as $item)
        @php($isActive = $activeTab === $item['key'])
        <a href="{{ $item['href'] }}" class="rounded-full border px-4 py-2 {{ $isActive ? 'border-teal-200 bg-teal-50 text-teal-600' : 'border-slate-200 text-slate-500 hover:text-teal-600' }}">
            {{ $item['label'] }}
        </a>
    @endforeach
</div>
