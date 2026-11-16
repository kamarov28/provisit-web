Berikut adalah hasil ekstraksi dan audit kode asli dari file `Code.gs`, `Index.html`, dan `CalendarEngine.gs` yang diberikan untuk keperluan migrasi PROVisit ke Laravel + Svelte. Seluruh potongan kode di bawah ini adalah **kode asli tanpa perubahan**.

---

## BAGIAN 1: PIC Sales dan Customer Master

### 1.1. Membaca Customer Master & Kolom F (Sales Initial)

**File:** `Code.gs`

**Fungsi:** `getCustomers(query)` & `getCustomerById_(customerId)`

**Tujuan:** Mengambil daftar customer dari spreadsheet eksternal (`CUSTOMER_SPREADSHEET_ID`) sheet `Customer_by_Company`, memetakan Kolom F sebagai `salesInitial`, dan menyimpannya ke Script Cache selama 10 menit.

**Kode asli:**

```javascript
function getCustomers(query) {
  const cache = CacheService.getScriptCache();
  const cacheKey = 'CACHE_ALL_CUSTOMERS_V1';
  let cached = cache.get(cacheKey);
  let allCustomers = null;

  if (cached) {
    try {
      allCustomers = JSON.parse(cached);
    } catch (e) {
      allCustomers = null;
    }
  }

  if (!allCustomers) {
    const config = getConfigMap_();
    const ss = SpreadsheetApp.openById(config.CUSTOMER_SPREADSHEET_ID);
    const sh = ss.getSheetByName(config.CUSTOMER_COMPANY_SHEET);

    if (!sh) throw new Error('Sheet Customer_by_Company tidak ditemukan.');

    const lastRow = sh.getLastRow();
    if (lastRow < 2) return [];

    // A = Customer ID, B = Customer ID Real, C = Nama Customer, F = PIC Sales
    const values = sh.getRange(2, 1, lastRow - 1, 6).getDisplayValues();
    allCustomers = values
      .filter(r => r[0] && r[2])
      .map(r => ({
        customerId: r[0],
        customerIdReal: r[1] || '',
        customerName: r[2],
        salesInitial: r[5] || ''
      }));

    try {
      // Simpan di cache max 600 detik (10 menit)
      cache.put(cacheKey, JSON.stringify(allCustomers), 600);
    } catch (err) {
      Logger.log('Cache put failed (size limit): ' + err);
    }
  }

  const q = String(query || '').trim().toLowerCase();
  return allCustomers.filter(r => !q || r.customerId.toLowerCase().includes(q) || r.customerName.toLowerCase().includes(q));
}

function getCustomerById_(customerId) {
  return getCustomers('')
    .find(x => x.customerId === customerId) || null;
}

```

**Dependensi:** `getConfigMap_()`, `CacheService`, `SpreadsheetApp`.

**Aturan bisnis:**

* Baris valid wajib memiliki Kolom A (`Customer ID` / `r[0]`) dan Kolom C (`Nama Customer` / `r[2]`).
* Kolom F (`r[5]`) dipetakan sebagai `salesInitial`.
* Pencarian bersifat *case-insensitive* pada `customerId` dan `customerName`.

---

### 1.2. Pencocokan Sales Initial dengan `USER_MASTER`

**File:** `Code.gs`

**Fungsi:** `getUserByInitial_(initial)` & `getObjectsFromSheet_(sheetName)`

**Tujuan:** Mencari baris user pada sheet `USER_MASTER` berdasarkan kecocokan kolom `Initial` (case-insensitive).

**Kode asli:**

```javascript
function getUserByInitial_(initial) {
  const target = String(initial || '').trim().toUpperCase();
  return getObjectsFromSheet_(PROVISIT.SHEETS.USER)
    .find(x => String(x.Initial || '').trim().toUpperCase() === target) || null;
}

function getObjectsFromSheet_(sheetName) {
  const sh = getSheet_(sheetName);
  const values = sh.getDataRange().getValues();

  if (values.length < 2) return [];

  const headers = values[0].map(String);

  return values.slice(1)
    .filter(row => row.some(v => v !== '' && v !== null))
    .map(row => {
      const obj = {};
      headers.forEach((h, i) => obj[h] = row[i]);
      return obj;
    });
}

```

**Dependensi:** `PROVISIT.SHEETS.USER` (`'USER_MASTER'`), `getSheet_()`.

**Aturan bisnis:** Pencocokan `Initial` dilakukan setelah di-`trim()` dan diubah ke huruf kapital (`toUpperCase()`).

---

### 1.3. Konteks Customer, PIC Customer, & Validasi PIC Sales untuk Portal

**File:** `Code.gs`

**Fungsi:** `getCustomerContextForPortal(customerId)` & `getCustomerPICs(customerId)`

**Tujuan:** Mengambil data customer, daftar PIC Customer, dan memvalidasi keberadaan serta status aktif PIC Sales di `USER_MASTER`.

**Kode asli:**

```javascript
function getCustomerContextForPortal(customerId) {
  const customer = getCustomerById_(customerId);
  if (!customer) throw new Error('Customer tidak ditemukan.');

  const salesInitial = String(customer.salesInitial || '').trim();
  if (!salesInitial) {
    throw new Error('Customer ini belum memiliki PIC Sales di Customer Master.');
  }

  const salesUser = getUserByInitial_(salesInitial);
  if (!salesUser || salesUser.Active !== true) {
    throw new Error(
      'PIC Sales "' + salesInitial + '" dari Customer Master belum cocok dengan USER_MASTER.'
    );
  }

  return {
    customer: customer,
    pics: getCustomerPICs(customerId),
    salesUser: {
      userId: salesUser.User_ID,
      initial: salesUser.Initial,
      fullName: salesUser.Full_Name
    }
  };
}

function getCustomerPICs(customerId) {
  if (!customerId) return [];

  const config = getConfigMap_();
  const ss = SpreadsheetApp.openById(config.CUSTOMER_SPREADSHEET_ID);
  const sh = ss.getSheetByName(config.CUSTOMER_PIC_SHEET);

  if (!sh) throw new Error('Sheet Customer_by_PIC tidak ditemukan.');

  const lastRow = sh.getLastRow();
  if (lastRow < 2) return [];

  // A Customer ID, B Customer, C PIC Sales, D PIC Customer, E Position
  const values = sh.getRange(2, 1, lastRow - 1, 5).getDisplayValues();

  return values
    .filter(r => r[0] === customerId && r[3])
    .map(r => ({
      customerId: r[0],
      customerName: r[1] || '',
      picCustomer: r[3],
      position: r[4] || ''
    }));
}

```

**Dependensi:** `getCustomerById_()`, `getUserByInitial_()`, `getConfigMap_()`.

**Aturan bisnis:**

* **Validasi PIC Sales kosong:** Jika `customer.salesInitial` kosong, sistem menolak dengan error `'Customer ini belum memiliki PIC Sales di Customer Master.'`.
* **Validasi PIC Sales tidak ditemukan / nonaktif:** Jika `salesUser` tidak ditemukan di `USER_MASTER` atau `salesUser.Active !== true` (wajib boolean `true`), sistem menolak dengan error `'PIC Sales "..." dari Customer Master belum cocok dengan USER_MASTER.'`.
* **PIC Customer:** Diambil dari sheet `Customer_by_PIC` (Kolom A = `customerId`, Kolom D = `picCustomer`, Kolom E = `position`). PIC Customer bersifat opsional (hasil array kosong tetap valid).

---

### 1.4. Penentuan dan Penyimpanan `Primary_Sales_User_ID`

*(Lihat detail lengkap pada Bagian 3.1 `createVisit` dan 3.2 `autosaveVisitFromPortal`)*

**Aturan bisnis:** Nilai `Primary_Sales_User_ID` pada `VISIT_DB` **selalu** diturunkan dari `salesUser.User_ID` hasil lookup `customer.salesInitial` di backend, mengabaikan nilai `primarySalesUserId` yang mungkin dikirim dari payload frontend.

---

## BAGIAN 2: New Visit UI (`Index.html`)

### 2.1. Markup HTML Input Customer, PIC Customer, PIC Sales, & Action Owner

**File:** `Index.html`

**Fungsi:** Struktur DOM Form Tab 1 & Tab 3

**Tujuan:** Menyediakan elemen input pencarian customer, tampilan readonly PIC Sales, dan dropdown owner Action Plan.

**Kode asli:**

```html
<!-- Tab 1: Customer, PIC Customer, dan PIC Sales -->
            <div class="full results">
              <label>Customer *</label>
              <div class="search-input-wrap">
                <input id="customerSearch" autocomplete="off"
                       placeholder="Ketik minimal 2 karakter nama customer / customer ID"
                       oninput="onCustomerSearchInput(this)">
                <button type="button" class="clear-btn" id="btnClearCustomer" onclick="clearCustomerSelection()" title="Reset pilihan customer">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                  </svg>
                </button>
              </div>
              <input id="customerId" type="hidden">
              <div id="customerResults"></div>
              <div id="selectedCustomer" class="helper"></div>
            </div>

            <div>
              <label>PIC Customer</label>
              <select id="picCustomer">
                <option value="">— Tidak wajib —</option>
              </select>
            </div>

            <div>
              <label>PIC Sales</label>
              <input id="salesDisplay" readonly placeholder="Otomatis mengikuti Customer Master">
              <input id="primarySalesUserId" type="hidden">
              <div class="helper">PIC Sales tidak dipilih manual.</div>
            </div>

<!-- Tab 3: Action Plan Owner -->
            <div>
              <label>Owner</label>
              <select id="actionOwner"></select>
            </div>

```

**Dependensi:** Tidak ada.

**Aturan bisnis:** Input `#salesDisplay` bersifat `readonly` sehingga user tidak dapat memilih/mengubah PIC Sales secara manual dari UI.

---

### 2.2. Event Listener Perubahan Input Customer & Pencarian Customer

**File:** `Index.html`

**Fungsi:** `init(data)`, `onCustomerSearchInput(input)`, `clearCustomerSelection()`, `searchCustomer()`, `renderCustomers(rows)`

**Tujuan:** Mereset relasi customer/PIC/sales setiap kali teks pencarian diubah, menunda pencarian 300ms (debounce), memanggil backend jika minimal 2 karakter, dan merender dropdown hasil.

**Kode asli:**

