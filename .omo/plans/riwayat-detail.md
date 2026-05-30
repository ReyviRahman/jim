# Tombol Detail Membership per User

## TL;DR

> **Quick Summary**: Menambahkan tombol Detail di halaman riwayat membership index yang mengarah ke halaman profil user read-only, menampilkan info dasar user dan tabel semua membership yang dimiliki atau diikuti oleh user tersebut.
>
> **Deliverables**:
> - Route baru: `admin.riwayat.detail` (`/dashboard/admin/riwayat/{user}`)
> - File baru: `resources/views/pages/dashboard/admin/riwayat/⚡detail.blade.php`
> - Modifikasi: Tombol Detail di `⚡index.blade.php`
>
> **Estimated Effort**: Short (1-2 jam)
> **Parallel Execution**: NO - sequential (Task 1 → Task 2 → Task 3)
> **Critical Path**: Task 1 (Route) → Task 2 (Detail Page) → Task 3 (Index Button) → F1-F4

---

## Context

### Original Request
User ingin menambahkan tombol Detail di halaman riwayat membership admin yang mengarah ke halaman detail per user, berisi tabel data membership yang dimiliki oleh user tersebut.

### Interview Summary
**Key Discussions**:
- **Tombol Detail**: Semua role yang bisa akses riwayat (admin, kasir_gym, head_coach) bisa melihat
- **Halaman Detail**: Menampilkan info profil user (nama, email, no telepon, foto) dan tabel membership
- **Kolom Tabel**: Sama dengan halaman index (kecuali kolom Aksi - read-only)
- **Scope Membership**: BOTH - membership di mana user adalah payer (user_id) OR member (pivot)
- **Tombol Kembali**: Ya, "Kembali ke Riwayat"

**Research Findings**:
- Route riwayat ada di `routes/web.php:88-89` menggunakan `Route::livewire`
- Model Membership punya relasi `user()` (BelongsTo) dan `members()` (BelongsToMany pivot)
- Halaman index menggunakan Livewire SFC dengan `#[Layout('layouts::admin')]`
- Pattern detail page existing: `akun/member/⚡detail.blade.php` (model binding `mount(User $user)`)

### Metis Review
**Identified Gaps** (addressed):
- Parameter binding: Gunakan model binding `mount(User $user)` (bukan raw ID)
- Scope membership: BOTH payer + pivot member
- Price labels: Hanya untuk admin role (mengikuti pattern index)
- Query shape: `where('user_id', $user->id)->orWhereHas('members', ...)`

---

## Work Objectives

### Core Objective
Menambahkan halaman profil user read-only yang diakses via tombol Detail dari halaman riwayat membership index, menampilkan info dasar user dan tabel semua membership yang dimiliki atau diikuti oleh user tersebut.

### Concrete Deliverables
- Route baru: `admin.riwayat.detail` dengan URI `/dashboard/admin/riwayat/{user}`
- File baru: `resources/views/pages/dashboard/admin/riwayat/⚡detail.blade.php`
- Modifikasi file: `resources/views/pages/dashboard/admin/riwayat/⚡index.blade.php` (tambah tombol Detail)

### Definition of Done
- [ ] Route `admin.riwayat.detail` terdaftar dan bisa diakses
- [ ] Tombol Detail muncul di setiap baris tabel index
- [ ] Halaman detail menampilkan info profil user (nama, email, phone, photo)
- [ ] Halaman detail menampilkan tabel membership milik user
- [ ] Tombol "Kembali ke Riwayat" berfungsi

### Must Have
- Route menggunakan model binding `mount(User $user)`
- Tombol Detail terlihat oleh semua role dalam middleware group
- Info profil: nama, email, phone, photo (dengan fallback avatar)
- Tabel membership dengan kolom: No, Member, Program/Paket, Total Bayar, Masa Aktif, Admin Follow Up, Sales Follow Up
- Query mencakup BOTH payer dan pivot member
- Tombol Kembali ke Riwayat

### Must NOT Have (Guardrails)
- Edit/Delete di halaman detail (read-only)
- Filter/search/sort di halaman detail
- Pagination di halaman detail
- Export Excel di halaman detail
- Modal/popup (harus halaman terpisah)
- Kolom Aksi di tabel detail
- Price labels untuk non-admin role

---

## Verification Strategy

> **ZERO HUMAN INTERVENTION** - ALL verification is agent-executed. No exceptions.

### Test Decision
- **Infrastructure exists**: NO (tests/ directory near-empty)
- **Automated tests**: NO - view-only feature, no business logic changes
- **Agent QA**: YES - setiap task memiliki QA scenarios
- **Evidence path**: `.omo/evidence/task-{N}-{scenario-slug}.{ext}`

### QA Policy
Every task MUST include agent-executed QA scenarios.

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Sequential - each task depends on previous):
├── Task 1: Route baru admin.riwayat.detail [quick]
├── Task 2: Halaman detail Livewire SFC [quick]
├── Task 3: Tombol Detail di halaman index [quick]

