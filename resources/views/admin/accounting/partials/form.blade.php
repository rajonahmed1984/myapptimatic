@php
    $entry = $entry ?? null;
    $formAction = $formAction ?? route('admin.accounting.store');
    $formMethod = strtoupper($formMethod ?? 'POST');
    $scope = $scope ?? 'ledger';
    $search = $search ?? '';
@endphp

<form method="POST" action="{{ $formAction }}" data-ajax-form="true" class="space-y-5">
    @csrf
    @if($formMethod !== 'POST')
        @method($formMethod)
    @endif
    <input type="hidden" name="scope" value="{{ $scope }}">
    <input type="hidden" name="search" value="{{ $search }}">

    @include('admin.accounting._form', compact('entry'))

    <div class="flex justify-end">
        <button type="submit" class="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white">
            {{ $formMethod === 'PUT' ? 'Update entry' : 'Save entry' }}
        </button>
    </div>
</form>
