<script>
    let { title = 'PROvisit' } = $props();
</script>

<div class="min-h-screen bg-slate-50 flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white rounded-2xl border border-slate-200 shadow-sm p-8 text-center">
        <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center mx-auto mb-4 font-bold text-xl">
            PV
        </div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">{title}</h1>
        <p class="text-sm text-slate-500 mt-2">Laravel + Svelte 5 + Inertia</p>
    </div>
</div>
