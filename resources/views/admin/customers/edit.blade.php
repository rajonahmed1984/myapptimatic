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
                            <th class="py-2">Project</th>
                            <th class="py-2">Created</th>
                            <th class="py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($projectClients as $clientUser)
                            <tr class="border-b border-slate-100">
                                <td class="py-2">{{ $clientUser->name }}</td>
                                <td class="py-2">{{ $clientUser->email }}</td>
                                <td class="py-2">{{ $clientUser->project?->name ?? '—' }}</td>
                                <td class="py-2">{{ $clientUser->created_at?->format($globalDateFormat) ?? '--' }}</td>
                                <td class="py-2 text-right">
                                    <button
                                        type="button"
                                        onclick="openEditModal({{ json_encode([
                                            'id' => $clientUser->id,
                                            'name' => $clientUser->name,
                                            'email' => $clientUser->email,
                                            'project_id' => $clientUser->project_id
                                        ]) }})"
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
    </div>

    <!-- Edit Project User Modal -->
    <div id="editModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-slate-900 bg-opacity-50 transition-opacity" aria-hidden="true" onclick="closeEditModal()"></div>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="editForm" method="POST" class="p-6">
                    @csrf
                    @method('PUT')

                    <div class="mb-6">
                        <h3 class="text-xl font-semibold text-slate-900">Edit Project Login</h3>
                        <p class="text-sm text-slate-500 mt-1">Update the project-specific login details</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="text-sm text-slate-600">Project</label>
                            <select id="edit_project_id" name="project_id" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                                <option value="">Select a project</option>
                                @foreach($projects as $projectOption)
                                    <option value="{{ $projectOption->id }}">{{ $projectOption->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-sm text-slate-600">Name</label>
                            <input id="edit_name" name="name" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        </div>

                        <div>
                            <label class="text-sm text-slate-600">Email</label>
                            <input id="edit_email" name="email" type="email" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        </div>

                        <div>
                            <label class="text-sm text-slate-600">Password</label>
                            <input id="edit_password" name="password" type="password" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                            <p class="mt-1 text-xs text-slate-500">Leave blank to keep current password</p>
                        </div>

                        <div>
                            <label class="text-sm text-slate-600">Confirm Password</label>
                            <input id="edit_password_confirmation" name="password_confirmation" type="password" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button type="button" onclick="closeEditModal()" class="text-sm text-slate-600 hover:text-teal-600">Cancel</button>
                        <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white hover:bg-teal-600">Update Login</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openEditModal(user) {
            document.getElementById('editForm').action = "{{ route('admin.customers.project-users.update', [$customer, '__USER_ID__']) }}".replace('__USER_ID__', user.id);
            document.getElementById('edit_name').value = user.name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_project_id').value = user.project_id;
            document.getElementById('edit_password').value = '';
            document.getElementById('edit_password_confirmation').value = '';
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });
    </script>
@endsection
