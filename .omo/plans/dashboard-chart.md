# Dashboard Admin Bar Chart

## TL;DR

> **Quick Summary**: Membuat halaman dashboard admin baru dengan Bar Chart menampilkan 3 kategori membership (Aktif, Belum Aktif, Cicilan) berdasarkan payment_date terakhir dari membership_transactions, dengan filter Flatpickr.
>
> **Deliverables**:
> - Route baru: `admin.dashboard` (`/dashboard/admin`)
> - File baru: `resources/views/pages/dashboard/admin/⚡index.blade.php`
> - Modifikasi: `routes/web.php`, `resources/views/layouts/admin.blade.php`
>
> **Estimated Effort**: Short (1-2 jam)
> **Parallel Execution**: NO - sequential
> **Critical Path**: Task 1 (Route) → Task 2 (Dashboard Page) → Task 3 (Layout Link) → F1-F4

---

## Context

### Original Request
User ingin membuat halaman dashboard admin dengan Bar Chart yang menampilkan:
1. Total memberships aktif (payment_status='paid' AND is_active=true)
2. Total memberships belum aktif (payment_status='paid' AND is_active=false)
3. Total memberships cicilan (payment_status='partial' AND is_active=false)

Data difilter berdasarkan payment_date terakhir dari tabel membership_transactions.
Filter menggunakan Flatpickr dengan default tanggal 15-15.
Visualisasi menggunakan Chart.js.

### Interview Summary
**Key Decisions**:
- Route: `/dashboard/admin` sebagai root admin dashboard
- File: `⚡index.blade.php` di `resources/views/pages/dashboard/admin/`
- Query: Subquery untuk ambil payment_date terakhir per membership
- Filter: Flatpickr range date
- Chart: Chart.js (via CDN)

**Research Findings**:
- Route admin ada di `routes/web.php:73`
- Layout admin ada di `resources/views/layouts/admin.blade.php`
- Model Membership punya `payment_status` dan `is_active`
- Model MembershipTransaction punya `payment_date` dan relasi ke Membership
- Flatpickr sudah digunakan di halaman riwayat

---

## Work Objectives

### Core Objective
Membuat halaman dashboard admin dengan Bar Chart yang menampilkan 3 kategori membership berdasarkan payment_date terakhir, dengan filter tanggal menggunakan Flatpickr.

### Concrete Deliverables
- Route baru: `admin.dashboard` dengan URI `/dashboard/admin`
- File baru: `resources/views/pages/dashboard/admin/⚡index.blade.php`
- Modifikasi: `routes/web.php` (tambah route)
- Modifikasi: `resources/views/layouts/admin.blade.php` (tambah link Dashboard)

### Definition of Done
- [x] Route `admin.dashboard` terdaftar dan bisa diakses
- [x] Bar Chart menampilkan 3 kategori (Aktif, Belum Aktif, Cicilan)
- [x] Filter Flatpickr berfungsi dengan default 15-15
- [x] Query menggunakan payment_date terakhir dari membership_transactions
- [x] Link Dashboard muncul di sidebar

### Must Have
- Route `/dashboard/admin` sebagai root admin
- Bar Chart dengan 3 kategori
- Filter tanggal dengan Flatpickr
- Default tanggal 15-15 (bulan ini)
- Query berdasarkan payment_date terakhir

### Must NOT Have (Guardrails)
- No Edit/Delete di halaman dashboard
- No pagination
- No export

---

## Verification Strategy

> **ZERO HUMAN INTERVENTION** - ALL verification is agent-executed.

### Test Decision
- **Infrastructure exists**: NO
- **Automated tests**: NO
- **Agent QA**: YES

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Sequential):
├── Task 1: Route baru admin.dashboard [quick]
├── Task 2: Halaman dashboard Livewire SFC [quick]
├── Task 3: Link Dashboard di sidebar [quick]

