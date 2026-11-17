<script>
    import {
        Calendar as CalendarIcon,
        Clock,
        MapPin,
        Building2,
        User as UserIcon,
        CheckCircle2,
        X,
        Briefcase,
        ExternalLink,
        Download,
        History,
        Copy
    } from '@lucide/svelte';

    let {
        visit = null,
        open = false,
        onClose,
        onEdit,
        onDuplicate,
        onToggleActionPlan,
        statusColor
    } = $props();

    function buildGoogleCalendarUrl(v) {
        if (!v) return '#';
        const dateClean = String(v.visit_date || '').replaceAll('-', '');
        const startRaw = v.start_time ? String(v.start_time).substring(0, 5).replace(':', '') : '0900';
        const startHour = parseInt(startRaw.slice(0, 2), 10) || 9;
        const startMin = startRaw.slice(2, 4) || '00';
        const endHour = String((startHour + 1) % 24).padStart(2, '0');
        const datesParam = `${dateClean}T${String(startHour).padStart(2, '0')}${startMin}00/${dateClean}T${endHour}${startMin}00`;

        const title = `[${v.visit_code}] ${v.customer_name_snapshot} - ${v.title}`;
        const details = [
            `Kode Visit: ${v.visit_code}`,
            `Customer: ${v.customer_name_snapshot}`,
            v.pic_name_snapshot ? `PIC: ${v.pic_name_snapshot}` : '',
            v.primary_sales_user?.name ? `Sales PIC: ${v.primary_sales_user.name}` : '',
            v.support_marketing?.length ? `Support Marketing: ${v.support_marketing.join(', ')}` : '',
            v.agenda ? `\nAgenda:\n${v.agenda}` : ''
        ].filter(Boolean).join('\n');

        const params = new URLSearchParams({
            action: 'TEMPLATE',
            text: title,
            dates: datesParam,
            ctz: 'Asia/Jakarta',
            details,
            location: v.customer_name_snapshot || ''
        });

        return `https://calendar.google.com/calendar/render?${params.toString()}`;
    }

    function isPlanOverdue(plan) {
        if (!plan.due_date || plan.status === 'Completed' || plan.status === 'Cancelled') return false;
        const todayStr = new Date().toISOString().split('T')[0];
        return String(plan.due_date).substring(0, 10) < todayStr;
    }

    function formatAuditLabel(log) {
        const actor = log.user?.initial || log.user?.name || 'System';
        if (log.action === 'CREATE') {
            return `${actor} membuat kunjungan ini`;
        }
        if (log.action === 'RESCHEDULE') {
            return `${actor} memindahkan tanggal dari ${log.old_values?.visit_date || '-'} ke ${log.new_values?.visit_date || '-'}`;
        }
        if (log.action === 'ACTION_PLAN_TOGGLE') {
            return `${actor} menandai action plan "${log.new_values?.task || ''}" sebagai ${log.new_values?.status || '-'}`;
        }
        if (log.action === 'STATUS_CHANGE') {
            return `${actor} mengubah status dari ${log.old_values?.status || '-'} menjadi ${log.new_values?.status || '-'}`;
        }
        return `${actor} memperbarui detail kunjungan`;
    }
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
                <div class="flex items-center justify-between gap-2 mt-2">
                    <p class="text-xs text-slate-400">
                        Dibuat pada {new Date(visit.created_at).toLocaleDateString('id-ID', { dateStyle: 'long' })}
                    </p>

                    <div class="flex items-center gap-1.5">
                        <a
                            href={buildGoogleCalendarUrl(visit)}
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md border border-slate-200 bg-white hover:bg-slate-50 text-[11px] font-medium text-slate-700 transition-colors"
                        >
                            <ExternalLink class="w-3 h-3" />
                            <span>Google Calendar</span>
                        </a>
                        <a
                            href="/visits/{visit.id}/calendar.ics"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md border border-slate-200 bg-white hover:bg-slate-50 text-[11px] font-medium text-slate-700 transition-colors"
                        >
                            <Download class="w-3 h-3" />
                            <span>.ics</span>
                        </a>
                    </div>
                </div>
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

            <!-- Action Plans (Interactive Checklist) -->
            {#if visit.action_plans && visit.action_plans.length > 0}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-xs font-semibold text-slate-900 uppercase tracking-wider">Action Plan</h4>
                        <span class="text-[10px] font-mono text-slate-400">
                            {visit.action_plans.filter(p => p.status === 'Completed').length}/{visit.action_plans.length} selesai
                        </span>
                    </div>
                    <div class="flex flex-col gap-2">
                        {#each visit.action_plans as plan}
                            {@const isDone = plan.status === 'Completed'}
                            {@const overdue = isPlanOverdue(plan)}
                            <button
                                type="button"
                                onclick={() => onToggleActionPlan?.(plan)}
                                class="w-full text-left flex items-start gap-2.5 p-2.5 border rounded-lg text-xs transition-colors cursor-pointer {isDone ? 'bg-emerald-50/40 border-emerald-200/70' : overdue ? 'bg-rose-50/40 border-rose-200' : 'bg-slate-50 border-slate-200 hover:bg-slate-100/70'}"
                            >
                                <span class="w-4 h-4 rounded border flex items-center justify-center shrink-0 mt-0.5 transition-colors {isDone ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-white border-slate-300'}">
                                    {#if isDone}
                                        <CheckCircle2 class="w-3 h-3" />
                                    {/if}
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium {isDone ? 'line-through text-slate-400' : 'text-slate-800'}">{plan.task_description}</p>
                                    <div class="flex flex-wrap items-center gap-2 mt-1 text-[10px] text-slate-400">
                                        <span>Owner: {plan.owner?.name || '-'}</span>
                                        <span>•</span>
                                        <span class={overdue ? 'text-rose-600 font-semibold' : ''}>
                                            Deadline: {plan.due_date || '-'}{overdue ? ' (Overdue)' : ''}
                                        </span>
                                        <span>•</span>
                                        <span>Prioritas: {plan.priority}</span>
                                    </div>
                                </div>
                            </button>
                        {/each}
                    </div>
                </div>
            {/if}

            <!-- Activity / Audit Log History (Notion Page Updates) -->
            {#if visit.audit_logs && visit.audit_logs.length > 0}
                <div class="border-t border-slate-100 pt-4">
                    <h4 class="text-xs font-semibold text-slate-900 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                        <History class="w-3.5 h-3.5 text-slate-400" />
                        <span>Riwayat Aktivitas</span>
                    </h4>
                    <div class="flex flex-col gap-2">
                        {#each visit.audit_logs as log}
                            <div class="flex items-start justify-between gap-2 text-[11px] text-slate-600 bg-slate-50/70 px-3 py-2 rounded-lg border border-slate-100">
                                <span>{formatAuditLabel(log)}</span>
                                <span class="text-[10px] text-slate-400 shrink-0">
                                    {new Date(log.created_at).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' })}
                                </span>
                            </div>
                        {/each}
                    </div>
                </div>
            {/if}
        </div>

        <!-- Drawer Footer -->
        <div class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    onclick={() => onEdit(visit)}
                    class="px-3.5 py-1.5 text-xs font-medium rounded-lg bg-slate-900 hover:bg-slate-800 text-white transition-colors cursor-pointer"
                >
                    Edit Visit
                </button>
                <button
                    type="button"
                    onclick={() => onDuplicate?.(visit)}
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-slate-200 rounded-lg bg-white hover:bg-slate-100 text-slate-700 transition-colors cursor-pointer"
                >
                    <Copy class="w-3.5 h-3.5" />
                    <span>Duplikat</span>
                </button>
            </div>
            <button
                type="button"
                onclick={onClose}
                class="px-3.5 py-1.5 text-xs font-medium border border-slate-200 rounded-lg bg-white hover:bg-slate-50 text-slate-700 transition-colors cursor-pointer"
            >
                Tutup
            </button>
        </div>
    </aside>
{/if}