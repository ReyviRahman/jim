# Dashboard Chart Learnings

## [2026-05-31] Task: Route + Dashboard + Sidebar

### What Worked
- Route admin.dashboard berhasil ditambahkan
- File dashboard berhasil dibuat dengan Livewire SFC
- Query joinSub dengan MAX(payment_date) berhasil
- Flatpickr default 15-15 berfungsi
- Chart.js menampilkan 3 bar
- Link Dashboard di sidebar berhasil

### Key Decisions
- Route /dashboard/admin sebagai root admin
- Chart.js via CDN
- Default tanggal 15-15
- Query menggunakan joinSub

### Files Modified
- routes/web.php
- resources/views/pages/dashboard/admin/?index.blade.php
- resources/views/layouts/admin.blade.php
- package.json & package-lock.json
- resources/js/app.js

### Commit
- d474644 - feat(dashboard): add admin dashboard with bar chart

