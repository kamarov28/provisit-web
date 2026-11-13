<script>
    import {
        Calendar as CalendarIcon,
        LayoutGrid,
        Table as TableIcon,
        Plus,
        ChevronLeft,
        ChevronRight,
        Clock,
        MapPin,
        Building2,
        User as UserIcon,
        CheckCircle2,
        X,
        Search,
        Filter,
        Briefcase,
        ArrowRight
    } from '@lucide/svelte';

    let {
        visits = [],
        stats = { total: 0, planned: 0, confirmed: 0, completed: 0 },
        principals = [],
        customers = [],
        salesUsers = [],
        filters = {}
    } = $props();

    let currentView = $state('calendar'); // 'calendar' | 'board' | 'table'
    let selectedVisit = $state(null);
    let isDrawerOpen = $state(false);
    let searchQuery = $state('');
    let selectedTeam = $state('ALL');

    // Date navigation for Calendar
    let currentDate = $state(new Date());

    const monthNames = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    let currentYear = $derived(currentDate.getFullYear());
    let currentMonth = $derived(currentDate.getMonth());

    function prevMonth() {
        currentDate = new Date(currentYear, currentMonth - 1, 1);
    }

    function nextMonth() {
        currentDate = new Date(currentYear, currentMonth + 1, 1);
    }

    function todayMonth() {
        currentDate = new Date();
    }

    // Days grid generator
    let calendarDays = $derived.by(() => {
        const year = currentYear;
        const month = currentMonth;
        const firstDayOfMonth = new Date(year, month, 1).getDay(); // 0 is Sunday
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();

        // Adjust starting day to Monday (0 = Monday, 6 = Sunday)
        const startOffset = (firstDayOfMonth + 6) % 7;

        const days = [];

        // Previous month days
        for (let i = startOffset - 1; i >= 0; i--) {
            const dayNum = daysInPrevMonth - i;
            const d = new Date(year, month - 1, dayNum);
            days.push({
                dateString: d.toISOString().split('T')[0],
                dayNumber: dayNum,
                isCurrentMonth: false,
                isToday: false
            });
        }

        // Current month days
        const todayStr = new Date().toISOString().split('T')[0];
        for (let i = 1; i <= daysInMonth; i++) {
            const d = new Date(year, month, i);
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
            days.push({
                dateString: dateStr,
                dayNumber: i,
                isCurrentMonth: true,
                isToday: dateStr === todayStr
            });
        }

        // Next month trailing days to complete 35 or 42 cells
        const remaining = (7 - (days.length % 7)) % 7;
        for (let i = 1; i <= remaining; i++) {
            days.push({
                dateString: `${year}-${String(month + 2).padStart(2, '0')}-${String(i).padStart(2, '0')}`,
                dayNumber: i,
                isCurrentMonth: false,
                isToday: false
            });
        }

        return days;
    });

    // Filtered visits
    let filteredVisits = $derived.by(() => {
        return visits.filter(v => {
            const matchSearch = !searchQuery || 
                (v.title && v.title.toLowerCase().includes(searchQuery.toLowerCase())) ||
                (v.customer_name_snapshot && v.customer_name_snapshot.toLowerCase().includes(searchQuery.toLowerCase())) ||
                (v.visit_code && v.visit_code.toLowerCase().includes(searchQuery.toLowerCase()));

            const matchTeam = selectedTeam === 'ALL' || 
                (v.primary_sales_user && v.primary_sales_user.team === selectedTeam);

            return matchSearch && matchTeam;
        });
    });

    function getVisitsForDate(dateStr) {
        return filteredVisits.filter(v => v.visit_date === dateStr);
    }

    function openVisitDetail(visit) {
        selectedVisit = visit;
        isDrawerOpen = true;
    }

    function closeDrawer() {
        isDrawerOpen = false;
        setTimeout(() => {
            selectedVisit = null;
        }, 200);
    }

    function statusColor(status) {
        switch (status) {
            case 'Completed':
                return 'bg-emerald-50 text-emerald-700 border-emerald-200';
            case 'Confirmed':
                return 'bg-blue-50 text-blue-700 border-blue-200';
            case 'In Progress':
                return 'bg-amber-50 text-amber-700 border-amber-200';
            case 'Cancelled':
                return 'bg-rose-50 text-rose-700 border-rose-200';
            default: // Planned
                return 'bg-slate-100 text-slate-700 border-slate-200';
        }
    }
