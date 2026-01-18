<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('table').forEach((table) => {
            if (table.closest('[data-table-responsive]')) {
                return;
            }
            const wrapper = document.createElement('div');
            wrapper.setAttribute('data-table-responsive', 'true');
            wrapper.className = 'overflow-x-auto';
            const parent = table.parentElement;
            if (parent) {
                parent.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            }
        });
    });
</script>
