<div
    x-data="{
        currencySymbol: @js(auth()->user()->company?->currencySymbol() ?? '₱'),
        open: false,
        step: 'form',
        loading: false,
        copied: false,
        temporaryPassword: '',
        createdEmployee: null,
        form: {
            name: '',
            email: '',
            hourly_rate: '',
        },
        errors: {},
        async submitEmployee() {
            this.loading = true;
            this.errors = {};

            try {
                const response = await fetch('{{ route('employer.employees.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(this.form),
                });

                const data = await response.json();

                if (! response.ok) {
                    if (response.status === 422) {
                        this.errors = data.errors ?? {};
                    } else {
                        this.errors = { general: [data.message ?? 'Unable to create employee. Please try again.'] };
                    }
                    return;
                }

                this.temporaryPassword = data.temporary_password;
                this.createdEmployee = data.user;
                this.step = 'success';
            } catch (error) {
                this.errors = { general: ['A network error occurred. Please check your connection and try again.'] };
            } finally {
                this.loading = false;
            }
        },
        async copyPassword() {
            if (! this.temporaryPassword) {
                return;
            }

            try {
                await navigator.clipboard.writeText(this.temporaryPassword);
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 2000);
            } catch (error) {
                this.errors = { general: ['Unable to copy to clipboard. Please copy the password manually.'] };
            }
        },
        resetForm() {
            this.step = 'form';
            this.form = { name: '', email: '', hourly_rate: '' };
            this.errors = {};
            this.temporaryPassword = '';
            this.createdEmployee = null;
            this.copied = false;
            this.loading = false;
        },
        closeModal() {
            this.resetForm();
            this.open = false;
        },
    }"
    @open-add-employee-modal.window="open = true"
    @keydown.escape.window="if (open) closeModal()"
>
    <div
        x-cloak
        x-show="open"
        class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="add-employee-title"
    >
        <div
            x-show="open"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 bg-slate-950/50"
            @click="closeModal()"
        ></div>

        <div
            x-show="open"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
            @click.stop
        >
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h2 id="add-employee-title" class="text-lg font-semibold text-slate-950">Add Employee</h2>
                    <p class="mt-1 text-sm text-slate-500" x-text="step === 'form' ? 'Create a new employee account with a temporary password.' : 'Share the temporary password securely with your employee.'"></p>
                </div>
                <button
                    type="button"
                    @click="closeModal()"
                    class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    aria-label="Close"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="px-6 py-5">
                <template x-if="errors.general">
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <template x-for="message in errors.general" :key="message">
                            <p x-text="message"></p>
                        </template>
                    </div>
                </template>

                <div x-show="step === 'form'" x-cloak>
                    <form @submit.prevent="submitEmployee()" class="space-y-5">
                        <div>
                            <label for="employee-name" class="block text-sm font-medium text-slate-700">Full Name</label>
                            <input
                                id="employee-name"
                                type="text"
                                x-model="form.name"
                                :disabled="loading"
                                required
                                autocomplete="name"
                                class="mt-1.5 block w-full rounded-lg border px-3 py-2.5 text-sm shadow-sm transition focus:outline-none focus:ring-2 disabled:bg-slate-50 disabled:text-slate-500"
                                :class="errors.name ? 'border-red-300 focus:border-red-500 focus:ring-red-200' : 'border-slate-300 focus:border-emerald-600 focus:ring-emerald-200'"
                                placeholder="Jane Smith"
                            >
                            <template x-if="errors.name">
                                <p class="mt-1.5 text-sm text-red-600" x-text="errors.name[0]"></p>
                            </template>
                        </div>

                        <div>
                            <label for="employee-email" class="block text-sm font-medium text-slate-700">Email Address</label>
                            <input
                                id="employee-email"
                                type="email"
                                x-model="form.email"
                                :disabled="loading"
                                required
                                autocomplete="email"
                                class="mt-1.5 block w-full rounded-lg border px-3 py-2.5 text-sm shadow-sm transition focus:outline-none focus:ring-2 disabled:bg-slate-50 disabled:text-slate-500"
                                :class="errors.email ? 'border-red-300 focus:border-red-500 focus:ring-red-200' : 'border-slate-300 focus:border-emerald-600 focus:ring-emerald-200'"
                                placeholder="jane@company.com"
                            >
                            <template x-if="errors.email">
                                <p class="mt-1.5 text-sm text-red-600" x-text="errors.email[0]"></p>
                            </template>
                        </div>

                        <div>
                            <label for="employee-hourly-rate" class="block text-sm font-medium text-slate-700">Hourly Rate</label>
                            <div class="relative mt-1.5">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500" x-text="currencySymbol"></span>
                                <input
                                    id="employee-hourly-rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    x-model="form.hourly_rate"
                                    :disabled="loading"
                                    required
                                    class="block w-full rounded-lg border py-2.5 pl-7 pr-3 text-sm shadow-sm transition focus:outline-none focus:ring-2 disabled:bg-slate-50 disabled:text-slate-500"
                                    :class="errors.hourly_rate ? 'border-red-300 focus:border-red-500 focus:ring-red-200' : 'border-slate-300 focus:border-emerald-600 focus:ring-emerald-200'"
                                    placeholder="15.00"
                                >
                            </div>
                            <template x-if="errors.hourly_rate">
                                <p class="mt-1.5 text-sm text-red-600" x-text="errors.hourly_rate[0]"></p>
                            </template>
                        </div>

                        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                @click="closeModal()"
                                :disabled="loading"
                                class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="loading"
                                class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <svg
                                    x-show="loading"
                                    x-cloak
                                    class="mr-2 h-4 w-4 animate-spin"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="loading ? 'Creating Employee...' : 'Create Employee'"></span>
                            </button>
                        </div>
                    </form>
                </div>

                <div x-show="step === 'success'" x-cloak class="space-y-5">
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-emerald-900">Employee Created Successfully!</h3>
                                <p class="mt-1 text-sm text-emerald-800">
                                    <span x-text="createdEmployee?.name"></span> can now sign in to the mobile app using their email and the temporary password below.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Temporary Password</p>
                        <p class="mt-1 text-sm text-amber-900">This password is shown only once. Copy it now and share it securely with the employee.</p>

                        <div class="mt-4 flex items-center gap-3 rounded-lg border border-amber-300 bg-white px-4 py-3 shadow-inner">
                            <code class="flex-1 break-all font-mono text-lg font-bold tracking-wide text-slate-950" x-text="temporaryPassword"></code>
                            <button
                                type="button"
                                @click="copyPassword()"
                                class="flex-shrink-0 rounded-lg border border-amber-300 bg-amber-100 px-3 py-2 text-sm font-semibold text-amber-900 transition hover:bg-amber-200"
                            >
                                <span x-text="copied ? 'Copied!' : 'Copy to Clipboard'"></span>
                            </button>
                        </div>
                    </div>

                    <dl class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Employee</dt>
                            <dd class="font-medium text-slate-950" x-text="createdEmployee?.name"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Email</dt>
                            <dd class="font-medium text-slate-950" x-text="createdEmployee?.email"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Hourly Rate</dt>
                            <dd class="font-medium text-slate-950" x-text="createdEmployee ? currencySymbol + Number(createdEmployee.hourly_rate).toFixed(2) : ''"></dd>
                        </div>
                    </dl>

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            @click="resetForm()"
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Add Another Employee
                        </button>
                        <button
                            type="button"
                            @click="closeModal()"
                            class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700"
                        >
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
