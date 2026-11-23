<script>
    import {
        Plus,
        CheckCircle2,
        X,
        ArrowRight,
        ArrowLeft,
        Trash2,
        AlertCircle
    } from '@lucide/svelte';
    import { router } from '@inertiajs/svelte';

    let {
        principals = [],
        customers = [],
        salesUsers = [],
        supportMarketingUsers = [],
        onOpenMaster
    } = $props();

    let isModalOpen = $state(false);
    let editingVisit = $state(null);
    let isEditing = $derived(editingVisit !== null);
    let currentStep = $state(1);
    let isSubmitting = $state(false);
    let formError = $state('');

    // Step 1: Info Kunjungan
    let formTitle = $state('');
    let formDate = $state(new Date().toISOString().split('T')[0]);
    let formStartTime = $state('10:00');
    let formVisitType = $state('Offline Visit');
    let formVisitPurpose = $state('Product Introduction / Presentation');
    let formCustomerId = $state('');
    let formCustomerSearch = $state('');
    let filteredCustomers = $derived.by(() => {
        const query = formCustomerSearch.trim().toLocaleLowerCase();
        if (query.length < 1) return [];
        return customers
            .filter(c => c.name.toLocaleLowerCase().includes(query))
            .slice(0, 8);
    });
    let formCustomerPicId = $state('');
    let formSalesUserId = $state('');
    let formSupportMarketing = $state([]);
    let formCompanionSales = $state([]);
    let formAgenda = $state('');
    let formPrevisitNotes = $state('');
    let formStatus = $state('Planned');

    // Step 2: Principal Reviews
    let formReviews = $state([
        { principal_id: '', principal_name: '', exposure_type: 'Introduction', opportunity_status: 'Interested', notes: '', products: [] }
    ]);

    // Step 3: Follow up & Hasil
    let formPostvisitResult = $state('');
    let formActionPlans = $state([
        { task_description: '', owner_user_id: '', due_date: '', priority: 'Normal' }
    ]);

    const timeOptions = [];
    for (let h = 0; h < 24; h++) {
        for (let m of [0, 15, 30, 45]) {
            timeOptions.push(`${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`);
        }
    }

    const visitPurposes = [
        'Product Introduction / Presentation',
        'Demo / Trial / Evaluation',
        'Technical Support / Troubleshooting',
        'Installation / Training',
        'Follow-up / Relationship',
        'Commercial / Negotiation'
    ];

    const exposureTypes = [
        'Introduction', 'Demo', 'Evaluation', 'Troubleshooting', 'Follow-up', 'Commercial'
    ];

    const opportunityStatuses = [
        'No Signal', 'Interested', 'Sample', 'Demo', 'Quotation', 'Negotiation', 'Won', 'Lost'
    ];

    let availablePics = $derived.by(() => {
        if (!formCustomerId) return [];
        const cust = customers.find(c => String(c.id) === String(formCustomerId));
        return cust && cust.pics ? cust.pics : [];
    });

    let selectedSalesUser = $derived(
        salesUsers.find(
            u => String(u.initial || '').trim().toUpperCase() ===
                String(customers.find(c => String(c.id) === String(formCustomerId))?.sales_initial || '').trim().toUpperCase()
        ) || null
    );

    export function openNew(initialDate = null) {
        editingVisit = null;
        currentStep = 1;
        formError = '';
        formTitle = '';
        formDate = initialDate || new Date().toISOString().split('T')[0];
        formStartTime = '10:00';
        formVisitType = 'Offline Visit';
        formVisitPurpose = 'Product Introduction / Presentation';
        formCustomerId = '';
        formCustomerSearch = '';
        formCustomerPicId = '';
        formSalesUserId = '';
        formSupportMarketing = [];
        formCompanionSales = [];
        formAgenda = '';
        formPrevisitNotes = '';
        formStatus = 'Planned';
        formReviews = [
            { principal_id: '', principal_name: '', exposure_type: 'Introduction', opportunity_status: 'Interested', notes: '', products: [] }
        ];
        formPostvisitResult = '';
        formActionPlans = [
            { task_description: '', owner_user_id: '', due_date: '', priority: 'Normal' }
        ];

        isModalOpen = true;
    }

    export function openEdit(visit, options = {}) {
        if (!visit) return;

        editingVisit = visit;
        currentStep = options.step || 1;
        formError = options.notice || '';

        formTitle = visit.title || '';
        formDate = visit.visit_date ? String(visit.visit_date).substring(0, 10) : new Date().toISOString().split('T')[0];
        formStartTime = visit.start_time ? String(visit.start_time).substring(0, 5) : '10:00';
        formVisitType = visit.visit_type || 'Offline Visit';
        formVisitPurpose = visit.visit_purpose || 'Product Introduction / Presentation';

        formCustomerId = visit.customer_id ? String(visit.customer_id) : '';
        formCustomerSearch = visit.customer?.name || visit.customer_name_snapshot || '';
        formCustomerPicId = visit.customer_pic_id ? String(visit.customer_pic_id) : '';
        formSalesUserId = visit.primary_sales_user_id ? String(visit.primary_sales_user_id) : '';
        formSupportMarketing = Array.isArray(visit.support_marketing)
            ? visit.support_marketing.map(item => String(item).trim().toUpperCase()).filter(item => item && item !== 'NONE')
            : [];
        formCompanionSales = Array.isArray(visit.companion_sales)
            ? visit.companion_sales.map(item => String(item).trim().toUpperCase()).filter(item => item && item !== 'NONE')
            : [];

        formAgenda = visit.agenda || '';
        formPrevisitNotes = visit.previsit_notes || '';
        formStatus = options.forceStatus || visit.status || 'Planned';

        if (visit.principal_reviews && visit.principal_reviews.length > 0) {
            formReviews = visit.principal_reviews.map(rev => ({
                principal_id: rev.principal_id ? String(rev.principal_id) : '',
                principal_name: rev.principal_name_snapshot || principals.find(p => String(p.id) === String(rev.principal_id))?.name || '',
                exposure_type: rev.exposure_type || 'Introduction',
                opportunity_status: rev.opportunity_status || 'Interested',
                notes: rev.notes || '',
                productSearch: '',
                productResults: [],
                productLoading: false,
                productError: '',
                products: (rev.products || []).map(prod => ({
                    product_id: prod.product_id,
                    catalogue: prod.product_catalogue,
                    description: prod.product_description_snapshot || ''
                }))
            }));
        } else {
            formReviews = [
                { principal_id: '', principal_name: '', exposure_type: 'Introduction', opportunity_status: 'Interested', notes: '', products: [] }
            ];
        }

        formPostvisitResult = visit.postvisit_result || '';

        if (visit.action_plans && visit.action_plans.length > 0) {
            formActionPlans = visit.action_plans.map(plan => ({
                task_description: plan.task_description || '',
                owner_user_id: plan.owner_user_id ? String(plan.owner_user_id) : '',
                due_date: plan.due_date ? String(plan.due_date).substring(0, 10) : '',
                priority: plan.priority || 'Normal'
            }));
        } else {
            formActionPlans = [
                { task_description: '', owner_user_id: formSalesUserId, due_date: '', priority: 'Normal' }
            ];
        }

        isModalOpen = true;
    }

    export function openDuplicate(visit) {
        if (!visit) return;

        editingVisit = null;
        currentStep = 1;
        formError = '';

        formTitle = `${visit.title} (Follow-up)`;
        formDate = new Date().toISOString().split('T')[0];
        formStartTime = visit.start_time ? String(visit.start_time).substring(0, 5) : '10:00';
        formVisitType = visit.visit_type || 'Offline Visit';
        formVisitPurpose = 'Follow-up / Relationship';

        formCustomerId = visit.customer_id ? String(visit.customer_id) : '';
        formCustomerSearch = visit.customer?.name || visit.customer_name_snapshot || '';
        formCustomerPicId = visit.customer_pic_id ? String(visit.customer_pic_id) : '';
        formSalesUserId = visit.primary_sales_user_id ? String(visit.primary_sales_user_id) : '';
        formSupportMarketing = Array.isArray(visit.support_marketing)
            ? visit.support_marketing.map(item => String(item).trim().toUpperCase()).filter(item => item && item !== 'NONE')
            : [];
        formCompanionSales = Array.isArray(visit.companion_sales)
            ? visit.companion_sales.map(item => String(item).trim().toUpperCase()).filter(item => item && item !== 'NONE')
            : [];

        formAgenda = visit.agenda || '';
        formPrevisitNotes = visit.previsit_notes || '';
        formStatus = 'Planned';

        if (visit.principal_reviews && visit.principal_reviews.length > 0) {
            formReviews = visit.principal_reviews.map(rev => ({
                principal_id: rev.principal_id ? String(rev.principal_id) : '',
                principal_name: rev.principal_name_snapshot || principals.find(p => String(p.id) === String(rev.principal_id))?.name || '',
                exposure_type: 'Follow-up',
                opportunity_status: rev.opportunity_status || 'Interested',
                notes: '',
                productSearch: '',
                productResults: [],
                productLoading: false,
                productError: '',
                products: (rev.products || []).map(prod => ({
                    product_id: prod.product_id,
                    catalogue: prod.product_catalogue,
                    description: prod.product_description_snapshot || ''
                }))
            }));
        } else {
            formReviews = [
                { principal_id: '', principal_name: '', exposure_type: 'Introduction', opportunity_status: 'Interested', notes: '', products: [] }
            ];
        }

        formPostvisitResult = '';
        formActionPlans = [
            { task_description: '', owner_user_id: formSalesUserId, due_date: '', priority: 'Normal' }
        ];

        isModalOpen = true;
    }

    function closeModal() {
        isModalOpen = false;
        editingVisit = null;
    }

    function toggleCompanionSales(initial) {
        const code = String(initial || '').trim().toUpperCase();
        if (!code || code === 'NONE') {
            formCompanionSales = [];
            return;
        }
        if (formCompanionSales.includes(code)) {
            formCompanionSales = formCompanionSales.filter(item => item !== code);
        } else {
            formCompanionSales = [...formCompanionSales, code];
        }
    }

    function toggleSupportMarketing(initial) {
        const code = String(initial || '').trim().toUpperCase();
        if (!code || code === 'NONE') {
            formSupportMarketing = [];
        formCompanionSales = [];
            return;
        }

        if (formSupportMarketing.includes(code)) {
            formSupportMarketing = formSupportMarketing.filter(item => item !== code);
        } else {
            formSupportMarketing = [...formSupportMarketing, code];
        }
    }

    function clearCustomerSelection() {
        const previousSalesUserId = formSalesUserId;

        formCustomerId = '';
        formCustomerPicId = '';
        formSalesUserId = '';

        formActionPlans = formActionPlans.map(plan => ({
            ...plan,
            owner_user_id:
                !plan.owner_user_id || String(plan.owner_user_id) === String(previousSalesUserId)
                    ? ''
                    : plan.owner_user_id
        }));
    }

    function selectCustomer(customer) {
        const previousSalesUserId = formSalesUserId;
        const salesInitial = String(customer.sales_initial || '').trim().toUpperCase();

        const matchedSalesUser = salesUsers.find(
            user => String(user.initial || '').trim().toUpperCase() === salesInitial
        );

        formCustomerId = String(customer.id);
        formCustomerSearch = customer.name;
        formCustomerPicId = '';
        formSalesUserId = matchedSalesUser ? String(matchedSalesUser.id) : '';

        formActionPlans = formActionPlans.map(plan => ({
            ...plan,
            owner_user_id:
                !plan.owner_user_id || String(plan.owner_user_id) === String(previousSalesUserId)
                    ? formSalesUserId
                    : plan.owner_user_id
        }));
    }

    function clearPrincipalSelection(review) {
        if (review.productSearchTimer) {
            clearTimeout(review.productSearchTimer);
        }

        review.productRequestId = (review.productRequestId || 0) + 1;
        review.principal_id = '';
        review.products = [];
        review.productSearch = '';
        review.productResults = [];
        review.productLoading = false;
        review.productError = '';
    }

    function selectPrincipal(review, principal) {
        if (review.productSearchTimer) {
            clearTimeout(review.productSearchTimer);
        }

        review.productRequestId = (review.productRequestId || 0) + 1;
        review.principal_id = String(principal.id);
        review.principal_name = principal.name;
        review.products = [];
        review.productSearch = '';
        review.productResults = [];
        review.productLoading = false;
        review.productError = '';
    }

    function searchProducts(review) {
        if (review.productSearchTimer) {
            clearTimeout(review.productSearchTimer);
        }

        const query = String(review.productSearch || '').trim();

        review.productResults = [];
        review.productError = '';

        if (!review.principal_id || query.length < 2) {
            review.productRequestId = (review.productRequestId || 0) + 1;
            review.productLoading = false;
            return;
        }

        const requestId = (review.productRequestId || 0) + 1;
        const principalId = String(review.principal_id);

        review.productRequestId = requestId;
        review.productLoading = true;

        review.productSearchTimer = setTimeout(async () => {
            try {
                const params = new URLSearchParams({
                    principal_id: principalId,
                    q: query
                });

                const response = await fetch(`/products/search?${params}`, {
                    headers: { Accept: 'application/json' }
                });

                if (!response.ok) {
                    throw new Error('Pencarian produk gagal.');
                }

                const results = await response.json();

                if (
                    review.productRequestId !== requestId ||
                    String(review.principal_id) !== principalId ||
                    String(review.productSearch || '').trim() !== query
                ) {
                    return;
                }

                const selectedCatalogues = (review.products || [])
                    .map(product =>
                        String(product.catalogue || '').trim().toLowerCase()
                    );

                review.productResults = results.filter(product =>
                    !selectedCatalogues.includes(
                        String(product.catalogue || '').trim().toLowerCase()
                    )
                );
            } catch (error) {
                if (review.productRequestId === requestId) {
                    review.productError = error.message || 'Gagal mencari produk.';
                }
            } finally {
                if (review.productRequestId === requestId) {
                    review.productLoading = false;
                }
            }
        }, 300);
    }

    function addReviewProduct(review, product) {
        const catalogue = String(product.catalogue || '').trim().toLowerCase();

        const duplicate = (review.products || []).some(item =>
            String(item.catalogue || '').trim().toLowerCase() === catalogue
        );

        if (duplicate) return;

        review.products = [
            ...(review.products || []),
            {
                product_id: String(product.id),
                catalogue: product.catalogue,
                description: product.description || product.name || ''
            }
        ];

        review.productSearch = '';
        review.productResults = [];
        review.productError = '';
    }

    function removeReviewProduct(review, productId) {
        review.products = (review.products || []).filter(
            product => String(product.product_id) !== String(productId)
        );
    }

    function setQuickDate(days) {
        const d = new Date();
        d.setDate(d.getDate() + days);
        formDate = d.toISOString().split('T')[0];
    }

    function addReviewItem() {
        formReviews = [
            ...formReviews,
            { principal_id: '', principal_name: '', exposure_type: 'Introduction', opportunity_status: 'Interested', notes: '', products: [] }
        ];
    }

    function removeReviewItem(index) {
        if (formReviews.length > 1) {
            formReviews = formReviews.filter((_, i) => i !== index);
        }
    }

    function addActionPlanItem() {
        formActionPlans = [
            ...formActionPlans,
            { task_description: '', owner_user_id: formSalesUserId, due_date: '', priority: 'Normal' }
        ];
    }

    function removeActionPlanItem(index) {
        if (formActionPlans.length > 1) {
            formActionPlans = formActionPlans.filter((_, i) => i !== index);
        }
    }

    function validateStep(step) {
        formError = '';
        if (step === 1) {
            if (!formTitle.trim()) {
                formError = 'Judul kunjungan (Visit Title) wajib diisi.';
                return false;
            }
            if (!formCustomerId) {
                formError = 'Customer wajib dipilih.';
                return false;
            }
            if (!formDate || !formStartTime) {
                formError = 'Tanggal dan jam mulai kunjungan wajib diisi.';
                return false;
            }
        } else if (step === 2) {
            if (formReviews.length === 0) {
                formError = 'Minimal 1 Principal Review wajib diisi.';
                return false;
            }
            for (let rev of formReviews) {
                if (!rev.principal_id) {
                    formError = 'Institution / Principal wajib dipilih pada semua review.';
                    return false;
                }
            }
        }
        return true;
    }

    function nextStep() {
        if (validateStep(currentStep)) {
            if (currentStep < 3) currentStep++;
        }
    }

    function prevStep() {
        formError = '';
        if (currentStep > 1) currentStep--;
    }

    function submitVisit() {
        if (!validateStep(1) || !validateStep(2)) return;

        if (!isEditing && !['Draft', 'Planned'].includes(formStatus)) {
            currentStep = 1;
            formError = 'Kunjungan baru hanya dapat dibuat dengan status Draft atau Planned.';
            return;
        }

        if (formStatus === 'Completed') {
            if (!formPostvisitResult.trim()) {
                currentStep = 3;
                formError = 'Aturan Perusahaan: Post-visit Result wajib dilengkapi jika status kunjungan Completed.';
                return;
            }
            const validPlans = formActionPlans.filter(p => p.task_description.trim());
            if (validPlans.length === 0) {
                currentStep = 3;
                formError = 'Aturan Perusahaan: Action Plan wajib diisi jika status kunjungan Completed.';
                return;
            }
        }

        isSubmitting = true;
        formError = '';

        const payload = {
            title: formTitle,
            visit_date: formDate,
            start_time: formStartTime,
            visit_type: formVisitType,
            visit_purpose: formVisitPurpose,
            customer_id: formCustomerId,
            customer_pic_id: formCustomerPicId || null,
            support_marketing: formSupportMarketing,
            companion_sales: formCompanionSales,
            agenda: formAgenda,
            previsit_notes: formPrevisitNotes,
            status: formStatus,
            postvisit_result: formPostvisitResult,
            principal_reviews: formReviews.map(r => ({
                principal_id: r.principal_id || null,
                principal_name: r.principal_name,
                exposure_type: r.exposure_type,
                opportunity_status: r.opportunity_status,
                notes: r.notes || null,
                products: r.products || []
            })),
            action_plans: formActionPlans.filter(p => p.task_description.trim())
        };

        const requestOptions = {
            onSuccess: () => {
                isSubmitting = false;
                isModalOpen = false;
                editingVisit = null;
            },
            onError: (err) => {
                isSubmitting = false;
                formError = Object.values(err)[0] || 'Gagal menyimpan kunjungan.';
            }
        };

        if (isEditing && editingVisit?.id) {
            router.put(`/visits/${editingVisit.id}`, payload, requestOptions);
        } else {
            router.post('/visits', payload, requestOptions);
        }
    }
