@php
    $isEdit = $user->exists;
    $currentRole = old('role', $user->roles->first()?->name);
@endphp

<x-portal-layout :title="$isEdit ? 'Edit Staff' : 'Add Staff'">
    <x-portal.page-intro
        eyebrow="School Structure"
        :title="$isEdit ? 'Edit staff member' : 'New staff member'"
        meta="Each account gets one primary role that controls dashboard access and KPI ownership."
    />

    <x-portal.panel :title="$isEdit ? $user->name : 'Account details'">
        <form method="POST" action="{{ $isEdit ? route('admin.users.update', $user) : route('admin.users.store') }}" enctype="multipart/form-data" class="space-y-5 max-w-2xl">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="flex items-center gap-4">
                @if ($isEdit)
                    <x-user-avatar :user="$user" size="lg" />
                @endif
                <div class="flex-1">
                    <x-input-label for="passport" value="Passport photograph" />
                    <x-file-input id="passport" name="passport" accept=".jpg,.jpeg,.png,.webp" button="Upload passport" empty="No photo chosen" />
                    <x-input-error :messages="$errors->get('passport')" class="mt-2" />
                    @if ($isEdit && $user->passport_path)
                        <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="remove_passport" value="1" class="rounded border-gray-300 text-red-600 shadow-sm focus:ring-red-500">
                            Remove current passport
                        </label>
                    @endif
                </div>
            </div>

            <div>
                <x-input-label for="name" value="Full name" />
                <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name', $user->name)" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="email" value="Email" />
                <x-text-input id="email" name="email" type="email" class="block mt-1 w-full" :value="old('email', $user->email)" required />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="phone" value="Phone (optional)" />
                <x-text-input id="phone" name="phone" type="text" class="block mt-1 w-full" :value="old('phone', $user->phone)" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="role" value="Role" />
                <select id="role" name="role" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select role...</option>
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected($currentRole === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('role')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="status" value="Status" />
                <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach (['active', 'inactive'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $user->status) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password" :value="$isEdit ? 'New password (leave blank to keep)' : 'Password'" />
                <x-text-input id="password" name="password" type="password" class="block mt-1 w-full" :required="! $isEdit" autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" value="Confirm password" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="block mt-1 w-full" autocomplete="new-password" />
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create account' }}</x-primary-button>
                <a href="{{ route('admin.users.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
