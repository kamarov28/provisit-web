<script>
    import {
        Calendar as CalendarIcon,
        Clock,
        MapPin,
        Building2,
        User as UserIcon,
        CheckCircle2,
        X,
        Briefcase
    } from '@lucide/svelte';

    let {
        visit = null,
        open = false,
        onClose,
        onEdit,
        statusColor
    } = $props();
</script>

{#if open && visit}
    <button
        type="button"
        class="fixed inset-0 z-40 bg-slate-900/20 backdrop-blur-2xs transition-opacity w-full h-full cursor-default"
        onclick={onClose}
        aria-label="Tutup panel"
    ></button>

    <aside class="fixed top-0 right-0 bottom-0 w-full max-w-xl bg-white z-50 shadow-2xl border-l border-slate-200 flex flex-col transform transition-transform duration-200 ease-out">
        <!-- Drawer Header -->
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="font-mono text-xs px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-700 font-semibold">
                    {visit.visit_code}
                </span>
                <span class="text-xs px-2 py-0.5 rounded font-medium border {statusColor(visit.status)}">
                    {visit.status}
                </span>
            </div>
            <button
                type="button"
                onclick={onClose}
                class="p-1 rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
            >
                <X class="w-4 h-4" />
            </button>
        </div>

        <!-- Drawer Body -->
        <div class="flex-1 overflow-y-auto p-6 flex flex-col gap-6">
            <div>
                <h2 class="text-xl font-bold text-slate-900 leading-snug">
                    {visit.title}
                </h2>
                <p class="text-xs text-slate-400 mt-1">
                    Dibuat pada {new Date(visit.created_at).toLocaleDateString('id-ID', { dateStyle: 'long' })}
                </p>
            </div>

            <!-- Properties Grid -->
            <div class="grid grid-cols-[125px_1fr] gap-y-3.5 gap-x-4 text-xs py-3 border-y border-slate-100">
                <span class="text-slate-400 flex items-center gap-1.5"><Building2 class="w-3.5 h-3.5" /> Customer</span>
                <span class="font-medium text-slate-900">{visit.customer_name_snapshot}</span>

                <span class="text-slate-400 flex items-center gap-1.5"><UserIcon class="w-3.5 h-3.5" /> PIC Pelanggan</span>
                <span class="text-slate-700">
                    {visit.pic_name_snapshot || '-'}
                    {#if visit.pic_position_snapshot}
                        <span class="text-slate-400">({visit.pic_position_snapshot})</span>
                    {/if}
                </span>

                <span class="text-slate-400 flex items-center gap-1.5"><CalendarIcon class="w-3.5 h-3.5" /> Tanggal</span>
                <span class="text-slate-700">{visit.visit_date}</span>

                <span class="text-slate-400 flex items-center gap-1.5"><Clock class="w-3.5 h-3.5" /> Waktu</span>
                <span class="text-slate-700">{visit.start_time ? visit.start_time.substring(0, 5) : '-'} WIB</span>

                <span class="text-slate-400 flex items-center gap-1.5"><Briefcase class="w-3.5 h-3.5" /> Sales PIC</span>
                <span class="text-slate-700 font-medium">
                    {visit.primary_sales_user?.name || '-'}
                    {#if visit.primary_sales_user?.team}
                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 border border-slate-200 ml-1">
                            Tim {visit.primary_sales_user.team}
                        </span>
                    {/if}
                </span>

                <span class="text-slate-400 flex items-center gap-1.5"><UserIcon class="w-3.5 h-3.5" /> Support Marketing</span>
                <span class="text-slate-700 font-medium">
                    {visit.support_marketing && visit.support_marketing.length > 0
                        ? visit.support_marketing.join(', ')
                        : 'None'}
                </span>

                <span class="text-slate-400 flex items-center gap-1.5"><MapPin class="w-3.5 h-3.5" /> Tipe Kunjungan</span>
                <span class="text-slate-700">{visit.visit_type} ({visit.visit_purpose})</span>
            </div>

            {#if visit.agenda}
                <div>
                    <h4 class="text-xs font-semibold text-slate-900 uppercase tracking-wider mb-1.5">Agenda</h4>
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700 leading-relaxed whitespace-pre-wrap">
                        {visit.agenda}
                    </div>
                </div>
            {/if}

            {#if visit.previsit_notes}
                <div>
                    <h4 class="text-xs font-semibold text-slate-900 uppercase tracking-wider mb-1.5">Pre-visit Notes</h4>
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700 leading-relaxed whitespace-pre-wrap">
                        {visit.previsit_notes}
                    </div>
                </div>
            {/if}

            {#if visit.postvisit_result}
                <div>
                    <h4 class="text-xs font-semibold text-slate-900 uppercase tracking-wider mb-1.5">Post-visit Result</h4>
                    <div class="p-3 bg-emerald-50/60 border border-emerald-200/80 rounded-lg text-xs text-slate-700 leading-relaxed whitespace-pre-wrap">
                        {visit.postvisit_result}
                    </div>
                </div>
            {/if}

            <!-- Principal Reviews -->
            <div>
                <h4 class="text-xs font-semibold text-slate-900 uppercase tracking-wider mb-2">Principal Review & Produk</h4>
                {#if visit.principal_reviews && visit.principal_reviews.length > 0}
                    <div class="flex flex-col gap-2">
                        {#each visit.principal_reviews as review}
                            <div class="border border-slate-200 rounded-lg p-3 bg-white">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-semibold text-xs text-slate-900">{review.principal_name_snapshot}</span>
                                    <span class="text-[10px] px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-600">
                                        {review.exposure_type || 'General'}
                                    </span>
                                </div>
                                {#if review.opportunity_status}
                                    <div class="text-[11px] text-slate-500 mb-2">
                                        Status: <span class="font-medium text-slate-700">{review.opportunity_status}</span>
                                    </div>
                                {/if}

                                {#if review.notes}
                                    <p class="text-[11px] text-slate-600 bg-slate-50 border border-slate-100 rounded p-2 mb-2 whitespace-pre-wrap">{review.notes}</p>
                                {/if}

                                {#if review.products && review.products.length > 0}
                                    <div class="flex flex-wrap gap-1.5 mt-2">
                                        {#each review.products as prod}
                                            <span class="text-[10px] px-2 py-0.5 rounded bg-slate-50 border border-slate-200 text-slate-700 font-mono">
                                                {prod.product_catalogue}: {prod.product_description_snapshot}
                                            </span>
                                        {/each}
                                    </div>
                                {/if}
                            </div>
                        {/each}
                    </div>
                {:else}
                    <p class="text-xs text-slate-400 italic">Belum ada review principal yang dicatat.</p>
                {/if}
            </div>

            <!-- Action Plans -->
            {#if visit.action_plans && visit.action_plans.length > 0}
                <div>
                    <h4 class="text-xs font-semibold text-slate-900 uppercase tracking-wider mb-2">Action Plan</h4>
                    <div class="flex flex-col gap-2">
                        {#each visit.action_plans as plan}
                            <div class="flex items-start gap-2 p-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                                <CheckCircle2 class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" />
                                <div class="flex-1">
                                    <p class="text-slate-800 font-medium">{plan.task_description}</p>
                                    <div class="flex items-center gap-2 mt-1 text-[10px] text-slate-400">
                                        <span>Owner: {plan.owner?.name || '-'}</span>
                                        <span>•</span>
                                        <span>Deadline: {plan.due_date || '-'}</span>
                                        <span>•</span>
                                        <span>Prioritas: {plan.priority}</span>
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>
            {/if}
        </div>

        <!-- Drawer Footer -->
        <div class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between gap-2">
            <button
                type="button"
                onclick={() => onEdit(visit)}
                class="px-3.5 py-1.5 text-xs font-medium rounded-lg bg-slate-900 hover:bg-slate-800 text-white transition-colors"
            >
                Edit Visit
            </button>
            <button
                type="button"
                onclick={onClose}
                class="px-3.5 py-1.5 text-xs font-medium border border-slate-200 rounded-lg bg-white hover:bg-slate-50 text-slate-700 transition-colors"
            >
                Tutup
            </button>
        </div>
    </aside>
{/if}