```javascript
// Di dalam function init(data):
    const customerInput = document.getElementById('customerSearch');

    customerInput.addEventListener('input', () => {
      document.getElementById('customerId').value = '';
      document.getElementById('selectedCustomer').textContent = '';
      document.getElementById('primarySalesUserId').value = '';
      document.getElementById('salesDisplay').value = '';
      resetPICs();

      clearTimeout(searchTimer);
      searchTimer = setTimeout(searchCustomer, 300);
    });

    document.addEventListener('click', e => {
      if (!e.target.closest('.results')) {
        document.getElementById('customerResults').innerHTML = '';
      }
    });

// Event handler & fungsi pencarian:
  function onCustomerSearchInput(input) {
    const clearBtn = document.getElementById('btnClearCustomer');
    if (clearBtn) {
      clearBtn.style.display = input.value.trim() ? 'inline-flex' : 'none';
    }
    input.classList.remove('input-error');
  }

  function clearCustomerSelection() {
    const custSearch = document.getElementById('customerSearch');
    const custId = document.getElementById('customerId');
    const selCust = document.getElementById('selectedCustomer');
    const clearBtn = document.getElementById('btnClearCustomer');

    if (custSearch) custSearch.value = '';
    if (custId) custId.value = '';
    if (selCust) selCust.innerHTML = '';
    if (clearBtn) clearBtn.style.display = 'none';
    resetPICs();
    document.getElementById('salesDisplay').value = '';
    document.getElementById('primarySalesUserId').value = '';
  }

  function searchCustomer() {
    const q = document.getElementById('customerSearch').value.trim();
    const box = document.getElementById('customerResults');

    if (q.length < 2) {
      box.innerHTML = '';
      return;
    }

    google.script.run
      .withSuccessHandler(renderCustomers)
      .withFailureHandler(showError)
      .searchCustomersForPortal(q);
  }

  function renderCustomers(rows) {
    const box = document.getElementById('customerResults');

    if (!rows.length) {
      box.innerHTML = '<div class="result-box"><div class="result-item">Customer tidak ditemukan</div></div>';
      return;
    }

    const inner = rows.map((r, i) => `
      <div class="result-item"
           onclick='selectCustomer(${JSON.stringify(JSON.stringify(r))})'>
        <b>${escapeHtml(r.customerName)}</b><br>
        <span style="font-size:12px;color:#6b7280">${escapeHtml(r.customerId)}</span>
      </div>
    `).join('');

    box.innerHTML = `<div class="result-box">${inner}</div>`;
  }

```

**Dependensi:** Backend `searchCustomersForPortal(q)` (mengembalikan max 25 item jika `q.length >= 2`), `resetPICs()`, `selectCustomer()`.

**Aturan bisnis:**

* Setiap ketikan pada `#customerSearch` langsung menghapus `customerId`, `primarySalesUserId`, `salesDisplay`, dan opsi `picCustomer` yang sebelumnya terpilih.
* Pencarian hanya ditembak ke server jika panjang karakter `>= 2` setelah debounce 300ms.

---

### 2.3. Pemilihan Customer, Pengisian Otomatis PIC Sales, & Default `actionOwner`

**File:** `Index.html`

**Fungsi:** `selectCustomer(json)`, `resetPICs()`, `fillPICs(rows)`, `fillUsers(id, users, selectedUserId)`

**Tujuan:** Mengisi data customer terpilih, menarik konteks PIC Customer dan PIC Sales dari backend, mengisi otomatis `salesDisplay` & `primarySalesUserId`, serta mengubah default `actionOwner` menjadi `salesUser.userId`.

**Kode asli:**

```javascript
  function selectCustomer(json) {
    const r = JSON.parse(json);

    document.getElementById('customerId').value = r.customerId;
    document.getElementById('customerSearch').value = r.customerName;
    document.getElementById('selectedCustomer').innerHTML =
      `<span style="display:inline-flex;align-items:center;gap:4px;background:#ecfdf5;color:#047857;font-weight:600;padding:3px 8px;border-radius:4px;border:1px solid #a7f3d0;margin-top:4px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        ${escapeHtml(r.customerId)} — ${escapeHtml(r.customerName)}
      </span>`;
    document.getElementById('customerResults').innerHTML = '';

    document.getElementById('salesDisplay').value = 'Loading...';
    resetPICs();

    google.script.run
      .withSuccessHandler(context => {
        fillPICs(context.pics || []);
        document.getElementById('primarySalesUserId').value = context.salesUser.userId;
        document.getElementById('salesDisplay').value =
          `${context.salesUser.initial} — ${context.salesUser.fullName}`;

        // Action owner default mengikuti PIC Sales customer.
        document.getElementById('actionOwner').value = context.salesUser.userId;
        checkScheduleConflict();
        scheduleAutosave();
      })
      .withFailureHandler(err => {
        document.getElementById('salesDisplay').value = '';
        showError(err);
      })
      .getCustomerContextForPortal(r.customerId);
  }

  function resetPICs() {
    document.getElementById('picCustomer').innerHTML =
      '<option value="">— Tidak wajib —</option>';
  }

  function fillPICs(rows) {
    resetPICs();
    const el = document.getElementById('picCustomer');

    rows.forEach(r => {
      const o = document.createElement('option');
      o.value = r.picCustomer;
      o.textContent = r.position
        ? `${r.picCustomer} — ${r.position}`
        : r.picCustomer;
      el.appendChild(o);
    });
  }

  function fillUsers(id, users, selectedUserId) {
    const el = document.getElementById(id);
    el.innerHTML = '';

    users.forEach(u => {
      const o = document.createElement('option');
      o.value = u.userId;
      o.textContent = `${u.initial} — ${u.fullName}`;
      if (u.userId === selectedUserId) o.selected = true;
      el.appendChild(o);
    });
  }

```

**Dependensi:** Backend `getCustomerContextForPortal(r.customerId)`, `checkScheduleConflict()`, `scheduleAutosave()`.

**Aturan bisnis:**

* Saat form pertama dibuka (`init` / `resetFormForNewVisit`), `actionOwner` diisi default ke `bootstrap.currentUser.userId`.
* Setelah customer dipilih (`selectCustomer`), `actionOwner` otomatis ditimpa menjadi `context.salesUser.userId` (PIC Sales dari customer tersebut), namun user masih dapat mengubah dropdown `actionOwner` secara manual setelahnya.
* Jika backend gagal memvalidasi PIC Sales (error dari `getCustomerContextForPortal`), `salesDisplay` dikosongkan dan pesan error ditampilkan via toast `showError(err)`.

---

### 2.4. Input Principal & Mekanisme Pencarian Produk

*(Catatan: Dalam codebase ini tidak ada entitas bernama "Institution"; entitas yang digunakan adalah **Principal** dan **Products** di Tab 2).*

**File:** `Index.html`

**Fungsi:** `addReview(initialData)`, `principalChanged(principalSelect)`, `scheduleProductSearch(input)`, `searchProduct(input)`, `renderProductResults(input, rows)`, `addProductToReview(clickedEl, json)`, `appendSelectedProduct(review, product)`, `removeSelectedProduct(btn)`, `collectProducts(review)`, `collectReviews()`

**Tujuan:** Mengelola blok Principal Review (dropdown Principal dari `bootstrap.principals` + autocomplete pencarian produk berdasarkan Principal terpilih).

**Kode asli:**

```javascript
  function addReview(initialData = null) {
    const idx = document.querySelectorAll('.review').length;
    const holder = document.getElementById('reviews');

    const block = document.createElement('div');
    block.className = 'review';
    block.dataset.clientKey =
      (initialData && initialData.clientKey) ? initialData.clientKey : makeClientKey('REV');

    const principalsList = (bootstrap && bootstrap.principals) ? bootstrap.principals : [];

    block.innerHTML = `
      <div class="review-head">
        <b>Principal Review #${idx + 1}</b>
        ${idx > 0 ? '<button class="danger" type="button" onclick="removeReview(this)">Remove</button>' : ''}
      </div>

      <div class="grid">
        <div>
          <label>Principal *</label>
          <select class="principal" onchange="principalChanged(this)">
            <option value="">— Select Principal —</option>
            ${principalsList.map(p =>
              `<option value="${escapeAttr(p.principalName)}">${escapeHtml(p.principalName)}</option>`
            ).join('')}
          </select>
        </div>

        <div class="full product-zone">
          <label>Products</label>
          <div class="results">
            <input class="productSearch"
                   autocomplete="off"
                   placeholder="Ketik minimal 2 karakter Catalogue / Description"
                   oninput="scheduleProductSearch(this)">
            <div class="productResults"></div>
          </div>
          <div class="selectedProducts" style="margin-top:8px;"></div>
          <div class="helper">Bisa menambahkan lebih dari satu product untuk principal yang sama.</div>
        </div>

        <div>
          <label>Exposure Type</label>
          <select class="exposureType">
            <option value=""></option>
            <option>Introduction</option>
            <option>Demo</option>
            <option>Evaluation</option>
            <option>Troubleshooting</option>
            <option>Follow-up</option>
            <option>Commercial</option>
          </select>
        </div>

        <div>
          <label>Opportunity Status</label>
          <select class="opportunityStatus">
            <option value=""></option>
            <option>No Signal</option>
            <option>Interested</option>
            <option>Sample</option>
            <option>Demo</option>
            <option>Quotation</option>
            <option>Negotiation</option>
            <option>Won</option>
            <option>Lost</option>
          </select>
        </div>

        <div class="full">
          <label>Notes</label>
          <textarea class="reviewNotes"></textarea>
        </div>

        <div class="full">
          <label>Customer Feedback</label>
          <textarea class="customerFeedback"></textarea>
        </div>

        <div class="full">
          <label>Next Step</label>
          <textarea class="nextStep"></textarea>
        </div>
      </div>
    `;

    holder.appendChild(block);

    if (initialData) {
      block.querySelector('.principal').value = initialData.principalName || '';
      block.querySelector('.exposureType').value = initialData.exposureType || '';
      block.querySelector('.reviewNotes').value = initialData.notes || '';
      block.querySelector('.customerFeedback').value = initialData.customerFeedback || '';
      block.querySelector('.opportunityStatus').value = initialData.opportunityStatus || '';
      block.querySelector('.nextStep').value = initialData.nextStep || '';

      (initialData.products || []).forEach(p => {
        appendSelectedProduct(block, p);
      });
    }
  }

  const productTimers = new WeakMap();

  function principalChanged(principalSelect) {
    const review = principalSelect.closest('.review');
    review.querySelector('.productSearch').value = '';
    review.querySelector('.productResults').innerHTML = '';
    review.querySelector('.selectedProducts').innerHTML = '';
    scheduleAutosave();
  }

  function scheduleProductSearch(input) {
    const oldTimer = productTimers.get(input);
    if (oldTimer) clearTimeout(oldTimer);
    const timer = setTimeout(() => searchProduct(input), 300);
    productTimers.set(input, timer);
  }

  function searchProduct(input) {
    const review = input.closest('.review');
    const principal = review.querySelector('.principal').value;
    const q = input.value.trim();
    const box = review.querySelector('.productResults');

    if (!principal) {
      box.innerHTML = '<div class="helper">Pilih Principal terlebih dahulu.</div>';
      return;
    }

    if (q.length < 2) {
      box.innerHTML = '';
      return;
    }

    google.script.run
      .withSuccessHandler(rows => renderProductResults(input, rows))
      .withFailureHandler(showError)
      .searchProductsForPortal(principal, q);
  }

  function renderProductResults(input, rows) {
    const review = input.closest('.review');
    const box = review.querySelector('.productResults');

    if (!rows.length) {
      box.innerHTML = '<div class="result-box"><div class="result-item">Product tidak ditemukan</div></div>';
      return;
    }

    const htmlRows = rows.map(r => `
      <div class="result-item"
           onclick='addProductToReview(this, ${JSON.stringify(JSON.stringify(r))})'>
        <b>${escapeHtml(r.catalogue || '(No catalogue)')}</b><br>
        <span style="font-size:12px;color:#6b7280">${escapeHtml(r.description || '')}</span>
      </div>
    `).join('');

    box.innerHTML = `<div class="result-box">${htmlRows}</div>`;
  }

  function addProductToReview(clickedEl, json) {
    const product = JSON.parse(json);
    const review = clickedEl.closest('.review');
    const holder = review.querySelector('.selectedProducts');

    const existing = Array.from(holder.querySelectorAll('.selected-product'))
      .some(x => x.dataset.catalogue === (product.catalogue || ''));

    if (existing) {
      review.querySelector('.productResults').innerHTML = '';
      review.querySelector('.productSearch').value = '';
      return;
    }

    appendSelectedProduct(review, product);

    review.querySelector('.productResults').innerHTML = '';
    review.querySelector('.productSearch').value = '';
    scheduleAutosave();
  }

  function appendSelectedProduct(review, product) {
    const holder = review.querySelector('.selectedProducts');

    const div = document.createElement('div');
    div.className = 'selected-product';
    div.dataset.catalogue = product.catalogue || '';
    div.dataset.description = product.description || '';
    div.style.cssText =
      'display:flex;justify-content:space-between;gap:10px;align-items:flex-start;' +
      'background:white;border:1px solid #d9e2e8;border-radius:8px;padding:9px 10px;margin-top:6px;';

    div.innerHTML = `
      <div>
        <b>${escapeHtml(product.catalogue || '(No catalogue)')}</b>
        <div style="font-size:12px;color:#6b7280">${escapeHtml(product.description || '')}</div>
      </div>
      <button type="button" class="danger" onclick="removeSelectedProduct(this)">Remove</button>
    `;

    holder.appendChild(div);
  }

  function removeSelectedProduct(btn) {
    btn.closest('.selected-product').remove();
    scheduleAutosave();
  }

  function collectProducts(review) {
    return Array.from(review.querySelectorAll('.selected-product')).map(x => ({
      catalogue: x.dataset.catalogue || '',
      description: x.dataset.description || ''
    }));
  }

  function collectReviews() {
    return Array.from(document.querySelectorAll('.review')).map(r => {
      return {
        clientKey: r.dataset.clientKey || makeClientKey('REV'),
        principalName: r.querySelector('.principal').value,
        products: collectProducts(r),
        exposureType: r.querySelector('.exposureType').value,
        notes: r.querySelector('.reviewNotes').value,
        customerFeedback: r.querySelector('.customerFeedback').value,
        opportunityStatus: r.querySelector('.opportunityStatus').value,
        nextStep: r.querySelector('.nextStep').value
      };
    });
  }

```