Wave FINAL (4 parallel reviews):
├── Task F1: Plan compliance audit (oracle)
├── Task F2: Code quality review (unspecified-high)
├── Task F3: Real manual QA (unspecified-high)
├── Task F4: Scope fidelity check (deep)
```

---

## TODOs

- [x] 1. Route baru `admin.dashboard`

  **What to do**:
  - Tambahkan route baru di `routes/web.php` di dalam middleware group `role:admin,kasir_gym,head_coach`
  - Route: `Route::livewire('/', 'pages::dashboard.admin.index')`
  - Name: `admin.dashboard`
  - Letakkan SEBELUM route `/absensi`

  **References**:
  - `routes/web.php:73-75` - Pattern route admin

  **Acceptance Criteria**:
  - [ ] Route terdaftar: `php artisan route:list --name=admin.dashboard`

  **Commit**: YES
  - Message: `feat(dashboard): add route for admin dashboard`
  - Files: `routes/web.php`

- [x] 2. Halaman dashboard Livewire SFC

  **What to do**:
  - Buat file: `resources/views/pages/dashboard/admin/⚡index.blade.php`
  - Gunakan Livewire SFC dengan `#[Layout('layouts::admin')]`
  - Tambahkan Flatpickr untuk filter tanggal (default 15-15)
  - Query data membership dengan 3 kategori:
    - Aktif: payment_status='paid' AND is_active=true
    - Belum Aktif: payment_status='paid' AND is_active=false
    - Cicilan: payment_status='partial' AND is_active=false
  - Ambil payment_date terakhir dari membership_transactions per membership
  - Tampilkan Bar Chart menggunakan Chart.js
  - Chart menampilkan count per kategori

  **Query Logic**:
  ```php
  $query = Membership::query();
  
  // Join dengan subquery untuk ambil payment_date terakhir
  $query->joinSub(
      MembershipTransaction::select('membership_id', DB::raw('MAX(payment_date) as last_payment_date'))
          ->groupBy('membership_id'),
      'last_transactions',
      'memberships.id',
      '=',
      'last_transactions.membership_id'
  );
  
  // Filter berdasarkan range tanggal
  if ($this->dateStart && $this->dateEnd) {
      $query->whereBetween('last_transactions.last_payment_date', [$this->dateStart, $this->dateEnd]);
  }
  
  // Hitung per kategori
  $aktif = (clone $query)->where('payment_status', 'paid')->where('is_active', true)->count();
  $belumAktif = (clone $query)->where('payment_status', 'paid')->where('is_active', false)->count();
  $cicilan = (clone $query)->where('payment_status', 'partial')->where('is_active', false)->count();
  ```

  **Must NOT do**:
  - Jangan tambahkan fitur CRUD
  - Jangan tambahkan pagination

  **References**:
  - `resources/views/pages/dashboard/admin/riwayat/⚡index.blade.php` - Pattern Flatpickr
  - `app/Models/Membership.php` - Model structure
  - `app/Models/MembershipTransaction.php` - Transaction model

  **Acceptance Criteria**:
  - [ ] File `⚡index.blade.php` terbuat
  - [ ] Flatpickr tampil dengan default 15-15
  - [ ] Chart.js tampil dengan 3 bar
  - [ ] Data sesuai dengan filter tanggal

  **Commit**: YES
  - Message: `feat(dashboard): add admin dashboard with bar chart`
  - Files: `resources/views/pages/dashboard/admin/⚡index.blade.php`

- [x] 3. Link Dashboard di sidebar

  **What to do**:
  - Modifikasi `resources/views/layouts/admin.blade.php`
  - Tambahkan link Dashboard di atas menu Absensi
  - Link ke `route('admin.dashboard')`
  - Icon: svg dashboard

  **References**:
  - `resources/views/layouts/admin.blade.php:33-43` - Pattern menu item

  **Acceptance Criteria**:
  - [ ] Link Dashboard muncul di sidebar
  - [ ] Link mengarah ke route admin.dashboard

  **Commit**: YES
  - Message: `feat(dashboard): add dashboard link to sidebar`
  - Files: `resources/views/layouts/admin.blade.php`

---

## Final Verification Wave

- [x] F1. **Plan Compliance Audit** — `oracle`
- [x] F2. **Code Quality Review** — `unspecified-high`
- [x] F3. **Real Manual QA** — `unspecified-high`
- [x] F4. **Scope Fidelity Check** — `deep`

---

## Commit Strategy

- **1**: `feat(dashboard): add route for admin dashboard` - routes/web.php
- **2**: `feat(dashboard): add admin dashboard with bar chart` - resources/views/pages/dashboard/admin/⚡index.blade.php
- **3**: `feat(dashboard): add dashboard link to sidebar` - resources/views/layouts/admin.blade.php

---

## Success Criteria

### Verification Commands
```bash
php artisan route:list --name=admin.dashboard
php artisan view:cache
```

### Final Checklist
- [x] Route `admin.dashboard` terdaftar
- [x] Halaman dashboard menampilkan Bar Chart
- [x] 3 kategori muncul (Aktif, Belum Aktif, Cicilan)
- [x] Filter Flatpickr berfungsi
- [x] Link Dashboard muncul di sidebar
