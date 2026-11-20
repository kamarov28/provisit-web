<script>
    import { useForm, page } from '@inertiajs/svelte';
    import { Mail, ShieldCheck, ArrowRight, RotateCcw } from '@lucide/svelte';

    let { pendingEmail = null, devOtpHint = null } = $props();

    let manualEmailStep = $state(false);
    let step = $derived(pendingEmail && !manualEmailStep ? 'verify' : 'email');

    const emailForm = useForm({
        email: 'marketing@prolabios.com',
    });

    const verifyForm = useForm({
        email: 'marketing@prolabios.com',
        code: '',
    });

    $effect(() => {
        if (pendingEmail) {
            manualEmailStep = false;
            emailForm.email = pendingEmail;
            verifyForm.email = pendingEmail;
        }
    });

    function handleSendCode(e) {
        e.preventDefault();
        emailForm.post('/login/send-code', {
            preserveScroll: true,
            onSuccess: () => {
                manualEmailStep = false;
                verifyForm.email = emailForm.email;
                verifyForm.code = '';
            },
        });
    }

    function handleVerifyCode(e) {
        e.preventDefault();
        verifyForm.post('/login/verify', {
            preserveScroll: true,
        });
    }

    function resetToEmail() {
        manualEmailStep = true;
        verifyForm.reset('code');
        verifyForm.clearErrors();
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
            {#if $page.props.flash?.success}
                <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-3.5 py-2.5 text-xs text-emerald-800">
                    {$page.props.flash.success}
                </div>
            {/if}

            {#if devOtpHint}
                <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-3.5 py-2.5 text-xs text-amber-900 flex items-center justify-between">
                    <span>Mode Lokal — Kode OTP Anda:</span>
                    <button
                        type="button"
                        onclick={() => (verifyForm.code = devOtpHint)}
                        class="font-mono font-bold tracking-widest bg-amber-200/70 hover:bg-amber-200 px-2 py-0.5 rounded cursor-pointer"
                    >
                        {devOtpHint}
                    </button>
                </div>
            {/if}

            {#if step === 'email'}
                <form onsubmit={handleSendCode} class="space-y-4">
                    <div>
                        <label for="loginEmail" class="block text-xs font-semibold text-slate-700 mb-1.5">
                            Email Kerja Terdaftar
                        </label>
                        <div class="relative">
                            <Mail class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <input
                                id="loginEmail"
                                type="email"
                                required
                                autocomplete="email"
                                bind:value={emailForm.email}
                                placeholder="marketing@prolabios.com"
                                class="w-full pl-9 pr-3 py-2.5 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-900/10 focus:border-slate-900 outline-none"
                            />
                        </div>
                        {#if emailForm.errors.email}
                            <p class="mt-1.5 text-xs text-rose-600">{emailForm.errors.email}</p>
                        {/if}
                    </div>

                    <button
                        type="submit"
                        disabled={emailForm.processing}
                        class="w-full flex items-center justify-center gap-2 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white text-sm font-medium py-2.5 px-4 rounded-lg transition-colors cursor-pointer"
                    >
                        <span>{emailForm.processing ? 'Mengirim Kode...' : 'Kirim Kode Masuk'}</span>
                        <ArrowRight class="w-4 h-4" />
                    </button>
                </form>
            {:else}
                <form onsubmit={handleVerifyCode} class="space-y-4">
                    <div class="flex items-center justify-between bg-slate-50 border border-slate-200/80 rounded-lg px-3 py-2">
                        <span class="text-xs font-medium text-slate-700 truncate">{verifyForm.email}</span>
                        <button
                            type="button"
                            onclick={resetToEmail}
                            class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-600 hover:text-slate-900 cursor-pointer shrink-0"
                        >
                            <RotateCcw class="w-3 h-3" />
                            Ganti
                        </button>
                    </div>

                    <div>
                        <label for="otpCode" class="block text-xs font-semibold text-slate-700 mb-1.5">
                            Kode Verifikasi (6 Digit)
                        </label>
                        <div class="relative">
                            <ShieldCheck class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <input
                                id="otpCode"
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                required
                                autocomplete="one-time-code"
                                bind:value={verifyForm.code}
                                placeholder="000000"
                                class="w-full pl-9 pr-3 py-2.5 text-center font-mono text-base tracking-[0.35em] border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-900/10 focus:border-slate-900 outline-none"
                            />
                        </div>
                        {#if verifyForm.errors.code}
                            <p class="mt-1.5 text-xs text-rose-600">{verifyForm.errors.code}</p>
                        {/if}
                    </div>

                    <button
                        type="submit"
                        disabled={verifyForm.processing}
                        class="w-full flex items-center justify-center gap-2 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white text-sm font-medium py-2.5 px-4 rounded-lg transition-colors cursor-pointer"
                    >
                        <span>{verifyForm.processing ? 'Memverifikasi...' : 'Verifikasi & Masuk'}</span>
                    </button>
                </form>
            {/if}
        </div>
    </div>
</div>