**Dependensi:** Backend `searchProductsForPortal(principal, q)`, `getPrincipals()` (via `bootstrap.principals`).

**Aturan bisnis:**

* Principal Review #1 tidak memiliki tombol Remove (`idx > 0`), memastikan selalu ada minimal 1 blok review di UI.
* Jika dropdown Principal diubah (`principalChanged`), seluruh produk yang sudah dipilih pada blok review tersebut langsung di-reset.
* Produk duplikat dicegah di UI berdasarkan kesamaan `catalogue` dalam satu blok review.

---

### 2.5. Mekanisme Pengumpulan Payload & Autosave di Frontend

**File:** `Index.html`

**Fungsi:** `installAutosaveTracking()`, `scheduleAutosave()`, `runAutosave()`, `hasMeaningfulContent(payload)`, `collectPayload()`, `manualSaveVisit()`

**Tujuan:** Menyimpan perubahan form secara otomatis (debounce 1200ms) maupun manual di Langkah 3 ke backend `autosaveFromPortal`.

**Kode asli:**

```javascript
  function installAutosaveTracking() {
    const formArea = document.querySelector('.wrap');

    const shouldIgnore = target => {
      if (!target) return true;
      if (target.id === 'customerSearch') return true;
      if (target.classList && target.classList.contains('productSearch')) return true;
      return false;
    };

    formArea.addEventListener('input', e => {
      if (shouldIgnore(e.target)) return;
      scheduleAutosave();
    });

    formArea.addEventListener('change', e => {
      if (shouldIgnore(e.target)) return;
      scheduleAutosave();
    });
  }

  function scheduleAutosave() {
    setSaveState('unsaved');

    const payload = collectPayload();
    writeLocalDraft(payload);

    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(runAutosave, 1200);
  }

  function runAutosave() {
    if (saveInFlight) {
      pendingSave = true;
      return;
    }

    const payload = collectPayload();
    payload.visitId = currentVisitId || payload.visitId || '';

    // Jangan buat row kosong hanya karena halaman dibuka.
    if (!hasMeaningfulContent(payload)) {
      setSaveState('unsaved');
      return;
    }

    saveInFlight = true;
    pendingSave = false;
    setSaveState('saving');

    google.script.run
      .withSuccessHandler(result => {
        saveInFlight = false;
        currentVisitId = result.visitId || currentVisitId;

        payload.visitId = currentVisitId;
        writeLocalDraft(payload);

        setSaveState('saved');
        hideMessage();

        const latestPayload = collectPayload();
        latestPayload.visitId = currentVisitId;
        latestPayload.workspaceMeta = {
          updatedAt: result.savedAt,
          updatedByInitial: result.savedBy
        };
        updateWorkspaceHeader(latestPayload);
        renderStatusOptions(false, result.status);

        if (result.status !== 'Draft') {
          localStorage.removeItem(LOCAL_DRAFT_KEY);
        }

        if (pendingSave) runAutosave();
      })
      .withFailureHandler(err => {
        saveInFlight = false;
        pendingSave = false;
        setSaveState('unsaved');
        showError(err);
      })
      .autosaveFromPortal(payload);
  }

  function hasMeaningfulContent(payload) {
    // Hanya simpan row baru ke Google Sheet jika sudah ada Judul & Customer
    // Menghindari baris sampah/setengah matang di database backend
    return Boolean(
      (payload.visitId && (payload.visitTitle || payload.customerId)) ||
      (payload.visitTitle && payload.customerId)
    );
  }

  function collectPayload() {
    const payload = {
      visitId: currentVisitId || '',
      visitTitle: document.getElementById('visitTitle').value.trim(),
      visitDate: document.getElementById('visitDate').value,
      startTime: document.getElementById('startTime').value,
      customerId: document.getElementById('customerId').value,
      customerName: document.getElementById('customerSearch').value,
      picCustomer: document.getElementById('picCustomer').value,
      primarySalesUserId: document.getElementById('primarySalesUserId').value,
      visitType: document.getElementById('visitType').value,
      visitPurpose: document.getElementById('visitPurpose').value,
      agenda: '',
      previsitNotes: document.getElementById('previsitNotes').value,
      postvisitResult: document.getElementById('postvisitResult').value,
      status: document.getElementById('status').value,
      supportMarketingUserIds: collectSupportMarketing(),
      principalReviews: collectReviews(),
      actions: []
    };

    const actionText = document.getElementById('actionText').value.trim();
    if (actionText) {
      payload.actions.push({
        clientKey: 'ACTION-PRIMARY',
        actionText,
        ownerUserId: document.getElementById('actionOwner').value,
        dueDate: document.getElementById('actionDueDate').value,
        priority: document.getElementById('actionPriority').value,
        status: 'Open'
      });
    }

    return payload;
  }

  function manualSaveVisit() {
    if (!validateTab1()) {
      switchFormTab('tab1', true);
      return;
    }

    const statusVal = document.getElementById('status').value;
    if (statusVal === 'Completed') {
      const postResult = document.getElementById('postvisitResult').value.trim();
      const actionTxt = document.getElementById('actionText').value.trim();

      if (!postResult || !actionTxt) {
        showError('Status "Completed" mewajibkan pengisian Hasil Kunjungan dan minimal 1 Action Plan.');
        switchFormTab('tab3');
        if (!postResult) {
          document.getElementById('postvisitResult').focus();
        } else {
          document.getElementById('actionText').focus();
        }
        return;
      }
    }

    scheduleAutosave();
    runAutosave();
  }

```

**Dependensi:** Backend `autosaveFromPortal(payload)` (Catatan: UI v0.8 saat ini **tidak** memanggil `saveVisitFromPortal` / `createVisit`, melainkan seluruh penyimpanan UI dialihkan melalui `autosaveFromPortal`).

**Aturan bisnis:**

* Autosave ke server **tidak akan jalan** untuk New Visit sebelum `visitTitle` dan `customerId` keduanya terisi (`hasMeaningfulContent`).
* Ketikan pada `#customerSearch` dan `.productSearch` diabaikan oleh autosave tracker agar tidak memicu penyimpanan saat user masih mengetik query pencarian.

---

## BAGIAN 3: Penyimpanan Visit (Backend `Code.gs`)

### 3.1. Fungsi `createVisit(payload)` & `validateCreateVisit_(payload)` (Legacy v0.3/v0.4 Save API)

**File:** `Code.gs`

**Fungsi:** `saveVisitFromPortal(payload)`, `createVisit(payload)`, `validateCreateVisit_(payload)`, `validatePrincipal_(principalName)`

**Tujuan:** Membuat record Visit baru beserta relasi Principal Review, Product Link, Action Plan, Support Marketing, dan Audit Log menggunakan `LockService`.

**Kode asli:**

