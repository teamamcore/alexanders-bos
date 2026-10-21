<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 card">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 card">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 card">
                <div class="max-w-xl">
                    <header>
                        <h2 class="text-lg font-medium text-slate-900">Passkeys &amp; device biometrics</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Sign in with Face ID, Touch ID, Windows Hello or a security key instead of a password.
                            Only a public key is stored here; your biometric stays on your device (SOW §3.13, MSA Ex. B.2).
                        </p>
                    </header>
                    <div class="mt-4 [&_h1]:hidden">
                        <livewire:passkeys />
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-8 card">
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
