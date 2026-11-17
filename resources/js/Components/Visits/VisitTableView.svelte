<script>
    let {
        visits = [],
        onSelectVisit,
        statusColor
    } = $props();
</script>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
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
                <tr
                    onclick={() => onSelectVisit(visit)}
                    class="hover:bg-slate-50/80 cursor-pointer transition-colors"
                >
                    <td class="py-3 px-4 font-mono font-medium text-slate-700">{visit.visit_code}</td>
                    <td class="py-3 px-4 text-slate-600">
                        <div>{visit.visit_date}</div>
                        <div class="text-[10px] text-slate-400">{visit.start_time || '-'}</div>
                    </td>
                    <td class="py-3 px-4 font-semibold text-slate-900">{visit.customer_name_snapshot}</td>
                    <td class="py-3 px-4 text-slate-600">
                        <div>{visit.pic_name_snapshot || '-'}</div>
                        <div class="text-[10px] text-slate-400">{visit.pic_position_snapshot || ''}</div>
                    </td>
                    <td class="py-3 px-4 text-slate-700 max-w-xs truncate">{visit.title}</td>
                    <td class="py-3 px-4 text-slate-600">
                        <span class="inline-flex items-center gap-1">
                            {visit.primary_sales_user?.name || '-'}
                            {#if visit.primary_sales_user?.team}
                                <span class="text-[9px] px-1 bg-slate-100 rounded text-slate-500 border border-slate-200">{visit.primary_sales_user.team}</span>
                            {/if}
                        </span>
                    </td>
                    <td class="py-3 px-4">
                        <span class="text-[10px] px-2 py-0.5 rounded font-medium border {statusColor(visit.status)}">
                            {visit.status}
                        </span>
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