```javascript
function saveVisitFromPortal(payload) {
  return createVisit(payload);
}

function createVisit(payload) {
  payload = payload || {};
  const actor = getCurrentUser_();
  const requestId = Utilities.getUuid();
  const now = new Date();

  validateCreateVisit_(payload);

  const customer = getCustomerById_(payload.customerId);
  if (!customer) throw new Error('Customer tidak ditemukan di DATABASE_CUSTOMER_v1.0.');

  let picPosition = '';
  if (payload.picCustomer) {
    const pics = getCustomerPICs(payload.customerId);
    const pic = pics.find(x => x.picCustomer === payload.picCustomer);
    if (!pic) {
      throw new Error('PIC Customer tidak cocok dengan customer yang dipilih.');
    }
    picPosition = pic.position || '';
  }

  const salesInitial = String(customer.salesInitial || '').trim();
  if (!salesInitial) {
    throw new Error('Customer belum memiliki PIC Sales di Customer Master.');
  }

  const salesUser = getUserByInitial_(salesInitial);
  if (!salesUser || salesUser.Active !== true) {
    throw new Error(
      'PIC Sales "' + salesInitial + '" dari Customer Master belum cocok dengan USER_MASTER.'
    );
  }

  const status = payload.status || 'Draft';
  const actions = Array.isArray(payload.actions) ? payload.actions : [];

  if (status === 'Completed' && actions.length === 0) {
    throw new Error('Action Plan wajib tersedia sebelum Visit ditandai Completed.');
  }

  const lock = LockService.getScriptLock();
  lock.waitLock(30000);

  try {
    const visitId = nextId_(PROVISIT.SHEETS.VISIT, 'VST');
    const visitRow = {
      Visit_ID: visitId,
      Visit_Title: payload.visitTitle,
      Visit_Date: parseLocalDate_(payload.visitDate),
      Start_Time: parseLocalTime_(payload.startTime),
      End_Time: '',
      Customer_ID: customer.customerId,
      Customer_Name_Snapshot: customer.customerName,
      PIC_Customer: payload.picCustomer || '',
      PIC_Position_Snapshot: picPosition,
      Primary_Sales_User_ID: salesUser.User_ID,
      Visit_Type: payload.visitType,
      Visit_Purpose: payload.visitPurpose,
      Agenda: payload.agenda || '',
      Previsit_Notes: payload.previsitNotes || '',
      Status: status,
      Calendar_Event_ID: '',
      Drive_Folder_ID: '',
      Postvisit_Result: payload.postvisitResult || '',
      Report_Completed_At: status === 'Completed' ? now : '',
      Created_At: now,
      Created_By: actor.User_ID,
      Updated_At: now,
      Updated_By: actor.User_ID,
      Is_Archived: false,
      Archived_At: '',
      Archived_By: ''
    };

    appendObjectRow_(PROVISIT.SHEETS.VISIT, visitRow);

    const reviewIds = [];
    payload.principalReviews.forEach(review => {
      const principal = String(review.principalName || '').trim();
      validatePrincipal_(principal);

      const reviewId = nextId_(PROVISIT.SHEETS.REVIEW, 'PRL');
      appendObjectRow_(PROVISIT.SHEETS.REVIEW, {
        Review_ID: reviewId,
        Visit_ID: visitId,
        Principal_Name: principal,
        // Kolom lama dipertahankan untuk kompatibilitas.
        // Mulai v0.4 produk disimpan di PRINCIPAL_REVIEW_PRODUCT_DB.
        Product_Catalogue: '',
        Product_Description_Snapshot: '',
        Exposure_Type: review.exposureType || '',
        Notes: review.notes || '',
        Customer_Feedback: review.customerFeedback || '',
        Opportunity_Status: review.opportunityStatus || '',
        Next_Step: review.nextStep || '',
        Created_At: now,
        Created_By: actor.User_ID,
        Updated_At: now,
        Updated_By: actor.User_ID,
        Client_Key: review.clientKey || ''
      });

      const products = Array.isArray(review.products) ? review.products : [];
      const seen = new Set();

      products.forEach(product => {
        const catalogue = String(product.catalogue || '').trim();
        const description = String(product.description || '').trim();
        const key = catalogue + '|' + description;

        if (!catalogue && !description) return;
        if (seen.has(key)) return;
        seen.add(key);

        appendObjectRow_(PROVISIT.SHEETS.REVIEW_PRODUCT, {
          Product_Link_ID: nextId_(PROVISIT.SHEETS.REVIEW_PRODUCT, 'RPD'),
          Review_ID: reviewId,
          Product_Catalogue: catalogue,
          Product_Description_Snapshot: description,
          Created_At: now,
          Created_By: actor.User_ID
        });
      });

      reviewIds.push(reviewId);
    });

    const actionIds = [];
    actions.forEach(action => {
      if (!String(action.actionText || '').trim()) return;

      const actionId = nextId_(PROVISIT.SHEETS.ACTION, 'ACT');
      appendObjectRow_(PROVISIT.SHEETS.ACTION, {
        Action_ID: actionId,
        Visit_ID: visitId,
        Action_Text: action.actionText,
        Owner_User_ID: action.ownerUserId || salesUser.User_ID,
        Due_Date: action.dueDate ? parseLocalDate_(action.dueDate) : '',
        Priority: action.priority || 'Normal',
        Status: action.status || 'Open',
        Completion_Note: '',
        Completed_At: '',
        Created_At: now,
        Created_By: actor.User_ID,
        Updated_At: now,
        Updated_By: actor.User_ID,
        Client_Key: action.clientKey || 'ACTION-PRIMARY'
      });
      actionIds.push(actionId);
    });

    const supportIds = [];
    const allowedSupportInitials = ['IQH', 'YUP', 'SNI', 'NAF'];
    const supportUsers = Array.isArray(payload.supportMarketingUserIds)
      ? payload.supportMarketingUserIds
      : [];

    [...new Set(supportUsers)].forEach(userId => {
      const supportUser = getUserById_(userId);
      if (!supportUser || supportUser.Active !== true) {
        throw new Error('Support Marketing tidak ditemukan atau tidak aktif.');
      }

      const initial = String(supportUser.Initial || '').toUpperCase();
      if (!allowedSupportInitials.includes(initial)) {
        throw new Error(initial + ' tidak termasuk pilihan Support Marketing.');
      }

      const supportId = nextId_(PROVISIT.SHEETS.SUPPORT, 'VSP');
      appendObjectRow_(PROVISIT.SHEETS.SUPPORT, {
        Visit_Support_ID: supportId,
        Visit_ID: visitId,
        User_ID: supportUser.User_ID,
        Support_Role: 'Marketing Support',
        Created_At: now,
        Created_By: actor.User_ID
      });
      supportIds.push(supportId);
    });

    createAudit_(actor.User_ID, 'CREATE', 'VISIT', visitId, '', '', payload.visitTitle, requestId);

    return {
      ok: true,
      visitId,
      reviewIds,
      actionIds,
      supportIds,
      createdBy: actor.Initial,
      createdAt: now
    };

  } finally {
    lock.releaseLock();
  }
}

function validateCreateVisit_(payload) {
  const required = {
    visitTitle: 'Visit Title',
    visitDate: 'Visit Date',
    startTime: 'Start Time',
    customerId: 'Customer',
    visitType: 'Visit Type',
    visitPurpose: 'Visit Purpose'
  };

  Object.keys(required).forEach(key => {
    if (!String(payload[key] || '').trim()) {
      throw new Error(required[key] + ' wajib diisi.');
    }
  });

  if (!Array.isArray(payload.principalReviews) || payload.principalReviews.length === 0) {
    throw new Error('Setiap Visit wajib memiliki minimal 1 Principal Review.');
  }

  payload.principalReviews.forEach((r, i) => {
    if (!String(r.principalName || '').trim()) {
      throw new Error('Principal pada Principal Review #' + (i + 1) + ' wajib dipilih.');
    }
  });
}

function validatePrincipal_(principalName) {
  const names = getPrincipals().map(x => x.principalName);
  if (!names.includes(principalName)) {
    throw new Error('Principal "' + principalName + '" tidak ditemukan di Principal_List.');
  }
}

```

**Dependensi:** `getCurrentUser_()`, `getCustomerById_()`, `getCustomerPICs()`, `getUserByInitial_()`, `getUserById_()`, `nextId_()`, `appendObjectRow_()`, `createAudit_()`, `parseLocalDate_()`, `parseLocalTime_()`.

**Aturan bisnis:**

* **Struktur Kolom `VISIT_DB`:** `Visit_ID`, `Visit_Title`, `Visit_Date`, `Start_Time`, `End_Time`, `Customer_ID`, `Customer_Name_Snapshot`, `PIC_Customer`, `PIC_Position_Snapshot`, `Primary_Sales_User_ID`, `Visit_Type`, `Visit_Purpose`, `Agenda`, `Previsit_Notes`, `Status`, `Calendar_Event_ID`, `Drive_Folder_ID`, `Postvisit_Result`, `Report_Completed_At`, `Created_At`, `Created_By`, `Updated_At`, `Updated_By`, `Is_Archived`, `Archived_At`, `Archived_By`.
* **Snapshot:** Nama Customer (`Customer_Name_Snapshot`), Jabatan PIC Customer (`PIC_Position_Snapshot`), dan Deskripsi Produk (`Product_Description_Snapshot`) disimpan sebagai *snapshot* pada saat pembuatan/update agar data historis tidak rusak jika master berubah.
* **Format ID:** `PREFIX-YYYY-000001` (`VST` untuk Visit, `PRL` untuk Principal Review, `RPD` untuk Review Product, `ACT` untuk Action, `VSP` untuk Visit Support, `AUD` untuk Audit).

---

### 3.2. Fungsi Utama Penyimpanan Aktif (`autosaveVisitFromPortal`) & Sinkronisasi Sub-Tabel

**File:** `Code.gs`

**Fungsi:** `autosaveFromPortal(payload)`, `autosaveVisitFromPortal(payload)`, `validatePayloadForStatus_(payload, status)`, `upsertDraftState_(visitId, payload, userId, now)`, `syncSupportForVisit_()`, `syncReviewsForVisit_()`, `syncProductsForReview_()`, `syncActionsForVisit_()`

**Tujuan:** Menangani baik *Create* (jika `visitId` kosong/tidak ditemukan) maupun *Update* (jika `visitId` sudah ada), menyimpan snapshot JSON ke `DRAFT_STATE_DB`, menyinkronkan tabel anak berdasarkan `Client_Key`, dan memicu sinkronisasi Google Calendar.

**Kode asli:**

