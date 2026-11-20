<script>
    import { Building2, User as UserIcon, Calendar as CalendarIcon } from '@lucide/svelte';

    let {
        visits = [],
        onSelectVisit,
        onRescheduleVisit,
        onStatusChange,
        statusColor
    } = $props();

    const statuses = ['Draft', 'Planned', 'Confirmed', 'Completed', 'Cancelled'];

    const timeOptions = [];
    for (let h = 0; h < 24; h++) {
        for (let m of [0, 15, 30, 45]) {
            timeOptions.push(`${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`);
        }
    }

    function isScheduleLocked(visit) {
        return ['Completed', 'Cancelled'].includes(visit.status);
    }
</script>

<!-- Mobile Card View (< md) -->
<div class="md:hidden flex flex-col gap-3">
    {#each visits as visit}
        {@const locked = isScheduleLocked(visit)}
        {@const currentStartTime = visit.start_time ? String(visit.start_time).substring(0, 5) : '10:00'}
        <div
            role="button"
            tabindex="0"
            onclick={() => onSelectVisit(visit)}
            onkeydown={(e) => e.key === 'Enter' && onSelectVisit(visit)}
            class="bg-white border border-slate-200 rounded-xl p-3.5 shadow-2xs flex flex-col gap-2.5 active:bg-slate-50/80 transition-colors"
        >
            <!-- Top Row: Visit Code & Inline Status Dropdown -->
            <div class="flex items-center justify-between gap-2">
                <span class="font-mono text-[11px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                    {visit.visit_code}
                </span>

                <div onclick={(e) => e.stopPropagation()} onkeydown={(e) => e.stopPropagation()}>
                    <select
                        value={visit.status}
                        aria-label="Ubah status {visit.visit_code}"
                        onchange={(e) => {
                            const nextVal = e.currentTarget.value;
                            if (nextVal && nextVal !== visit.status) {
                                onStatusChange?.(visit, nextVal);
                                e.currentTarget.value = visit.status;
                            }
                        }}
                        class="text-[11px] px-2 py-1 rounded-md font-medium border outline-none cursor-pointer {statusColor(visit.status)}"
                    >
                        {#each statuses as st}
                            <option value={st} class="bg-white text-slate-800">{st}</option>
                        {/each}
                    </select>
                </div>
            </div>

            <!-- Middle Row: Customer, Title & PIC -->
            <div>
                <div class="flex items-center gap-1.5 text-xs font-bold text-slate-900">
                    <Building2 class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                    <span class="truncate">{visit.customer_name_snapshot}</span>
                </div>
                <p class="text-xs text-slate-700 mt-0.5 line-clamp-2">{visit.title}</p>
                {#if visit.pic_name_snapshot}
                    <div class="flex items-center gap-1 text-[11px] text-slate-500 mt-1">
                        <UserIcon class="w-3 h-3 text-slate-400 shrink-0" />
                        <span class="truncate">
                            {visit.pic_name_snapshot}{visit.pic_position_snapshot ? ` (${visit.pic_position_snapshot})` : ''}
                        </span>
                    </div>
                {/if}
            </div>

            <!-- Bottom Row: Inline Date/Time & Sales PIC -->
            <div
                class="pt-2 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-[11px]"
                onclick={(e) => e.stopPropagation()}
                onkeydown={(e) => e.stopPropagation()}
            >
                {#if locked}
                    <div class="flex items-center gap-1.5 text-slate-600">
                        <CalendarIcon class="w-3.5 h-3.5 text-slate-400" />
                        <span>{visit.visit_date} • {currentStartTime} WIB</span>
                    </div>
                {:else}
                    <div class="flex items-center gap-1.5">
                        <input
                            type="date"
                            value={visit.visit_date}
                            aria-label="Tanggal kunjungan"
                            onchange={(e) => {
                                const val = e.currentTarget.value;
                                if (val && val !== visit.visit_date) {
                                    onRescheduleVisit?.(visit, val, currentStartTime);
                                }
                            }}
                            class="px-1.5 py-1 border border-slate-200 rounded bg-slate-50 text-[11px] text-slate-700 outline-none focus:border-slate-400"
                        />
                        <select
                            value={currentStartTime}
                            aria-label="Jam kunjungan"
                            onchange={(e) => {
                                const val = e.currentTarget.value;
                                if (val && val !== currentStartTime) {
                                    onRescheduleVisit?.(visit, visit.visit_date, val);
                                }
                            }}
                            class="px-1.5 py-1 border border-slate-200 rounded bg-slate-50 text-[11px] text-slate-600 outline-none focus:border-slate-400"
                        >
                            {#if !timeOptions.includes(currentStartTime)}
                                <option value={currentStartTime}>{currentStartTime}</option>
                            {/if}
                            {#each timeOptions as t}
                                <option value={t}>{t}</option>
                            {/each}
                        </select>
                    </div>
                {/if}

                <span class="inline-flex items-center gap-1 text-slate-600 font-medium ml-auto">
                    {visit.primary_sales_user?.initial || visit.primary_sales_user?.name || '-'}
                    {#if visit.primary_sales_user?.team}
                        <span class="text-[9px] px-1.5 py-0.5 bg-slate-100 rounded text-slate-500 border border-slate-200">
                            {visit.primary_sales_user.team}
                        </span>
                    {/if}
                </span>
            </div>
        </div>
    {:else}
        <div class="bg-white border border-slate-200 rounded-xl p-8 text-center text-xs text-slate-400">
            Belum ada data kunjungan yang cocok.
        </div>
    {/each}
</div>

<!-- Desktop & Tablet Table View (>= md) -->
<div class="hidden md:block bg-white border border-slate-200 rounded-xl overflow-x-auto shadow-2xs">
    <table class="w-full text-left border-collapse text-xs">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold uppercase text-[10px] tracking-wider">
                <th class="py-3 px-4">Kode</th>
                <th class="py-3 px-4">Tanggal & Jam</th>
                <th class="py-3 px-4">Customer</th>
                <th class="py-3 px-4">PIC</th>
                <th class="py-3 px-4">Agenda / Judul</th>
                <th class="py-3 px-4">Sales</th>
                <th class="py-3 px-4">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            {#each visits as visit}
                {@const locked = isScheduleLocked(visit)}
                {@const currentStartTime = visit.start_time ? String(visit.start_time).substring(0, 5) : '10:00'}
                <tr
                    onclick={() => onSelectVisit(visit)}
                    class="hover:bg-slate-50/80 cursor-pointer transition-colors"
                >
                    <td class="py-3 px-4 font-mono font-medium text-slate-700 whitespace-nowrap">{visit.visit_code}</td>

                    <!-- Inline Quick-Edit Tanggal & Jam -->
                    <td class="py-2.5 px-4 text-slate-600" onclick={(e) => e.stopPropagation()}>
                        {#if locked}
                            <div class="text-xs text-slate-700">{visit.visit_date}</div>
                            <div class="text-[10px] text-slate-400">{currentStartTime} WIB</div>
                        {:else}
                            <div class="flex items-center gap-1.5">
                                <input
                                    type="date"
                                    value={visit.visit_date}
                                    onchange={(e) => {
                                        const val = e.currentTarget.value;
                                        if (val && val !== visit.visit_date) {
                                            onRescheduleVisit?.(visit, val, currentStartTime);
                                        }
                                    }}
                                    class="px-1.5 py-1 border border-transparent hover:border-slate-200 focus:border-slate-400 rounded bg-transparent hover:bg-white text-[11px] text-slate-700 outline-none cursor-pointer transition-colors"
                                />
                                <select
                                    value={currentStartTime}
                                    onchange={(e) => {
                                        const val = e.currentTarget.value;
                                        if (val && val !== currentStartTime) {
                                            onRescheduleVisit?.(visit, visit.visit_date, val);
                                        }
                                    }}
                                    class="px-1 py-1 border border-transparent hover:border-slate-200 focus:border-slate-400 rounded bg-transparent hover:bg-white text-[11px] text-slate-500 outline-none cursor-pointer transition-colors"
                                >
                                    {#if !timeOptions.includes(currentStartTime)}
                                        <option value={currentStartTime}>{currentStartTime}</option>
                                    {/if}
                                    {#each timeOptions as t}
                                        <option value={t}>{t}</option>
                                    {/each}
                                </select>
                            </div>
                        {/if}
                    </td>

                    <td class="py-3 px-4 font-semibold text-slate-900">{visit.customer_name_snapshot}</td>
                    <td class="py-3 px-4 text-slate-600">
                        <div>{visit.pic_name_snapshot || '-'}</div>
                        <div class="text-[10px] text-slate-400">{visit.pic_position_snapshot || ''}</div>
                    </td>
                    <td class="py-3 px-4 text-slate-700 max-w-xs truncate">{visit.title}</td>
                    <td class="py-3 px-4 text-slate-600 whitespace-nowrap">
                        <span class="inline-flex items-center gap-1">
                            {visit.primary_sales_user?.name || '-'}
                            {#if visit.primary_sales_user?.team}
                                <span class="text-[9px] px-1 bg-slate-100 rounded text-slate-500 border border-slate-200">{visit.primary_sales_user.team}</span>
                            {/if}
                        </span>
                    </td>

                    <!-- Inline Quick-Edit Status -->
                    <td class="py-2.5 px-4" onclick={(e) => e.stopPropagation()}>
                        <select
                            value={visit.status}
                            onchange={(e) => {
                                const nextVal = e.currentTarget.value;
                                if (nextVal && nextVal !== visit.status) {
                                    onStatusChange?.(visit, nextVal);
                                    e.currentTarget.value = visit.status;
                                }
                            }}
                            class="text-[11px] px-2 py-1 rounded-md font-medium border outline-none cursor-pointer transition-colors {statusColor(visit.status)}"
                        >
                            {#each statuses as st}
                                <option value={st} class="bg-white text-slate-800">{st}</option>
                            {/each}
                        </select>
                    </td>
                </tr>
            {:else}
                <tr>
                    <td colspan="7" class="py-8 text-center text-slate-400">
                        Belum ada data kunjungan yang cocok.
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</div>