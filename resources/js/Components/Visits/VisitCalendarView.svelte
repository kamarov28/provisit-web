<script>
    import { Plus, SlidersHorizontal, X } from '@lucide/svelte';

    let {
        days = [],
        scale = 'month', // 'month' | 'week'
        getVisitsForDate,
        onSelectVisit,
        onQuickAdd,
        onRescheduleVisit,
        statusColor
    } = $props();

    let draggedVisit = $state(null);
    let dragOverDate = $state(null);

    // Overflow "+N lainnya" popover state
    let overflowDate = $state(null);

    // Notion-style Card Properties toggle state
    let isPropsOpen = $state(false);
    let cardProps = $state({
        showTime: true,
        showTitle: false,
        showSales: true,
        showPrincipal: true,
        showSupport: false,
        showStatus: false
    });

    const dayHeaders = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    const shortDayHeaders = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
    const shortMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    function formatShortDate(dateString) {
        if (!dateString) return '';
        const parts = dateString.split('-');
        if (parts.length !== 3) return dateString;
        const mIdx = parseInt(parts[1], 10) - 1;
        return `${parseInt(parts[2], 10)} ${shortMonths[mIdx] || parts[1]}`;
    }

    function canDrag(visit) {
        return !['Completed', 'Cancelled'].includes(visit.status);
    }

    function handleDragStart(e, visit) {
        if (!canDrag(visit)) {
            e.preventDefault();
            return;
        }
        draggedVisit = visit;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(visit.id));
    }

    function handleDragOver(e, dateString) {
        if (!draggedVisit) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        dragOverDate = dateString;
    }

    function handleDragLeave(dateString) {
        if (dragOverDate === dateString) {
            dragOverDate = null;
        }
    }

    function handleDrop(e, targetDate) {
        e.preventDefault();
        dragOverDate = null;

        if (!draggedVisit) return;
        const visit = draggedVisit;
        draggedVisit = null;

        if (visit.visit_date !== targetDate && onRescheduleVisit) {
            onRescheduleVisit(visit, targetDate);
        }
    }

    function handleDragEnd() {
        draggedVisit = null;
        dragOverDate = null;
    }

    function getPrimaryPrincipal(visit) {
        if (!visit.principal_reviews || visit.principal_reviews.length === 0) return '';
        const first = visit.principal_reviews[0].principal_name_snapshot || '';
        const extra = visit.principal_reviews.length > 1 ? ` +${visit.principal_reviews.length - 1}` : '';
        return first ? `${first}${extra}` : '';
    }
</script>

