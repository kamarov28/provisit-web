/**
 * PROVisit Backend v0.3.0
 * -----------------------
 * File ini adalah mesin dasar PROVisit.
 *
 * CARA PASANG:
 * 1) Buka PROVisit_Backend_DB_v1.0
 * 2) Extensions > Apps Script
 * 3) Hapus contoh kode yang ada di Code.gs
 * 4) Paste seluruh isi file ini
 * 5) Save
 * 6) Jalankan fungsi: healthCheck
 * 7) Beri izin akses Google ketika diminta
 *
 * Tahap v0.3:
 * - mengenali user PROVisit
 * - membaca Customer + PIC Customer
 * - membaca Principal + Product
 * - membuat Visit
 * - membuat Principal Review
 * - membuat Action Plan
 * - membuat Audit Log
 */

const PROVISIT = {
  SHEETS: {
    VISIT: 'VISIT_DB',
    SUPPORT: 'VISIT_SUPPORT_DB',
    REVIEW: 'PRINCIPAL_REVIEW_DB',
    REVIEW_PRODUCT: 'PRINCIPAL_REVIEW_PRODUCT_DB',
    ACTION: 'ACTION_DB',
    ATTACHMENT: 'ATTACHMENT_DB',
    USER: 'USER_MASTER',
    AUDIT: 'AUDIT_LOG',
    CONFIG: 'CONFIG',
    DRAFT: 'DRAFT_STATE_DB'
  }
};


/* =========================================================
 *  PUBLIC / TEST FUNCTIONS
 * ========================================================= */

/**
 * Test paling aman. Tidak menulis data apa pun.
 * Jalankan fungsi ini pertama kali dari Apps Script.
 */
function clearAllCaches() {
  const cache = CacheService.getScriptCache();
  cache.removeAll(['CACHE_ALL_CUSTOMERS_V1', 'CACHE_ALL_PRINCIPALS_V1', 'V06_SCHEMA_CHECKED_V1']);
  Logger.log('Caches cleared');
  return 'CLEARED';
}

function testRender() {
  const output = doGet();
  Logger.log('Title: ' + output.getTitle());
  return 'SUCCESS';
}

function healthCheck() {
  ensureV06Schema_();
  const user = getCurrentUser_();
  const config = getConfigMap_();

  const customers = getCustomers('');
  const principals = getPrincipals();
  const users = getActiveUsers_();

  const result = {
    status: 'OK',
    backendVersion: config.BACKEND_VERSION || 'unknown',
    timestamp: new Date(),
    currentUser: {
      userId: user.User_ID,
      email: user.Email,
      initial: user.Initial,
      fullName: user.Full_Name,
      role: user.Role
    },
    connection: {
      activeUsers: users.length,
      customersReadable: customers.length,
      principalsReadable: principals.length
    }
  };

  Logger.log(JSON.stringify(result, null, 2));
  return result;
}


/**
 * Mengambil customer untuk dropdown / search portal.
 * query boleh kosong.
 */
/**
 * Mengambil customer untuk dropdown / search portal.
 * query boleh kosong. Dilengkapi Script Cache (10 menit) agar cepat.
 */
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


/**
 * Setelah customer dipilih, fungsi ini mengambil PIC milik customer tersebut.
 * PIC tidak wajib, sehingga hasil kosong tetap valid.
 */
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


/**
 * Mengambil daftar Principal dari DATABASE_PRODUCT_v1.0.
 */
