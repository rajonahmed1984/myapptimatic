<div id="expensesTable">
    <div class="overflow-hidden">
        <div class="overflow-x-auto">
            <div class="mt-4 px-3 py-3 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
                <table class="min-w-full text-left text-sm text-slate-700 whitespace-nowrap">
                    <thead>
                        <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Title</th>
                            <th class="px-3 py-2">Category</th>
                            <th class="px-3 py-2">Source</th>
                            <th class="px-3 py-2">Person</th>
                            <th class="px-3 py-2">Amount</th>
                            <th class="px-3 py-2">Invoice</th>
                            <th class="px-3 py-2">Attachment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @include('admin.expenses._table_rows', ['expenses' => $expenses])
                    </tbody>
                </table>
            </div>
            @include('admin.expenses._pagination', ['expenses' => $expenses])
        </div>
    </div>
</div>
