<script>
    import { X, Building2, UserPlus, PackagePlus, CheckCircle2, AlertCircle } from '@lucide/svelte';
    import { router } from '@inertiajs/svelte';

    let {
        customers = [],
        principals = [],
        salesUsers = []
    } = $props();

    let isOpen = $state(false);
    let activeTab = $state('customer'); // 'customer' | 'pic' | 'product'
    let isSubmitting = $state(false);
    let errorMsg = $state('');
    let successMsg = $state('');

    // Form Customer
    let custName = $state('');
    let custCode = $state('');
    let custSalesInitial = $state('');
    let custSector = $state('');
    let custCity = $state('');
    let custPhone = $state('');

    // Form PIC
    let picCustomerId = $state('');
    let picName = $state('');
    let picPosition = $state('');
    let picDepartment = $state('');
    let picPhone = $state('');
    let picEmail = $state('');

    // Form Product
    let prodPrincipalId = $state('');
    let prodCatalogue = $state('');
    let prodDescription = $state('');
    let prodCategory = $state('');
    let prodUnit = $state('');

    let uniqueSalesInitials = $derived.by(() => {
        const seen = new Set();
        const list = [];
        for (const u of salesUsers) {
            const init = String(u.initial || '').trim().toUpperCase();
            if (init && !seen.has(init)) {
                seen.add(init);
                list.push({ initial: init, name: u.name });
            }
        }
        return list;
    });

    export function open(tab = 'customer') {
        activeTab = tab;
        errorMsg = '';
        successMsg = '';
        isOpen = true;
    }

    function close() {
        isOpen = false;
    }

    function submitCustomer(e) {
        e.preventDefault();
        errorMsg = '';
        successMsg = '';
        isSubmitting = true;

        router.post('/master/customers', {
            name: custName,
            customer_code: custCode,
            sales_initial: custSalesInitial,
            sector: custSector,
            city: custCity,
            phone: custPhone
        }, {
            preserveScroll: true,
            onSuccess: () => {
                isSubmitting = false;
                successMsg = `Customer "${custName}" berhasil disimpan.`;
                custName = '';
                custCode = '';
                custSector = '';
                custCity = '';
                custPhone = '';
            },
            onError: (err) => {
                isSubmitting = false;
                errorMsg = Object.values(err)[0] || 'Gagal menyimpan customer.';
            }
        });
    }

    function submitPic(e) {
        e.preventDefault();
        errorMsg = '';
        successMsg = '';
        isSubmitting = true;

        router.post('/master/customer-pics', {
            customer_id: picCustomerId,
            name: picName,
            position: picPosition,
            department: picDepartment,
            phone: picPhone,
            email: picEmail
        }, {
            preserveScroll: true,
            onSuccess: () => {
                isSubmitting = false;
                successMsg = `PIC "${picName}" berhasil ditambahkan.`;
                picName = '';
                picPosition = '';
                picDepartment = '';
                picPhone = '';
                picEmail = '';
            },
            onError: (err) => {
                isSubmitting = false;
                errorMsg = Object.values(err)[0] || 'Gagal menyimpan PIC.';
            }
        });
    }

    function submitProduct(e) {
        e.preventDefault();
        errorMsg = '';
        successMsg = '';
        isSubmitting = true;

        router.post('/master/products', {
            principal_id: prodPrincipalId,
            catalogue: prodCatalogue,
            description: prodDescription,
            category: prodCategory,
            unit: prodUnit
        }, {
            preserveScroll: true,
            onSuccess: () => {
                isSubmitting = false;
                successMsg = `Produk "${prodCatalogue.toUpperCase()}" berhasil ditambahkan.`;
                prodCatalogue = '';
                prodDescription = '';
                prodCategory = '';
                prodUnit = '';
            },
            onError: (err) => {
                isSubmitting = false;
                errorMsg = Object.values(err)[0] || 'Gagal menyimpan produk.';
            }
        });
    }
</script>

