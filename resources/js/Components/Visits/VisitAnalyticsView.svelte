<script>
    import { TrendingUp, Users, Layers, Award, CheckCircle2 } from '@lucide/svelte';

    let {
        visits = []
    } = $props();

    const opportunityOrder = [
        { key: 'No Signal', color: 'bg-slate-400' },
        { key: 'Interested', color: 'bg-sky-500' },
        { key: 'Sample', color: 'bg-indigo-500' },
        { key: 'Demo', color: 'bg-violet-500' },
        { key: 'Quotation', color: 'bg-amber-500' },
        { key: 'Negotiation', color: 'bg-orange-500' },
        { key: 'Won', color: 'bg-emerald-600' },
        { key: 'Lost', color: 'bg-rose-500' }
    ];

    let metrics = $derived.by(() => {
        const activeVisits = visits.filter(v => v.status !== 'Cancelled');
        const completedCount = activeVisits.filter(v => v.status === 'Completed').length;
        const completionRate = activeVisits.length > 0
            ? Math.round((completedCount / activeVisits.length) * 100)
            : 0;

        let totalReviews = 0;
        let hotOpportunities = 0; // Quotation, Negotiation, Won
        let wonCount = 0;

        const oppCounts = {};
        const principalMap = new Map();
        const salesMap = new Map();
        const teamMap = new Map();

        for (const v of activeVisits) {
            // Sales & Team aggregation
            const salesName = v.primary_sales_user?.name || 'Unassigned';
            const salesInit = v.primary_sales_user?.initial || '-';
            const teamName = v.primary_sales_user?.team || 'Lainnya';
            const salesKey = `${salesInit}|${salesName}`;

            if (!salesMap.has(salesKey)) {
                salesMap.set(salesKey, {
                    initial: salesInit,
                    name: salesName,
                    team: teamName,
                    total: 0,
                    completed: 0,
                    planned: 0
                });
            }
            const sEntry = salesMap.get(salesKey);
            sEntry.total++;
            if (v.status === 'Completed') sEntry.completed++;
            if (['Draft', 'Planned', 'Confirmed'].includes(v.status)) sEntry.planned++;

            if (!teamMap.has(teamName)) {
                teamMap.set(teamName, { team: teamName, total: 0, completed: 0 });
            }
            const tEntry = teamMap.get(teamName);
            tEntry.total++;
            if (v.status === 'Completed') tEntry.completed++;

            // Principal Reviews aggregation
            for (const rev of v.principal_reviews || []) {
                totalReviews++;
                const opp = rev.opportunity_status || 'No Signal';
                oppCounts[opp] = (oppCounts[opp] || 0) + 1;

                if (['Quotation', 'Negotiation', 'Won'].includes(opp)) hotOpportunities++;
                if (opp === 'Won') wonCount++;

                const pName = rev.principal_name_snapshot || 'Unknown Principal';
                if (!principalMap.has(pName)) {
                    principalMap.set(pName, {
                        name: pName,
                        reviews: 0,
                        demos: 0,
                        quotationsOrWon: 0
                    });
                }
                const pEntry = principalMap.get(pName);
                pEntry.reviews++;
                if (rev.exposure_type === 'Demo' || opp === 'Demo') pEntry.demos++;
                if (['Quotation', 'Negotiation', 'Won'].includes(opp)) pEntry.quotationsOrWon++;
            }
        }

        const salesLeaderboard = Array.from(salesMap.values()).sort((a, b) => b.total - a.total);
        const teamBreakdown = Array.from(teamMap.values()).sort((a, b) => b.total - a.total);
        const topPrincipals = Array.from(principalMap.values()).sort((a, b) => b.reviews - a.reviews);

        return {
            activeTotal: activeVisits.length,
            completedCount,
            completionRate,
            totalReviews,
            hotOpportunities,
            wonCount,
            oppCounts,
            salesLeaderboard,
            teamBreakdown,
            topPrincipals
        };
    });
</script>