function getPrincipals() {
  const cache = CacheService.getScriptCache();
  const cacheKey = 'CACHE_ALL_PRINCIPALS_V1';
  const cached = cache.get(cacheKey);

  if (cached) {
    try {
      return JSON.parse(cached);
    } catch (e) {}
  }

  const config = getConfigMap_();
  const ss = SpreadsheetApp.openById(config.PRODUCT_SPREADSHEET_ID);
  const sh = ss.getSheetByName(config.PRINCIPAL_SHEET);

  if (!sh) throw new Error('Sheet Principal_List tidak ditemukan.');

  const lastRow = sh.getLastRow();
  if (lastRow < 2) return [];

  const principals = sh.getRange(2, 1, lastRow - 1, 1)
    .getDisplayValues()
    .flat()
    .filter(Boolean)
    .sort((a, b) => a.localeCompare(b, 'en', {sensitivity: 'base'}))
    .map(name => ({ principalName: name }));

  try {
    cache.put(cacheKey, JSON.stringify(principals), 1800); // cache 30 menit
  } catch (err) {}

  return principals;
}

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



/**
 * Setelah Principal dipilih, ambil produk principal tersebut.
 * Product_Details: A Catalogue, B Description, D Principal.
 */
function getProductsByPrincipal(principalName) {
  if (!principalName) return [];

  const config = getConfigMap_();
  const ss = SpreadsheetApp.openById(config.PRODUCT_SPREADSHEET_ID);
  const sh = ss.getSheetByName(config.PRODUCT_DETAIL_SHEET);

  if (!sh) throw new Error('Sheet Product_Details tidak ditemukan.');

  const lastRow = sh.getLastRow();
  if (lastRow < 2) return [];

  const values = sh.getRange(2, 1, lastRow - 1, 4).getDisplayValues();

  return values
    .filter(r => r[3] === principalName && (r[0] || r[1]))
    .map(r => ({
      catalogue: r[0] || '',
      description: r[1] || '',
      principalName: r[3]
    }));
}


/**
 * Membuat satu Visit beserta minimal satu Principal Review.
 *
 * Contoh payload nantinya dikirim dari portal:
 * {
 *   visitTitle: "MICA Advance Demo - Kalbe Farma",
 *   visitDate: "2026-10-02",
 *   startTime: "10:00",
 *   customerId: "CPMA0010",
 *   picCustomer: "Fernando",
 *   primarySalesUserId: "USR-0008",
 *   visitType: "Offline Visit",
 *   visitPurpose: "Product Demo",
 *   agenda: "...",
 *   status: "Planned",
 *   principalReviews: [{
 *      principalName: "Diamidex",
 *      productCatalogue: "",
 *      exposureType: "Demo",
 *      notes: "MICA Advance",
 *      customerFeedback: "",
 *      opportunityStatus: "Interested",
 *      nextStep: ""
 *   }],
 *   actions: []
 * }
 */
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


/* =========================================================
 *  v0.4 SCHEMA INITIALIZATION
 * ========================================================= */

function ensureV04Schema_() {
  const ss = backend_();
  let sh = ss.getSheetByName(PROVISIT.SHEETS.REVIEW_PRODUCT);

  if (!sh) {
    sh = ss.insertSheet(PROVISIT.SHEETS.REVIEW_PRODUCT);
  }

  const headers = [
    'Product_Link_ID',
    'Review_ID',
    'Product_Catalogue',
    'Product_Description_Snapshot',
    'Created_At',
    'Created_By'
  ];

  const current = sh.getRange(1, 1, 1, headers.length).getDisplayValues()[0];
  const headerMissing = headers.some((h, i) => current[i] !== h);

  if (headerMissing) {
    sh.getRange(1, 1, 1, headers.length).setValues([headers]);
    sh.setFrozenRows(1);
  }
}