{#if isOpen}
    <button
        type="button"
        class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-2xs w-full h-full cursor-default"
        onclick={close}
        aria-label="Tutup modal master data"
    ></button>

    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 pointer-events-none">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-lg flex flex-col pointer-events-auto overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Quick Master Data</h3>
                    <p class="text-xs text-slate-400">Tambah Customer, PIC Pelanggan, atau Katalog Produk baru</p>
                </div>
                <button type="button" onclick={close} class="p-1 rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-100">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Tabs -->
            <div class="grid grid-cols-3 border-b border-slate-100 bg-slate-50/70 text-xs font-medium">
                <button
                    type="button"
                    onclick={() => { activeTab = 'customer'; errorMsg = ''; successMsg = ''; }}
                    class="py-2.5 px-3 border-b-2 flex items-center justify-center gap-1.5 transition-colors cursor-pointer {activeTab === 'customer' ? 'border-slate-900 text-slate-900 bg-white font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800'}"
                >
                    <Building2 class="w-3.5 h-3.5" />
                    <span>Customer</span>
                </button>
                <button
                    type="button"
                    onclick={() => { activeTab = 'pic'; errorMsg = ''; successMsg = ''; }}
                    class="py-2.5 px-3 border-b-2 flex items-center justify-center gap-1.5 transition-colors cursor-pointer {activeTab === 'pic' ? 'border-slate-900 text-slate-900 bg-white font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800'}"
                >
                    <UserPlus class="w-3.5 h-3.5" />
                    <span>PIC Customer</span>
                </button>
                <button
                    type="button"
                    onclick={() => { activeTab = 'product'; errorMsg = ''; successMsg = ''; }}
                    class="py-2.5 px-3 border-b-2 flex items-center justify-center gap-1.5 transition-colors cursor-pointer {activeTab === 'product' ? 'border-slate-900 text-slate-900 bg-white font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800'}"
                >
                    <PackagePlus class="w-3.5 h-3.5" />
                    <span>Produk</span>
                </button>
            </div>

            {#if errorMsg}
                <div class="mx-6 mt-4 p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center gap-2">
                    <AlertCircle class="w-4 h-4 shrink-0" />
                    <span>{errorMsg}</span>
                </div>
            {/if}

            {#if successMsg}
                <div class="mx-6 mt-4 p-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs flex items-center gap-2">
                    <CheckCircle2 class="w-4 h-4 shrink-0" />
                    <span>{successMsg}</span>
                </div>
            {/if}

            <div class="p-6 text-xs">
                {#if activeTab === 'customer'}
                    <form onsubmit={submitCustomer} class="flex flex-col gap-3.5">
                        <div>
                            <label for="mdCustName" class="block font-semibold text-slate-700 mb-1">Nama Customer / Institusi *</label>
                            <input id="mdCustName" type="text" required bind:value={custName} placeholder="Contoh: PT Bio Farma (Persero)" class="w-full px-3 py-2 border border-slate-200 rounded-lg outline-none focus:border-slate-400" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="mdCustSales" class="block font-semibold text-slate-700 mb-1">PIC Sales (Inisial) *</label>
                                <select id="mdCustSales" required bind:value={custSalesInitial} class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-white outline-none focus:border-slate-400">
                                    <option value="">— Pilih PIC Sales —</option>
                                    {#each uniqueSalesInitials as s}
                                        <option value={s.initial}>{s.initial} — {s.name}</option>
                                    {/each}
                                </select>
                            </div>

                            <div>
                                <label for="mdCustCode" class="block font-semibold text-slate-700 mb-1">Kode Customer (Opsional)</label>
                                <input id="mdCustCode" type="text" bind:value={custCode} placeholder="Otomatis jika kosong" class="w-full px-3 py-2 border border-slate-200 rounded-lg outline-none focus:border-slate-400" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="mdCustSector" class="block font-semibold text-slate-700 mb-1">Sektor</label>
                                <input id="mdCustSector" type="text" bind:value={custSector} placeholder="Farmasi / F&B / Lab" class="w-full px-3 py-2 border border-slate-200 rounded-lg outline-none focus:border-slate-400" />
                            </div>

                            <div>
                                <label for="mdCustCity" class="block font-semibold text-slate-700 mb-1">Kota</label>
                                <input id="mdCustCity" type="text" bind:value={custCity} placeholder="Jakarta / Bandung" class="w-full px-3 py-2 border border-slate-200 rounded-lg outline-none focus:border-slate-400" />
                            </div>
                        </div>

                        <div class="pt-2 flex justify-end gap-2">
                            <button type="button" onclick={close} class="px-3.5 py-2 border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50">Tutup</button>
                            <button type="submit" disabled={isSubmitting} class="px-4 py-2 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white font-medium rounded-lg cursor-pointer">
                                {isSubmitting ? 'Menyimpan...' : 'Simpan Customer'}
                            </button>
                        </div>
                    </form>
                {/if}

                {#if activeTab === 'pic'}
                    <form onsubmit={submitPic} class="flex flex-col gap-3.5">
                        <div>
                            <label for="mdPicCust" class="block font-semibold text-slate-700 mb-1">Customer *</label>
                            <select id="mdPicCust" required bind:value={picCustomerId} class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-white outline-none focus:border-slate-400">
                                <option value="">— Pilih Customer —</option>
                                {#each customers as c}
                                    <option value={String(c.id)}>{c.name}</option>
                                {/each}
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="mdPicName" class="block font-semibold text-slate-700 mb-1">Nama PIC *</label>
                                <input id="mdPicName" type="text" required bind:value={picName} placeholder="Contoh: Ibu Rina" class="w-full px-3 py-2 border border-slate-200 rounded-lg outline-none focus:border-slate-400" />
                            </div>

                            <div>
                                <label for="mdPicPos" class="block font-semibold text-slate-700 mb-1">Jabatan</label>
                                <input id="mdPicPos" type="text" bind:value={picPosition} placeholder="QC Manager / Analyst" class="w-full px-3 py-2 border border-slate-200 rounded-lg outline-none focus:border-slate-400" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="mdPicPhone" class="block font-semibold text-slate-700 mb-1">No. Telepon / WA</label>
                                <input id="mdPicPhone" type="text" bind:value={picPhone} placeholder="0812..." class="w-full px-3 py-2 border border-slate-200 rounded-lg outline-none focus:border-slate-400" />
                            </div>

                            <div>
                                <label for="mdPicEmail" class="block font-semibold text-slate-700 mb-1">Email</label>
                                <input id="mdPicEmail" type="email" bind:value={picEmail} placeholder="nama@perusahaan.com" class="w-full px-3 py-2 border border-slate-200 rounded-lg outline-none focus:border-slate-400" />
                            </div>
                        </div>

                        <div class="pt-2 flex justify-end gap-2">
                            <button type="button" onclick={close} class="px-3.5 py-2 border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50">Tutup</button>
                            <button type="submit" disabled={isSubmitting} class="px-4 py-2 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white font-medium rounded-lg cursor-pointer">
                                {isSubmitting ? 'Menyimpan...' : 'Simpan PIC'}
                            </button>
                        </div>
                    </form>
                {/if}

                {#if activeTab === 'product'}
                    <form onsubmit={submitProduct} class="flex flex-col gap-3.5">
                        <div>
                            <label for="mdProdPrin" class="block font-semibold text-slate-700 mb-1">Principal *</label>
                            <select id="mdProdPrin" required bind:value={prodPrincipalId} class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-white outline-none focus:border-slate-400">
                                <option value="">— Pilih Principal —</option>
                                {#each principals as p}
                                    <option value={String(p.id)}>{p.name}</option>
                                {/each}
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="mdProdCat" class="block font-semibold text-slate-700 mb-1">No. Catalogue *</label>
                                <input id="mdProdCat" type="text" required bind:value={prodCatalogue} placeholder="Contoh: 610005" class="w-full px-3 py-2 border border-slate-200 rounded-lg outline-none focus:border-slate-400 font-mono" />
                            </div>

                            <div>
                                <label for="mdProdCategory" class="block font-semibold text-slate-700 mb-1">Kategori / Unit</label>
                                <input id="mdProdCategory" type="text" bind:value={prodCategory} placeholder="Media / Reagent / Instrument" class="w-full px-3 py-2 border border-slate-200 rounded-lg outline-none focus:border-slate-400" />
                            </div>
                        </div>

                        <div>
                            <label for="mdProdDesc" class="block font-semibold text-slate-700 mb-1">Deskripsi Produk *</label>
                            <input id="mdProdDesc" type="text" required bind:value={prodDescription} placeholder="Contoh: Tryptic Soy Agar (TSA) 500g" class="w-full px-3 py-2 border border-slate-200 rounded-lg outline-none focus:border-slate-400" />
                        </div>

                        <div class="pt-2 flex justify-end gap-2">
                            <button type="button" onclick={close} class="px-3.5 py-2 border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50">Tutup</button>
                            <button type="submit" disabled={isSubmitting} class="px-4 py-2 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white font-medium rounded-lg cursor-pointer">
                                {isSubmitting ? 'Menyimpan...' : 'Simpan Produk'}
                            </button>
                        </div>
                    </form>
                {/if}
            </div>
        </div>
    </div>
{/if}