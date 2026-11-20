<script>
    import {
        Calendar as CalendarIcon,
        LayoutGrid,
        Table as TableIcon,
        BarChart3,
        Plus,
        ChevronLeft,
        ChevronRight,
        Search,
        Filter,
        X,
        RotateCcw,
        Database
    } from '@lucide/svelte';
    import { router } from '@inertiajs/svelte';
    import VisitCalendarView from '../../Components/Visits/VisitCalendarView.svelte';
    import VisitBoardView from '../../Components/Visits/VisitBoardView.svelte';
    import VisitTableView from '../../Components/Visits/VisitTableView.svelte';
    import VisitAnalyticsView from '../../Components/Visits/VisitAnalyticsView.svelte';
    import VisitDetailDrawer from '../../Components/Visits/VisitDetailDrawer.svelte';
    import VisitWizardModal from '../../Components/Visits/VisitWizardModal.svelte';
    import MasterDataModal from '../../Components/Visits/MasterDataModal.svelte';

    let {
        visits = [],
        stats = { total: 0, planned: 0, confirmed: 0, completed: 0 },
        principals = [],
        customers = [],
        salesUsers = [],
        supportMarketingUsers = [],
        filters = {}
    } = $props();

    let currentView = $state('calendar'); // 'calendar' | 'board' | 'table' | 'analytics'
    let calendarScale = $state('month'); // 'month' | 'week'
    let activeSavedView = $state('all'); // 'all' | 'upcoming' | 'followup' | 'completed'

    let selectedVisitId = $state(null);
    let selectedVisit = $derived(
        selectedVisitId ? (visits.find(v => v.id === selectedVisitId) || null) : null
    );
    let isDrawerOpen = $state(false);
    let wizardModal = $state(null);
    let masterModal = $state(null);


    // Search & Quick Dropdowns
    let searchQuery = $state('');
    let selectedTeam = $state('ALL');
    let selectedStatus = $state('ALL');

    // Notion-style Advanced Filter Builder
    let isFilterOpen = $state(false);
    let filterPrincipalId = $state('');
    let filterSalesUserId = $state('');
    let filterSupport = $state('');
    let filterVisitType = $state('');
    let filterDateFrom = $state('');
    let filterDateTo = $state('');

    let activeAdvancedFilterCount = $derived(
        [
            filterPrincipalId,
            filterSalesUserId,
            filterSupport,
            filterVisitType,
            filterDateFrom,
            filterDateTo
        ].filter(Boolean).length
    );

    function resetAdvancedFilters() {
        filterPrincipalId = '';
        filterSalesUserId = '';
        filterSupport = '';
        filterVisitType = '';
        filterDateFrom = '';
        filterDateTo = '';
    }

    // Date navigation for Calendar
    let currentDate = $state(new Date());

    const monthNames = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    let currentYear = $derived(currentDate.getFullYear());
    let currentMonth = $derived(currentDate.getMonth());

    function toLocalDateStr(d) {
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }

    function prevPeriod() {
        if (calendarScale === 'week') {
            const d = new Date(currentDate);
            d.setDate(d.getDate() - 7);
            currentDate = d;
        } else {
            currentDate = new Date(currentYear, currentMonth - 1, 1);
        }
    }

    function nextPeriod() {
        if (calendarScale === 'week') {
            const d = new Date(currentDate);
            d.setDate(d.getDate() + 7);
            currentDate = d;
        } else {
            currentDate = new Date(currentYear, currentMonth + 1, 1);
        }
    }

    function todayPeriod() {
        currentDate = new Date();
    }

    let calendarDays = $derived.by(() => {
        const todayStr = toLocalDateStr(new Date());

        if (calendarScale === 'week') {
            const base = new Date(currentDate);
            const dayOfWeek = base.getDay();
            const mondayOffset = (dayOfWeek + 6) % 7;
            const monday = new Date(base);
            monday.setDate(base.getDate() - mondayOffset);

            const days = [];
            for (let i = 0; i < 7; i++) {
                const d = new Date(monday);
                d.setDate(monday.getDate() + i);
                const dateStr = toLocalDateStr(d);
                days.push({
                    dateString: dateStr,
                    dayNumber: d.getDate(),
                    isCurrentMonth: d.getMonth() === currentMonth,
                    isToday: dateStr === todayStr
                });
            }
            return days;
        }

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
                dateString: toLocalDateStr(d),
                dayNumber: dayNum,
                isCurrentMonth: false,
                isToday: false
            });
        }

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
            const d = new Date(year, month + 1, i);
            days.push({
                dateString: toLocalDateStr(d),
                dayNumber: i,
                isCurrentMonth: false,
                isToday: false
            });
        }

        return days;
    });

    let calendarPeriodLabel = $derived.by(() => {
        if (calendarScale === 'week' && calendarDays.length === 7) {
            const start = new Date(calendarDays[0].dateString + 'T00:00:00');
            const end = new Date(calendarDays[6].dateString + 'T00:00:00');
            if (start.getMonth() === end.getMonth()) {
                return `${start.getDate()} - ${end.getDate()} ${monthNames[start.getMonth()]} ${start.getFullYear()}`;
            }
            return `${start.getDate()} ${monthNames[start.getMonth()].slice(0, 3)} - ${end.getDate()} ${monthNames[end.getMonth()].slice(0, 3)} ${end.getFullYear()}`;
        }
        return `${monthNames[currentMonth]} ${currentYear}`;
    });

    let savedViewCounts = $derived.by(() => {
        const todayStr = toLocalDateStr(new Date());
        let upcoming = 0;
        let followup = 0;
        let completed = 0;

        for (const v of visits) {
            if (['Draft', 'Planned', 'Confirmed'].includes(v.status) && v.visit_date >= todayStr) {
                upcoming++;
            }
            const hasOpenAction = (v.action_plans || []).some(p => !['Completed', 'Cancelled'].includes(p.status));
            const isOverdueOpen = ['Draft', 'Planned', 'Confirmed'].includes(v.status) && v.visit_date < todayStr;
            if (isOverdueOpen || (v.status === 'Completed' && hasOpenAction)) {
                followup++;
            }
            if (v.status === 'Completed') {
                completed++;
            }
        }

        return {
            all: visits.length,
            upcoming,
            followup,
            completed
        };
    });

    let filteredVisits = $derived.by(() => {
        const todayStr = toLocalDateStr(new Date());

        return visits.filter(v => {
            if (activeSavedView === 'upcoming') {
                if (!(['Draft', 'Planned', 'Confirmed'].includes(v.status) && v.visit_date >= todayStr)) {
                    return false;
                }
            } else if (activeSavedView === 'followup') {
                const hasOpenAction = (v.action_plans || []).some(p => !['Completed', 'Cancelled'].includes(p.status));
                const isOverdueOpen = ['Draft', 'Planned', 'Confirmed'].includes(v.status) && v.visit_date < todayStr;
                if (!(isOverdueOpen || (v.status === 'Completed' && hasOpenAction))) {
                    return false;
                }
            } else if (activeSavedView === 'completed') {
                if (v.status !== 'Completed') return false;
            }

            const matchSearch = !searchQuery ||
                (v.title && v.title.toLowerCase().includes(searchQuery.toLowerCase())) ||
                (v.customer_name_snapshot && v.customer_name_snapshot.toLowerCase().includes(searchQuery.toLowerCase())) ||
                (v.visit_code && v.visit_code.toLowerCase().includes(searchQuery.toLowerCase()));

            if (!matchSearch) return false;

            if (selectedTeam !== 'ALL' && v.primary_sales_user?.team !== selectedTeam) return false;
            if (selectedStatus !== 'ALL' && v.status !== selectedStatus) return false;

            if (filterPrincipalId) {
                const hasPrincipal = (v.principal_reviews || []).some(r => String(r.principal_id) === String(filterPrincipalId));
                if (!hasPrincipal) return false;
            }

            if (filterSalesUserId && String(v.primary_sales_user_id) !== String(filterSalesUserId)) {
                return false;
            }

            if (filterSupport) {
                const supList = Array.isArray(v.support_marketing) ? v.support_marketing : [];
                if (filterSupport === 'NONE') {
                    if (supList.length > 0) return false;
                } else if (!supList.includes(filterSupport)) {
                    return false;
                }
            }

            if (filterVisitType && v.visit_type !== filterVisitType) {
                return false;
            }

            if (filterDateFrom && v.visit_date < filterDateFrom) {
                return false;
            }

            if (filterDateTo && v.visit_date > filterDateTo) {
                return false;
            }

            return true;
        });
    });

    function getVisitsForDate(dateStr) {
        return filteredVisits.filter(v => v.visit_date === dateStr);
    }

    function openVisitDetail(visit) {
        selectedVisitId = visit.id;
        isDrawerOpen = true;
    }

    function closeDrawer() {
        isDrawerOpen = false;
        setTimeout(() => {
            selectedVisitId = null;
        }, 200);
    }

    function handleEditVisit(visit) {
        isDrawerOpen = false;
        wizardModal?.openEdit(visit);
    }

    function handleDuplicateVisit(visit) {
        isDrawerOpen = false;
        wizardModal?.openDuplicate(visit);
    }

    function handleToggleActionPlan(plan) {
        if (!plan?.id) return;
        router.patch(`/action-plans/${plan.id}/toggle`, {}, { preserveScroll: true });
    }
    function handleQuickAdd(dateStr) {
        wizardModal?.openNew(dateStr);
    }

    function handleRescheduleVisit(visit, newDate, newStartTime = null) {
        const payload = { visit_date: newDate };
        if (newStartTime !== null) {
            payload.start_time = newStartTime;
        }
        router.patch(`/visits/${visit.id}/reschedule`, payload, {
            preserveScroll: true
        });
    }

    function handleBoardStatusChange(visit, newStatus) {
        if (newStatus === 'Completed') {
            const hasResult = Boolean(String(visit.postvisit_result || '').trim());
            const hasPlans = Array.isArray(visit.action_plans) && visit.action_plans.length > 0;
            if (!hasResult || !hasPlans) {
                wizardModal?.openEdit(visit, {
                    step: 3,
                    forceStatus: 'Completed',
                    notice: 'Lengkapi Post-visit Result dan Action Plan untuk menyelesaikan kunjungan ini.'
                });
                return;
            }
        }

        router.patch(`/visits/${visit.id}/status`, {
            status: newStatus
        }, {
            preserveScroll: true
        });
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
    <header class="border-b border-slate-200/80 bg-white sticky top-0 z-30 px-4 sm:px-6 py-3 sm:py-3.5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <!-- Brand & Mobile CTA -->
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center font-bold text-xs tracking-wider shadow-sm shrink-0">
                    PV
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h1 class="text-base font-semibold tracking-tight text-slate-900">PROvisit</h1>
                        <span class="text-[11px] sm:text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-600 font-medium border border-slate-200 truncate">
                            Mitra Analitika
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-400 truncate">Portal Pelaporan & Tracking Kunjungan Sales</p>
                </div>
            </div>

            <!-- Mobile Action Buttons -->
            <div class="lg:hidden flex items-center gap-1.5 shrink-0">
                <button
                    type="button"
                    onclick={() => masterModal?.open('customer')}
                    title="Quick Master Data (Customer / PIC / Produk)"
                    class="inline-flex items-center justify-center p-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-lg transition-colors cursor-pointer"
                >
                    <Database class="w-4 h-4" />
                </button>
                <button
                    type="button"
                    onclick={() => wizardModal?.openNew()}
                    class="inline-flex items-center gap-1.5 bg-slate-900 hover:bg-slate-800 text-white px-3 py-2 rounded-lg text-xs font-medium shadow-xs transition-colors cursor-pointer"
                >
                    <Plus class="w-3.5 h-3.5" />
                    <span>Catat Visit</span>
                </button>
            </div>
        </div>

        <!-- View Switcher, Dropdown Filters & Desktop CTA -->
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <!-- View Switcher -->
            <div class="flex items-center bg-slate-100/90 p-1 rounded-lg border border-slate-200 overflow-x-auto max-w-full">
                <button
                    type="button"
                    onclick={() => currentView = 'calendar'}
                    class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-md text-xs font-medium whitespace-nowrap transition-all {currentView === 'calendar' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'}"
                >
                    <CalendarIcon class="w-3.5 h-3.5 shrink-0" />
                    <span>Kalender</span>
                </button>
                <button
                    type="button"
                    onclick={() => currentView = 'board'}
                    class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-md text-xs font-medium whitespace-nowrap transition-all {currentView === 'board' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'}"
                >
                    <LayoutGrid class="w-3.5 h-3.5 shrink-0" />
                    <span>Board</span>
                </button>
                <button
                    type="button"
                    onclick={() => currentView = 'table'}
                    class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-md text-xs font-medium whitespace-nowrap transition-all {currentView === 'table' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'}"
                >
                    <TableIcon class="w-3.5 h-3.5 shrink-0" />
                    <span>Tabel</span>
                </button>
                <button
                    type="button"
                    onclick={() => currentView = 'analytics'}
                    class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-md text-xs font-medium whitespace-nowrap transition-all {currentView === 'analytics' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'}"
                >
                    <BarChart3 class="w-3.5 h-3.5 shrink-0" />
                    <span>Analitik</span>
                </button>
            </div>

            <!-- Status & Team Dropdowns -->
            <div class="flex items-center gap-2 flex-1 sm:flex-initial">
                <select
                    bind:value={selectedStatus}
                    aria-label="Filter Status"
                    class="flex-1 sm:flex-initial text-xs font-medium bg-white border border-slate-200 rounded-lg px-2.5 sm:px-3 py-2 text-slate-700 outline-none hover:border-slate-300 focus:border-slate-400 cursor-pointer"
                >
                    <option value="ALL">Semua Status</option>
                    <option value="Draft">Draft</option>
                    <option value="Planned">Planned</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>

                <select
                    bind:value={selectedTeam}
                    aria-label="Filter Tim"
                    class="flex-1 sm:flex-initial text-xs font-medium bg-white border border-slate-200 rounded-lg px-2.5 sm:px-3 py-2 text-slate-700 outline-none hover:border-slate-300 focus:border-slate-400 cursor-pointer"
                >
                    <option value="ALL">Semua Tim</option>
                    <option value="YES">Tim YES</option>
                    <option value="MOU">Tim MOU</option>
                    <option value="MANAGEMENT">Manajemen</option>
                </select>
            </div>

            <!-- Desktop Master Data & New Visit Buttons -->
            <div class="hidden lg:flex items-center gap-2">
                <button
                    type="button"
                    onclick={() => masterModal?.open('customer')}
                    title="Tambah Customer, PIC, atau Produk baru"
                    class="flex items-center gap-1.5 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 px-3 py-2 rounded-lg text-xs font-medium transition-colors cursor-pointer"
                >
                    <Database class="w-3.5 h-3.5 text-slate-500" />
                    <span>Master Data</span>
                </button>

                <button
                    type="button"
                    onclick={() => wizardModal?.openNew()}
                    class="flex items-center gap-1.5 bg-slate-900 hover:bg-slate-800 text-white px-3.5 py-2 rounded-lg text-xs font-medium shadow-xs transition-colors cursor-pointer"
                >
                    <Plus class="w-3.5 h-3.5" />
                    <span>Catat Visit</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Workspace -->
    <main class="flex-1 p-4 sm:p-6 max-w-7xl w-full mx-auto flex flex-col gap-4">
        <!-- Summary Stats Ribbon -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div class="bg-white border border-slate-200/80 rounded-xl p-3.5 sm:p-4 shadow-2xs">
                <span class="text-[10px] sm:text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Kunjungan</span>
                <p class="text-xl sm:text-2xl font-bold text-slate-900 mt-1">{stats.total}</p>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-xl p-3.5 sm:p-4 shadow-2xs">
                <span class="text-[10px] sm:text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Terencana (Planned)</span>
                <p class="text-xl sm:text-2xl font-bold text-slate-800 mt-1">{stats.planned}</p>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-xl p-3.5 sm:p-4 shadow-2xs">
                <span class="text-[10px] sm:text-[11px] font-semibold text-blue-500 uppercase tracking-wider">Terkonfirmasi</span>
                <p class="text-xl sm:text-2xl font-bold text-blue-700 mt-1">{stats.confirmed}</p>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-xl p-3.5 sm:p-4 shadow-2xs">
                <span class="text-[10px] sm:text-[11px] font-semibold text-emerald-600 uppercase tracking-wider">Selesai (Completed)</span>
                <p class="text-xl sm:text-2xl font-bold text-emerald-700 mt-1">{stats.completed}</p>
            </div>
        </section>

        <!-- Saved Views Tabs (Notion Database Views) -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200/80">
            <div class="flex items-center gap-1 text-xs overflow-x-auto whitespace-nowrap pb-px">
                <button
                    type="button"
                    onclick={() => activeSavedView = 'all'}
                    class="px-3 py-2 border-b-2 font-medium flex items-center gap-1.5 transition-colors cursor-pointer shrink-0 {activeSavedView === 'all' ? 'border-slate-900 text-slate-900 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800'}"
                >
                    <span>Semua Kunjungan</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-slate-100 text-slate-600">{savedViewCounts.all}</span>
                </button>
                <button
                    type="button"
                    onclick={() => activeSavedView = 'upcoming'}
                    class="px-3 py-2 border-b-2 font-medium flex items-center gap-1.5 transition-colors cursor-pointer shrink-0 {activeSavedView === 'upcoming' ? 'border-slate-900 text-slate-900 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800'}"
                >
                    <span>Jadwal Mendatang</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-blue-50 text-blue-700">{savedViewCounts.upcoming}</span>
                </button>
                <button
                    type="button"
                    onclick={() => activeSavedView = 'followup'}
                    class="px-3 py-2 border-b-2 font-medium flex items-center gap-1.5 transition-colors cursor-pointer shrink-0 {activeSavedView === 'followup' ? 'border-slate-900 text-slate-900 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800'}"
                >
                    <span>Butuh Follow-up</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-amber-50 text-amber-700">{savedViewCounts.followup}</span>
                </button>
                <button
                    type="button"
                    onclick={() => activeSavedView = 'completed'}
                    class="px-3 py-2 border-b-2 font-medium flex items-center gap-1.5 transition-colors cursor-pointer shrink-0 {activeSavedView === 'completed' ? 'border-slate-900 text-slate-900 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800'}"
                >
                    <span>Selesai</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-emerald-50 text-emerald-700">{savedViewCounts.completed}</span>
                </button>
            </div>

            <span class="text-[11px] text-slate-400 pb-1.5 sm:pb-0">
                Menampilkan <strong class="text-slate-600">{filteredVisits.length}</strong> kunjungan
            </span>
        </div>

        <!-- Search, Advanced Filter Builder & Calendar Period Navigator -->
        <div class="flex flex-col gap-2.5">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <div class="flex items-center gap-2 flex-1">
                    <div class="relative flex-1 sm:max-w-sm">
                        <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            type="text"
                            bind:value={searchQuery}
                            placeholder="Cari customer, agenda, atau kode visit..."
                            class="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-lg text-xs placeholder:text-slate-400 focus:outline-none focus:border-slate-400"
                        />
                    </div>

                    <button
                        type="button"
                        onclick={() => isFilterOpen = !isFilterOpen}
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border text-xs font-medium transition-colors cursor-pointer shrink-0 {isFilterOpen || activeAdvancedFilterCount > 0 ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'}"
                    >
                        <Filter class="w-3.5 h-3.5" />
                        <span>Filter</span>
                        {#if activeAdvancedFilterCount > 0}
                            <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-white text-slate-900 font-bold">
                                {activeAdvancedFilterCount}
                            </span>
                        {/if}
                    </button>

                    {#if activeAdvancedFilterCount > 0}
                        <button
                            type="button"
                            onclick={resetAdvancedFilters}
                            class="inline-flex items-center gap-1 px-2.5 py-2 rounded-lg text-xs text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors cursor-pointer shrink-0"
                        >
                            <RotateCcw class="w-3.5 h-3.5" />
                            <span class="hidden sm:inline">Reset</span>
                        </button>
                    {/if}
                </div>

                {#if currentView === 'calendar'}
                    <div class="flex flex-wrap items-center justify-between sm:justify-start gap-2">
                        <div class="flex items-center gap-2">
                            <a
                                href="/visits/calendar.ics"
                                title="Download / Subscribe iCalendar (.ics)"
                                class="px-2.5 py-1.5 text-xs font-medium border border-slate-200 bg-white hover:bg-slate-50 rounded-md text-slate-600 transition-colors"
                            >
                                Export .ics
                            </a>

                            <!-- Month / Week Scale Switcher -->
                            <div class="flex items-center bg-slate-100 p-0.5 rounded-md border border-slate-200 text-xs">
                                <button
                                    type="button"
                                    onclick={() => calendarScale = 'month'}
                                    class="px-2.5 py-1 rounded text-[11px] font-medium transition-colors cursor-pointer {calendarScale === 'month' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-500 hover:text-slate-800'}"
                                >
                                    Bulan
                                </button>
                                <button
                                    type="button"
                                    onclick={() => calendarScale = 'week'}
                                    class="px-2.5 py-1 rounded text-[11px] font-medium transition-colors cursor-pointer {calendarScale === 'week' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-500 hover:text-slate-800'}"
                                >
                                    Minggu
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                onclick={todayPeriod}
                                class="px-2.5 py-1.5 text-xs font-medium border border-slate-200 bg-white hover:bg-slate-50 rounded-md transition-colors"
                            >
                                Hari Ini
                            </button>

                            <div class="flex items-center border border-slate-200 bg-white rounded-md overflow-hidden">
                                <button
                                    type="button"
                                    onclick={prevPeriod}
                                    class="p-1.5 hover:bg-slate-50 text-slate-600 transition-colors"
                                    aria-label="Previous Period"
                                >
                                    <ChevronLeft class="w-4 h-4" />
                                </button>
                                <span class="px-2.5 sm:px-3 text-xs font-semibold text-slate-800 min-w-32 sm:min-w-40 text-center select-none">
                                    {calendarPeriodLabel}
                                </span>
                                <button
                                    type="button"
                                    onclick={nextPeriod}
                                    class="p-1.5 hover:bg-slate-50 text-slate-600 transition-colors"
                                    aria-label="Next Period"
                                >
                                    <ChevronRight class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                {/if}
            </div>

            <!-- Collapsible Advanced Filter Bar -->
            {#if isFilterOpen}
                <div class="bg-white border border-slate-200 rounded-xl p-3.5 shadow-2xs grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 text-xs">
                    <div>
                        <label for="fltPrincipal" class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Principal</label>
                        <select
                            id="fltPrincipal"
                            bind:value={filterPrincipalId}
                            class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg bg-white text-xs outline-none focus:border-slate-400"
                        >
                            <option value="">Semua Principal</option>
                            {#each principals as p}
                                <option value={String(p.id)}>{p.name}</option>
                            {/each}
                        </select>
                    </div>

                    <div>
                        <label for="fltSales" class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">PIC Sales</label>
                        <select
                            id="fltSales"
                            bind:value={filterSalesUserId}
                            class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg bg-white text-xs outline-none focus:border-slate-400"
                        >
                            <option value="">Semua Sales</option>
                            {#each salesUsers as u}
                                <option value={String(u.id)}>{u.initial ? `${u.initial} ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ${u.name}` : u.name}</option>
                            {/each}
                        </select>
                    </div>

                    <div>
                        <label for="fltSupport" class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Support MKT</label>
                        <select
                            id="fltSupport"
                            bind:value={filterSupport}
                            class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg bg-white text-xs outline-none focus:border-slate-400"
                        >
                            <option value="">Semua</option>
                            <option value="NONE">None (Tanpa Support)</option>
                            {#each supportMarketingUsers as sup}
                                {@const code = String(sup.initial || '').trim().toUpperCase()}
                                <option value={code}>{code} ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â {sup.name}</option>
                            {/each}
                        </select>
                    </div>

                    <div>
                        <label for="fltType" class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Tipe Visit</label>
                        <select
                            id="fltType"
                            bind:value={filterVisitType}
                            class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg bg-white text-xs outline-none focus:border-slate-400"
                        >
                            <option value="">Semua Tipe</option>
                            <option value="Offline Visit">Offline Visit</option>
                            <option value="Online Visit">Online Visit</option>
                        </select>
                    </div>

                    <div>
                        <label for="fltDateFrom" class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Dari Tanggal</label>
                        <input
                            id="fltDateFrom"
                            type="date"
                            bind:value={filterDateFrom}
                            class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg bg-white text-xs outline-none focus:border-slate-400"
                        />
                    </div>

                    <div>
                        <label for="fltDateTo" class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Sampai Tanggal</label>
                        <input
                            id="fltDateTo"
                            type="date"
                            bind:value={filterDateTo}
                            class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg bg-white text-xs outline-none focus:border-slate-400"
                        />
                    </div>
                </div>
            {/if}
        </div>

        {#if currentView === 'calendar'}
            <VisitCalendarView
                days={calendarDays}
                scale={calendarScale}
                {getVisitsForDate}
                onSelectVisit={openVisitDetail}
                onQuickAdd={handleQuickAdd}
                onRescheduleVisit={handleRescheduleVisit}
                {statusColor}
            />
        {/if}

        {#if currentView === 'board'}
            <VisitBoardView
                visits={filteredVisits}
                onSelectVisit={openVisitDetail}
                onStatusChange={handleBoardStatusChange}
                {statusColor}
            />
        {/if}

        {#if currentView === 'table'}
            <VisitTableView
                visits={filteredVisits}
                onSelectVisit={openVisitDetail}
                onRescheduleVisit={handleRescheduleVisit}
                onStatusChange={handleBoardStatusChange}
                {statusColor}
            />
        {/if}

        {#if currentView === 'analytics'}
            <VisitAnalyticsView
                visits={filteredVisits}
            />
        {/if}
    </main>

    <VisitDetailDrawer
        visit={selectedVisit}
        open={isDrawerOpen}
        onClose={closeDrawer}
        onEdit={handleEditVisit}
        onDuplicate={handleDuplicateVisit}
        onToggleActionPlan={handleToggleActionPlan}
        {statusColor}
    />

    <VisitWizardModal
        bind:this={wizardModal}
        {principals}
        {customers}
        {salesUsers}
        {supportMarketingUsers}
        onOpenMaster={(tab) => masterModal?.open(tab)}
    />

    <MasterDataModal
        bind:this={masterModal}
        {principals}
        {customers}
        {salesUsers}
    />
</div>