Wave FINAL (After ALL tasks - 4 parallel reviews):
├── Task F1: Plan compliance audit (oracle)
├── Task F2: Code quality review (unspecified-high)
├── Task F3: Real manual QA (unspecified-high)
├── Task F4: Scope fidelity check (deep)
-> Present results -> Get explicit user okay

Critical Path: Task 1 → Task 2 → Task 3 → F1-F4 → user okay
```

### Dependency Matrix

- **1**: None → 2, 3
- **2**: 1 → 3
- **3**: 1, 2 → F1-F4

### Agent Dispatch Summary

- **1**: **3** - T1 → `quick`, T2 → `quick`, T3 → `quick`
- **FINAL**: **4** - F1 → `oracle`, F2 → `unspecified-high`, F3 → `unspecified-high`, F4 → `deep`

---

## TODOs

- [x] 1. Route baru `admin.riwayat.detail`

  **What to do**:
  - Tambahkan route baru di `routes/web.php` di dalam middleware group `role:admin,kasir_gym,head_coach`
  - Route: `Route::livewire('/riwayat/{user}', 'pages::dashboard.admin.riwayat.detail')`
  - Name: `admin.riwayat.detail`
  - Gunakan model binding `User $user`

  **Must NOT do**:
  - Jangan buat route di luar middleware group yang ada
  - Jangan gunakan parameter raw ID, harus model binding

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Hanya menambahkan satu baris route
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Wave 1 (Task 1)
  - **Blocks**: Task 2, Task 3
  - **Blocked By**: None

  **References**:
  - `routes/web.php:88-89` - Pattern route riwayat index yang sudah ada
  - `routes/web.php:129` - Pattern detail page dengan model binding (rekap-bonus)
  - `routes/web.php:188` - Pattern detail page dengan model binding (sesi-pt)

  **Acceptance Criteria**:
  - [ ] Route terdaftar: `php artisan route:list --name=admin.riwayat.detail` menampilkan route
  - [ ] Route menggunakan model binding User

  **QA Scenarios**:

  ```
  Scenario: Route terdaftar dengan benar
    Tool: Bash
    Preconditions: Aplikasi running
    Steps:
      1. Run: php artisan route:list --name=admin.riwayat.detail
    Expected Result: Output menampilkan route dengan URI /dashboard/admin/riwayat/{user} dan name admin.riwayat.detail
    Failure Indicators: Route tidak muncul atau URI salah
    Evidence: .omo/evidence/task-1-route-verification.txt
  ```

  **Commit**: YES
  - Message: `feat(riwayat): add route for membership detail per user`
  - Files: `routes/web.php`

- [x] 2. Halaman detail Livewire SFC

  **What to do**:
  - Buat file baru: `resources/views/pages/dashboard/admin/riwayat/⚡detail.blade.php`
  - Gunakan Livewire SFC dengan `#[Layout('layouts::admin')]`
  - Terima parameter `User $user` via `mount()`
  - Tampilkan info profil user: nama, email, phone, photo (dengan fallback avatar)
  - Tampilkan tabel membership dengan query BOTH (payer + pivot member)
  - Kolom tabel: No, Member, Program/Paket, Total Bayar, Masa Aktif, Admin Follow Up, Sales Follow Up
  - Tanpa kolom Aksi (read-only)
  - Price labels hanya untuk admin role
  - Tambahkan tombol "Kembali ke Riwayat"

  **Must NOT do**:
  - Jangan tambahkan filter/search/sort/pagination
  - Jangan tambahkan kolom Aksi (Edit/Hapus)
  - Jangan tampilkan price labels untuk non-admin

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: View-only page, copy-paste dari index dengan modifikasi
  - **Skills**: [`livewire-development`]
    - `livewire-development`: Membuat Livewire SFC component

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Wave 1 (Task 2)
  - **Blocks**: Task 3
  - **Blocked By**: Task 1

  **References**:
  - `resources/views/pages/dashboard/admin/riwayat/⚡index.blade.php` - Copy markup tabel dari sini
  - `resources/views/pages/dashboard/admin/akun/member/⚡detail.blade.php` - Pattern tampilan profil user
  - `app/Models/Membership.php` - Relasi user() dan members()

  **Acceptance Criteria**:
  - [ ] File `⚡detail.blade.php` terbuat
  - [ ] Halaman bisa diakses tanpa error
  - [ ] Info profil user tampil (nama, email, phone, photo)
  - [ ] Tabel membership tampil dengan kolom yang benar
  - [ ] Tombol Kembali ke Riwayat ada dan berfungsi

  **QA Scenarios**:

  ```
  Scenario: Halaman detail tampil dengan benar
    Tool: Playwright
    Preconditions: User admin login, ada membership di database
    Steps:
      1. Navigate to /dashboard/admin/riwayat
      2. Click tombol Detail di baris pertama
      3. Assert URL mengandung /riwayat/
      4. Assert page contains nama user
      5. Assert page contains email user
      6. Assert page contains tabel membership
      7. Assert page contains tombol "Kembali ke Riwayat"
    Expected Result: Halaman detail tampil lengkap dengan profil dan tabel
    Failure Indicators: 404 error, data tidak tampil, atau elemen hilang
    Evidence: .omo/evidence/task-2-detail-page.png

  Scenario: User tanpa membership
    Tool: Playwright
    Preconditions: User admin login, ada user tanpa membership
    Steps:
      1. Navigate ke detail page user tanpa membership
      2. Assert page shows empty state message
    Expected Result: Tabel menampilkan pesan "Belum ada riwayat membership untuk user ini."
    Failure Indicators: Error atau tabel kosong tanpa pesan
    Evidence: .omo/evidence/task-2-empty-state.png
  ```

  **Commit**: YES
  - Message: `feat(riwayat): add detail page for user memberships`
  - Files: `resources/views/pages/dashboard/admin/riwayat/⚡detail.blade.php`

