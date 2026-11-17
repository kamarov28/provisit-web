<script>
    let {
        calendarDays = [],
        getVisitsForDate,
        onSelectVisit,
        statusColor
    } = $props();
</script>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-2xs flex-1 flex flex-col">
    <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50/75 text-[11px] font-semibold text-slate-500 uppercase tracking-wider py-2 text-center">
        <div>Senin</div>
        <div>Selasa</div>
        <div>Rabu</div>
        <div>Kamis</div>
        <div>Jumat</div>
        <div>Sabtu</div>
        <div>Minggu</div>
    </div>

    <div class="grid grid-cols-7 flex-1 auto-rows-fr divide-x divide-y divide-slate-100">
        {#each calendarDays as day}
            {@const dayVisits = getVisitsForDate(day.dateString)}
            <div class="min-h-28 p-2 flex flex-col gap-1 transition-colors hover:bg-slate-50/50 {day.isCurrentMonth ? 'bg-white' : 'bg-slate-50/40 text-slate-400'}">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-medium {day.isToday ? 'w-5 h-5 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold text-[11px]' : ''}">
                        {day.dayNumber}
                    </span>
                    {#if dayVisits.length > 0}
                        <span class="text-[10px] text-slate-400 font-medium">
                            {dayVisits.length} visit
                        </span>
                    {/if}
                </div>

                <div class="flex flex-col gap-1 overflow-y-auto max-h-24">
                    {#each dayVisits as visit}
                        <button
                            type="button"
                            onclick={() => onSelectVisit(visit)}
                            class="text-left px-2 py-1 rounded text-[11px] font-medium border truncate transition-all hover:scale-[1.02] shadow-2xs {statusColor(visit.status)}"
                        >
                            <div class="flex items-center gap-1">
                                {#if visit.start_time}
                                    <span class="opacity-75 text-[10px]">{visit.start_time.substring(0, 5)}</span>
                                {/if}
                                <span class="truncate">{visit.customer_name_snapshot}</span>
                            </div>
                        </button>
                    {/each}
                </div>
            </div>
        {/each}
    </div>
</div>