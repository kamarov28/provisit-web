<script>
    import { useForm } from '@inertiajs/svelte';
    import { Mail, Lock, Eye, EyeOff, ArrowRight } from '@lucide/svelte';

    let showPassword = $state(false);

    const form = useForm({
        email: 'marketing@prolabios.com',
        password: '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        form.post('/login', {
            preserveScroll: true,
            onFinish: () => form.reset('password'),
        });
    }
</script>

<svelte:head>
    <title>Masuk - PROvisit</title>
</svelte:head>

<div class="min-h-screen bg-slate-50 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-slate-900 text-white font-bold text-lg shadow-sm">
            PV
        </div>
        <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-900">PROvisit</h1>
        <p class="mt-1 text-xs text-slate-500">Sales Visit &amp; Principal Exposure Management</p>
    </div>

    <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-7 px-6 shadow-sm border border-slate-200/80 rounded-2xl sm:px-8">
            <form onsubmit={handleSubmit} class="space-y-4">
                <div>
                    <label for="loginEmail" class="block text-xs font-semibold text-slate-700 mb-1.5">
                        Email
                    </label>
                    <div class="relative">
                        <Mail class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <input
                            id="loginEmail"
                            type="email"
                            required
                            autocomplete="email"
                            bind:value={form.email}
                            placeholder="marketing@prolabios.com"
                            class="w-full pl-9 pr-3 py-2.5 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-900/10 focus:border-slate-900 outline-none"
                        />
                    </div>
                    {#if form.errors.email}
                        <p class="mt-1.5 text-xs text-rose-600">{form.errors.email}</p>
                    {/if}
                </div>

                <div>
                    <label for="loginPassword" class="block text-xs font-semibold text-slate-700 mb-1.5">
                        Kata Sandi
                    </label>
                    <div class="relative">
                        <Lock class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <input
                            id="loginPassword"
                            type={showPassword ? 'text' : 'password'}
                            required
                            autocomplete="current-password"
                            bind:value={form.password}
                            placeholder="Masukkan kata sandi"
                            class="w-full pl-9 pr-10 py-2.5 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-900/10 focus:border-slate-900 outline-none"
                        />
                        <button
                            type="button"
                            onclick={() => (showPassword = !showPassword)}
                            aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Lihat kata sandi'}
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1 cursor-pointer"
                        >
                            {#if showPassword}
                                <EyeOff class="w-4 h-4" />
                            {:else}
                                <Eye class="w-4 h-4" />
                            {/if}
                        </button>
                    </div>
                    {#if form.errors.password}
                        <p class="mt-1.5 text-xs text-rose-600">{form.errors.password}</p>
                    {/if}
                </div>

                <button
                    type="submit"
                    disabled={form.processing}
                    class="w-full flex items-center justify-center gap-2 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white text-sm font-medium py-2.5 px-4 rounded-lg transition-colors cursor-pointer"
                >
                    <span>{form.processing ? 'Memproses...' : 'Masuk'}</span>
                    <ArrowRight class="w-4 h-4" />
                </button>
            </form>
        </div>
    </div>
</div>