- [x] 3. Tombol Detail di halaman index

  **What to do**:
  - Modifikasi `resources/views/pages/dashboard/admin/riwayat/⚡index.blade.php`
  - Tambahkan tombol Detail di kolom Aksi (sebelum tombol Edit)
  - Tombol menggunakan `route('admin.riwayat.detail', $membership->user_id)`
  - Gunakan `wire:navigate` untuk navigasi Livewire
  - Tombol terlihat oleh semua role (bukan hanya admin)

  **Must NOT do**:
  - Jangan sembunyikan tombol Detail untuk non-admin
  - Jangan ubah kolom lain

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Hanya menambahkan satu tombol link
  - **Skills**: [`livewire-development`]
    - `livewire-development`: Menggunakan wire:navigate

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Wave 1 (Task 3)
  - **Blocks**: F1-F4
  - **Blocked By**: Task 1, Task 2

  **References**:
  - `resources/views/pages/dashboard/admin/riwayat/⚡index.blade.php:416-429` - Kolom Aksi existing

  **Acceptance Criteria**:
  - [ ] Tombol Detail muncul di setiap baris tabel
  - [ ] Tombol mengarah ke route admin.riwayat.detail
  - [ ] Tombol terlihat oleh semua role

  **QA Scenarios**:

  ```
  Scenario: Tombol Detail tampil dan berfungsi
    Tool: Playwright
    Preconditions: User admin login, ada data membership
    Steps:
      1. Navigate to /dashboard/admin/riwayat
      2. Assert tombol Detail ada di baris pertama tabel
      3. Click tombol Detail
      4. Assert URL berubah ke /dashboard/admin/riwayat/{user_id}
    Expected Result: Navigasi ke halaman detail berhasil
    Failure Indicators: Tombol tidak ada atau navigasi gagal
    Evidence: .omo/evidence/task-3-detail-button.png
  ```

  **Commit**: YES
  - Message: `feat(riwayat): add detail button to membership index`
  - Files: `resources/views/pages/dashboard/admin/riwayat/⚡index.blade.php`

---

## Final Verification Wave

- [x] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify implementation exists. For each "Must NOT Have": search codebase for forbidden patterns. Check evidence files exist in .omo/evidence/.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [x] F2. **Code Quality Review** — `unspecified-high`
  Review semua file yang diubah: cek syntax error, konsistensi style, penggunaan wire:navigate, route name consistency.
  Output: `Syntax [PASS/FAIL] | Style [PASS/FAIL] | Consistency [PASS/FAIL] | VERDICT`

- [x] F3. **Real Manual QA** — `unspecified-high`
  Test end-to-end: login sebagai admin, akses riwayat, click Detail, verifikasi halaman detail, test tombol Kembali, test empty state.
  Output: `Scenarios [N/N pass] | Integration [N/N] | Edge Cases [N tested] | VERDICT`

- [x] F4. **Scope Fidelity Check** — `deep`
  For each task: read "What to do", read actual diff. Verify 1:1 - everything in spec was built, nothing beyond spec was built. Check "Must NOT do" compliance.
  Output: `Tasks [N/N compliant] | Contamination [CLEAN/N issues] | Unaccounted [CLEAN/N files] | VERDICT`

---

## Commit Strategy

- **1**: `feat(riwayat): add route for membership detail per user` - routes/web.php
- **2**: `feat(riwayat): add detail page for user memberships` - resources/views/pages/dashboard/admin/riwayat/⚡detail.blade.php
- **3**: `feat(riwayat): add detail button to membership index` - resources/views/pages/dashboard/admin/riwayat/⚡index.blade.php

---

## Success Criteria

### Verification Commands
```bash
# Route verification
php artisan route:list --name=admin.riwayat.detail

# Syntax check
php artisan view:cache
```

### Final Checklist
- [ ] Route `admin.riwayat.detail` terdaftar dan bisa diakses
- [ ] Tombol Detail muncul di halaman index
- [ ] Halaman detail menampilkan profil user
- [ ] Halaman detail menampilkan tabel membership
- [ ] Tombol Kembali berfungsi
- [ ] Tidak ada kolom Aksi di tabel detail
- [ ] Tidak ada filter/search/sort di halaman detail
- [ ] Price labels hanya untuk admin role