<div class="flex flex-col gap-5">
    <!-- Top KPI Cards -->
    <div class="grid grid-cols-4 gap-4">
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs">
            <div class="flex items-center justify-between text-slate-400">
                <span class="text-[11px] font-semibold uppercase tracking-wider">Completion Rate</span>
                <CheckCircle2 class="w-4 h-4 text-emerald-600" />
            </div>
            <p class="text-2xl font-bold text-slate-900 mt-1.5">{metrics.completionRate}%</p>
            <p class="text-[11px] text-slate-500 mt-1">{metrics.completedCount} dari {metrics.activeTotal} visit aktif selesai</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs">
            <div class="flex items-center justify-between text-slate-400">
                <span class="text-[11px] font-semibold uppercase tracking-wider">Total Principal Review</span>
                <Layers class="w-4 h-4 text-blue-600" />
            </div>
            <p class="text-2xl font-bold text-slate-900 mt-1.5">{metrics.totalReviews}</p>
            <p class="text-[11px] text-slate-500 mt-1">Rata-rata {metrics.activeTotal > 0 ? (metrics.totalReviews / metrics.activeTotal).toFixed(1) : '0.0'} principal / visit</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs">
            <div class="flex items-center justify-between text-slate-400">
                <span class="text-[11px] font-semibold uppercase tracking-wider">Pipeline Lanjut</span>
                <TrendingUp class="w-4 h-4 text-amber-500" />
            </div>
            <p class="text-2xl font-bold text-slate-900 mt-1.5">{metrics.hotOpportunities}</p>
            <p class="text-[11px] text-slate-500 mt-1">Quotation, Negotiation & Won</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs">
            <div class="flex items-center justify-between text-slate-400">
                <span class="text-[11px] font-semibold uppercase tracking-wider">Deal Won</span>
                <Award class="w-4 h-4 text-emerald-600" />
            </div>
            <p class="text-2xl font-bold text-emerald-700 mt-1.5">{metrics.wonCount}</p>
            <p class="text-[11px] text-slate-500 mt-1">Konversi {metrics.totalReviews > 0 ? Math.round((metrics.wonCount / metrics.totalReviews) * 100) : 0}% dari total review</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-5">
        <!-- Opportunity Pipeline Distribution -->
        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-2xs flex flex-col gap-3.5">
            <div>
                <h3 class="text-sm font-bold text-slate-900">Distribusi Opportunity Status</h3>
                <p class="text-[11px] text-slate-400">Sebaran status peluang berdasarkan seluruh Principal Review terfilter</p>
            </div>

            <div class="flex flex-col gap-2.5">
                {#each opportunityOrder as item}
                    {@const count = metrics.oppCounts[item.key] || 0}
                    {@const pct = metrics.totalReviews > 0 ? Math.round((count / metrics.totalReviews) * 100) : 0}
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-slate-700">{item.key}</span>
                            <span class="text-slate-500 font-mono text-[11px]">{count} ({pct}%)</span>
                        </div>
                        <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-300 {item.color}" style="width: {pct}%"></div>
                        </div>
                    </div>
                {/each}
            </div>
        </div>

        <!-- Team & Top Principals -->
        <div class="flex flex-col gap-5">
            <!-- Breakdown per Tim -->
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-2xs flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Aktivitas per Tim</h3>
                        <p class="text-[11px] text-slate-400">Perbandingan volume kunjungan antar divisi</p>
                    </div>
                    <Users class="w-4 h-4 text-slate-400" />
                </div>

                <div class="grid grid-cols-3 gap-2.5">
                    {#each metrics.teamBreakdown as t}
                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200/80">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{t.team}</span>
                            <p class="text-lg font-bold text-slate-900 mt-0.5">{t.total}</p>
                            <span class="text-[10px] text-emerald-700 font-medium">{t.completed} selesai</span>
                        </div>
                    {:else}
                        <p class="col-span-3 text-xs text-slate-400">Belum ada data tim.</p>
                    {/each}
                </div>
            </div>

            <!-- Top Principal -->
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-2xs flex flex-col gap-3 flex-1">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Performa per Principal</h3>
                    <p class="text-[11px] text-slate-400">Frekuensi pembahasan produk & jumlah demo/quotation</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 text-[10px] font-semibold uppercase text-slate-400">
                                <th class="py-2">Principal</th>
                                <th class="py-2 text-right">Review</th>
                                <th class="py-2 text-right">Demo</th>
                                <th class="py-2 text-right">Quotation/Won</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            {#each metrics.topPrincipals as p}
                                <tr>
                                    <td class="py-2 font-semibold text-slate-800">{p.name}</td>
                                    <td class="py-2 text-right font-mono text-slate-700">{p.reviews}</td>
                                    <td class="py-2 text-right font-mono text-slate-600">{p.demos}</td>
                                    <td class="py-2 text-right font-mono font-semibold text-emerald-700">{p.quotationsOrWon}</td>
                                </tr>
                            {:else}
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-slate-400">Belum ada data principal.</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Performance Table -->
    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-2xs flex flex-col gap-3">
        <div>
            <h3 class="text-sm font-bold text-slate-900">Ringkasan Kunjungan per PIC Sales</h3>
            <p class="text-[11px] text-slate-400">Rekapitulasi kunjungan terencana dan selesai untuk setiap sales representative</p>
        </div>

        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-semibold uppercase text-slate-500">
                    <th class="py-2.5 px-3">Inisial</th>
                    <th class="py-2.5 px-3">Nama PIC Sales</th>
                    <th class="py-2.5 px-3">Tim</th>
                    <th class="py-2.5 px-3 text-right">Total Visit</th>
                    <th class="py-2.5 px-3 text-right">Terjadwal</th>
                    <th class="py-2.5 px-3 text-right">Selesai</th>
                    <th class="py-2.5 px-3 text-right">Rasio Selesai</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                {#each metrics.salesLeaderboard as s}
                    {@const rate = s.total > 0 ? Math.round((s.completed / s.total) * 100) : 0}
                    <tr>
                        <td class="py-2.5 px-3 font-mono font-bold text-slate-700">{s.initial}</td>
                        <td class="py-2.5 px-3 font-medium text-slate-900">{s.name}</td>
                        <td class="py-2.5 px-3">
                            <span class="text-[10px] px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-600">{s.team}</span>
                        </td>
                        <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">{s.total}</td>
                        <td class="py-2.5 px-3 text-right font-mono text-blue-700">{s.planned}</td>
                        <td class="py-2.5 px-3 text-right font-mono text-emerald-700">{s.completed}</td>
                        <td class="py-2.5 px-3 text-right font-mono text-slate-600">{rate}%</td>
                    </tr>
                {:else}
                    <tr>
                        <td colspan="7" class="py-6 text-center text-slate-400">Belum ada data kunjungan sales.</td>
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>
</div>