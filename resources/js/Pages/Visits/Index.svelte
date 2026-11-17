<script>
    import {
        Calendar as CalendarIcon,
        LayoutGrid,
        Table as TableIcon,
        Plus,
        ChevronLeft,
        ChevronRight,
        Search
    } from '@lucide/svelte';
    import VisitCalendarView from '../../Components/Visits/VisitCalendarView.svelte';
    import VisitBoardView from '../../Components/Visits/VisitBoardView.svelte';
    import VisitTableView from '../../Components/Visits/VisitTableView.svelte';
    import VisitDetailDrawer from '../../Components/Visits/VisitDetailDrawer.svelte';
    import VisitWizardModal from '../../Components/Visits/VisitWizardModal.svelte';

    let {
        visits = [],
        stats = { total: 0, planned: 0, confirmed: 0, completed: 0 },
        principals = [],
        customers = [],
        salesUsers = [],
        supportMarketingUsers = [],
        filters = {}
    } = $props();

    let currentView = $state('calendar'); // 'calendar' | 'board' | 'table'
    let selectedVisit = $state(null);
    let isDrawerOpen = $state(false);
    let searchQuery = $state('');
    let selectedTeam = $state('ALL');
    let wizardModal = $state(null);

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

    let calendarDays = $derived.by(() => {
        const year = currentYear;
        const month = currentMonth;
        const firstDayOfMonth = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();

        const startOffset = (firstDayOfMonth + 6) % 7;
        const days = [];

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

        const todayStr = new Date().toISOString().split('T')[0];
        for (let i = 1; i <= daysInMonth; i++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
            days.push({
                dateString: dateStr,
                dayNumber: i,
                isCurrentMonth: true,
                isToday: dateStr === todayStr
            });
        }

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

    function handleEditVisit(visit) {
        isDrawerOpen = false;
        wizardModal?.openEdit(visit);
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
            default:
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
                onclick={() => wizardModal?.openNew()}
                class="flex items-center gap-1.5 bg-slate-900 hover:bg-slate-800 text-white px-3.5 py-2 rounded-lg text-xs font-medium shadow-xs transition-colors cursor-pointer"
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

        {#if currentView === 'calendar'}
            <VisitCalendarView
                {calendarDays}
                {getVisitsForDate}
                onSelectVisit={openVisitDetail}
                {statusColor}
            />
        {/if}

        {#if currentView === 'board'}
            <VisitBoardView
                visits={filteredVisits}
                onSelectVisit={openVisitDetail}
                {statusColor}
            />
        {/if}

        {#if currentView === 'table'}
            <VisitTableView
                visits={filteredVisits}
                onSelectVisit={openVisitDetail}
                {statusColor}
            />
        {/if}
    </main>

    <VisitDetailDrawer
        visit={selectedVisit}
        open={isDrawerOpen}
        onClose={closeDrawer}
        onEdit={handleEditVisit}
        {statusColor}
    />

    <VisitWizardModal
        bind:this={wizardModal}
        {principals}
        {customers}
        {salesUsers}
        {supportMarketingUsers}
    />
</div>