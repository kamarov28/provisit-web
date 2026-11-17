<script>
    import { Building2 } from '@lucide/svelte';

    let {
        visits = [],
        onSelectVisit,
        statusColor
    } = $props();

    const columns = ['Planned', 'Confirmed', 'In Progress', 'Completed'];
</script>

<div class="grid grid-cols-4 gap-4 flex-1 items-start">
    {#each columns as columnStatus}
        {@const statusVisits = visits.filter(v => v.status === columnStatus)}
        <div class="bg-slate-100/70 rounded-xl p-3 border border-slate-200/80 flex flex-col gap-2.5">
            <div class="flex items-center justify-between px-1">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-800">{columnStatus}</span>
                    <span class="text-[11px] px-1.5 py-0.5 rounded-full bg-slate-200 text-slate-700 font-bold">
                        {statusVisits.length}
                    </span>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                {#each statusVisits as visit}
                    <div
                        role="button"
                        tabindex="0"
                        onclick={() => onSelectVisit(visit)}
                        onkeydown={(e) => e.key === 'Enter' && onSelectVisit(visit)}
                        class="bg-white border border-slate-200 rounded-lg p-3.5 shadow-2xs hover:border-slate-300 transition-all cursor-pointer flex flex-col gap-2"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-mono text-slate-400">{visit.visit_code}</span>
                            <span class="text-[10px] px-2 py-0.5 rounded font-medium border {statusColor(visit.status)}">
                                {visit.status}
                            </span>
                        </div>
                        <h4 class="text-xs font-semibold text-slate-900 leading-snug line-clamp-2">
                            {visit.title}
                        </h4>
                        <div class="flex items-center gap-1.5 text-[11px] text-slate-500">
                            <Building2 class="w-3.5 h-3.5 shrink-0 text-slate-400" />
                            <span class="truncate">{visit.customer_name_snapshot}</span>
                        </div>
                        <div class="flex items-center justify-between pt-1 border-t border-slate-100 text-[10px] text-slate-400">
                            <span>{visit.visit_date}</span>
                            <span>{visit.primary_sales_user?.name || 'Sales'}</span>
                        </div>
                    </div>
                {:else}
                    <div class="p-4 text-center text-xs text-slate-400 border border-dashed border-slate-200 rounded-lg">
                        Tidak ada visit
                    </div>
                {/each}
            </div>
        </div>
    {/each}
</div>