@extends('layouts.admin')

@section('title', 'Edit Customer')
@section('page-title', 'Edit Customer')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Customer</div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $customer->name }}</h1>
            <div class="mt-1 text-sm text-slate-500">Client ID: {{ $customer->id }}</div>
        </div>
        <div class="text-sm text-slate-600">
            <div>Status: {{ ucfirst($customer->status) }}</div>
            <div>Created: {{ $customer->created_at?->format($globalDateFormat) ?? '--' }}</div>
        </div>
    </div>

    <div class="card p-6">
        @include('admin.customers.partials.tabs', ['customer' => $customer, 'activeTab' => 'profile'])

        <form method="POST" action="{{ route('admin.customers.update', $customer) }}" enctype="multipart/form-data" hx-boost="false" class="mt-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm text-slate-600">Name</label>
                    <input name="name" value="{{ old('name', $customer->name) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Company Name</label>
                    <input name="company_name" value="{{ old('company_name', $customer->company_name) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Email</label>
                    <input name="email" type="email" value="{{ old('email', $customer->email) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Phone</label>
                    <input name="phone" value="{{ old('phone', $customer->phone) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Address</label>
                    <textarea name="address" rows="2" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('address', $customer->address) }}</textarea>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Status</label>
                    <select name="status" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        <option value="active" {{ old('status', $customer->status) === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status', $customer->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Access Override Until</label>
                    <input name="access_override_until" type="date" value="{{ old('access_override_until', $customer->access_override_until?->format('Y-m-d')) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    <p class="mt-1 text-xs text-slate-500">Grant temporary access even if status is inactive</p>
                </div>
            </div>

            <div>
                <label class="text-sm text-slate-600">Notes</label>
                <textarea name="notes" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('notes', $customer->notes) }}</textarea>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-sm text-slate-600">Avatar</label>
                    <input name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    <div class="mt-2">
                        <x-avatar :path="$customer->avatar_path" :name="$customer->name" size="h-16 w-16" textSize="text-sm" />
                    </div>
                </div>
                <div>
                    <label class="text-sm text-slate-600">NID</label>
                    <input name="nid_file" type="file" accept=".jpg,.jpeg,.png,.pdf" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    @if($customer->nid_path)
                        <div class="mt-1 text-xs text-slate-500">
                            <a href="{{ route('admin.user-documents.show', ['type' => 'customer', 'id' => $customer->id, 'doc' => 'nid']) }}" class="text-teal-600 hover:text-teal-500">View current NID</a>
                        </div>
                    @endif
                </div>
                <div>
                    <label class="text-sm text-slate-600">CV</label>
                    <input name="cv_file" type="file" accept=".pdf" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    @if($customer->cv_path)
                        <div class="mt-1 text-xs text-slate-500">
                            <a href="{{ route('admin.user-documents.show', ['type' => 'customer', 'id' => $customer->id, 'doc' => 'cv']) }}" class="text-teal-600 hover:text-teal-500">View current CV</a>
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Update customer</button>
                <a href="{{ route('admin.customers.index') }}" class="text-sm text-slate-600 hover:text-teal-600">Cancel</a>
            </div>
        </form>
    </div>

    <div class="card p-6 mt-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="section-label">Project clients</div>
                <h2 class="text-xl font-semibold text-slate-900">Project-specific logins</h2>
                <p class="text-sm text-slate-500">Assign a dedicated login that can only view and update one project.</p>
            </div>
        </div>

        @if($projectClients->isNotEmpty())
            <div class="mt-4 overflow-x-auto text-sm">
                <table class="min-w-full text-left text-slate-700">
                    <thead>
                        <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                            <th class="py-2">Name</th>
                            <th class="py-2">Email</th>
                            <th class="py-2">Status</th>
                            <th class="py-2">Project</th>
                            <th class="py-2">Created</th>
                            <th class="py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($projectClients as $clientUser)
                            <tr class="border-b border-slate-100" data-project-user-row="{{ $clientUser->id }}">
                                <td class="py-2" data-user-field="name">{{ $clientUser->name }}</td>
                                <td class="py-2" data-user-field="email">{{ $clientUser->email }}</td>
                                <td class="py-2" data-user-field="status">
                                    <x-status-badge :status="$clientUser->status ?? 'active'" />
                                </td>
                                <td class="py-2" data-user-field="project">{{ $clientUser->project?->name ?? '—' }}</td>
                                <td class="py-2" data-user-field="created">{{ $clientUser->created_at?->format($globalDateFormat) ?? '--' }}</td>
                                <td class="py-2 text-right">
                                    <button
                                        type="button"
                                        data-project-user-edit
                                        data-user-id="{{ $clientUser->id }}"
                                        data-user-name="{{ $clientUser->name }}"
                                        data-user-email="{{ $clientUser->email }}"
                                        data-user-status="{{ $clientUser->status ?? 'active' }}"
                                        data-project-id="{{ $clientUser->project_id }}"
                                        data-project-name="{{ $clientUser->project?->name ?? '' }}"
                                        data-created-at="{{ $clientUser->created_at?->format($globalDateFormat) ?? '--' }}"
                                        class="text-teal-600 hover:text-teal-500 mr-3"
                                    >
                                        Edit
                                    </button>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.customers.project-users.destroy', [$customer, $clientUser]) }}"
                                        class="inline"
                                        onsubmit="return confirm('Are you sure you want to delete this project login? This action cannot be undone.');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-600 hover:text-rose-500">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="mt-3 text-sm text-slate-500">No project-specific logins have been created yet.</p>
        @endif

        <form method="POST" action="{{ route('admin.customers.project-users.store', $customer) }}" class="mt-6 grid gap-4 md:grid-cols-2 text-sm">
            @csrf
            <div>
                <label class="text-sm text-slate-600">Project</label>
                <select name="project_id" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                    <option value="">Select a project</option>
                    @foreach($projects as $projectOption)
                        <option value="{{ $projectOption->id }}" @selected(old('project_id') == $projectOption->id)>{{ $projectOption->name }}</option>
                    @endforeach
                </select>
                @error('project_id')
                    <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm text-slate-600">Name</label>
                <input name="name" value="{{ old('name') }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                @error('name')
                    <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm text-slate-600">Email</label>
                <input name="email" type="email" value="{{ old('email') }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                @error('email')
                    <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm text-slate-600">Password</label>
                <input name="password" type="password" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                @error('password')
                    <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                @enderror
            </div>
            <div class="md:col-span-2">
                <label class="text-sm text-slate-600">Confirm Password</label>
                <input name="password_confirmation" type="password" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Create project login</button>
            </div>
        </form>

        <div id="project-user-edit-panel" class="mt-8 hidden">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="section-label">Edit project login</div>
                    <h3 class="text-lg font-semibold text-slate-900">Update project-specific login</h3>
                    <p class="text-sm text-slate-500">Changes are saved without leaving this page.</p>
                </div>
            </div>

            <form id="project-user-edit-form" method="POST" hx-boost="false" data-fetch-url-template="{{ route('admin.customers.project-users.show', [$customer, '__USER_ID__']) }}" data-update-url-template="{{ route('admin.customers.project-users.update', [$customer, '__USER_ID__']) }}" class="mt-4 grid gap-4 md:grid-cols-2 text-sm">
                @csrf
                @method('PUT')

                <div>
                    <label class="text-sm text-slate-600">Project</label>
                    <select name="project_id" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        <option value="">Select a project</option>
                        @foreach($projects as $projectOption)
                            <option value="{{ $projectOption->id }}">{{ $projectOption->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-rose-500 hidden" data-error-for="project_id"></p>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Name</label>
                    <input name="name" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    <p class="mt-1 text-xs text-rose-500 hidden" data-error-for="name"></p>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Email</label>
                    <input name="email" type="email" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    <p class="mt-1 text-xs text-rose-500 hidden" data-error-for="email"></p>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Status</label>
                    <select name="status" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <p class="mt-1 text-xs text-rose-500 hidden" data-error-for="status"></p>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Password</label>
                    <input name="password" type="password" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    <p class="mt-1 text-xs text-slate-500">Leave blank to keep current password</p>
                    <p class="mt-1 text-xs text-rose-500 hidden" data-error-for="password"></p>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-slate-600">Confirm Password</label>
                    <input name="password_confirmation" type="password" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div class="md:col-span-2 flex items-center justify-end gap-3">
                    <button type="button" class="text-sm text-slate-600 hover:text-teal-600" data-edit-cancel>Cancel</button>
                    <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Update login</button>
                </div>
            </form>
            <p id="project-user-edit-status" class="mt-3 text-sm text-slate-500 hidden"></p>
        </div>

        <div id="project-user-details" class="mt-6 hidden rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <div class="section-label">Updated login details</div>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-6">
                <div>
                    <div class="text-lg font-semibold text-slate-900" data-detail="name">--</div>
                    <div class="mt-1 text-sm text-slate-600">Email: <span data-detail="email">--</span></div>
                    <div class="text-sm text-slate-600">Project: <span data-detail="project">--</span></div>
                    <div class="mt-1 text-sm text-slate-600 flex items-center gap-2">Status: <span data-detail="status">--</span></div>
                </div>
                <div class="text-sm text-slate-500">
                    <div>Created: <span data-detail="created">--</span></div>
                    <div>Updated: <span data-detail="updated">--</span></div>
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const editPanel = document.getElementById('project-user-edit-panel');
                const editForm = document.getElementById('project-user-edit-form');
                const statusEl = document.getElementById('project-user-edit-status');
                const detailsPanel = document.getElementById('project-user-details');

                if (!editPanel || !editForm) {
                    return;
                }

                const fetchUrlTemplate = editForm.dataset.fetchUrlTemplate || '';
                const updateUrlTemplate = editForm.dataset.updateUrlTemplate || '';
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const detailMap = {
                    name: detailsPanel?.querySelector('[data-detail="name"]'),
                    email: detailsPanel?.querySelector('[data-detail="email"]'),
                    project: detailsPanel?.querySelector('[data-detail="project"]'),
                    status: detailsPanel?.querySelector('[data-detail="status"]'),
                    created: detailsPanel?.querySelector('[data-detail="created"]'),
                    updated: detailsPanel?.querySelector('[data-detail="updated"]'),
                };

                const setStatus = (message, isError = false) => {
                    if (!statusEl) {
                        return;
                    }

                    if (!message) {
                        statusEl.textContent = '';
                        statusEl.classList.add('hidden');
                        statusEl.classList.remove('text-rose-600', 'text-emerald-600');
                        return;
                    }

                    statusEl.textContent = message;
                    statusEl.classList.remove('hidden');
                    statusEl.classList.toggle('text-rose-600', isError);
                    statusEl.classList.toggle('text-emerald-600', !isError);
                };

                const clearErrors = () => {
                    editForm.querySelectorAll('[data-error-for]').forEach((el) => {
                        el.textContent = '';
                        el.classList.add('hidden');
                    });
                };

                const showErrors = (errors) => {
                    Object.entries(errors || {}).forEach(([field, messages]) => {
                        const el = editForm.querySelector(`[data-error-for="${field}"]`);
                        if (!el) {
                            return;
                        }
                        const text = Array.isArray(messages) ? messages.join(' ') : String(messages);
                        el.textContent = text;
                        el.classList.remove('hidden');
                    });
                };

                const renderStatusBadge = (data) => {
                    const label = data?.status_label || '--';
                    const classes = data?.status_classes || '';
                    if (!label || label === '--') {
                        return null;
                    }

                    const badge = document.createElement('span');
                    badge.className = `inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${classes}`.trim();
                    badge.textContent = label;
                    return badge;
                };

                const updateStatusBadge = (target, data) => {
                    if (!target) {
                        return;
                    }

                    target.innerHTML = '';
                    const badge = renderStatusBadge(data);
                    if (!badge) {
                        target.textContent = '--';
                        return;
                    }
                    target.appendChild(badge);
                };

                const updateRow = (data) => {
                    if (!data || !data.id) {
                        return;
                    }

                    const row = document.querySelector(`[data-project-user-row="${data.id}"]`);
                    if (!row) {
                        return;
                    }

                    const nameCell = row.querySelector('[data-user-field="name"]');
                    const emailCell = row.querySelector('[data-user-field="email"]');
                    const statusCell = row.querySelector('[data-user-field="status"]');
                    const projectCell = row.querySelector('[data-user-field="project"]');

                    if (nameCell) {
                        nameCell.textContent = data.name || '--';
                    }
                    if (emailCell) {
                        emailCell.textContent = data.email || '--';
                    }
                    if (statusCell) {
                        updateStatusBadge(statusCell, data);
                    }
                    if (projectCell) {
                        projectCell.textContent = data.project_name || '--';
                    }

                    const editBtn = row.querySelector('[data-project-user-edit]');
                    if (editBtn) {
                        editBtn.dataset.userName = data.name || '';
                        editBtn.dataset.userEmail = data.email || '';
                        editBtn.dataset.userStatus = data.status || '';
                        editBtn.dataset.projectId = data.project_id || '';
                        editBtn.dataset.projectName = data.project_name || '';
                        if (data.created_at) {
                            editBtn.dataset.createdAt = data.created_at;
                        }
                    }
                };

                const updateDetails = (data) => {
                    if (!detailsPanel || !data) {
                        return;
                    }

                    if (detailMap.name) {
                        detailMap.name.textContent = data.name || '--';
                    }
                    if (detailMap.email) {
                        detailMap.email.textContent = data.email || '--';
                    }
                    if (detailMap.project) {
                        detailMap.project.textContent = data.project_name || '--';
                    }
                    if (detailMap.status) {
                        updateStatusBadge(detailMap.status, data);
                    }
                    if (detailMap.created) {
                        detailMap.created.textContent = data.created_at || '--';
                    }
                    if (detailMap.updated) {
                        detailMap.updated.textContent = data.updated_at || '--';
                    }

                    detailsPanel.classList.remove('hidden');
                };

                const setFormUser = (data) => {
                    if (!data || !data.id) {
                        return;
                    }

                    if (!updateUrlTemplate) {
                        return;
                    }

                    editForm.dataset.userId = data.id;
                    editForm.action = updateUrlTemplate.replace('__USER_ID__', data.id);
                    editForm.querySelector('[name="name"]').value = data.name || '';
                    editForm.querySelector('[name="email"]').value = data.email || '';
                    editForm.querySelector('[name="project_id"]').value = data.project_id || data.projectId || '';
                    editForm.querySelector('[name="status"]').value = data.status || data.userStatus || 'active';
                    editForm.querySelector('[name="password"]').value = '';
                    editForm.querySelector('[name="password_confirmation"]').value = '';
                };

                const loadUser = async (payload) => {
                    if (!payload?.id) {
                        return;
                    }

                    if (!window.fetch || !fetchUrlTemplate) {
                        setFormUser(payload);
                        return;
                    }

                    setStatus('Loading login details...');

                    try {
                        const response = await fetch(fetchUrlTemplate.replace('__USER_ID__', payload.id), {
                            headers: { 'Accept': 'application/json' },
                        });

                        const responsePayload = await response.json().catch(() => null);
                        if (!response.ok || !responsePayload?.ok) {
                            setStatus(responsePayload?.message || 'Unable to load project login.', true);
                            setFormUser(payload);
                            return;
                        }

                        setFormUser(responsePayload.data || {});
                        setStatus('');
                    } catch (error) {
                        setStatus('Unable to load project login.', true);
                        setFormUser(payload);
                    }
                };

                document.addEventListener('click', (event) => {
                    const btn = event.target.closest('[data-project-user-edit]');
                    if (btn) {
                        const payload = {
                            id: btn.dataset.userId,
                            name: btn.dataset.userName || '',
                            email: btn.dataset.userEmail || '',
                            status: btn.dataset.userStatus || 'active',
                            projectId: btn.dataset.projectId || '',
                        };

                        clearErrors();
                        setStatus('');
                        editPanel.classList.remove('hidden');
                        editPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        loadUser(payload);
                        return;
                    }

                    if (event.target.closest('[data-edit-cancel]')) {
                        editPanel.classList.add('hidden');
                        setStatus('');
                    }
                });

                editForm.addEventListener('submit', async (event) => {
                    if (!window.fetch) {
                        return;
                    }

                    event.preventDefault();
                    clearErrors();
                    setStatus('Saving changes...');

                    try {
                        const response = await fetch(editForm.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: new FormData(editForm),
                        });

                        const payload = await response.json().catch(() => null);

                        if (!response.ok) {
                            if (response.status === 422 && payload?.errors) {
                                showErrors(payload.errors);
                                setStatus('Please fix the highlighted fields.', true);
                                return;
                            }
                            setStatus(payload?.message || 'Unable to update project login.', true);
                            return;
                        }

                        if (!payload?.ok) {
                            setStatus(payload?.message || 'Unable to update project login.', true);
                            return;
                        }

                        updateRow(payload.data || {});
                        updateDetails(payload.data || {});
                        editForm.querySelector('[name="password"]').value = '';
                        editForm.querySelector('[name="password_confirmation"]').value = '';
                        setStatus(payload.message || 'Project login updated.');
                    } catch (error) {
                        setStatus('Unable to update project login.', true);
                    }
                });
            });
        </script>
    @endpush
@endsection
