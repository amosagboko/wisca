<x-portal-layout title="My Profile">
    <x-portal.page-intro
        eyebrow="Account"
        title="My Profile"
        meta="Upload a passport photograph so colleagues can recognise you in queues, assignments, and the sidebar."
    />

    <x-portal.panel title="Passport & details">
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-5 max-w-2xl">
            @csrf
            @method('PUT')

            <div class="flex items-center gap-4">
                <x-user-avatar :user="$user" size="xl" />
                <div>
                    <p class="font-medium text-slate-800">{{ $user->name }}</p>
                    <p class="text-sm text-slate-500">{{ $user->getRoleNames()->first() }}</p>
                    <p class="mt-1 text-xs text-slate-500">Use a clear head-and-shoulders passport photo (JPG or PNG, max 2 MB).</p>
                </div>
            </div>

            <div>
                <x-input-label for="passport" value="Passport photograph" />
                <x-file-input id="passport" name="passport" accept=".jpg,.jpeg,.png,.webp" button="Upload passport" empty="No photo chosen" />
                <x-input-error :messages="$errors->get('passport')" class="mt-2" />
                @if ($user->passport_path)
                    <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remove_passport" value="1" class="rounded border-gray-300 text-red-600 shadow-sm focus:ring-red-500">
                        Remove current passport
                    </label>
                @endif
            </div>

            <div>
                <x-input-label for="name" value="Full name" />
                <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name', $user->name)" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="phone" value="Phone (optional)" />
                <x-text-input id="phone" name="phone" type="text" class="block mt-1 w-full" :value="old('phone', $user->phone)" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password" value="New password (leave blank to keep)" />
                <x-text-input id="password" name="password" type="password" class="block mt-1 w-full" autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" value="Confirm password" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="block mt-1 w-full" autocomplete="new-password" />
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <x-primary-button>Save profile</x-primary-button>
            </div>
        </form>
    </x-portal.panel>
</x-portal-layout>