```javascript
function autosaveFromPortal(payload) {
  return autosaveVisitFromPortal(payload);
}

function autosaveVisitFromPortal(payload) {
  ensureV06Schema_();

  payload = payload || {};
  const actor = getCurrentUser_();
  const now = new Date();
  const status = String(payload.status || 'Draft');

  validatePayloadForStatus_(payload, status);

  const lock = LockService.getScriptLock();
  lock.waitLock(30000);

  try {
    let visitId = String(payload.visitId || '').trim();
    let visitRow = visitId ? findObjectRowById_(PROVISIT.SHEETS.VISIT, 'Visit_ID', visitId) : null;
    let isNew = false;

    if (!visitRow) {
      visitId = nextId_(PROVISIT.SHEETS.VISIT, 'VST');
      isNew = true;
    }

    let customer = null;
    let salesUser = null;
    let picPosition = '';

    if (payload.customerId) {
      customer = getCustomerById_(payload.customerId);
      if (!customer) {
        throw new Error('Customer tidak ditemukan di DATABASE_CUSTOMER_v1.0.');
      }

      const salesInitial = String(customer.salesInitial || '').trim();
      if (!salesInitial) {
        throw new Error('Customer belum memiliki PIC Sales di Customer Master.');
      }

      salesUser = getUserByInitial_(salesInitial);
      if (!salesUser || salesUser.Active !== true) {
        throw new Error(
          'PIC Sales "' + salesInitial + '" dari Customer Master belum cocok dengan USER_MASTER.'
        );
      }

      if (payload.picCustomer) {
        const pic = getCustomerPICs(payload.customerId)
          .find(x => x.picCustomer === payload.picCustomer);

        if (!pic) {
          throw new Error('PIC Customer tidak cocok dengan customer yang dipilih.');
        }
        picPosition = pic.position || '';
      }
    }

    const visitObject = {
      Visit_ID: visitId,
      Visit_Title: payload.visitTitle || '',
      Visit_Date: payload.visitDate ? parseLocalDate_(payload.visitDate) : '',
      Start_Time: payload.startTime ? parseLocalTime_(payload.startTime) : '',
      End_Time: '',
      Customer_ID: customer ? customer.customerId : '',
      Customer_Name_Snapshot: customer ? customer.customerName : '',
      PIC_Customer: payload.picCustomer || '',
      PIC_Position_Snapshot: picPosition,
      Primary_Sales_User_ID: salesUser ? salesUser.User_ID : '',
      Visit_Type: payload.visitType || '',
      Visit_Purpose: payload.visitPurpose || '',
      Agenda: '',
      Previsit_Notes: payload.previsitNotes || '',
      Status: status,
      Postvisit_Result: payload.postvisitResult || '',
      Updated_At: now,
      Updated_By: actor.User_ID,
      Is_Archived: false
    };

    if (status === 'Completed') {
      visitObject.Report_Completed_At = now;
    } else if (visitRow && visitRow.object.Report_Completed_At) {
      visitObject.Report_Completed_At = '';
    }

    if (isNew) {
      visitObject.Calendar_Event_ID = '';
      visitObject.Drive_Folder_ID = '';
      visitObject.Postvisit_Result = payload.postvisitResult || '';
      visitObject.Created_At = now;
      visitObject.Created_By = actor.User_ID;
      visitObject.Archived_At = '';
      visitObject.Archived_By = '';

      appendObjectRow_(PROVISIT.SHEETS.VISIT, visitObject);
      createAudit_(
        actor.User_ID,
        'CREATE',
        'VISIT',
        visitId,
        'Status',
        '',
        status,
        Utilities.getUuid()
      );
    } else {
      updateObjectRowAt_(PROVISIT.SHEETS.VISIT, visitRow.rowNumber, visitObject);
    }

    payload.visitId = visitId;
    upsertDraftState_(visitId, payload, actor.User_ID, now);

    syncSupportForVisit_(visitId, payload, actor.User_ID, now);
    syncReviewsForVisit_(visitId, payload, actor.User_ID, now);
    syncActionsForVisit_(visitId, payload, actor.User_ID, now);

    // Auto-sync ke Google Calendar jika status Planned atau Confirmed
    if (typeof syncVisitToCalendar === 'function' && (status === 'Planned' || status === 'Confirmed')) {
      try {
        syncVisitToCalendar(visitId);
      } catch (calErr) {
        Logger.log('Calendar sync warning: ' + calErr);
      }
    }

    return {
      ok: true,
      visitId: visitId,
      status: status,
      savedAt: Utilities.formatDate(now, 'Asia/Jakarta', 'HH:mm:ss'),
      savedBy: actor.Initial
    };

  } finally {
    lock.releaseLock();
  }
}

function validatePayloadForStatus_(payload, status) {
  // Draft dan Cancelled boleh belum lengkap.
  if (status === 'Draft' || status === 'Cancelled') return;

  const required = {
    visitTitle: 'Visit Title',
    visitDate: 'Visit Date',
    startTime: 'Start Time',
    customerId: 'Customer',
    visitType: 'Visit Type',
    visitPurpose: 'Visit Purpose'
  };

  Object.keys(required).forEach(key => {
    if (!String(payload[key] || '').trim()) {
      throw new Error(required[key] + ' wajib diisi sebelum status menjadi ' + status + '.');
    }
  });

  const reviews = Array.isArray(payload.principalReviews)
    ? payload.principalReviews.filter(r => String(r.principalName || '').trim())
    : [];

  if (reviews.length === 0) {
    throw new Error('Minimal 1 Principal Review wajib diisi sebelum status menjadi ' + status + '.');
  }

  if (status === 'Completed') {
    const actions = Array.isArray(payload.actions)
      ? payload.actions.filter(a => String(a.actionText || '').trim())
      : [];

    if (actions.length === 0) {
      throw new Error('Action Plan wajib diisi sebelum Visit menjadi Completed.');
    }
  }
}

function upsertDraftState_(visitId, payload, userId, now) {
  const row = findObjectRowById_(PROVISIT.SHEETS.DRAFT, 'Visit_ID', visitId);

  const obj = {
    Visit_ID: visitId,
    Draft_JSON: JSON.stringify(payload),
    Updated_At: now,
    Updated_By: userId
  };

  if (!row) {
    obj.Created_At = now;
    obj.Created_By = userId;
    appendObjectRow_(PROVISIT.SHEETS.DRAFT, obj);
  } else {
    updateObjectRowAt_(PROVISIT.SHEETS.DRAFT, row.rowNumber, obj);
  }
}

function syncSupportForVisit_(visitId, payload, userId, now) {
  const desired = new Set(
    (Array.isArray(payload.supportMarketingUserIds)
      ? payload.supportMarketingUserIds
      : []
    ).filter(Boolean)
  );

  const allowedInitials = ['IQH', 'YUP', 'SNI', 'NAF'];
  const rows = getObjectsWithRow_(PROVISIT.SHEETS.SUPPORT)
    .filter(x => x.object.Visit_ID === visitId && x.object.Support_Role === 'Marketing Support');

  // Remove support yang sudah tidak dipilih.
  rows
    .filter(x => !desired.has(x.object.User_ID))
    .sort((a, b) => b.rowNumber - a.rowNumber)
    .forEach(x => getSheet_(PROVISIT.SHEETS.SUPPORT).deleteRow(x.rowNumber));

  const existingIds = new Set(
    getObjectsWithRow_(PROVISIT.SHEETS.SUPPORT)
      .filter(x => x.object.Visit_ID === visitId && x.object.Support_Role === 'Marketing Support')
      .map(x => x.object.User_ID)
  );

  desired.forEach(userIdToAdd => {
    if (existingIds.has(userIdToAdd)) return;

    const u = getUserById_(userIdToAdd);
    if (!u || u.Active !== true) {
      throw new Error('Support Marketing tidak ditemukan atau tidak aktif.');
    }

    const initial = String(u.Initial || '').toUpperCase();
    if (!allowedInitials.includes(initial)) {
      throw new Error(initial + ' tidak termasuk pilihan Support Marketing.');
    }

    appendObjectRow_(PROVISIT.SHEETS.SUPPORT, {
      Visit_Support_ID: nextId_(PROVISIT.SHEETS.SUPPORT, 'VSP'),
      Visit_ID: visitId,
      User_ID: u.User_ID,
      Support_Role: 'Marketing Support',
      Created_At: now,
      Created_By: userId
    });
  });
}

function syncReviewsForVisit_(visitId, payload, userId, now) {
  const desiredReviews = (Array.isArray(payload.principalReviews)
    ? payload.principalReviews
    : []
  ).filter(r => String(r.principalName || '').trim());

  const desiredKeys = new Set(
    desiredReviews.map(r => String(r.clientKey || '').trim()).filter(Boolean)
  );

  const current = getObjectsWithRow_(PROVISIT.SHEETS.REVIEW)
    .filter(x => x.object.Visit_ID === visitId && String(x.object.Client_Key || '').trim());

  // Hapus review yang di-remove dari UI.
  const removed = current.filter(x => !desiredKeys.has(String(x.object.Client_Key)));

  removed.forEach(x => {
    deleteRowsWhere_(PROVISIT.SHEETS.REVIEW_PRODUCT, 'Review_ID', x.object.Review_ID);
  });

  removed
    .sort((a, b) => b.rowNumber - a.rowNumber)
    .forEach(x => getSheet_(PROVISIT.SHEETS.REVIEW).deleteRow(x.rowNumber));

  const refreshed = getObjectsWithRow_(PROVISIT.SHEETS.REVIEW)
    .filter(x => x.object.Visit_ID === visitId && String(x.object.Client_Key || '').trim());

  const byClientKey = new Map(
    refreshed.map(x => [String(x.object.Client_Key), x])
  );

  desiredReviews.forEach(review => {
    const clientKey = String(review.clientKey || '').trim();
    if (!clientKey) return;

    validatePrincipal_(review.principalName);

    let record = byClientKey.get(clientKey);
    let reviewId;

    const reviewObj = {
      Visit_ID: visitId,
      Principal_Name: review.principalName,
      Product_Catalogue: '',
      Product_Description_Snapshot: '',
      Exposure_Type: review.exposureType || '',
      Notes: review.notes || '',
      Customer_Feedback: review.customerFeedback || '',
      Opportunity_Status: review.opportunityStatus || '',
      Next_Step: review.nextStep || '',
      Updated_At: now,
      Updated_By: userId,
      Client_Key: clientKey
    };

    if (!record) {
      reviewId = nextId_(PROVISIT.SHEETS.REVIEW, 'PRL');

      appendObjectRow_(PROVISIT.SHEETS.REVIEW, Object.assign({
        Review_ID: reviewId,
        Created_At: now,
        Created_By: userId
      }, reviewObj));

    } else {
      reviewId = record.object.Review_ID;
      updateObjectRowAt_(PROVISIT.SHEETS.REVIEW, record.rowNumber, reviewObj);
    }

    syncProductsForReview_(reviewId, review.products || [], userId, now);
  });
}

function syncProductsForReview_(reviewId, products, userId, now) {
  const desired = new Map();

  (Array.isArray(products) ? products : []).forEach(p => {
    const catalogue = String(p.catalogue || '').trim();
    const description = String(p.description || '').trim();
    if (!catalogue && !description) return;

    desired.set(catalogue + '|' + description, {
      catalogue: catalogue,
      description: description
    });
  });

  const rows = getObjectsWithRow_(PROVISIT.SHEETS.REVIEW_PRODUCT)
    .filter(x => x.object.Review_ID === reviewId);

  rows
    .filter(x => !desired.has(
      String(x.object.Product_Catalogue || '') + '|' +
      String(x.object.Product_Description_Snapshot || '')
    ))
    .sort((a, b) => b.rowNumber - a.rowNumber)
    .forEach(x => getSheet_(PROVISIT.SHEETS.REVIEW_PRODUCT).deleteRow(x.rowNumber));

  const existingKeys = new Set(
    getObjectsWithRow_(PROVISIT.SHEETS.REVIEW_PRODUCT)
      .filter(x => x.object.Review_ID === reviewId)
      .map(x =>
        String(x.object.Product_Catalogue || '') + '|' +
        String(x.object.Product_Description_Snapshot || '')
      )
  );

  desired.forEach((p, key) => {
    if (existingKeys.has(key)) return;

    appendObjectRow_(PROVISIT.SHEETS.REVIEW_PRODUCT, {
      Product_Link_ID: nextId_(PROVISIT.SHEETS.REVIEW_PRODUCT, 'RPD'),
      Review_ID: reviewId,
      Product_Catalogue: p.catalogue,
      Product_Description_Snapshot: p.description,
      Created_At: now,
      Created_By: userId
    });
  });
}

function syncActionsForVisit_(visitId, payload, userId, now) {
  const desiredActions = (Array.isArray(payload.actions) ? payload.actions : [])
    .filter(a => String(a.actionText || '').trim());

  const desiredKeys = new Set(
    desiredActions.map(a => String(a.clientKey || 'ACTION-PRIMARY'))
  );

  const current = getObjectsWithRow_(PROVISIT.SHEETS.ACTION)
    .filter(x => x.object.Visit_ID === visitId && String(x.object.Client_Key || '').trim());

  current
    .filter(x => !desiredKeys.has(String(x.object.Client_Key)))
    .sort((a, b) => b.rowNumber - a.rowNumber)
    .forEach(x => getSheet_(PROVISIT.SHEETS.ACTION).deleteRow(x.rowNumber));

  const refreshed = getObjectsWithRow_(PROVISIT.SHEETS.ACTION)
    .filter(x => x.object.Visit_ID === visitId && String(x.object.Client_Key || '').trim());

  const byKey = new Map(
    refreshed.map(x => [String(x.object.Client_Key), x])
  );

  desiredActions.forEach(action => {
    const clientKey = String(action.clientKey || 'ACTION-PRIMARY');
    const existing = byKey.get(clientKey);

    const actionObj = {
      Visit_ID: visitId,
      Action_Text: action.actionText,
      Owner_User_ID: action.ownerUserId || '',
      Due_Date: action.dueDate ? parseLocalDate_(action.dueDate) : '',
      Priority: action.priority || 'Normal',
      Status: action.status || 'Open',
      Completion_Note: action.completionNote || '',
      Updated_At: now,
      Updated_By: userId,
      Client_Key: clientKey
    };

    if (!existing) {
      appendObjectRow_(PROVISIT.SHEETS.ACTION, Object.assign({
        Action_ID: nextId_(PROVISIT.SHEETS.ACTION, 'ACT'),
        Completed_At: '',
        Created_At: now,
        Created_By: userId
      }, actionObj));
    } else {
      updateObjectRowAt_(PROVISIT.SHEETS.ACTION, existing.rowNumber, actionObj);
    }
  });
}

```