</script>

<div class="min-h-screen bg-[#FBFBFA] text-[#37352F] flex flex-col font-sans">
    <!-- Top Navigation Bar -->
    <header class="border-b border-slate-200/80 bg-white sticky top-0 z-30 px-6 py-3.5 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center font-bold text-xs tracking-wider shadow-sm">
                PV
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-base font-semibold tracking-tight text-slate-900">PROvisit</h1>
                    <span class="text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-600 font-medium border border-slate-200">
                        Mitra Analitika
                    </span>
                </div>
                <p class="text-[11px] text-slate-400">Portal Pelaporan & Tracking Kunjungan Sales</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <!-- View Switcher -->
            <div class="flex items-center bg-slate-100/90 p-1 rounded-lg border border-slate-200">
                <button
                    type="button"
                    onclick={() => currentView = 'calendar'}
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all {currentView === 'calendar' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'}"
                >
                    <CalendarIcon class="w-3.5 h-3.5" />
                    <span>Kalender</span>
                </button>
                <button
                    type="button"
                    onclick={() => currentView = 'board'}
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all {currentView === 'board' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'}"
                >
                    <LayoutGrid class="w-3.5 h-3.5" />
                    <span>Status Board</span>
                </button>
                <button
                    type="button"
                    onclick={() => currentView = 'table'}
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all {currentView === 'table' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'}"
                >
                    <TableIcon class="w-3.5 h-3.5" />
                    <span>Tabel</span>
                </button>
            </div>

            <!-- Team Filter -->
            <div class="relative">
                <select
                    bind:value={selectedTeam}
                    class="text-xs font-medium bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-700 outline-none hover:border-slate-300 focus:border-slate-400 cursor-pointer"
                >
                    <option value="ALL">Semua Tim</option>
                    <option value="YES">Tim YES</option>
                    <option value="MOU">Tim MOU</option>
                    <option value="MANAGEMENT">Manajemen</option>
                </select>
            </div>

            <!-- New Visit Button -->
            <button
                type="button"
                class="flex items-center gap-1.5 bg-slate-900 hover:bg-slate-800 text-white px-3.5 py-2 rounded-lg text-xs font-medium shadow-xs transition-colors"
            >
                <Plus class="w-3.5 h-3.5" />
                <span>Catat Visit</span>
            </button>
        </div>
    </header>

    <!-- Main Workspace -->
    <main class="flex-1 p-6 max-w-7xl w-full mx-auto flex flex-col gap-5">
        <!-- Summary Stats Ribbon -->
        <section class="grid grid-cols-4 gap-4">
            <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-2xs">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Kunjungan</span>
                <p class="text-2xl font-bold text-slate-900 mt-1">{stats.total}</p>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-2xs">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Terencana (Planned)</span>
                <p class="text-2xl font-bold text-slate-800 mt-1">{stats.planned}</p>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-2xs">
                <span class="text-[11px] font-semibold text-blue-500 uppercase tracking-wider">Terkonfirmasi</span>
                <p class="text-2xl font-bold text-blue-700 mt-1">{stats.confirmed}</p>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-2xs">
                <span class="text-[11px] font-semibold text-emerald-600 uppercase tracking-wider">Selesai (Completed)</span>
                <p class="text-2xl font-bold text-emerald-700 mt-1">{stats.completed}</p>
            </div>
        </section>

        <!-- Search and Quick Filter Toolbar -->
        <div class="flex items-center justify-between gap-4">
            <div class="relative flex-1 max-w-sm">
                <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                    type="text"
                    bind:value={searchQuery}
                    placeholder="Cari customer, agenda, atau kode visit..."
                    class="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-lg text-xs placeholder:text-slate-400 focus:outline-none focus:border-slate-400"
                />
            </div>

            {#if currentView === 'calendar'}
                <!-- Month Navigator -->
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        onclick={todayMonth}
                        class="px-2.5 py-1 text-xs font-medium border border-slate-200 bg-white hover:bg-slate-50 rounded-md transition-colors"
                    >
                        Hari Ini
                    </button>
                    <div class="flex items-center border border-slate-200 bg-white rounded-md overflow-hidden">
                        <button
                            type="button"
                            onclick={prevMonth}
                            class="p-1.5 hover:bg-slate-50 text-slate-600 transition-colors"
                            aria-label="Previous Month"
                        >
                            <ChevronLeft class="w-4 h-4" />
                        </button>
                        <span class="px-3 text-xs font-semibold text-slate-800 min-w-36 text-center select-none">
                            {monthNames[currentMonth]} {currentYear}
                        </span>
                        <button
                            type="button"
                            onclick={nextMonth}
                            class="p-1.5 hover:bg-slate-50 text-slate-600 transition-colors"
                            aria-label="Next Month"
                        >
                            <ChevronRight class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            {/if}
        </div>

        <!-- VIEW 1: CALENDAR VIEW -->
        {#if currentView === 'calendar'}
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-2xs flex-1 flex flex-col">
                <!-- Day of week header -->
                <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50/75 text-[11px] font-semibold text-slate-500 uppercase tracking-wider py-2 text-center">
                    <div>Senin</div>
                    <div>Selasa</div>
                    <div>Rabu</div>
                    <div>Kamis</div>
                    <div>Jumat</div>
                    <div>Sabtu</div>
                    <div>Minggu</div>
                </div>

                <!-- Calendar grid -->
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

                            <!-- Visit Chips -->
                            <div class="flex flex-col gap-1 overflow-y-auto max-h-24">
                                {#each dayVisits as visit}
                                    <button
                                        type="button"
                                        onclick={() => openVisitDetail(visit)}
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
        {/if}

        <!-- VIEW 2: KANBAN BOARD VIEW -->
        {#if currentView === 'board'}
            <div class="grid grid-cols-4 gap-4 flex-1 items-start">
                {#each ['Planned', 'Confirmed', 'In Progress', 'Completed'] as columnStatus}
                    {@const statusVisits = filteredVisits.filter(v => v.status === columnStatus)}
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
                                    onclick={() => openVisitDetail(visit)}
                                    onkeydown={(e) => e.key === 'Enter' && openVisitDetail(visit)}
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
        {/if}

        <!-- VIEW 3: TABLE VIEW -->
        {#if currentView === 'table'}
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
                        {#each filteredVisits as visit}
                            <tr
                                onclick={() => openVisitDetail(visit)}
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
        {/if}
    </main>

    <!-- NOTION-STYLE SIDE-PEEK DRAWER -->
    {#if isDrawerOpen && selectedVisit}
        <button
            type="button"
            class="fixed inset-0 z-40 bg-slate-900/20 backdrop-blur-2xs transition-opacity w-full h-full cursor-default"
            onclick={closeDrawer}
            aria-label="Tutup panel"
        ></button>

        <aside class="fixed top-0 right-0 bottom-0 w-full max-w-xl bg-white z-50 shadow-2xl border-l border-slate-200 flex flex-col transform transition-transform duration-200 ease-out">
            <!-- Drawer Header -->
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="font-mono text-xs px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-700 font-semibold">
                        {selectedVisit.visit_code}
                    </span>
                    <span class="text-xs px-2 py-0.5 rounded font-medium border {statusColor(selectedVisit.status)}">
                        {selectedVisit.status}
                    </span>
                </div>
                <button
                    type="button"
                    onclick={closeDrawer}
                    class="p-1 rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Drawer Body -->
            <div class="flex-1 overflow-y-auto p-6 flex flex-col gap-6">
                <!-- Title -->
                <div>
                    <h2 class="text-xl font-bold text-slate-900 leading-snug">
                        {selectedVisit.title}
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Dibuat pada {new Date(selectedVisit.created_at).toLocaleDateString('id-ID', { dateStyle: 'long' })}
                    </p>
                </div>

                <!-- Properties Grid (Notion Style) -->
                <div class="grid grid-cols-[120px_1fr] gap-y-3.5 gap-x-4 text-xs py-3 border-y border-slate-100">
                    <span class="text-slate-400 flex items-center gap-1.5"><Building2 class="w-3.5 h-3.5" /> Customer</span>
                    <span class="font-medium text-slate-900">{selectedVisit.customer_name_snapshot}</span>

                    <span class="text-slate-400 flex items-center gap-1.5"><UserIcon class="w-3.5 h-3.5" /> PIC Pelanggan</span>
                    <span class="text-slate-700">
                        {selectedVisit.pic_name_snapshot || '-'} 
                        {#if selectedVisit.pic_position_snapshot}
                            <span class="text-slate-400">({selectedVisit.pic_position_snapshot})</span>
                        {/if}
                    </span>

                    <span class="text-slate-400 flex items-center gap-1.5"><CalendarIcon class="w-3.5 h-3.5" /> Tanggal</span>
                    <span class="text-slate-700">{selectedVisit.visit_date}</span>

                    <span class="text-slate-400 flex items-center gap-1.5"><Clock class="w-3.5 h-3.5" /> Waktu</span>
                    <span class="text-slate-700">{selectedVisit.start_time ? selectedVisit.start_time.substring(0, 5) : '-'} WIB</span>

                    <span class="text-slate-400 flex items-center gap-1.5"><Briefcase class="w-3.5 h-3.5" /> Sales PIC</span>
                    <span class="text-slate-700 font-medium">
                        {selectedVisit.primary_sales_user?.name || '-'}
                        {#if selectedVisit.primary_sales_user?.team}
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 border border-slate-200 ml-1">
                                Tim {selectedVisit.primary_sales_user.team}
                            </span>
                        {/if}
                    </span>

                    <span class="text-slate-400 flex items-center gap-1.5"><MapPin class="w-3.5 h-3.5" /> Tipe Kunjungan</span>
                    <span class="text-slate-700">{selectedVisit.visit_type} ({selectedVisit.visit_purpose})</span>
                </div>

                <!-- Agenda Section -->
                {#if selectedVisit.agenda}
                    <div>
                        <h4 class="text-xs font-semibold text-slate-900 uppercase tracking-wider mb-1.5">Agenda</h4>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700 leading-relaxed whitespace-pre-wrap">
                            {selectedVisit.agenda}
                        </div>
                    </div>
                {/if}

                <!-- Previsit Notes -->
                {#if selectedVisit.previsit_notes}
                    <div>
                        <h4 class="text-xs font-semibold text-slate-900 uppercase tracking-wider mb-1.5">Pre-visit Notes</h4>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700 leading-relaxed whitespace-pre-wrap">
                            {selectedVisit.previsit_notes}
                        </div>
                    </div>
                {/if}

                <!-- Principal Reviews -->
                <div>
                    <h4 class="text-xs font-semibold text-slate-900 uppercase tracking-wider mb-2">Principal Review & Produk</h4>
                    {#if selectedVisit.principal_reviews && selectedVisit.principal_reviews.length > 0}
                        <div class="flex flex-col gap-2">
                            {#each selectedVisit.principal_reviews as review}
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
                {#if selectedVisit.action_plans && selectedVisit.action_plans.length > 0}
                    <div>
                        <h4 class="text-xs font-semibold text-slate-900 uppercase tracking-wider mb-2">Action Plan</h4>
                        <div class="flex flex-col gap-2">
                            {#each selectedVisit.action_plans as plan}
                                <div class="flex items-start gap-2 p-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                                    <CheckCircle2 class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" />
                                    <div class="flex-1">
                                        <p class="text-slate-800 font-medium">{plan.task_description}</p>
                                        <div class="flex items-center gap-2 mt-1 text-[10px] text-slate-400">
                                            <span>Deadline: {plan.due_date || '-'}</span>
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
            <div class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick={closeDrawer}
                    class="px-3.5 py-1.5 text-xs font-medium border border-slate-200 rounded-lg bg-white hover:bg-slate-50 text-slate-700 transition-colors"
                >
                    Tutup
                </button>
            </div>
        </aside>
    {/if}
</div>
