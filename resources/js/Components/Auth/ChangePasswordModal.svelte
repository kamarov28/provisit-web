<script>
    import { X, KeyRound, Eye, EyeOff, CheckCircle2 } from '@lucide/svelte';
    import { useForm } from '@inertiajs/svelte';

    let { authUser = null } = $props();

    let isOpen = $state(false);
    let showCurrent = $state(false);
    let showNew = $state(false);
    let showConfirm = $state(false);
    let localSuccess = $state('');

    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    export function open() {
        form.reset();
        form.clearErrors();
        localSuccess = '';
        showCurrent = false;
        showNew = false;
        showConfirm = false;
        isOpen = true;
    }

    export function close() {
        isOpen = false;
    }

    function submit(e) {
        e.preventDefault();
        localSuccess = '';
        form.put('/profile/password', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                localSuccess = 'Kata sandi berhasil diperbarui.';
                setTimeout(() => {
                    isOpen = false;
                    localSuccess = '';
                }, 1200);
            },
        });
    }
</script>

{#if isOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center">
                        <KeyRound class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Ganti Kata Sandi</h3>
                        <p class="text-[11px] text-slate-500">{authUser?.email || 'Akun Pengguna'}</p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={close}
                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <form onsubmit={submit} class="p-5 space-y-4">
                {#if localSuccess}
                    <div class="flex items-center gap-2 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-medium">
                        <CheckCircle2 class="w-4 h-4 shrink-0" />
                        <span>{localSuccess}</span>
                    </div>
                {/if}

                <div>
                    <label for="current_password" class="block text-xs font-medium text-slate-700 mb-1">
                        Kata Sandi Saat Ini
                    </label>
                    <div class="relative">
                        <input
                            id="current_password"
                            type={showCurrent ? 'text' : 'password'}
                            bind:value={form.current_password}
                            required
                            autocomplete="current-password"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 pr-9 text-xs text-slate-900 focus:border-slate-900 focus:outline-hidden"
                            placeholder="Masukkan kata sandi lama"
                        />
                        <button
                            type="button"
                            onclick={() => (showCurrent = !showCurrent)}
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
                        >
                            {#if showCurrent}<EyeOff class="w-3.5 h-3.5" />{:else}<Eye class="w-3.5 h-3.5" />{/if}
                        </button>
                    </div>
                    {#if form.errors.current_password}
                        <p class="mt-1 text-[11px] text-rose-600">{form.errors.current_password}</p>
                    {/if}
                </div>

                <div>
                    <label for="new_password" class="block text-xs font-medium text-slate-700 mb-1">
                        Kata Sandi Baru (Minimal 8 Karakter)
                    </label>
                    <div class="relative">
                        <input
                            id="new_password"
                            type={showNew ? 'text' : 'password'}
                            bind:value={form.password}
                            required
                            minlength="8"
                            autocomplete="new-password"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 pr-9 text-xs text-slate-900 focus:border-slate-900 focus:outline-hidden"
                            placeholder="Masukkan kata sandi baru"
                        />
                        <button
                            type="button"
                            onclick={() => (showNew = !showNew)}
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
                        >
                            {#if showNew}<EyeOff class="w-3.5 h-3.5" />{:else}<Eye class="w-3.5 h-3.5" />{/if}
                        </button>
                    </div>
                    {#if form.errors.password}
                        <p class="mt-1 text-[11px] text-rose-600">{form.errors.password}</p>
                    {/if}
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-medium text-slate-700 mb-1">
                        Konfirmasi Kata Sandi Baru
                    </label>
                    <div class="relative">
                        <input
                            id="password_confirmation"
                            type={showConfirm ? 'text' : 'password'}
                            bind:value={form.password_confirmation}
                            required
                            minlength="8"
                            autocomplete="new-password"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 pr-9 text-xs text-slate-900 focus:border-slate-900 focus:outline-hidden"
                            placeholder="Ulangi kata sandi baru"
                        />
                        <button
                            type="button"
                            onclick={() => (showConfirm = !showConfirm)}
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
                        >
                            {#if showConfirm}<EyeOff class="w-3.5 h-3.5" />{:else}<Eye class="w-3.5 h-3.5" />{/if}
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        type="button"
                        onclick={close}
                        class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50 cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        disabled={form.processing}
                        class="px-3.5 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white text-xs font-medium cursor-pointer"
                    >
                        {form.processing ? 'Menyimpan...' : 'Simpan Kata Sandi'}
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}