**Dependensi:** `ensureV06Schema_()`, `syncVisitToCalendar()` (dari `CalendarEngine.gs`), serta seluruh helper database (`findObjectRowById_`, `updateObjectRowAt_`, `getObjectsWithRow_`, `deleteRowsWhere_`).

**Aturan bisnis:**

* **Validasi Bertingkat Berdasarkan Status:**
* Status `Draft` dan `Cancelled`: Boleh disimpan meskipun field belum lengkap.
* Status `Planned`, `Confirmed`, `Completed`: Wajib memiliki `visitTitle`, `visitDate`, `startTime`, `customerId`, `visitType`, `visitPurpose`, dan minimal 1 `principalReviews` yang memiliki `principalName`.
* Status `Completed`: Selain aturan di atas, wajib memiliki minimal 1 `actions` dengan `actionText` tidak kosong, dan otomatis mengisi kolom `Report_Completed_At = now` (jika status diubah mundur dari Completed ke status lain, `Report_Completed_At` dikosongkan kembali).


* **Idempotensi Sub-Record (`Client_Key`):** Sinkronisasi `PRINCIPAL_REVIEW_DB` dan `ACTION_DB` menggunakan UUID dari frontend (`Client_Key`, misal `REV-<uuid>` dan `ACTION-PRIMARY`) untuk menentukan apakah baris harus di-insert, di-update, atau dihapus (upsert).
* **Audit Log pada Autosave:** Audit log (`CREATE` pada modul `VISIT`) hanya dicatat saat `isNew === true`. Update berikutnya melalui `autosaveVisitFromPortal` tidak menulis baris baru ke `AUDIT_LOG`.
* **Error Calendar Tidak Membatalkan Save:** Pemanggilan `syncVisitToCalendar(visitId)` dibungkus `try...catch` sehingga kegagalan integrasi kalender hanya dicatat ke `Logger.log` dan tidak menggagalkan penyimpanan data visit.

---

## BAGIAN 4: Calendar Integration (`CalendarEngine.gs`)

### 4.1. Membaca `Primary_Sales_User_ID` & Menentukan Team / Kalender

**File:** `CalendarEngine.gs`

**Fungsi:** `getVisitById_(visitId)`, `getSalesTeam_(userKey)`, `getCalendarMaster_()`, `getCalendarByType_(type)`, `getCalendarByTeam_(team)`

**Tujuan:** Mengambil data visit dari `VISIT_DB`, membaca kolom `Primary_Sales_User_ID`, mencari `Team` user tersebut di `USER_MASTER`, lalu memetakan ke target Google Calendar di `CALENDAR_MASTER`.

**Kode asli:**

```javascript
function getVisitById_(visitId){
  const sheet =
    SpreadsheetApp
    .getActive()
    .getSheetByName(
      "VISIT_DB"
    );

  const data =
    sheet
    .getDataRange()
    .getValues();

  const headers =
    data[0];

  const idCol =
    headers.indexOf(
      "Visit_ID"
    );

  for(
    let i = 1;
    i < data.length;
    i++
  ){
    if(
      data[i][idCol] === visitId
    ){
      const obj = {};
      headers.forEach(
        function(header,index){
          obj[header] =
            data[i][index];
        }
      );
      return obj;
    }
  }
  return null;
}

function getSalesTeam_(userKey){
  const sheet =
    SpreadsheetApp
    .getActive()
    .getSheetByName(
      "USER_MASTER"
    );

  const data =
    sheet
    .getDataRange()
    .getValues();

  const headers =
    data.shift();

  const userCol =
    headers.indexOf(
      "User_ID"
    );

  const initialCol =
    headers.indexOf(
      "Initial"
    );

  const teamCol =
    headers.indexOf(
      "Team"
    );

  for(
    let i = 0;
    i < data.length;
    i++
  ){
    const row =
      data[i];

    if(
      row[userCol] === userKey ||
      row[initialCol] === userKey
    ){
      return row[teamCol];
    }
  }
  return null;
}

function getCalendarMaster_(){
  const sheet =
    SpreadsheetApp
    .getActive()
    .getSheetByName(
      "CALENDAR_MASTER"
    );

  const data =
    sheet
    .getDataRange()
    .getValues();

  const headers =
    data[0];

  const activeCol =
    headers.indexOf(
      "Active"
    );

  return data
    .slice(1)
    .filter(
      function(row){
        return (
          row[activeCol] === true ||
          String(row[activeCol])
          .toUpperCase()
          === "TRUE"
        );
      }
    )
    .map(
      function(row){
        const obj = {};
        headers.forEach(
          function(header,index){
            obj[header] =
              row[index];
          }
        );
        return obj;
      }
    );
}

function getCalendarByType_(type){
  return getCalendarMaster_()
    .find(
      function(calendar){
        return (
          calendar.Calendar_Type
          ===
          type
        );
      }
    );
}

function getCalendarByTeam_(team){
  return getCalendarMaster_()
    .find(
      function(calendar){
        return (
          calendar.Calendar_Type
          ===
          "TEAM"
          &&
          calendar.Team
          ===
          team
        );
      }
    );
}

```

**Dependensi:** Sheet `VISIT_DB`, `USER_MASTER` (memiliki kolom `Team`), dan `CALENDAR_MASTER` (memiliki kolom `Calendar_ID`, `Calendar_Name`, `Calendar_Type`, `Team`, `Google_Calendar_ID`, `Active`).

**Aturan bisnis:**

* Setiap visit yang disinkronkan dikirim ke **dua** kalender (jika konfigurasi aktif ditemukan):
1. Kalender dengan `Calendar_Type === "MANAGEMENT"`.
2. Kalender dengan `Calendar_Type === "TEAM"` yang nilai kolom `Team`-nya cocok dengan kolom `Team` milik `Primary_Sales_User_ID` di `USER_MASTER`.



---

### 4.2. Pembuatan, Pembaruan, & Pencatatan Log Jadwal Kunjungan

**File:** `CalendarEngine.gs`

**Fungsi:** `syncVisitToCalendar(visitId)`, `createCalendarEventIfNeeded_(visit, calendarConfig)`, `updateCalendarEvent_(eventId, visit, calendarConfig)`, `getExistingCalendarEvent_(visitId, calendarId)`, `logCalendarEvent_(...)`, `updateCalendarStatus_(...)`, `buildStartDateTime_(visit)`

**Tujuan:** Membuat atau mengupdate event pada Google Calendar dengan durasi tetap 60 menit untuk visit berstatus `Planned` atau `Confirmed`, dan mencatat log sinkronisasi ke `CALENDAR_EVENT_LOG`.

**Kode asli:**

```javascript
function syncVisitToCalendar(visitId) {
  const visit =
    getVisitById_(visitId);

  if (!visit) {
    throw new Error(
      "Visit tidak ditemukan: " + visitId
    );
  }

  /**
- hanya sync Planned dan Confirmed
   */
  if (
    visit.Status !== "Planned" &&
    visit.Status !== "Confirmed"
  ) {
    Logger.log(
      "SKIP STATUS : " +
      visit.Status
    );
    return;
  }

  try {
    /**
- MANAGEMENT CALENDAR
     */
    const managementCalendar =
      getCalendarByType_("MANAGEMENT");

    if (managementCalendar) {
      createCalendarEventIfNeeded_(
        visit,
        managementCalendar
      );
    }

    /**
- TEAM CALENDAR
     */
    const team =
      getSalesTeam_(
        visit.Primary_Sales_User_ID
      );

    if (team) {
      const teamCalendar =
        getCalendarByTeam_(team);

      if (teamCalendar) {
        createCalendarEventIfNeeded_(
          visit,
          teamCalendar
        );
      }
    }

    updateCalendarStatus_(
      visit.Visit_ID,
      "SUCCESS",
      ""
    );
  }
  catch(error){
    updateCalendarStatus_(
      visit.Visit_ID,
      "FAILED",
      error.message
    );
    throw error;
  }
}

function createCalendarEventIfNeeded_(
  visit,
  calendarConfig
){
  const existing =
    getExistingCalendarEvent_(
      visit.Visit_ID,
      calendarConfig.Calendar_ID
    );

  if(existing){
    Logger.log(
      "EXISTING EVENT : " +
      visit.Visit_ID +
      " | " +
      calendarConfig.Calendar_Name
    );

    updateCalendarEvent_(
      existing.eventId,
      visit,
      calendarConfig
    );

    return existing.eventId;
  }

  const calendar =
    CalendarApp
    .getCalendarById(
      calendarConfig.Google_Calendar_ID
    );

  if(!calendar){
    throw new Error(
      "Calendar tidak ditemukan : " +
      calendarConfig.Google_Calendar_ID
    );
  }

  const start =
    buildStartDateTime_(
      visit
    );

  const duration = 60;

  const end =
    new Date(
      start.getTime()
      +
      duration * 60000
    );

  const title =
    "[PROVisit] " +
    visit.Visit_Title;

  const description =
`
PROVisit

Visit ID:
${visit.Visit_ID}

Customer:
${visit.Customer_Name_Snapshot}

PIC Customer:
${visit.PIC_Customer || "-"}

Sales:
${visit.Primary_Sales_User_ID}

Purpose:
${visit.Visit_Purpose}

Status:
${visit.Status}
`;

  const event =
    calendar.createEvent(
      title,
      start,
      end,
      {
        description:
        description
      }
    );

  logCalendarEvent_(
    visit.Visit_ID,
    calendarConfig.Calendar_ID,
    "CREATE",
    event.getId(),
    "SUCCESS",
    "",
    title,
    start,
    end,
    "ACTIVE"
);

  Logger.log(
    "CREATE SUCCESS : " +
    visit.Visit_ID +
    " -> " +
    calendarConfig.Calendar_Name
  );

  return event;
}

function updateCalendarEvent_(
  eventId,
  visit,
  calendarConfig
){
  const calendar =
    CalendarApp.getCalendarById(
      calendarConfig.Google_Calendar_ID
    );

  if(!calendar){
    throw new Error(
      "Calendar tidak ditemukan"
    );
  }

  const event =
    calendar.getEventById(eventId);

  if(!event){
    throw new Error(
      "Event tidak ditemukan: " + eventId
    );
  }

  const start =
    buildStartDateTime_(visit);

  const end =
    new Date(
      start.getTime() + 60 * 60000
    );

  const title =
    "[PROVisit] " + visit.Visit_Title;

  event.setTitle(title);
  event.setTime(start, end);

  event.setDescription(
`PROVisit

Visit ID:
${visit.Visit_ID}

Customer:
${visit.Customer_Name_Snapshot}

PIC Customer:
${visit.PIC_Customer || "-"}

Sales:
${visit.Primary_Sales_User_ID}

Purpose:
${visit.Visit_Purpose}