<div class="bg-white border border-slate-200 rounded-xl shadow-2xs flex-1 flex flex-col relative">
    <!-- Calendar Sub-header: Properties Toggle -->
    <div class="px-3.5 sm:px-4 py-2 border-b border-slate-100 bg-white rounded-t-xl flex items-center justify-between gap-2 text-xs">
        <div class="text-[11px] text-slate-400 truncate">
            {#if scale === 'week'}
                <span class="hidden sm:inline">Tampilan Mingguan • Seret kartu untuk pindah hari</span>
                <span class="sm:hidden">Mingguan • Geser horizontal untuk lihat hari</span>
            {:else}
                <span class="hidden sm:inline">Tampilan Bulanan • Hover tanggal untuk <span class="font-semibold text-slate-600">+</span> atau seret kartu untuk reschedule</span>
                <span class="sm:hidden">Bulanan • Tap <span class="font-semibold text-slate-600">+</span> untuk tambah visit</span>
            {/if}
        </div>

        <div class="relative shrink-0">
            <button
                type="button"
                onclick={() => isPropsOpen = !isPropsOpen}
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border text-[11px] font-medium transition-colors cursor-pointer {isPropsOpen ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'}"
            >
                <SlidersHorizontal class="w-3 h-3" />
                <span>Properties</span>
            </button>

            {#if isPropsOpen}
                <button
                    type="button"
                    class="fixed inset-0 z-20 w-full h-full cursor-default"
                    onclick={() => isPropsOpen = false}
                    aria-label="Tutup menu properties"
                ></button>

                <div class="absolute right-0 mt-1.5 w-60 rounded-xl border border-slate-200 bg-white shadow-xl p-3 z-30 flex flex-col gap-2">
                    <div class="flex items-center justify-between pb-1.5 border-b border-slate-100">
                        <span class="text-[11px] font-bold text-slate-800 uppercase tracking-wider">Properti Kartu Kalender</span>
                        <button type="button" onclick={() => isPropsOpen = false} class="text-slate-400 hover:text-slate-700">
                            <X class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <label class="flex items-center justify-between py-1 cursor-pointer select-none">
                        <span class="text-xs text-slate-700">Jam Mulai</span>
                        <input type="checkbox" bind:checked={cardProps.showTime} class="rounded border-slate-300 text-slate-900 focus:ring-slate-500" />
                    </label>

                    <label class="flex items-center justify-between py-1 cursor-pointer select-none">
                        <span class="text-xs text-slate-700">Judul Kunjungan</span>
                        <input type="checkbox" bind:checked={cardProps.showTitle} class="rounded border-slate-300 text-slate-900 focus:ring-slate-500" />
                    </label>

                    <label class="flex items-center justify-between py-1 cursor-pointer select-none">
                        <span class="text-xs text-slate-700">Inisial PIC Sales</span>
                        <input type="checkbox" bind:checked={cardProps.showSales} class="rounded border-slate-300 text-slate-900 focus:ring-slate-500" />
                    </label>

                    <label class="flex items-center justify-between py-1 cursor-pointer select-none">
                        <span class="text-xs text-slate-700">Principal</span>
                        <input type="checkbox" bind:checked={cardProps.showPrincipal} class="rounded border-slate-300 text-slate-900 focus:ring-slate-500" />
                    </label>

                    <label class="flex items-center justify-between py-1 cursor-pointer select-none">
                        <span class="text-xs text-slate-700">Support Marketing</span>
                        <input type="checkbox" bind:checked={cardProps.showSupport} class="rounded border-slate-300 text-slate-900 focus:ring-slate-500" />
                    </label>

                    <label class="flex items-center justify-between py-1 cursor-pointer select-none">
                        <span class="text-xs text-slate-700">Badge Status</span>
                        <input type="checkbox" bind:checked={cardProps.showStatus} class="rounded border-slate-300 text-slate-900 focus:ring-slate-500" />
                    </label>
                </div>
            {/if}
        </div>
    </div>

    <!-- Scrollable Calendar Wrapper for Mobile -->
    <div class="overflow-x-auto flex-1 flex flex-col rounded-b-xl">
        <div class="min-w-[760px] md:min-w-0 flex-1 flex flex-col">
            <!-- Day of Week Header -->
            <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50/75 text-[11px] font-semibold text-slate-500 uppercase tracking-wider py-2 text-center">
                {#if scale === 'week' && days.length === 7}
                    {#each days as day, idx}
                        <div class="flex flex-col items-center gap-0.5 {day.isToday ? 'text-slate-900' : ''}">
                            <span class="hidden sm:inline">{dayHeaders[idx]}</span>
                            <span class="sm:hidden">{shortDayHeaders[idx]}</span>
                            <span class="text-[10px] font-normal {day.isToday ? 'px-2 py-0.5 rounded-full bg-slate-900 text-white font-semibold' : 'text-slate-400'}">
                                {formatShortDate(day.dateString)}
                            </span>
                        </div>
                    {/each}
                {:else}
                    {#each dayHeaders as headerName, idx}
                        <div>
                            <span class="hidden sm:inline">{headerName}</span>
                            <span class="sm:hidden">{shortDayHeaders[idx]}</span>
                        </div>
                    {/each}
                {/if}
            </div>

            <!-- Calendar Grid -->
            <div class="grid grid-cols-7 flex-1 auto-rows-fr divide-x divide-y divide-slate-100">
                {#each days as day}
                    {@const dayVisits = getVisitsForDate(day.dateString)}
                    {@const maxVisible = scale === 'week' ? 12 : 3}
                    {@const visibleVisits = dayVisits.slice(0, maxVisible)}
                    {@const hiddenCount = Math.max(0, dayVisits.length - maxVisible)}
                    {@const isDropTarget = dragOverDate === day.dateString && draggedVisit?.visit_date !== day.dateString}

                    <div
                        role="region"
                        aria-label="Tanggal {day.dateString}"
                        ondragover={(e) => handleDragOver(e, day.dateString)}
                        ondragleave={() => handleDragLeave(day.dateString)}
                        ondrop={(e) => handleDrop(e, day.dateString)}
                        class="group relative {scale === 'week' ? 'min-h-80 sm:min-h-96' : 'min-h-28 sm:min-h-32'} p-1.5 sm:p-2 flex flex-col gap-1.5 transition-colors {isDropTarget ? 'bg-blue-50/80 ring-2 ring-inset ring-blue-400' : day.isCurrentMonth || scale === 'week' ? 'bg-white hover:bg-slate-50/60' : 'bg-slate-50/40 text-slate-400 hover:bg-slate-100/50'}"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                {#if scale === 'month'}
                                    <span class="text-xs font-medium {day.isToday ? 'w-5 h-5 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold text-[11px]' : ''}">
                                        {day.dayNumber}
                                    </span>
                                {/if}
                                {#if dayVisits.length > 0}
                                    <span class="text-[10px] text-slate-400 font-medium">
                                        {dayVisits.length} visit
                                    </span>
                                {/if}
                            </div>

                            <button
                                type="button"
                                onclick={() => onQuickAdd?.(day.dateString)}
                                title="Catat visit pada {day.dateString}"
                                class="opacity-75 sm:opacity-0 sm:group-hover:opacity-100 focus:opacity-100 w-5 h-5 rounded border border-slate-200 bg-white hover:bg-slate-900 hover:text-white hover:border-slate-900 text-slate-500 flex items-center justify-center transition-all shadow-2xs cursor-pointer"
                            >
                                <Plus class="w-3 h-3" />
                            </button>
                        </div>

                        <!-- Visit Chips -->
                        <div class="flex flex-col gap-1">
                            {#each visibleVisits as visit}
                                {@const draggable = canDrag(visit)}
                                {@const principalLabel = cardProps.showPrincipal ? getPrimaryPrincipal(visit) : ''}
                                <button
                                    type="button"
                                    {draggable}
                                    ondragstart={(e) => handleDragStart(e, visit)}
                                    ondragend={handleDragEnd}
                                    onclick={() => onSelectVisit(visit)}
                                    title="{visit.visit_code} • {visit.title} ({visit.status}){draggable ? ' — Seret untuk pindah tanggal' : ''}"
                                    class="text-left px-2 py-1.5 rounded-md text-[11px] font-medium border transition-all hover:scale-[1.01] shadow-2xs flex flex-col gap-0.5 {draggable ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer'} {draggedVisit?.id === visit.id ? 'opacity-40' : ''} {statusColor(visit.status)}"
                                >
                                    <div class="flex items-center justify-between gap-1">
                                        <div class="flex items-center gap-1 min-w-0">
                                            {#if cardProps.showTime && visit.start_time}
                                                <span class="opacity-75 text-[10px] font-mono shrink-0">{visit.start_time.substring(0, 5)}</span>
                                            {/if}
                                            <span class="truncate font-semibold">{visit.customer_name_snapshot}</span>
                                        </div>
                                        {#if cardProps.showSales && visit.primary_sales_user?.initial}
                                            <span class="text-[9px] font-mono font-semibold px-1 rounded bg-white/75 border border-current/15 shrink-0">
                                                {visit.primary_sales_user.initial}
                                            </span>
                                        {/if}
                                    </div>

                                    {#if cardProps.showTitle && visit.title}
                                        <span class="text-[10px] opacity-85 truncate">{visit.title}</span>
                                    {/if}

                                    {#if principalLabel || (cardProps.showSupport && visit.support_marketing?.length > 0) || cardProps.showStatus}
                                        <div class="flex flex-wrap items-center gap-1 mt-0.5">
                                            {#if principalLabel}
                                                <span class="text-[9px] px-1 py-0.2 rounded bg-white/70 text-slate-700 truncate max-w-full">
                                                    {principalLabel}
                                                </span>
                                            {/if}
                                            {#if cardProps.showSupport && visit.support_marketing?.length > 0}
                                                <span class="text-[9px] px-1 py-0.2 rounded bg-white/70 font-mono text-slate-600">
                                                    MKT: {visit.support_marketing.join(',')}
                                                </span>
                                            {/if}
                                            {#if cardProps.showStatus}
                                                <span class="text-[9px] px-1 py-0.2 rounded bg-white/80 font-semibold">
                                                    {visit.status}
                                                </span>
                                            {/if}
                                        </div>
                                    {/if}
                                </button>
                            {/each}

                            {#if hiddenCount > 0}
                                <button
                                    type="button"
                                    onclick={() => overflowDate = day.dateString}
                                    class="text-left px-2 py-1 rounded text-[11px] font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors cursor-pointer"
                                >
                                    +{hiddenCount} lainnya
                                </button>
                            {/if}
                        </div>

                        <!-- "+N lainnya" Popover -->
                        {#if overflowDate === day.dateString}
                            <button
                                type="button"
                                class="fixed inset-0 z-30 w-full h-full bg-slate-900/20 sm:bg-transparent cursor-default"
                                onclick={() => overflowDate = null}
                                aria-label="Tutup daftar tanggal"
                            ></button>

                            <div class="fixed inset-x-4 top-1/4 mx-auto max-w-xs sm:max-w-none sm:mx-0 sm:inset-auto sm:left-1 sm:top-8 z-40 sm:w-64 rounded-xl border border-slate-200 bg-white shadow-2xl p-3 flex flex-col gap-2">
                                <div class="flex items-center justify-between pb-1.5 border-b border-slate-100">
                                    <span class="text-xs font-bold text-slate-900">{formatShortDate(day.dateString)} ({dayVisits.length} Visit)</span>
                                    <button type="button" onclick={() => overflowDate = null} class="text-slate-400 hover:text-slate-700">
                                        <X class="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                <div class="flex flex-col gap-1.5 max-h-60 overflow-y-auto">
                                    {#each dayVisits as visit}
                                        <button
                                            type="button"
                                            onclick={() => { overflowDate = null; onSelectVisit(visit); }}
                                            class="text-left px-2.5 py-1.5 rounded-lg text-[11px] font-medium border transition-colors hover:brightness-95 {statusColor(visit.status)}"
                                        >
                                            <div class="flex items-center justify-between gap-1">
                                                <span class="truncate font-semibold">
                                                    {visit.start_time ? `${visit.start_time.substring(0, 5)} • ` : ''}{visit.customer_name_snapshot}
                                                </span>
                                                {#if visit.primary_sales_user?.initial}
                                                    <span class="text-[9px] font-mono px-1 rounded bg-white/75 shrink-0">{visit.primary_sales_user.initial}</span>
                                                {/if}
                                            </div>
                                            <span class="block text-[10px] opacity-80 truncate mt-0.5">{visit.title}</span>
                                        </button>
                                    {/each}
                                </div>

                                <button
                                    type="button"
                                    onclick={() => { overflowDate = null; onQuickAdd?.(day.dateString); }}
                                    class="w-full mt-1 py-1.5 px-2.5 rounded-lg border border-dashed border-slate-300 hover:border-slate-900 hover:text-slate-900 text-[11px] font-medium text-slate-600 flex items-center justify-center gap-1 transition-colors cursor-pointer"
                                >
                                    <Plus class="w-3 h-3" />
                                    <span>Tambah Visit</span>
                                </button>
                            </div>
                        {/if}
                    </div>
                {/each}
            </div>
        </div>
    </div>
</div>