</script>

{#if isModalOpen}
    <button
        type="button"
        class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-2xs transition-opacity w-full h-full cursor-default"
        onclick={closeModal}
        aria-label="Tutup form"
    ></button>

    <div class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4 pointer-events-none">
        <div class="bg-white rounded-xl sm:rounded-2xl border border-slate-200 shadow-2xl w-full max-w-2xl max-h-[95vh] sm:max-h-[90vh] flex flex-col pointer-events-auto overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <!-- Modal Header -->
            <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-slate-100 flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <h3 class="text-sm sm:text-base font-bold text-slate-900 truncate">
                        {isEditing ? `Edit Kunjungan (${editingVisit.visit_code})` : 'Catat Kunjungan Baru'}
                    </h3>
                    <p class="text-[11px] sm:text-xs text-slate-400 truncate">
                        {isEditing ? 'Perbarui informasi, status, atau hasil kunjungan' : 'Ikuti 3 tahapan pengisian formulir kunjungan'}
                    </p>
                </div>
                <button
                    type="button"
                    onclick={closeModal}
                    class="p-1.5 rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors shrink-0"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- 3 Steps Tabs -->
            <div class="grid grid-cols-3 border-b border-slate-100 bg-slate-50/60 text-[11px] sm:text-xs font-medium">
                <button
                    type="button"
                    onclick={() => currentStep = 1}
                    class="py-2.5 px-2 sm:py-3 sm:px-4 border-b-2 flex items-center justify-center gap-1.5 sm:gap-2 transition-all {currentStep === 1 ? 'border-slate-900 text-slate-900 bg-white font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800'}"
                >
                    <span class="w-4 h-4 sm:w-5 sm:h-5 rounded-full flex items-center justify-center text-[10px] shrink-0 {currentStep === 1 ? 'bg-slate-900 text-white' : 'bg-slate-200 text-slate-600'}">1</span>
                    <span class="truncate">Info Kunjungan</span>
                </button>
                <button
                    type="button"
                    onclick={() => { if (validateStep(1)) currentStep = 2; }}
                    class="py-2.5 px-2 sm:py-3 sm:px-4 border-b-2 flex items-center justify-center gap-1.5 sm:gap-2 transition-all {currentStep === 2 ? 'border-slate-900 text-slate-900 bg-white font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800'}"
                >
                    <span class="w-4 h-4 sm:w-5 sm:h-5 rounded-full flex items-center justify-center text-[10px] shrink-0 {currentStep === 2 ? 'bg-slate-900 text-white' : 'bg-slate-200 text-slate-600'}">2</span>
                    <span class="truncate">Principal & Produk</span>
                </button>
                <button
                    type="button"
                    onclick={() => { if (validateStep(1) && validateStep(2)) currentStep = 3; }}
                    class="py-2.5 px-2 sm:py-3 sm:px-4 border-b-2 flex items-center justify-center gap-1.5 sm:gap-2 transition-all {currentStep === 3 ? 'border-slate-900 text-slate-900 bg-white font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800'}"
                >
                    <span class="w-4 h-4 sm:w-5 sm:h-5 rounded-full flex items-center justify-center text-[10px] shrink-0 {currentStep === 3 ? 'bg-slate-900 text-white' : 'bg-slate-200 text-slate-600'}">3</span>
                    <span class="truncate">Follow-up & Hasil</span>
                </button>
            </div>

            <!-- Form Error Notice -->
            {#if formError}
                <div class="mx-4 sm:mx-6 mt-3 sm:mt-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center gap-2">
                    <AlertCircle class="w-4 h-4 shrink-0" />
                    <span>{formError}</span>
                </div>
            {/if}

            <!-- Modal Body -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-6">
                <!-- STEP 1: INFO KUNJUNGAN -->
                {#if currentStep === 1}
                    <div class="flex flex-col gap-4 text-xs">
                        <div>
                            <label for="formTitle" class="block font-semibold text-slate-700 mb-1">Judul Kunjungan (Visit Title) *</label>
                            <input
                                id="formTitle"
                                type="text"
                                bind:value={formTitle}
                                placeholder="Contoh: Demo MICA Advance - Kalbe Farma"
                                class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400"
                            />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="formDate" class="block font-semibold text-slate-700 mb-1">Tanggal Kunjungan *</label>
                                <input
                                    id="formDate"
                                    type="date"
                                    bind:value={formDate}
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400"
                                />
                                <div class="flex items-center gap-1.5 mt-1.5">
                                    <button type="button" onclick={() => setQuickDate(0)} class="px-2 py-0.5 rounded text-[10px] bg-slate-100 hover:bg-slate-200 text-slate-600">Hari Ini</button>
                                    <button type="button" onclick={() => setQuickDate(1)} class="px-2 py-0.5 rounded text-[10px] bg-slate-100 hover:bg-slate-200 text-slate-600">Besok</button>
                                    <button type="button" onclick={() => setQuickDate(7)} class="px-2 py-0.5 rounded text-[10px] bg-slate-100 hover:bg-slate-200 text-slate-600">+7 Hari</button>
                                </div>
                            </div>

                            <div>
                                <label for="formStartTime" class="block font-semibold text-slate-700 mb-1">Waktu Mulai *</label>
                                <select
                                    id="formStartTime"
                                    bind:value={formStartTime}
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400 bg-white"
                                >
                                    {#each timeOptions as t}
                                        <option value={t}>{t}</option>
                                    {/each}
                                </select>
                                <span class="text-[10px] text-slate-400 mt-1 block">Interval setiap 15 menit</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="formVisitType" class="block font-semibold text-slate-700 mb-1">Tipe Kunjungan *</label>
                                <select
                                    id="formVisitType"
                                    bind:value={formVisitType}
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400 bg-white"
                                >
                                    <option value="Offline Visit">Offline Visit</option>
                                    <option value="Online Visit">Online Visit</option>
                                </select>
                            </div>

                            <div>
                                <label for="formVisitPurpose" class="block font-semibold text-slate-700 mb-1">Tujuan Kunjungan</label>
                                <select
                                    id="formVisitPurpose"
                                    bind:value={formVisitPurpose}
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400 bg-white"
                                >
                                    {#each visitPurposes as p}
                                        <option value={p}>{p}</option>
                                    {/each}
                                </select>
                            </div>
                        </div>

                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex flex-col gap-3">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label for="formCustomerId" class="font-semibold text-slate-800">Customer / Institusi *</label>
                                    {#if onOpenMaster}
                                        <button
                                            type="button"
                                            onclick={() => onOpenMaster('customer')}
                                            class="text-[11px] font-medium text-blue-600 hover:text-blue-800 cursor-pointer"
                                        >
                                            + Customer baru
                                        </button>
                                    {/if}
                                </div>
                                <div class="relative">
                                    <input
                                        id="formCustomerId"
                                        type="text"
                                        bind:value={formCustomerSearch}
                                        oninput={clearCustomerSelection}
                                        autocomplete="off"
                                        placeholder="Ketik nama customer atau institusi..."
                                        class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400 bg-white"
                                    />
                                    {#if formCustomerSearch.trim() && !formCustomerId}
                                        <div class="absolute z-30 mt-1 w-full max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                                            {#if filteredCustomers.length > 0}
                                                {#each filteredCustomers as c}
                                                    <button
                                                        type="button"
                                                        onclick={() => selectCustomer(c)}
                                                        class="w-full px-3 py-2 text-left hover:bg-slate-50 border-b border-slate-100 last:border-0"
                                                    >
                                                        <span class="block text-xs font-medium text-slate-800">{c.name}</span>
                                                        {#if c.customer_code}
                                                            <span class="block mt-0.5 text-[10px] text-slate-400">{c.customer_code}</span>
                                                        {/if}
                                                    </button>
                                                {/each}
                                            {:else}
                                                <p class="px-3 py-3 text-xs text-slate-500">Tidak ada customer yang cocok.</p>
                                            {/if}
                                        </div>
                                    {/if}
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label for="formCustomerPicId" class="font-semibold text-slate-700">PIC Customer</label>
                                        {#if onOpenMaster}
                                            <button
                                                type="button"
                                                onclick={() => onOpenMaster('pic')}
                                                class="text-[11px] font-medium text-blue-600 hover:text-blue-800 cursor-pointer"
                                            >
                                                + PIC baru
                                            </button>
                                        {/if}
                                    </div>
                                    <select
                                        id="formCustomerPicId"
                                        bind:value={formCustomerPicId}
                                        class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400 bg-white"
                                    >
                                        <option value="">— Bebas / Belum Ditentukan —</option>
                                        {#each availablePics as pic}
                                            <option value={pic.id}>{pic.name} {pic.position ? `(${pic.position})` : ''}</option>
                                        {/each}
                                    </select>
                                </div>

                                <div>
                                    <label for="formSalesUserId" class="block font-semibold text-slate-700 mb-1">PIC Sales Penanggung Jawab</label>
                                    <input
                                        id="formSalesUserId"
                                        type="text"
                                        value={selectedSalesUser ? `${selectedSalesUser.name} (${selectedSalesUser.initial})` : formCustomerId ? 'PIC Sales belum terdaftar' : 'Otomatis dari Customer Master'}
                                        readonly
                                        class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs bg-slate-100 text-slate-600 cursor-not-allowed"
                                    />
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="block font-semibold text-slate-700">Rekan Kunjungan / Co-Sales <span class="font-normal text-slate-400">(Opsional, jika lebih dari 1 orang)</span></span>
                                {#if formCompanionSales.length > 0}
                                    <button
                                        type="button"
                                        onclick={() => toggleCompanionSales('NONE')}
                                        class="text-[11px] text-slate-500 hover:text-slate-800 underline cursor-pointer"
                                    >
                                        Reset
                                    </button>
                                {/if}
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 p-2.5 rounded-lg border border-slate-200 bg-slate-50/60 max-h-40 overflow-y-auto">
                                {#each salesUsers.filter((u) => u.initial && String(u.id) !== String(formSalesUserId)) as u (u.id)}
                                    {@const code = String(u.initial).trim().toUpperCase()}
                                    <label class="inline-flex items-center gap-2 px-2 py-1 rounded-md hover:bg-white cursor-pointer select-none transition-colors">
                                        <input
                                            type="checkbox"
                                            checked={formCompanionSales.includes(code)}
                                            onchange={() => toggleCompanionSales(code)}
                                            class="rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                                        />
                                        <span class="text-xs text-slate-700 truncate">
                                            <strong class="font-semibold text-slate-900">{code}</strong> — {u.name}
                                        </span>
                                    </label>
                                {/each}
                            </div>
                        </div>

                        <div>
                            <span class="block font-semibold text-slate-700 mb-1.5">Support Marketing</span>
                            <div class="flex flex-wrap items-center gap-4">
                                <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                                    <input
                                        type="checkbox"
                                        checked={formSupportMarketing.length === 0}
                                        onchange={() => toggleSupportMarketing('NONE')}
                                        class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-xs font-medium text-slate-800">None</span>
                                </label>

                                {#each supportMarketingUsers as sup}
                                    {@const initCode = String(sup.initial || '').trim().toUpperCase()}
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer select-none" title={sup.name}>
                                        <input
                                            type="checkbox"
                                            checked={formSupportMarketing.includes(initCode)}
                                            onchange={() => toggleSupportMarketing(initCode)}
                                            class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        <span class="text-xs font-medium text-slate-700">{initCode}</span>
                                    </label>
                                {/each}
                            </div>
                            <span class="text-[11px] text-slate-400 mt-1 block">Bisa pilih lebih dari satu. Default = None.</span>
                        </div>

                        <div>
                            <label for="formAgenda" class="block font-semibold text-slate-700 mb-1">Agenda Pertemuan</label>
                            <textarea
                                id="formAgenda"
                                bind:value={formAgenda}
                                rows="2"
                                placeholder="Rincian topik atau tujuan agenda yang akan dibahas..."
                                class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400"
                            ></textarea>
                        </div>

                        <div>
                            <label for="formPrevisitNotes" class="block font-semibold text-slate-700 mb-1">Pre-visit Notes</label>
                            <textarea
                                id="formPrevisitNotes"
                                bind:value={formPrevisitNotes}
                                rows="2"
                                placeholder="Persiapan dokumen, sertifikat analisa, atau sampel produk..."
                                class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400"
                            ></textarea>
                        </div>

                        <div>
                            <label for="formStatus" class="block font-semibold text-slate-700 mb-1">Status Kunjungan *</label>
                            <select
                                id="formStatus"
                                bind:value={formStatus}
                                class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400 bg-white font-semibold"
                            >
                                <option value="Draft">Draft</option>
                                <option value="Planned">Planned (Terencana)</option>
                                {#if isEditing}
                                    <option value="Confirmed">Confirmed (Terkonfirmasi)</option>
                                    <option value="Completed">Completed (Selesai)</option>
                                    <option value="Cancelled">Cancelled (Dibatalkan)</option>
                                {/if}
                            </select>

                            {#if formStatus === 'Completed'}
                                <span class="text-[11px] text-amber-600 font-medium mt-1 block">
                                    Status Completed mewajibkan Post-visit Result dan Action Plan.
                                </span>
                            {/if}
                        </div>
                    </div>
                {/if}

                <!-- STEP 2: PRINCIPAL & PRODUK -->
                {#if currentStep === 2}
                    <div class="flex flex-col gap-4 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <span class="font-semibold text-slate-800 text-sm">Principal Review</span>
                                <p class="text-[11px] text-slate-400">Minimal satu principal wajib dicatat pada kunjungan ini</p>
                            </div>
                            <button
                                type="button"
                                onclick={addReviewItem}
                                class="flex items-center gap-1 text-xs font-medium text-slate-700 hover:text-slate-900 border border-slate-200 px-2.5 py-1.5 rounded-lg bg-white shadow-2xs hover:bg-slate-50 shrink-0"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Tambah Principal</span>
                            </button>
                        </div>

                        {#each formReviews as review, index}
                            <div class="border border-slate-200 rounded-xl p-3.5 sm:p-4 bg-slate-50/50 flex flex-col gap-3">
                                <div class="flex items-center justify-between pb-2 border-b border-slate-200/80">
                                    <span class="font-bold text-slate-800 text-xs">Review #{index + 1}</span>
                                    {#if formReviews.length > 1}
                                        <button
                                            type="button"
                                            onclick={() => removeReviewItem(index)}
                                            class="text-rose-500 hover:text-rose-700 p-1 transition-colors"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    {/if}
                                </div>

                                <div>
                                    <label for="reviewPrincipal{index}" class="block font-semibold text-slate-700 mb-1">Institution / Principal *</label>
                                    <div class="relative">
                                        <input
                                            id="reviewPrincipal{index}"
                                            type="text"
                                            bind:value={review.principal_name}
                                            oninput={() => clearPrincipalSelection(review)}
                                            autocomplete="off"
                                            placeholder="Ketik nama institution atau principal..."
                                            class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400 bg-white"
                                        />
                                        {#if review.principal_name.trim() && !principals.some(p => p.name.trim().toLocaleLowerCase() === review.principal_name.trim().toLocaleLowerCase() && String(p.id) === String(review.principal_id))}
                                            <div class="absolute z-30 mt-1 w-full max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                                                {#each principals.filter(p => p.name.toLocaleLowerCase().includes(review.principal_name.trim().toLocaleLowerCase())).slice(0, 8) as p}
                                                    <button
                                                        type="button"
                                                        onclick={() => selectPrincipal(review, p)}
                                                        class="w-full px-3 py-2 text-left text-xs text-slate-800 hover:bg-slate-50 border-b border-slate-100 last:border-0"
                                                    >
                                                        {p.name}
                                                    </button>
                                                {/each}
                                                {#if !principals.some(p => p.name.toLocaleLowerCase().includes(review.principal_name.trim().toLocaleLowerCase()))}
                                                    <p class="px-3 py-3 text-xs text-slate-500">Tidak ada institution / principal yang cocok.</p>
                                                {/if}
                                            </div>
                                        {/if}
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="reviewExposure{index}" class="block font-semibold text-slate-700 mb-1">Exposure Type</label>
                                        <select
                                            id="reviewExposure{index}"
                                            bind:value={review.exposure_type}
                                            class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400 bg-white"
                                        >
                                            {#each exposureTypes as exp}
                                                <option value={exp}>{exp}</option>
                                            {/each}
                                        </select>
                                    </div>

                                    <div>
                                        <label for="reviewOpportunity{index}" class="block font-semibold text-slate-700 mb-1">Opportunity Status</label>
                                        <select
                                            id="reviewOpportunity{index}"
                                            bind:value={review.opportunity_status}
                                            class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400 bg-white"
                                        >
                                            {#each opportunityStatuses as opp}
                                                <option value={opp}>{opp}</option>
                                            {/each}
                                        </select>
                                    </div>
                                </div>

                                <div class="border-t border-slate-200 pt-3 flex flex-col gap-2">
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <label for="productSearch{index}" class="font-semibold text-slate-700">Produk Principal</label>
                                            {#if onOpenMaster}
                                                <button
                                                    type="button"
                                                    onclick={() => onOpenMaster('product')}
                                                    class="text-[11px] font-medium text-blue-600 hover:text-blue-800 cursor-pointer"
                                                >
                                                    + Produk baru
                                                </button>
                                            {/if}
                                        </div>

                                        {#if review.principal_id}
                                            <input
                                                id="productSearch{index}"
                                                type="text"
                                                bind:value={review.productSearch}
                                                oninput={() => searchProducts(review)}
                                                autocomplete="off"
                                                placeholder="Cari Catalogue atau Description (min. 2 karakter)..."
                                                class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400 bg-white"
                                            />

                                            {#if review.productLoading}
                                                <p class="text-[10px] text-slate-400 mt-1">Mencari produk...</p>
                                            {:else if review.productError}
                                                <p class="text-[10px] text-rose-600 mt-1">{review.productError}</p>
                                            {:else if (review.productSearch || '').trim().length >= 2 && review.productResults?.length > 0}
                                                <div class="mt-1 max-h-48 overflow-y-auto rounded-lg border border-slate-200 bg-white">
                                                    {#each review.productResults as product}
                                                        <button
                                                            type="button"
                                                            onclick={() => addReviewProduct(review, product)}
                                                            class="w-full px-3 py-2 text-left border-b border-slate-100 last:border-0 hover:bg-slate-50"
                                                        >
                                                            <span class="block font-semibold text-slate-800">{product.catalogue}</span>
                                                            <span class="block text-[10px] text-slate-500">{product.description || product.name}</span>
                                                        </button>
                                                    {/each}
                                                </div>
                                            {:else if (review.productSearch || '').trim().length >= 2}
                                                <p class="text-[10px] text-slate-400 mt-1">Tidak ada produk yang cocok atau semua hasil sudah dipilih.</p>
                                            {/if}
                                        {:else}
                                            <p class="text-[10px] text-slate-400">Pilih Principal terlebih dahulu untuk mencari produk.</p>
                                        {/if}
                                    </div>

                                    {#if review.products?.length > 0}
                                        <div class="flex flex-col gap-1.5">
                                            <span class="text-[10px] font-semibold text-slate-500">Produk dipilih ({review.products.length})</span>

                                            {#each review.products as product}
                                                <div class="flex items-start justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2">
                                                    <div class="min-w-0">
                                                        <span class="block text-xs font-semibold text-slate-800">{product.catalogue}</span>
                                                        <span class="block text-[10px] text-slate-500">{product.description}</span>
                                                    </div>

                                                    <button
                                                        type="button"
                                                        onclick={() => removeReviewProduct(review, product.product_id)}
                                                        class="shrink-0 text-rose-500 hover:text-rose-700"
                                                        aria-label="Hapus produk"
                                                    >
                                                        <X class="w-3.5 h-3.5" />
                                                    </button>
                                                </div>
                                            {/each}
                                        </div>
                                    {/if}
                                </div>

                                <div>
                                    <label for="reviewNotes{index}" class="block font-semibold text-slate-700 mb-1">Notes</label>
                                    <textarea
                                        id="reviewNotes{index}"
                                        bind:value={review.notes}
                                        rows="2"
                                        placeholder="Catatan diskusi produk, kebutuhan spesifik, atau feedback customer..."
                                        class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400 bg-white"
                                    ></textarea>
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}

                <!-- STEP 3: FOLLOW UP & HASIL -->
                {#if currentStep === 3}
                    <div class="flex flex-col gap-4 text-xs">
                        <!-- Card 1: Post-visit Result -->
                        <div class="border border-slate-200 rounded-xl p-3.5 sm:p-4 bg-white flex flex-col gap-2.5">
                            <div class="pb-2 border-b border-slate-100">
                                <h4 class="text-sm font-bold text-slate-900">Post-visit Result (Hasil Kunjungan)</h4>
                            </div>

                            <div>
                                <label for="formPostvisitResult" class="block font-semibold text-slate-700 mb-1">Ringkasan Hasil Pertemuan</label>
                                <textarea
                                    id="formPostvisitResult"
                                    bind:value={formPostvisitResult}
                                    rows="3"
                                    placeholder="Tuliskan poin penting hasil meeting atau temuan aktual di lapangan..."
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400"
                                ></textarea>
                                <span class="text-[11px] text-slate-400 mt-1 block">Wajib dilengkapi jika visit berstatus Completed.</span>
                            </div>
                        </div>

                        <!-- Card 2: Action Plan -->
                        <div class="border border-slate-200 rounded-xl p-3.5 sm:p-4 bg-white flex flex-col gap-3">
                            <div class="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-slate-100">
                                <div>
                                    <h4 class="text-sm font-bold text-slate-900">Action Plan</h4>
                                    <p class="text-[11px] text-slate-400 mt-0.5">Action Plan wajib ada jika status Visit = Completed.</p>
                                </div>
                                <button
                                    type="button"
                                    onclick={addActionPlanItem}
                                    class="flex items-center gap-1 text-xs font-medium text-slate-700 hover:text-slate-900 border border-slate-200 px-2.5 py-1.5 rounded-lg bg-white shadow-2xs hover:bg-slate-50 shrink-0"
                                >
                                    <Plus class="w-3.5 h-3.5" />
                                    <span>Tambah Action Plan</span>
                                </button>
                            </div>

                            {#each formActionPlans as plan, index}
                                <div class="p-3 bg-slate-50/70 border border-slate-200/80 rounded-xl flex flex-col gap-3">
                                    {#if formActionPlans.length > 1}
                                        <div class="flex items-center justify-between pb-1.5 border-b border-slate-200/70">
                                            <span class="font-semibold text-slate-700 text-xs">Action Plan #{index + 1}</span>
                                            <button
                                                type="button"
                                                onclick={() => removeActionPlanItem(index)}
                                                class="text-rose-500 hover:text-rose-700 p-1"
                                                aria-label="Hapus action plan"
                                            >
                                                <Trash2 class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    {/if}

                                    <div>
                                        <label for="planTask{index}" class="block font-semibold text-slate-700 mb-1">Action</label>
                                        <input
                                            id="planTask{index}"
                                            type="text"
                                            bind:value={plan.task_description}
                                            placeholder="Contoh: Send quotation MICA Advance"
                                            class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400 bg-white"
                                        />
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label for="planOwner{index}" class="block font-semibold text-slate-700 mb-1">Owner</label>
                                            <select
                                                id="planOwner{index}"
                                                bind:value={plan.owner_user_id}
                                                class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400 bg-white"
                                            >
                                                <option value="">— Pilih Owner —</option>
                                                {#each salesUsers as u}
                                                    <option value={String(u.id)}>{u.initial ? `${u.initial} — ${u.name}` : u.name}</option>
                                                {/each}
                                            </select>
                                        </div>

                                        <div>
                                            <label for="planDueDate{index}" class="block font-semibold text-slate-700 mb-1">Due Date</label>
                                            <input
                                                id="planDueDate{index}"
                                                type="date"
                                                bind:value={plan.due_date}
                                                class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400 bg-white"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label for="planPriority{index}" class="block font-semibold text-slate-700 mb-1">Priority</label>
                                        <select
                                            id="planPriority{index}"
                                            bind:value={plan.priority}
                                            class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-slate-400 bg-white"
                                        >
                                            <option value="Normal">Normal</option>
                                            <option value="Low">Low</option>
                                            <option value="High">High</option>
                                            <option value="Critical">Critical</option>
                                        </select>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    </div>
                {/if}
            </div>

            <!-- Modal Stepper Footer -->
            <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-t border-slate-100 bg-slate-50/70 flex items-center justify-between gap-2">
                <div>
                    <span class="text-xs text-slate-500 font-medium">Langkah {currentStep} dari 3</span>
                </div>

                <div class="flex items-center gap-2">
                    {#if currentStep > 1}
                        <button
                            type="button"
                            onclick={prevStep}
                            class="flex items-center gap-1.5 px-3 py-2 text-xs font-medium border border-slate-200 bg-white hover:bg-slate-50 rounded-lg text-slate-700 transition-colors"
                        >
                            <ArrowLeft class="w-3.5 h-3.5" />
                            <span>Kembali</span>
                        </button>
                    {/if}

                    {#if currentStep < 3}
                        <button
                            type="button"
                            onclick={nextStep}
                            class="flex items-center gap-1.5 px-3.5 sm:px-4 py-2 text-xs font-medium bg-slate-900 hover:bg-slate-800 text-white rounded-lg transition-colors cursor-pointer"
                        >
                            <span>Lanjut<span class="hidden sm:inline">{' '}ke Langkah {currentStep + 1}</span></span>
                            <ArrowRight class="w-3.5 h-3.5" />
                        </button>
                    {:else}
                        <button
                            type="button"
                            disabled={isSubmitting}
                            onclick={submitVisit}
                            class="flex items-center gap-1.5 px-3.5 sm:px-4 py-2 text-xs font-medium bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-lg transition-colors cursor-pointer"
                        >
                            <CheckCircle2 class="w-3.5 h-3.5" />
                            <span>{isSubmitting ? 'Menyimpan...' : isEditing ? 'Simpan Perubahan' : 'Simpan Kunjungan'}</span>
                        </button>
                    {/if}
                </div>
            </div>
        </div>
    </div>
{/if}
