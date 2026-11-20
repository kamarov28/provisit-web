<script>
    import { Building2 } from '@lucide/svelte';

    let {
        visits = [],
        onSelectVisit,
        onStatusChange,
        statusColor
    } = $props();

    const columns = [
        { key: 'Draft', label: 'Draft' },
        { key: 'Planned', label: 'Planned' },
        { key: 'Confirmed', label: 'Confirmed' },
        { key: 'Completed', label: 'Completed' }
    ];

    const allStatuses = ['Draft', 'Planned', 'Confirmed', 'Completed', 'Cancelled'];

    let draggedVisit = $state(null);
    let dragOverColumn = $state(null);

    function handleDragStart(e, visit) {
        draggedVisit = visit;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(visit.id));
    }

    function handleDragOver(e, columnKey) {
        if (!draggedVisit) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        dragOverColumn = columnKey;
    }

    function handleDragLeave(columnKey) {
        if (dragOverColumn === columnKey) {
            dragOverColumn = null;
        }
    }

    function handleDrop(e, targetStatus) {
        e.preventDefault();
        dragOverColumn = null;

        if (!draggedVisit) return;
        const visit = draggedVisit;
        draggedVisit = null;

        if (visit.status !== targetStatus && onStatusChange) {
            onStatusChange(visit, targetStatus);
        }
    }

    function handleDragEnd() {
        draggedVisit = null;
        dragOverColumn = null;
    }
</script>

<div class="flex overflow-x-auto snap-x snap-mandatory gap-3 pb-2 lg:grid lg:grid-cols-4 lg:gap-4 lg:overflow-visible lg:pb-0 flex-1 items-start">
    {#each columns as col}
        {@const statusVisits = visits.filter(v => v.status === col.key)}
        {@const isDropTarget = dragOverColumn === col.key && draggedVisit?.status !== col.key}
        <div
            role="region"
            aria-label="Kolom {col.label}"
            ondragover={(e) => handleDragOver(e, col.key)}
            ondragleave={() => handleDragLeave(col.key)}
            ondrop={(e) => handleDrop(e, col.key)}
            class="min-w-[275px] sm:min-w-[300px] lg:min-w-0 snap-start rounded-xl p-3 border flex flex-col gap-2.5 min-h-80 lg:min-h-96 transition-colors {isDropTarget ? 'bg-blue-50/70 border-blue-400 ring-2 ring-blue-300/60' : 'bg-slate-100/70 border-slate-200/80'}"
        >
            <div class="flex items-center justify-between px-1">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-800">{col.label}</span>
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
                        draggable="true"
                        ondragstart={(e) => handleDragStart(e, visit)}
                        ondragend={handleDragEnd}
                        onclick={() => onSelectVisit(visit)}
                        onkeydown={(e) => e.key === 'Enter' && onSelectVisit(visit)}
                        title="Seret kartu untuk mengubah status"
                        class="bg-white border border-slate-200 rounded-lg p-3.5 shadow-2xs hover:border-slate-300 transition-all cursor-grab active:cursor-grabbing flex flex-col gap-2 {draggedVisit?.id === visit.id ? 'opacity-40' : ''}"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-mono text-slate-400">{visit.visit_code}</span>

                            <!-- Desktop Static Status Badge -->
                            <span class="hidden lg:inline-block text-[10px] px-2 py-0.5 rounded font-medium border {statusColor(visit.status)}">
                                {visit.status}
                            </span>

                            <!-- Mobile/Tablet Touch Status Switcher -->
                            <div class="lg:hidden" onclick={(e) => e.stopPropagation()} onkeydown={(e) => e.stopPropagation()}>
                                <select
                                    value={visit.status}
                                    aria-label="Pindah status {visit.visit_code}"
                                    onchange={(e) => {
                                        const nextVal = e.currentTarget.value;
                                        if (nextVal && nextVal !== visit.status) {
                                            onStatusChange?.(visit, nextVal);
                                            e.currentTarget.value = visit.status;
                                        }
                                    }}
                                    class="text-[10px] px-1.5 py-0.5 rounded font-medium border outline-none cursor-pointer {statusColor(visit.status)}"
                                >
                                    {#each allStatuses as st}
                                        <option value={st} class="bg-white text-slate-800">{st}</option>
                                    {/each}
                                </select>
                            </div>
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
                            <span>{visit.primary_sales_user?.initial || visit.primary_sales_user?.name || 'Sales'}</span>
                        </div>
                    </div>
                {:else}
                    <div class="p-6 text-center text-xs text-slate-400 border border-dashed border-slate-200 rounded-lg">
                        Seret visit ke sini
                    </div>
                {/each}
            </div>
        </div>
    {/each}
</div>