function ensureV06Schema_() {
  const cache = CacheService.getScriptCache();
  if (cache.get('V06_SCHEMA_CHECKED_V1')) return;

  ensureV04Schema_();

  const ss = backend_();

  let draft = ss.getSheetByName(PROVISIT.SHEETS.DRAFT);
  if (!draft) {
    draft = ss.insertSheet(PROVISIT.SHEETS.DRAFT);
  }

  const draftHeaders = [
    'Visit_ID',
    'Draft_JSON',
    'Created_At',
    'Created_By',
    'Updated_At',
    'Updated_By'
  ];

  const currentDraftHeaders =
    draft.getRange(1, 1, 1, draftHeaders.length).getDisplayValues()[0];

  if (draftHeaders.some((h, i) => currentDraftHeaders[i] !== h)) {
    draft.getRange(1, 1, 1, draftHeaders.length).setValues([draftHeaders]);
    draft.setFrozenRows(1);
  }

  ensureHeader_(PROVISIT.SHEETS.REVIEW, 'Client_Key');
  ensureHeader_(PROVISIT.SHEETS.ACTION, 'Client_Key');

  try {
    cache.put('V06_SCHEMA_CHECKED_V1', 'OK', 21600); // 6 jam skip pemeriksaan
  } catch (e) {}
}


function ensureHeader_(sheetName, headerName) {
  const sh = getSheet_(sheetName);
  const lastCol = Math.max(sh.getLastColumn(), 1);
  const headers = sh.getRange(1, 1, 1, lastCol).getDisplayValues()[0];

  if (headers.includes(headerName)) return;

  sh.getRange(1, lastCol + 1).setValue(headerName);
}


/* =========================================================
 *  VALIDATION
 * ========================================================= */

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


/* =========================================================
 *  USER / ACCESS
 * ========================================================= */

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


function getActiveUsers_() {
  return getObjectsFromSheet_(PROVISIT.SHEETS.USER)
    .filter(x => x.Email && x.Active === true);
}


function getUserByEmail_(email) {
  const target = String(email || '').trim().toLowerCase();
  return getObjectsFromSheet_(PROVISIT.SHEETS.USER)
    .find(x => String(x.Email || '').trim().toLowerCase() === target) || null;
}


function getUserById_(userId) {
  return getObjectsFromSheet_(PROVISIT.SHEETS.USER)
    .find(x => x.User_ID === userId) || null;
}


function getUserByInitial_(initial) {
  const target = String(initial || '').trim().toUpperCase();
  return getObjectsFromSheet_(PROVISIT.SHEETS.USER)
    .find(x => String(x.Initial || '').trim().toUpperCase() === target) || null;
}


/* =========================================================
 *  CUSTOMER HELPERS
 * ========================================================= */

function getCustomerById_(customerId) {
  return getCustomers('')
    .find(x => x.customerId === customerId) || null;
}


/* =========================================================
 *  DATABASE HELPERS
 * ========================================================= */

function backend_() {
  return SpreadsheetApp.getActiveSpreadsheet();
}