Status:
${visit.Status}`
  );

  logCalendarEvent_(
    visit.Visit_ID,
    calendarConfig.Calendar_ID,
    "CREATE",
    event.getId(),
    "SUCCESS",
    "",
    title,
    start,
    end,
    "ACTIVE"
);
}

function getExistingCalendarEvent_(
  visitId,
  calendarId
){
  const sheet =
    SpreadsheetApp
    .getActive()
    .getSheetByName(
      "CALENDAR_EVENT_LOG"
    );

  if(!sheet){
    return null;
  }

  const data =
    sheet
    .getDataRange()
    .getValues();

  if(data.length <= 1){
    return null;
  }

  const headers =
    data[0];

  const visitCol =
    headers.indexOf(
      "Visit_ID"
    );

  const calCol =
    headers.indexOf(
      "Calendar_ID"
    );

  const eventCol =
    headers.indexOf(
      "Calendar_Event_ID"
    );

  const statusCol =
    headers.indexOf(
      "Sync_Status"
    );

  for(
    let i = 1;
    i < data.length;
    i++
  ){
    if(
      data[i][visitCol] === visitId &&
      data[i][calCol] === calendarId &&
      data[i][statusCol] === "SUCCESS"
    ){
      return {
        eventId: data[i][eventCol],
        eventTitle: data[i][headers.indexOf("Event_Title_Snapshot")],
        eventStart: data[i][headers.indexOf("Event_Start_Snapshot")],
        eventEnd: data[i][headers.indexOf("Event_End_Snapshot")]
      };
    }
  }
  return null;
}

function logCalendarEvent_(
  visitId,
  calendarId,
  action,
  eventId,
  syncStatus,
  errorMessage,
  eventTitle,
  eventStart,
  eventEnd,
  eventStatus
) {
  const sheet =
    SpreadsheetApp
      .getActive()
      .getSheetByName("CALENDAR_EVENT_LOG");

  const now = new Date();

  sheet.appendRow([
    Utilities.getUuid(),       // Calendar_Log_ID
    visitId,                   // Visit_ID
    calendarId,                // Calendar_ID
    action,                    // Action
    eventId,                   // Calendar_Event_ID
    syncStatus,                // Sync_Status
    errorMessage || "",        // Error_Message
    now,                       // Created_At
    now,                       // Updated_At
    eventStatus || "ACTIVE",   // Event_Status
    eventTitle || "",          // Event_Title_Snapshot
    eventStart || "",          // Event_Start_Snapshot
    eventEnd || ""              // Event_End_Snapshot
  ]);
}

function updateCalendarStatus_(
  visitId,
  status,
  errorMessage
){
  const sheet =
    SpreadsheetApp
    .getActive()
    .getSheetByName(
      "VISIT_DB"
    );

  const data =
    sheet
    .getDataRange()
    .getValues();

  const headers =
    data[0];

  const visitCol =
    headers.indexOf(
      "Visit_ID"
    );

  const statusCol =
    headers.indexOf(
      "Calendar_Sync_Status"
    );

  const errorCol =
    headers.indexOf(
      "Calendar_Sync_Error"
    );

  for(
    let i = 1;
    i < data.length;
    i++
  ){
    if(
      data[i][visitCol]
      ===
      visitId
    ){
      if(statusCol !== -1){
        sheet
        .getRange(
          i+1,
          statusCol+1
        )
        .setValue(
          status
        );
      }

      if(errorCol !== -1){
        sheet
        .getRange(
          i+1,
          errorCol+1
        )
        .setValue(
          errorMessage
        );
      }
      return;
    }
  }
}

function buildStartDateTime_(visit){
  let rawDate =
    visit.Visit_Date;

  let date;

  if(rawDate instanceof Date){
    date =
      new Date(
        rawDate.getFullYear(),
        rawDate.getMonth(),
        rawDate.getDate()
      );
  }
  else if(
    typeof rawDate === "number"
  ){
    date =
      new Date(
        1899,
        11,
        30 + rawDate
      );
  }
  else{
    const value =
      String(rawDate);

    if(
      value.indexOf("/") !== -1
    ){
      const parts =
        value.split("/");

      date =
        new Date(
          Number(parts[2]),
          Number(parts[1])-1,
          Number(parts[0])
        );
    }
    else{
      date =
        new Date(value);
    }
  }

  if(
    isNaN(date.getTime())
  ){
    throw new Error(
      "Format tanggal tidak valid: "
      +
      rawDate
    );
  }

  let hour = 0;
  let minute = 0;

  if(
    visit.Start_Time instanceof Date
  ){
    hour =
      visit.Start_Time.getHours();

    minute =
      visit.Start_Time.getMinutes();
  }
  else{
    const time =
      String(
        visit.Start_Time
      )
      .split(":");

    hour =
      Number(time[0])
      ||
      0;

    minute =
      Number(time[1])
      ||
      0;
  }

  date.setHours(
    hour,
    minute,
    0,
    0
  );

  return date;
}

```

**Dependensi:** `CalendarApp`, `Utilities.getUuid()`.

**Aturan bisnis:**

* **Filter Status:** Hanya visit dengan status `"Planned"` atau `"Confirmed"` yang dikirim ke Google Calendar. Status `Draft`, `Completed`, dan `Cancelled` di-skip.
* **Durasi Default:** Semua event kalender dibuat dengan durasi fix **60 menit** (`duration = 60`), format judul `[PROVisit] <Visit_Title>`, dan timezone aplikasi (`Asia/Jakarta`).
* **Pencegahan Duplikasi Event:** Sebelum membuat event baru, sistem mengecek `CALENDAR_EVENT_LOG` berdasarkan pasangan `Visit_ID` + `Calendar_ID` dengan `Sync_Status === "SUCCESS"`. Jika ditemukan, sistem meng-update event yang sudah ada menggunakan `updateCalendarEvent_`.
* **Kolom Tambahan pada `VISIT_DB`:** `updateCalendarStatus_` mencari kolom opsional `Calendar_Sync_Status` dan `Calendar_Sync_Error` pada `VISIT_DB` untuk menyimpan status sinkronisasi terakhir.

---

## BAGIAN 5: Aturan Bisnis Tambahan

### 5.1. Hak Akses & Autentikasi User (`Code.gs`)

**File:** `Code.gs`

**Fungsi:** `getCurrentUser_()`, `getBootstrapData()`

**Tujuan:** Memvalidasi user yang mengakses portal berdasarkan email Google Workspace mereka terhadap tabel `USER_MASTER`.

**Kode asli:**

```javascript
function getCurrentUser_() {
  let email = '';

  try {
    email = Session.getActiveUser().getEmail();
  } catch (e) {}

  if (!email) {
    try {
      email = Session.getEffectiveUser().getEmail();
    } catch (e) {}
  }

  if (!email) {
    throw new Error(
      'Email user tidak dapat dikenali. Pastikan Apps Script menggunakan akun Google Workspace Prolabios.'
    );
  }

  const user = getUserByEmail_(email);
  if (!user) {
    if (String(email).trim().toLowerCase() === 'marketing@prolabios.com') {
      return {
        User_ID: 'USR-DEV-MKT',
        Email: 'marketing@prolabios.com',
        Initial: 'MKT',
        Full_Name: 'Marketing Prolabios (Dev Admin)',
        Role: 'Admin',
        Active: true
      };
    }
    throw new Error('Akun ' + email + ' belum terdaftar di USER_MASTER.');
  }
  if (user.Active !== true) {
    throw new Error('Akun PROVisit Anda sedang nonaktif.');
  }

  return user;
}

function getBootstrapData() {
  ensureV06Schema_();
  const user = getCurrentUser_();
  const supportInitials = ['IQH', 'YUP', 'SNI', 'NAF'];
  const supportMarketingUsers = getActiveUsers_()
    .filter(u => supportInitials.includes(String(u.Initial || '').toUpperCase()))
    .map(u => ({
      userId: u.User_ID,
      initial: u.Initial,
      fullName: u.Full_Name
    }));

  return {
    currentUser: {
      userId: user.User_ID,
      email: user.Email,
      initial: user.Initial,
      fullName: user.Full_Name,
      role: user.Role
    },
    activeUsers: getActiveUsers_().map(u => ({
      userId: u.User_ID,
      initial: u.Initial,
      fullName: u.Full_Name,
      role: u.Role
    })),
    principals: getPrincipals(),
    visitPurposes: getVisitPurposes_(),
    supportMarketingUsers: supportMarketingUsers
  };
}

```

**Aturan bisnis:**

* **Whitelist Email & Fallback Admin:** Hanya email yang terdaftar di `USER_MASTER` dengan `Active === true` yang dapat mengakses backend, dengan pengecualian hardcode untuk akun `marketing@prolabios.com` yang otomatis diberi peran `Admin` (`USR-DEV-MKT`) meskipun tidak ada di `USER_MASTER`.
* **Whitelist Support Marketing:** Pilihan checkbox *Support Marketing* di-hardcode secara ketat hanya untuk 4 inisial: `['IQH', 'YUP', 'SNI', 'NAF']` (baik di `getBootstrapData()`, `createVisit()`, maupun `syncSupportForVisit_()`).

---

### 5.2. Pengecekan Bentrok Jadwal di Frontend (`Index.html`)

**File:** `Index.html`

**Fungsi:** `checkScheduleConflict()`

**Tujuan:** Memberi peringatan visual jika PIC Sales yang sama sudah memiliki jadwal visit pada tanggal dan jam yang sama.

**Kode asli:**

```javascript
  function checkScheduleConflict() {
    const alertBox = document.getElementById('scheduleConflictAlert');
    if (!alertBox) return;

    const date = document.getElementById('visitDate').value;
    const time = document.getElementById('startTime').value;
    const salesUserId = document.getElementById('primarySalesUserId').value;

    if (!date || !time || !salesUserId) {
      alertBox.style.display = 'none';
      return;
    }

    const conflict = allVisitsCache.find(v =>
      v.visitId !== currentVisitId &&
      v.primarySalesUserId === salesUserId &&
      v.visitDate === date &&
      v.startTime === time &&
      v.status !== 'Cancelled'
    );

    if (conflict) {
      alertBox.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;margin-right:4px;">
          <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
          <line x1="12" y1="9" x2="12" y2="13"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        Jadwal bentrok: PIC Sales sudah memiliki visit "${escapeHtml(conflict.visitTitle)}" pada jam ini!
      `;
      alertBox.style.display = 'block';
    } else {
      alertBox.style.display = 'none';
    }
  }

```

**Aturan bisnis:** Pengecekan bentrok bersifat *soft warning* (tidak memblokir penyimpanan) dan hanya berjalan di sisi klien berdasarkan 200 data visit terakhir yang dimuat di `allVisitsCache`. *(Catatan bug pada kode lama: `v.visitDate` di `allVisitsCache` diformat `dd MMM yyyy`, sedangkan input `#visitDate` bertipe `date` berformat `yyyy-MM-dd`, sehingga perbandingan string ini pada kode lama tidak pernah bernilai `true` kecuali formatnya disamakan saat migrasi).*

---

### 5.3. Default Value, Opsi Status New Visit, & Relasi Antar Spreadsheet (`Index.html` & `Code.gs`)

**File:** `Index.html` & `Code.gs`

**Fungsi:** `renderStatusOptions(isNew, currentVal)`, `getVisitPurposes_()`, `getPrincipals()`, `searchProductsForPortal(principalName, query)`

**Kode asli:**

