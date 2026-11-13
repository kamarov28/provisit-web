# PROvisit Web

Sistem manajemen dan pelaporan agenda kunjungan sales terpadu untuk PT Prolabios Mitra Analitika. Dirancang dengan pendekatan UI modern ala Notion (Calendar View, Kanban Board, dan Side Drawer) dengan performa tinggi berbasis Laravel 13, Svelte 5 via Inertia.js, dan database relasional MariaDB/MySQL.

---

## Fitur Utama

- **Multi-View Agenda:**
  - **Calendar View:** Visualisasi jadwal kunjungan interaktif per bulan dan minggu.
  - **Kanban Board:** Tracking pipeline status kunjungan (`Planned`, `Confirmed`, `In Progress`, `Completed`, `Cancelled`).
  - **Table View:** Filter cepat berdasarkan Sales, Principal, Customer, dan rentang tanggal.
- **Side Drawer Detail:** Inspeksi detail kunjungan, agenda, PIC, dan riwayat revisi tanpa berpindah halaman.
- **Form Kunjungan Terstruktur:**
  - Data kunjungan, customer, dan PIC tersinkronisasi.
  - Principal Review & pemetaan produk katalog (Biowest, MICA, SPL Life Sciences).
  - Action Plan & penugasan PIC follow-up.
- **Audit Trail & Tertelusur:** Setiap perubahan jadwal dan status tercatat otomatis pada tabel audit log.

---

## Tech Stack

- **Backend:** Laravel 13.x (PHP 8.3+)
- **Frontend:** Svelte 5 via Inertia.js
- **Styling:** Tailwind CSS v4 & Lucide Icons
- **Database:** MariaDB / MySQL 8.0+
- **Tooling:** Vite, Composer, Laragon / Apache LiteSpeed

---

## Struktur Database

| Tabel | Deskripsi |
|---|---|
| `users` | Master pengguna (Sales tim YES/MOU, Manager, Admin) |
| `customers` | Master data institusi/pelanggan |
| `customer_pics` | Daftar kontak PIC per pelanggan |
| `principals` | Master data principal manufaktur |
| `products` | Katalog produk per principal |
| `visits` | Data transaksi dan agenda kunjungan |
| `principal_reviews` | Review eksposur principal per kunjungan |
| `principal_review_products`| Item katalog produk yang dibahas saat review |
| `action_plans` | Tindak lanjut (follow-up) dan deadline |
| `audit_logs` | Jejak rekam perubahan data |

---

## Panduan Instalasi Lokal (Laragon)

### 1. Clone Repositori
```bash
git clone https://github.com/kamarov28/provisit-web.git
cd provisit-web
```

### 2. Dependensi PHP & Node.js
```bash
composer install
npm install
```

### 3. Konfigurasi Environment
Salin berkas `.env.example` ke `.env`:
```bash
cp .env.example .env
php artisan key:generate
```

Sesuaikan koneksi database di `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=provisit_db
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Migrasi & Seeder Database
```bash
php artisan migrate --seed
```

Data bawaan seeder:
- **Admin:** `admin@prolabios.com` (password: `password`)
- **Sales YES:** `sales.yes@prolabios.com` (password: `password`)
- **Sales MOU:** `sales.mou@prolabios.com` (password: `password`)

### 5. Kompilasi Aset Frontend
```bash
# Mode development (live reload)
npm run dev

# Mode production build
npm run build
```

---

## Lisensi

Internal PT Prolabios Mitra Analitika. Seluruh hak cipta dilindungi.