function getSheet_(name) {
  const sh = backend_().getSheetByName(name);
  if (!sh) throw new Error('Backend sheet "' + name + '" tidak ditemukan.');
  return sh;
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


function appendObjectRow_(sheetName, obj) {
  const sh = getSheet_(sheetName);
  const headers = sh.getRange(1, 1, 1, sh.getLastColumn()).getValues()[0];

  const row = headers.map(h =>
    Object.prototype.hasOwnProperty.call(obj, h) ? obj[h] : ''
  );

  sh.appendRow(row);
}


function nextId_(sheetName, prefix) {
  const sh = getSheet_(sheetName);
  const year = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy');
  const lastRow = sh.getLastRow();

  let max = 0;

  if (lastRow >= 2) {
    const ids = sh.getRange(2, 1, lastRow - 1, 1).getDisplayValues().flat();

    ids.forEach(id => {
      const m = String(id).match(new RegExp('^' + prefix + '-' + year + '-(\\d{6})$'));
      if (m) max = Math.max(max, Number(m[1]));
    });
  }

  return prefix + '-' + year + '-' + String(max + 1).padStart(6, '0');
}


function getConfigMap_() {
  const rows = getObjectsFromSheet_(PROVISIT.SHEETS.CONFIG);
  const map = {};

  rows
    .filter(r => r.Active === true || String(r.Active).toUpperCase() === 'TRUE')
    .forEach(r => {
      map[r.Config_Key] = r.Config_Value;
    });

  return map;
}


/* =========================================================
 *  AUDIT LOG
 * ========================================================= */

function createAudit_(userId, action, module, recordId, fieldName, oldValue, newValue, requestId) {
  appendObjectRow_(PROVISIT.SHEETS.AUDIT, {
    Audit_ID: nextId_(PROVISIT.SHEETS.AUDIT, 'AUD'),
    Timestamp: new Date(),
    User_ID: userId,
    Action: action,
    Module: module,
    Record_ID: recordId,
    Field_Name: fieldName || '',
    Old_Value: oldValue === undefined ? '' : oldValue,
    New_Value: newValue === undefined ? '' : newValue,
    Request_ID: requestId || Utilities.getUuid()
  });
}


/* =========================================================
 *  DATE / TIME HELPERS
 * ========================================================= */

function parseLocalDate_(value) {
  if (value instanceof Date) return value;

  const parts = String(value).split('-').map(Number);
  if (parts.length !== 3 || parts.some(isNaN)) {
    throw new Error('Format tanggal harus YYYY-MM-DD.');
  }

  return new Date(parts[0], parts[1] - 1, parts[2]);
}


function parseLocalTime_(value) {
  if (value instanceof Date) return value;

  const parts = String(value).split(':').map(Number);
  if (parts.length < 2 || parts.some(isNaN)) {
    throw new Error('Format waktu harus HH:MM.');
  }

  // Google Sheets menyimpan time sebagai bagian dari satu hari.
  return new Date(1899, 11, 30, parts[0], parts[1], 0);
}



/* =========================================================
 *  AUTOSAVE / DRAFT ENGINE v0.6
 * ========================================================= */

/**
 * Menyimpan perubahan tanpa tombol Save.
 * Draft boleh belum lengkap.
 * Planned / Confirmed wajib punya field utama + minimal 1 Principal Review.
 * Completed juga wajib punya Action Plan.
 */
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


function loadDraftFromPortal(visitId) {
  ensureV06Schema_();
  getCurrentUser_(); // authorization check

  const row = findObjectRowById_(PROVISIT.SHEETS.DRAFT, 'Visit_ID', visitId);
  if (!row) return null;

  try {
    return JSON.parse(row.object.Draft_JSON || '{}');
  } catch (e) {
    return null;
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


function getObjectsWithRow_(sheetName) {
  const sh = getSheet_(sheetName);
  const values = sh.getDataRange().getValues();

  if (values.length < 2) return [];

  const headers = values[0].map(String);

  return values.slice(1)
    .map((row, index) => {
      const obj = {};
      headers.forEach((h, i) => obj[h] = row[i]);

      return {
        rowNumber: index + 2,
        object: obj
      };
    })
    .filter(x =>
      Object.values(x.object).some(v => v !== '' && v !== null)
    );
}


function findObjectRowById_(sheetName, idHeader, idValue) {
  return getObjectsWithRow_(sheetName)
    .find(x => String(x.object[idHeader] || '') === String(idValue || '')) || null;
}


function updateObjectRowAt_(sheetName, rowNumber, obj) {
  const sh = getSheet_(sheetName);
  const lastCol = sh.getLastColumn();
  const headers = sh.getRange(1, 1, 1, lastCol).getValues()[0];
  const current = sh.getRange(rowNumber, 1, 1, lastCol).getValues()[0];

  headers.forEach((h, i) => {
    if (Object.prototype.hasOwnProperty.call(obj, h)) {
      current[i] = obj[h];
    }
  });

  sh.getRange(rowNumber, 1, 1, lastCol).setValues([current]);
}


function deleteRowsWhere_(sheetName, headerName, value) {
  const sh = getSheet_(sheetName);
  const rows = getObjectsWithRow_(sheetName)
    .filter(x => String(x.object[headerName] || '') === String(value || ''))
    .sort((a, b) => b.rowNumber - a.rowNumber);

  rows.forEach(x => sh.deleteRow(x.rowNumber));
}


/* =========================================================
 *  WEB APP v0.2
 * ========================================================= */

/**
 * Entry point portal.
 */
function doGet(e) {
  try {
    return HtmlService
      .createHtmlOutputFromFile('Index')
      .setTitle('PROVisit')
      .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
  } catch (err) {
    return HtmlService.createHtmlOutput('<h3>Error Loading PROVisit:</h3><pre>' + (err.stack || err.message || err) + '</pre>');
  }
}


/**
 * Data awal untuk New Visit Form.
 * Dipanggil ketika portal dibuka.
 */
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



/**
 * Versi ringan customer search untuk portal.
 * Minimal 2 karakter agar portal tidak perlu memuat 1.000+ customer sekaligus.
 */
function searchCustomersForPortal(query) {
  const q = String(query || '').trim();
  if (q.length < 2) return [];

  return getCustomers(q).slice(0, 25);
}


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


/**
 * Mengambil produk berdasarkan principal untuk dropdown portal.
 */
function getProductsForPortal(principalName) {
  return getProductsByPrincipal(principalName).slice(0, 300);
}


/**
 * Product search untuk portal.
 * Hanya mencari di principal yang dipilih, minimal 2 karakter,
 * dan hanya mengembalikan maksimal 20 hasil.
 */
function searchProductsForPortal(principalName, query) {
  const principal = String(principalName || '').trim();
  const q = String(query || '').trim().toLowerCase();

  if (!principal || q.length < 2) return [];

  const config = getConfigMap_();
  const ss = SpreadsheetApp.openById(config.PRODUCT_SPREADSHEET_ID);
  const sh = ss.getSheetByName(config.PRODUCT_DETAIL_SHEET);

  if (!sh) throw new Error('Sheet Product_Details tidak ditemukan.');

  const lastRow = sh.getLastRow();
  if (lastRow < 2) return [];

  // A Catalogue, B Description, D Principal
  const values = sh.getRange(2, 1, lastRow - 1, 4).getDisplayValues();
  const out = [];

  for (let i = 0; i < values.length; i++) {
    const catalogue = String(values[i][0] || '');
    const description = String(values[i][1] || '');
    const rowPrincipal = String(values[i][3] || '');

    if (rowPrincipal !== principal) continue;

    const haystack = (catalogue + ' ' + description).toLowerCase();
    if (!haystack.includes(q)) continue;

    out.push({
      catalogue: catalogue,
      description: description,
      principalName: rowPrincipal
    });

    if (out.length >= 20) break;
  }

  return out;
}


/**
 * Wrapper save dari UI.
 */
function saveVisitFromPortal(payload) {
  return createVisit(payload);
}


function autosaveFromPortal(payload) {
  return autosaveVisitFromPortal(payload);
}

/**
 * Quick action untuk mengubah status visit langsung dari tabel utama.
 */
function quickUpdateVisitStatus(visitId, newStatus) {
  const actor = getCurrentUser_();
  const validStatuses = ['Draft', 'Planned', 'Confirmed', 'Completed', 'Cancelled'];
  if (!validStatuses.includes(newStatus)) {
    throw new Error('Status tidak valid: ' + newStatus);
  }

  const visitRow = getObjectsWithRow_(PROVISIT.SHEETS.VISIT)
    .find(x => x.object.Visit_ID === visitId);

  if (!visitRow) {
    throw new Error('Visit tidak ditemukan: ' + visitId);
  }

  const now = new Date();
  const updateData = {
    Status: newStatus,
    Updated_At: now,
    Updated_By: actor.User_ID
  };

  if (newStatus === 'Completed') {
    updateData.Report_Completed_At = now;
  }

  updateObjectRowAt_(PROVISIT.SHEETS.VISIT, visitRow.rowNumber, updateData);

  createAudit_(
    actor.User_ID,
    'UPDATE_STATUS',
    'VISIT',
    visitId,
    'Status',
    visitRow.object.Status,
    newStatus,
    Utilities.getUuid()
  );

  // Sync Calendar bila relevan
  if (typeof syncVisitToCalendar === 'function' && (newStatus === 'Planned' || newStatus === 'Confirmed')) {
    try {
      syncVisitToCalendar(visitId);
    } catch (e) {
      Logger.log('Calendar sync err: ' + e);
    }
  }

  return { ok: true, visitId: visitId, status: newStatus };
}


/* =========================================================
 *  VISIT LIST / OPEN WORKSPACE v0.7
 * ========================================================= */

/**
 * Mengambil daftar visit untuk halaman utama.
 * Semua user aktif boleh melihat seluruh visit sesuai business rule saat ini.
 */
function getVisitListForPortal(filters) {
  ensureV06Schema_();
  getCurrentUser_(); // authorization check

  filters = filters || {};
  const search = String(filters.search || '').trim().toLowerCase();
  const status = String(filters.status || '').trim();
  const salesUserId = String(filters.salesUserId || '').trim();

  const users = getActiveUsers_();
  const usersById = new Map(users.map(u => [String(u.User_ID), u]));

  let rows = getObjectsWithRow_(PROVISIT.SHEETS.VISIT)
    .map(x => x.object)
    .filter(v => !toBoolean_(v.Is_Archived));

  if (search) {
    rows = rows.filter(v => {
      const haystack = [
        v.Visit_ID,
        v.Visit_Title,
        v.Customer_Name_Snapshot,
        v.PIC_Customer,
        v.Visit_Purpose
      ].join(' ').toLowerCase();

      return haystack.includes(search);
    });
  }

  if (status) {
    rows = rows.filter(v => String(v.Status || '') === status);
  }

  if (salesUserId) {
    rows = rows.filter(v => String(v.Primary_Sales_User_ID || '') === salesUserId);
  }

  rows.sort((a, b) => {
    const ta = asTimestamp_(a.Updated_At || a.Created_At);
    const tb = asTimestamp_(b.Updated_At || b.Created_At);
    return tb - ta;
  });

  return rows.slice(0, 200).map(v => {
    const sales = usersById.get(String(v.Primary_Sales_User_ID || '')) || {};
    const updater = usersById.get(String(v.Updated_By || '')) || {};

    return {
      visitId: String(v.Visit_ID || ''),
      visitTitle: String(v.Visit_Title || '(Untitled Visit)'),
      visitDate: formatDateForPortal_(v.Visit_Date),
      startTime: formatTimeForPortal_(v.Start_Time),
      customerName: String(v.Customer_Name_Snapshot || ''),
      picCustomer: String(v.PIC_Customer || ''),
      salesUserId: String(v.Primary_Sales_User_ID || ''),
      salesInitial: String(sales.Initial || ''),
      status: String(v.Status || ''),
      visitType: String(v.Visit_Type || ''),
      visitPurpose: String(v.Visit_Purpose || ''),
      updatedAt: formatDateTimeForPortal_(v.Updated_At || v.Created_At),
      updatedByInitial: String(updater.Initial || '')
    };
  });
}


/**
 * Membuka satu visit sebagai workspace yang dapat diedit.
 * Untuk visit v0.6+, ambil snapshot Draft_JSON.
 * Untuk visit lama, bangun ulang payload dari tabel-tabel normal.
 */
function getVisitWorkspacePayload(visitId) {
  ensureV06Schema_();
  getCurrentUser_(); // authorization check

  const visitRecord = findObjectRowById_(
    PROVISIT.SHEETS.VISIT,
    'Visit_ID',
    visitId
  );

  if (!visitRecord || toBoolean_(visitRecord.object.Is_Archived)) {
    throw new Error('Visit tidak ditemukan atau sudah diarsipkan.');
  }

  const visit = visitRecord.object;
  let payload = null;

  const draftRow = findObjectRowById_(
    PROVISIT.SHEETS.DRAFT,
    'Visit_ID',
    visitId
  );

  if (draftRow && draftRow.object.Draft_JSON) {
    try {
      payload = JSON.parse(draftRow.object.Draft_JSON);
    } catch (e) {
      payload = null;
    }
  }

  if (!payload) {
    payload = reconstructVisitPayload_(visitId, visit);
  }

  // Header selalu mengikuti source of truth VISIT_DB.
  payload.visitId = String(visit.Visit_ID || visitId);
  payload.visitTitle = String(visit.Visit_Title || payload.visitTitle || '');
  payload.visitDate = formatDateForInput_(visit.Visit_Date);
  payload.startTime = formatTimeForInput_(visit.Start_Time);
  payload.customerId = String(visit.Customer_ID || payload.customerId || '');
  payload.customerName =
    String(visit.Customer_Name_Snapshot || payload.customerName || '');
  payload.picCustomer =
    String(visit.PIC_Customer || payload.picCustomer || '');
  payload.primarySalesUserId =
    String(visit.Primary_Sales_User_ID || payload.primarySalesUserId || '');
  payload.visitType =
    String(visit.Visit_Type || payload.visitType || 'Offline Visit');
  payload.postvisitResult =
    String(visit.Postvisit_Result || payload.postvisitResult || '');
  payload.visitPurpose =
    String(visit.Visit_Purpose || payload.visitPurpose || '');
  payload.status =
    String(visit.Status || payload.status || 'Draft');

  const users = getActiveUsers_();
  const updater = users.find(
    u => String(u.User_ID) === String(visit.Updated_By || '')
  ) || {};

  payload.workspaceMeta = {
    visitId: payload.visitId,
    updatedAt: formatDateTimeForPortal_(visit.Updated_At || visit.Created_At),
    updatedByInitial: String(updater.Initial || ''),
    createdAt: formatDateTimeForPortal_(visit.Created_At),
    createdBy: String(visit.Created_By || '')
  };

  return payload;
}


function reconstructVisitPayload_(visitId, visit) {
  const supportMarketingUserIds =
    getObjectsWithRow_(PROVISIT.SHEETS.SUPPORT)
      .filter(x =>
        String(x.object.Visit_ID || '') === visitId &&
        String(x.object.Support_Role || '') === 'Marketing Support'
      )
      .map(x => String(x.object.User_ID || ''))
      .filter(Boolean);

  const reviewRows = getObjectsWithRow_(PROVISIT.SHEETS.REVIEW)
    .filter(x => String(x.object.Visit_ID || '') === visitId);

  const principalReviews = reviewRows.map(row => {
    const r = row.object;

    let clientKey = String(r.Client_Key || '').trim();
    if (!clientKey) {
      clientKey = 'REV-' + String(r.Review_ID || Utilities.getUuid());
      updateObjectRowAt_(
        PROVISIT.SHEETS.REVIEW,
        row.rowNumber,
        {Client_Key: clientKey}
      );
    }

    const products = getObjectsWithRow_(PROVISIT.SHEETS.REVIEW_PRODUCT)
      .filter(x => String(x.object.Review_ID || '') === String(r.Review_ID || ''))
      .map(x => ({
        catalogue: String(x.object.Product_Catalogue || ''),
        description: String(x.object.Product_Description_Snapshot || '')
      }));

    // Compatibility untuk data v0.3 yang masih menyimpan satu product langsung di review.
    if (
      products.length === 0 &&
      (r.Product_Catalogue || r.Product_Description_Snapshot)
    ) {
      products.push({
        catalogue: String(r.Product_Catalogue || ''),
        description: String(r.Product_Description_Snapshot || '')
      });
    }

    return {
      clientKey: clientKey,
      principalName: String(r.Principal_Name || ''),
      products: products,
      exposureType: String(r.Exposure_Type || ''),
      notes: String(r.Notes || ''),
      customerFeedback: String(r.Customer_Feedback || ''),
      opportunityStatus: String(r.Opportunity_Status || ''),
      nextStep: String(r.Next_Step || '')
    };
  });

  const actionRows = getObjectsWithRow_(PROVISIT.SHEETS.ACTION)
    .filter(x => String(x.object.Visit_ID || '') === visitId);

  const actions = actionRows.map(row => {
    const a = row.object;

    let clientKey = String(a.Client_Key || '').trim();
    if (!clientKey) {
      clientKey = 'ACT-' + String(a.Action_ID || Utilities.getUuid());
      updateObjectRowAt_(
        PROVISIT.SHEETS.ACTION,
        row.rowNumber,
        {Client_Key: clientKey}
      );
    }

    return {
      clientKey: clientKey,
      actionText: String(a.Action_Text || ''),
      ownerUserId: String(a.Owner_User_ID || ''),
      dueDate: formatDateForInput_(a.Due_Date),
      priority: String(a.Priority || 'Normal'),
      status: String(a.Status || 'Open'),
      completionNote: String(a.Completion_Note || '')
    };
  });

  return {
    visitId: visitId,
    visitTitle: String(visit.Visit_Title || ''),
    visitDate: formatDateForInput_(visit.Visit_Date),
    startTime: formatTimeForInput_(visit.Start_Time),
    customerId: String(visit.Customer_ID || ''),
    customerName: String(visit.Customer_Name_Snapshot || ''),
    picCustomer: String(visit.PIC_Customer || ''),
    primarySalesUserId: String(visit.Primary_Sales_User_ID || ''),
    visitType: String(visit.Visit_Type || 'Offline Visit'),
    visitPurpose: String(visit.Visit_Purpose || ''),
    agenda: '',
    previsitNotes: String(visit.Previsit_Notes || ''),
    status: String(visit.Status || 'Draft'),
    supportMarketingUserIds: supportMarketingUserIds,
    principalReviews: principalReviews,
    actions: actions
  };
}


function toBoolean_(value) {
  if (value === true) return true;
  return String(value || '').toUpperCase() === 'TRUE';
}


function asTimestamp_(value) {
  if (value instanceof Date) return value.getTime();

  const parsed = new Date(value);
  return isNaN(parsed.getTime()) ? 0 : parsed.getTime();
}


function formatDateForInput_(value) {
  if (!value) return '';

  const d = value instanceof Date ? value : new Date(value);
  if (isNaN(d.getTime())) return '';

  return Utilities.formatDate(d, 'Asia/Jakarta', 'yyyy-MM-dd');
}


function formatTimeForInput_(value) {
  if (!value) return '';

  if (value instanceof Date) {
    return Utilities.formatDate(value, 'Asia/Jakarta', 'HH:mm');
  }

  const s = String(value);
  const m = s.match(/(\d{1,2}):(\d{2})/);
  if (!m) return '';

  return String(m[1]).padStart(2, '0') + ':' + m[2];
}


function formatDateForPortal_(value) {
  if (!value) return '';

  const d = value instanceof Date ? value : new Date(value);
  if (isNaN(d.getTime())) return String(value || '');

  return Utilities.formatDate(d, 'Asia/Jakarta', 'dd MMM yyyy');
}


function formatTimeForPortal_(value) {
  return formatTimeForInput_(value);
}


function formatDateTimeForPortal_(value) {
  if (!value) return '';

  const d = value instanceof Date ? value : new Date(value);
  if (isNaN(d.getTime())) return String(value || '');

  return Utilities.formatDate(d, 'Asia/Jakarta', 'dd MMM yyyy HH:mm');
}