```javascript
// Index.html:
  function renderStatusOptions(isNew, currentVal) {
    const select = document.getElementById('status');
    if (!select) return;

    if (isNew) {
      select.innerHTML = `
        <option value="Draft" ${currentVal === 'Draft' ? 'selected' : ''}>Draft</option>
        <option value="Planned" ${currentVal === 'Planned' ? 'selected' : ''}>Planned</option>
      `;
    } else {
      select.innerHTML = `
        <option value="Draft" ${currentVal === 'Draft' ? 'selected' : ''}>Draft</option>
        <option value="Planned" ${currentVal === 'Planned' ? 'selected' : ''}>Planned</option>
        <option value="Confirmed" ${currentVal === 'Confirmed' ? 'selected' : ''}>Confirmed</option>
        <option value="Completed" ${currentVal === 'Completed' ? 'selected' : ''}>Completed</option>
        <option value="Cancelled" ${currentVal === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
      `;
    }
    select.value = currentVal || 'Draft';
  }

// Code.gs:
function getVisitPurposes_() {
  return getObjectsFromSheet_(PROVISIT.SHEETS.CONFIG)
    .filter(r =>
      r.Config_Group === 'VISIT_PURPOSE' &&
      (r.Active === true || String(r.Active).toUpperCase() === 'TRUE')
    )
    .sort((a, b) => String(a.Config_Key).localeCompare(String(b.Config_Key)))
    .map(r => r.Config_Value)
    .filter(Boolean);
}

```

**Aturan bisnis:**

* **Opsi Status pada New Visit:** Saat membuat visit baru (`isNew === true`), dropdown Status di UI hanya menampilkan **`Draft`** (default) dan **`Planned`**. Opsi `Confirmed`, `Completed`, dan `Cancelled` baru muncul setelah visit tersimpan pertama kali (`isNew === false`).
* **Default Value Form:** `Visit_Type` = `'Offline Visit'`, `Status` = `'Draft'`, `Support Marketing` = `'None'`, `Action Priority` = `'Normal'`, `Action Status` = `'Open'`, `Start Time` = interval kelipatan 15 menit (`00:00` s/d `23:45`).
* **Relasi 3 Spreadsheet Terpisah:**
1. **Backend DB (`SpreadsheetApp.getActiveSpreadsheet()`):** `VISIT_DB`, `VISIT_SUPPORT_DB`, `PRINCIPAL_REVIEW_DB`, `PRINCIPAL_REVIEW_PRODUCT_DB`, `ACTION_DB`, `ATTACHMENT_DB`, `USER_MASTER`, `AUDIT_LOG`, `CONFIG`, `DRAFT_STATE_DB`, `CALENDAR_MASTER`, `CALENDAR_EVENT_LOG`.
2. **Customer DB (`config.CUSTOMER_SPREADSHEET_ID`):** Sheet `Customer_by_Company` (Kolom A, B, C, F) dan `Customer_by_PIC` (Kolom A, B, C, D, E).
3. **Product DB (`config.PRODUCT_SPREADSHEET_ID`):** Sheet `Principal_List` (Kolom A) dan `Product_Details` (Kolom A = Catalogue, B = Description, D = Principal).



---

## RINGKASAN AUDIT MIGRASI

### 1. Semua Fungsi yang Berhasil Ditemukan

* **Backend (`Code.gs`):** `clearAllCaches`, `testRender`, `healthCheck`, `getCustomers`, `getCustomerPICs`, `getPrincipals`, `getVisitPurposes_`, `getProductsByPrincipal`, `createVisit`, `ensureV04Schema_`, `ensureV06Schema_`, `ensureHeader_`, `validateCreateVisit_`, `validatePrincipal_`, `getCurrentUser_`, `getActiveUsers_`, `getUserByEmail_`, `getUserById_`, `getUserByInitial_`, `getCustomerById_`, `backend_`, `getSheet_`, `getObjectsFromSheet_`, `appendObjectRow_`, `nextId_`, `getConfigMap_`, `createAudit_`, `parseLocalDate_`, `parseLocalTime_`, `autosaveVisitFromPortal`, `validatePayloadForStatus_`, `upsertDraftState_`, `loadDraftFromPortal`, `syncSupportForVisit_`, `syncReviewsForVisit_`, `syncProductsForReview_`, `syncActionsForVisit_`, `getObjectsWithRow_`, `findObjectRowById_`, `updateObjectRowAt_`, `deleteRowsWhere_`, `doGet`, `getBootstrapData`, `searchCustomersForPortal`, `getCustomerContextForPortal`, `getProductsForPortal`, `searchProductsForPortal`, `saveVisitFromPortal`, `autosaveFromPortal`, `quickUpdateVisitStatus`, `getVisitListForPortal`, `getVisitWorkspacePayload`, `reconstructVisitPayload_`, `toBoolean_`, `asTimestamp_`, `formatDateForInput_`, `formatTimeForInput_`, `formatDateForPortal_`, `formatTimeForPortal_`, `formatDateTimeForPortal_`.
* **Calendar Engine (`CalendarEngine.gs`):** `syncVisitToCalendar`, `createCalendarEventIfNeeded_`, `updateCalendarEvent_`, `getExistingCalendarEvent_`, `getVisitById_`, `getSalesTeam_`, `getCalendarMaster_`, `getCalendarByType_`, `getCalendarByTeam_`, `logCalendarEvent_`, `updateCalendarStatus_`, `buildStartDateTime_`, `syncAllActiveVisits`, `testCalendarSync`, `debugVisitTeamRouting`, `debugCalendarRouting`, `debugCalendarLog`.
* **Frontend (`Index.html`):** `installKeyboardShortcuts`, `init`, `showLoadingOverlay`, `hideLoadingOverlay`, `fillVisitListSalesFilter`, `scheduleVisitListReload`, `showVisitList`, `showFormView`, `loadVisitList`, `updateStatCounters`, `quickFilterStatus`, `setQuickDate`, `resetAllFilters`, `updateStatCardActiveState`, `validateTab1`, `switchFormTab`, `navigateStep`, `onStatusChanged`, `onVisitSearchInput`, `clearVisitSearch`, `onCustomerSearchInput`, `clearCustomerSelection`, `cancelAndDiscardVisit`, `updateStepperButtons`, `manualSaveVisit`, `filterAndRenderVisits`, `renderVisitList`, `quickChangeStatus`, `openVisitWorkspace`, `updateWorkspaceHeader`, `openNewVisit`, `renderStatusOptions`, `resetFormForNewVisit`, `checkScheduleConflict`, `fillStartTimes`, `setSaveState`, `installAutosaveTracking`, `scheduleAutosave`, `runAutosave`, `hasMeaningfulContent`, `writeLocalDraft`, `readLocalDraft`, `startBlankVisit`, `fillSupportMarketing`, `supportNoneChanged`, `supportOptionChanged`, `collectSupportMarketing`, `fillVisitPurposes`, `fillUsers`, `collectPayload`, `restorePayload`, `showError`, `hideMessage`, `escapeHtml`, `escapeAttr`, `searchCustomer`, `renderCustomers`, `selectCustomer`, `resetPICs`, `fillPICs`, `makeClientKey`, `addReview`, `removeReview`, `principalChanged`, `scheduleProductSearch`, `searchProduct`, `renderProductResults`, `addProductToReview`, `appendSelectedProduct`, `removeSelectedProduct`, `collectProducts`, `collectReviews`.

### 2. Fungsi yang Belum Ditemukan

* Tidak ada fungsi yang hilang/terputus di antara 3 file yang disertakan; seluruh pemanggilan antar-fungsi di dalam `Code.gs`, `Index.html`, dan `CalendarEngine.gs` terdefinisi lengkap.
* Catatan penamaan: Entitas **"Institution"** yang disebut dalam daftar pertanyaan tidak ada dalam codebase (yang digunakan adalah **Customer** dan **Principal**).

### 3. Dependensi yang Belum Bisa Dipastikan

* **Isi tabel `CONFIG`:** Nilai spesifik `CUSTOMER_SPREADSHEET_ID`, `CUSTOMER_COMPANY_SHEET`, `CUSTOMER_PIC_SHEET`, `PRODUCT_SPREADSHEET_ID`, `PRODUCT_DETAIL_SHEET`, dan `PRINCIPAL_SHEET` dibaca dinamis dari sheet `CONFIG` (tidak di-hardcode di kode).
* **Isi tabel `CALENDAR_MASTER`:** Daftar `Google_Calendar_ID` dan pemetaan nilai kolom `Team` (pada komentar header tertulis contoh `YES/MOU`, namun daftar lengkap tim bergantung pada isi sheet `USER_MASTER` & `CALENDAR_MASTER`).
* **Skema penuh `ATTACHMENT_DB` & `Google Drive Folder`:** Kolom `Drive_Folder_ID` diinisialisasi kosong (`''`) pada `VISIT_DB` dan konstanta `PROVISIT.SHEETS.ATTACHMENT = 'ATTACHMENT_DB'` dideklarasikan, namun belum ada fungsi upload/integrasi Google Drive di file-file ini.
* **Event Penghapusan / Pembatalan Kalender:** `CalendarEngine.gs` belum memiliki fungsi untuk menghapus atau membatalkan event Google Calendar ketika status visit diubah menjadi `Cancelled` atau `Draft`.

### 4. Aturan Bisnis yang Wajib Dipertahankan saat Migrasi ke Laravel + Svelte

1. **Strict Customer -> PIC Sales Lock:** `Primary_Sales_User_ID` wajib di-resolve di backend dari `Customer_by_Company` (Kolom F / `salesInitial`) -> `USER_MASTER` (`Initial`, `Active === true`). Jika customer belum punya PIC Sales atau user-nya nonaktif, sistem wajib menolak pemilihan/penyimpanan.
2. **Default Action Owner Otomatis:** Saat customer dipilih di UI, default `actionOwner` otomatis mengikuti `Primary_Sales_User_ID` customer tersebut.
3. **Data Snapshotting:** `Customer_Name_Snapshot`, `PIC_Position_Snapshot`, dan `Product_Description_Snapshot` wajib disimpan sebagai kolom snapshot statis pada tabel transaksi agar perubahan master data di masa depan tidak mengubah histori kunjungan lama.
4. **Validasi Progresif Berdasarkan Status:**
* `Draft` / `Cancelled`: Boleh disimpan parsial (minimal punya `Visit_Title` dan `Customer_ID` untuk memicu pembuatan record pertama kali).
* `Planned` / `Confirmed`: Wajib lengkap seluruh field utama + minimal 1 `Principal Review`.
* `Completed`: Wajib lengkap field utama + minimal 1 `Principal Review` + minimal 1 `Action Plan` + mencatat timestamp `Report_Completed_At`.


5. **Whitelist Support Marketing:** Hanya user aktif dengan inisial `IQH`, `YUP`, `SNI`, `NAF` yang valid dipilih sebagai `Marketing Support`.
6. **Idempotent Upsert via `Client_Key`:** Sinkronisasi relasi `Principal Review` dan `Action Plan` saat autosave/update menggunakan identifier unik dari frontend (`Client_Key`) untuk melakukan *insert/update/delete* tanpa menduplikasi baris.
7. **Dual-Calendar Sync (Management + Team):** Setiap visit yang berstatus `Planned` atau `Confirmed` wajib dikirim ke Kalender Management dan Kalender Team (berdasarkan `Team` milik `Primary_Sales_User_ID`) dengan durasi 60 menit, serta mengecek log (`CALENDAR_EVENT_LOG`) untuk melakukan *update* alih-alih membuat event ganda.
