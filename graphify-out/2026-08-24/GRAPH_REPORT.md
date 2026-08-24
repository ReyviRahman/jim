# Graph Report - jim  (2026-08-24)

## Corpus Check
- 358 files · ~512,813 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 2020 nodes · 2756 edges · 292 communities (248 shown, 44 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 107 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `d373d882`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- DeviceEventWebhookTest
- WhatsAppIntegration
- AdminMembershipAdminFeeTest
- AGENTS.md
- HikvisionUserService
- Membership
- CLAUDE.md
- Maatwebsite\Excel\Concerns\Exportable
- Illuminate\Database\Eloquent\Relations\BelongsTo
- User
- devDependencies
- Livewire Development
- Livewire Development
- Tombol Detail Membership per User
- What You Must Do When Invoked
- Quick Reference
- PtPaymentBatch
- admin/booking-jadwal/⚡index.blade.php
- Quick Reference
- Illuminate\Database\Seeder
- BonusPaymentTest
- Illuminate\Database\Eloquent\Factories\HasFactory
- TestCase
- Dashboard Admin Bar Chart
- Beverage
- app.js
- rekap-bonus/⚡detail.blade.php
- DeviceEventMonitoringTest
- RekapBonusDetailTableTest
- Maatwebsite\Excel\Concerns\WithEvents
- Illuminate\Database\Eloquent\Model
- PtBooking
- rentang-bonus/⚡index.blade.php
- BeverageSale.php
- scripts
- Tailwind CSS Development
- ⚡membership-detail.blade.php
- Tailwind CSS Development
- sesi-pt/⚡detail.blade.php
- composer.json
- Panduan Koneksi Hikvision melalui Tunnel
- Membership.php
- BeverageSaleExportDetail
- Architecture Best Practices
- Security Best Practices
- Architecture Best Practices
- Security Best Practices
- Queue & Job Best Practices
- Queue & Job Best Practices
- Advanced Query Patterns
- Database Performance Best Practices
- Events & Notifications Best Practices
- beverages/⚡index.blade.php
- Advanced Query Patterns
- Database Performance Best Practices
- Events & Notifications Best Practices
- require-dev
- Illuminate\Database\Eloquent\Factories\Factory
- command
- Caching Best Practices
- Eloquent Best Practices
- Migration Best Practices
- ⚡restock.blade.php
- Caching Best Practices
- Eloquent Best Practices
- Migration Best Practices
- graphify reference: extra exports and benchmark
- README.md
- Blade & Views Best Practices
- Error Handling Best Practices
- Task Scheduling Best Practices
- Testing Best Practices
- BeverageSale
- Blade & Views Best Practices
- Error Handling Best Practices
- Task Scheduling Best Practices
- Testing Best Practices
- member/⚡index.blade.php
- penjualan/⚡index.blade.php
- require
- setup
- admin/membership/⚡index.blade.php
- AdminBookingAttendanceTest
- PtMembershipRemainingSessionsTest
- ⚡gabung.blade.php
- Collection Best Practices
- HTTP Client Best Practices
- Mail Best Practices
- Routing & Controllers Best Practices
- Conventions & Style
- Validation & Forms Best Practices
- PtSchedule
- admin/jadwal-pt/⚡index.blade.php
- Collection Best Practices
- HTTP Client Best Practices
- Mail Best Practices
- Routing & Controllers Best Practices
- Conventions & Style
- Validation & Forms Best Practices
- sales.blade.php
- config
- [2026-05-31] Task: Route + Dashboard + Sidebar
- member/jadwal-pt/⚡index.blade.php
- AdminAttendanceTableTest
- Configuration Best Practices
- AppServiceProvider.php
- Configuration Best Practices
- dashboard/⚡navbar.blade.php
- graphify reference: query, path, explain
- pt-berjalan/⚡index.blade.php
- BeverageInvoiceExport
- CoachKonsultan
- psr-4
- invoice.blade.php
- pt-booking/⚡index.blade.php
- SalesKonsultan
- membership/⚡edit.blade.php
- graphify reference: add a URL and watch a folder
- graphify reference: commit hook and native CLAUDE.md integration
- graphify reference: incremental update and cluster-only
- ⚡hutang.blade.php
- custom-pagination.blade.php
- ExampleTest
- pt/booking-jadwal/⚡index.blade.php
- graphify reference: GitHub clone and cross-repo merge
- graphify reference: transcribe video and audio
- extra
- post-autoload-dump
- pengeluaran/⚡index.blade.php
- admin/package/⚡index.blade.php
- riwayat/⚡index.blade.php
- laravel-boost
- ⚡pos.blade.php
- akun/admin/⚡index.blade.php
- ⚡invoice-create.blade.php
- ⚡invoice-edit.blade.php
- renew/⚡index.blade.php
- ⚡checkout.blade.php
- checkConnection
- extraction-spec.md
- delete({{ $membership->id }})
- navbar
- registration.member
- components/⚡navbar.blade.php
- admin.blade.php
- member.blade.php
- pt.blade.php
- sales/⚡index.blade.php
- trainer/⚡index.blade.php
- ⚡paket.blade.php
- ⚡home.blade.php
- member/package/⚡index.blade.php
- device-events/⚡index.blade.php
- pages/⚡index.blade.php
- ⚡login.blade.php

## God Nodes (most connected - your core abstractions)
1. `User` - 112 edges
2. `Membership` - 74 edges
3. `TestCase` - 38 edges
4. `DeviceEventWebhookTest` - 32 edges
5. `PtBooking` - 28 edges
6. `BonusPaymentTest` - 25 edges
7. `WhatsAppIntegration` - 21 edges
8. `DeviceEventMonitoringTest` - 20 edges
9. `Quick Reference` - 20 edges
10. `Quick Reference` - 20 edges

## Surprising Connections (you probably didn't know these)
- `AdminAttendanceTableTest` --inherits--> `TestCase`  [EXTRACTED]
  tests/Feature/AdminAttendanceTableTest.php → tests/TestCase.php
- `AdminBookingAttendanceTest` --inherits--> `TestCase`  [EXTRACTED]
  tests/Feature/AdminBookingAttendanceTest.php → tests/TestCase.php
- `AdminMembershipAdminFeeTest` --inherits--> `TestCase`  [EXTRACTED]
  tests/Feature/AdminMembershipAdminFeeTest.php → tests/TestCase.php
- `BonusPaymentTest` --inherits--> `TestCase`  [EXTRACTED]
  tests/Feature/BonusPaymentTest.php → tests/TestCase.php
- `CheckExpiredMembershipsTest` --inherits--> `TestCase`  [EXTRACTED]
  tests/Feature/CheckExpiredMembershipsTest.php → tests/TestCase.php

## Import Cycles
- None detected.

## Communities (292 total, 44 thin omitted)

### Community 0 - "DeviceEventWebhookTest"
Cohesion: 0.05
Nodes (19): BuildMembershipInvoiceData, BonusPaymentPdfController, MembershipInvoiceController, SesiPtSlipController, BeverageApiController, BeverageSaleController, Controller, DeviceEventController (+11 more)

### Community 1 - "WhatsAppIntegration"
Cohesion: 0.09
Nodes (9): MetaWhatsAppException, MetaWhatsAppService, self, WhatsAppIntegration, Illuminate\Http\Client\ConnectionException, Illuminate\Http\Client\PendingRequest, Illuminate\Http\Client\RequestException, RuntimeException (+1 more)

### Community 2 - "AdminMembershipAdminFeeTest"
Cohesion: 0.09
Nodes (5): GymPackage, MembershipTransaction, Livewire\Features\SupportTesting\Testable, AdminMembershipAdminFeeTest, MembershipInvoiceTest

### Community 3 - "AGENTS.md"
Cohesion: 0.05
Nodes (37): APIs & Eloquent Resources, Application Structure & Architecture, Architecture, Artisan, Code Style, Conventions, Database, Deployment (+29 more)

### Community 4 - "HikvisionUserService"
Cohesion: 0.10
Nodes (13): HikvisionUserService, SyncHikvisionMember, MembershipExpiredNotification, Carbon\CarbonInterface, Illuminate\Bus\Queueable, Illuminate\Contracts\Queue\ShouldBeUnique, Illuminate\Contracts\Queue\ShouldQueue, Illuminate\Foundation\Queue\Queueable (+5 more)

### Community 5 - "Membership"
Cohesion: 0.09
Nodes (5): Membership, Illuminate\Database\Eloquent\Relations\BelongsToMany, Illuminate\Database\Eloquent\Relations\HasMany, Illuminate\Notifications\Notifiable, CheckExpiredMembershipsTest

### Community 6 - "CLAUDE.md"
Cohesion: 0.06
Nodes (31): APIs & Eloquent Resources, Application Structure & Architecture, Artisan, Conventions, Database, Deployment, Do Things the Laravel Way, Documentation Files (+23 more)

### Community 7 - "Maatwebsite\Excel\Concerns\Exportable"
Cohesion: 0.15
Nodes (8): AdminExport, MemberExport, MembershipExport, PtScheduleExport, Maatwebsite\Excel\Concerns\Exportable, Maatwebsite\Excel\Concerns\FromQuery, Maatwebsite\Excel\Concerns\WithHeadings, Maatwebsite\Excel\Concerns\WithMapping

### Community 8 - "Illuminate\Database\Eloquent\Relations\BelongsTo"
Cohesion: 0.11
Nodes (5): Attendance, BonusPaymentItem, MembershipUser, PtSession, Illuminate\Database\Eloquent\Relations\BelongsTo

### Community 9 - "User"
Cohesion: 0.15
Nodes (4): User, Illuminate\Foundation\Auth\User, HeadCoachAccessTest, WhatsAppSalesMessagingTest

### Community 10 - "devDependencies"
Cohesion: 0.07
Nodes (26): axios, chart.js, concurrently, flatpickr, flowbite, laravel-vite-plugin, dependencies, chart.js (+18 more)

### Community 11 - "Livewire Development"
Cohesion: 0.08
Nodes (24): Component-Scoped Interceptors, Intercept Messages, Intercept Requests, Interceptor System (v4), Livewire 4 JavaScript Integration, Magic Properties, Alpine & JavaScript, Basic Usage (+16 more)

### Community 12 - "Livewire Development"
Cohesion: 0.08
Nodes (24): Component-Scoped Interceptors, Intercept Messages, Intercept Requests, Interceptor System (v4), Livewire 4 JavaScript Integration, Magic Properties, Alpine & JavaScript, Basic Usage (+16 more)

### Community 13 - "Tombol Detail Membership per User"
Cohesion: 0.08
Nodes (25): Agent Dispatch Summary, Commit Strategy, Concrete Deliverables, Context, Core Objective, Definition of Done, Dependency Matrix, Execution Strategy (+17 more)

### Community 14 - "What You Must Do When Invoked"
Cohesion: 0.08
Nodes (24): For /graphify add and --watch, For /graphify query, For the commit hook and native CLAUDE.md integration, For --update and --cluster-only, /graphify, Honesty Rules, Interpreter guard for subcommands, Part A - Structural extraction for code files (+16 more)

### Community 15 - "Quick Reference"
Cohesion: 0.08
Nodes (23): 10. Routing & Controllers → `rules/routing.md`, 11. HTTP Client → `rules/http-client.md`, 12. Events, Notifications & Mail → `rules/events-notifications.md`, `rules/mail.md`, 13. Error Handling → `rules/error-handling.md`, 14. Task Scheduling → `rules/scheduling.md`, 15. Architecture → `rules/architecture.md`, 16. Migrations → `rules/migrations.md`, 17. Collections → `rules/collections.md` (+15 more)

### Community 16 - "PtPaymentBatch"
Cohesion: 0.13
Nodes (5): PtPaymentBatch, PtPaymentBatchItem, PtSessionCategory, PtSessionCategoryFactory, PtSessionPaymentTest

### Community 17 - "admin/booking-jadwal/⚡index.blade.php"
Cohesion: 0.08
Nodes (23): approveBooking({{ $booking->id }}), approveCancellation({{ $booking->id }}), closeCancelModal, closeChangeCoachModal, closeInsertModal, closeRejectModal, markAsAttended({{ $booking->id }}), markAsNoshow({{ $booking->id }}) (+15 more)

### Community 18 - "Quick Reference"
Cohesion: 0.08
Nodes (23): 10. Routing & Controllers → `rules/routing.md`, 11. HTTP Client → `rules/http-client.md`, 12. Events, Notifications & Mail → `rules/events-notifications.md`, `rules/mail.md`, 13. Error Handling → `rules/error-handling.md`, 14. Task Scheduling → `rules/scheduling.md`, 15. Architecture → `rules/architecture.md`, 16. Migrations → `rules/migrations.md`, 17. Collections → `rules/collections.md` (+15 more)

### Community 19 - "Illuminate\Database\Seeder"
Cohesion: 0.14
Nodes (10): BeverageSeeder, CoachKonsultanSeeder, DatabaseSeeder, GymPackageSeeder, KasirKonsultanSeeder, SalesKonsultanSeeder, UserSeeder, Factory (+2 more)

### Community 21 - "Illuminate\Database\Eloquent\Factories\HasFactory"
Cohesion: 0.11
Nodes (6): BeverageInvoice, BeverageInvoiceItem, BeverageRestock, KasirKonsultan, self, Illuminate\Database\Eloquent\Factories\HasFactory

### Community 22 - "TestCase"
Cohesion: 0.16
Nodes (6): Illuminate\Foundation\Testing\RefreshDatabase, Illuminate\Foundation\Testing\TestCase, ExampleTest, MemberAccountIndexTest, PtMembershipInitialSessionsTest, TestCase

### Community 23 - "Dashboard Admin Bar Chart"
Cohesion: 0.09
Nodes (21): Commit Strategy, Concrete Deliverables, Context, Core Objective, Dashboard Admin Bar Chart, Definition of Done, Execution Strategy, Final Checklist (+13 more)

### Community 24 - "Beverage"
Cohesion: 0.12
Nodes (5): SyncBeverageStock, Beverage, BeverageStokSnapshot, DepositBeverage, Illuminate\Database\Eloquent\SoftDeletes

### Community 25 - "app.js"
Cohesion: 0.20
Nodes (16): annotateResponsiveRows(), destroyDashboardSidebar(), initializeDashboardSidebar(), initializePageUi(), initializeResponsiveTables(), responsiveColumnLabels(), responsiveHeaderText(), restoreSidebarScrollPosition() (+8 more)

### Community 26 - "rekap-bonus/⚡detail.blade.php"
Cohesion: 0.11
Nodes (11): closeBonusPaymentDetail, closeBonusPaymentModal, confirmBonusPayment, deleteBonusPayment({{ $payment->id }}), openBonusPaymentDetail({{ $payment->id }}), openBonusPaymentModal, exportExcel, setFilterTime( (+3 more)

### Community 29 - "Maatwebsite\Excel\Concerns\WithEvents"
Cohesion: 0.18
Nodes (5): BeverageStockCombinedSheet, BeverageStockExport, PenjualanExport, Maatwebsite\Excel\Concerns\WithEvents, Maatwebsite\Excel\Concerns\WithTitle

### Community 30 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.16
Nodes (5): BonusPayment, Expense, Period, PtScheduleDay, Illuminate\Database\Eloquent\Model

### Community 32 - "rentang-bonus/⚡index.blade.php"
Cohesion: 0.12
Nodes (15): deleteCoachKonsultan({{ $item->id }}), deleteKasirKonsultan({{ $item->id }}), deleteKonsultan({{ $item->id }}), edit({{ $item->id }}), editCoach({{ $item->id }}), editKasir({{ $item->id }}), openCoachModal, openKasirModal (+7 more)

### Community 33 - "BeverageSale.php"
Cohesion: 0.21
Nodes (4): BeverageSaleExport, BeverageSaleExport, Maatwebsite\Excel\Concerns\FromCollection, Maatwebsite\Excel\Concerns\ShouldAutoSize

### Community 34 - "scripts"
Cohesion: 0.13
Nodes (15): scripts, dev, post-create-project-cmd, post-update-cmd, pre-package-uninstall, test, Composer\\Config::disableProcessTimeout, Illuminate\\Foundation\\ComposerScripts::prePackageUninstall (+7 more)

### Community 35 - "Tailwind CSS Development"
Cohesion: 0.14
Nodes (13): Basic Usage, Common Patterns, Common Pitfalls, CSS-First Configuration, Dark Mode, Documentation, Flexbox Layout, Grid Layout (+5 more)

### Community 36 - "⚡membership-detail.blade.php"
Cohesion: 0.14
Nodes (13): bulkAttended, bulkNoshow, closeBookingModal, closeInitialSessionsModal, closeRemainingSessionsModal, openCreateBookingModal, openInitialSessionsModal, openRemainingSessionsModal (+5 more)

### Community 37 - "Tailwind CSS Development"
Cohesion: 0.14
Nodes (13): Basic Usage, Common Patterns, Common Pitfalls, CSS-First Configuration, Dark Mode, Documentation, Flexbox Layout, Grid Layout (+5 more)

### Community 38 - "sesi-pt/⚡detail.blade.php"
Cohesion: 0.14
Nodes (13): closePaymentDetailModal, closePaymentPreview, confirmPayment, delete({{ $ptSessionCategory->id }}), deletePaymentBatch({{ $batch->id }}), edit({{ $ptSessionCategory->id }}), openPaymentDetailModal({{ $batch->id }}), openPaymentPreview (+5 more)

### Community 39 - "composer.json"
Cohesion: 0.14
Nodes (13): autoload-dev, psr-4, description, keywords, license, minimum-stability, name, prefer-stable (+5 more)

### Community 40 - "Panduan Koneksi Hikvision melalui Tunnel"
Cohesion: 0.14
Nodes (13): Alur koneksi, Instal dan autentikasi, Jalankan tunnel, Keamanan, Memeriksa ngrok, Menjalankan sinkronisasi massal, Named Tunnel (disarankan untuk produksi), Opsi 1 — ngrok (+5 more)

### Community 41 - "Membership.php"
Cohesion: 0.21
Nodes (4): CheckExpiredMemberships, Carbon\Carbon, Illuminate\Console\Command, Illuminate\Database\Eloquent\Builder

### Community 42 - "BeverageSaleExportDetail"
Cohesion: 0.18
Nodes (4): BeverageSaleExportDetail, RekapBonusExport, Maatwebsite\Excel\Concerns\WithStyles, PhpOffice\PhpSpreadsheet\Worksheet\Worksheet

### Community 43 - "Architecture Best Practices"
Cohesion: 0.17
Nodes (11): Architecture Best Practices, Code to Interfaces, Convention Over Configuration, Default Sort by Descending, Single-Purpose Action Classes, Use Atomic Locks for Race Conditions, Use `Concurrency::run()` for Parallel Execution, Use `Context` for Request-Scoped Data (+3 more)

### Community 44 - "Security Best Practices"
Cohesion: 0.17
Nodes (11): Audit Dependencies, Authorize Every Action, CSRF Protection, Encrypt Sensitive Database Fields, Escape Output to Prevent XSS, Keep Secrets Out of Code, Mass Assignment Protection, Prevent SQL Injection (+3 more)

### Community 45 - "Architecture Best Practices"
Cohesion: 0.17
Nodes (11): Architecture Best Practices, Code to Interfaces, Convention Over Configuration, Default Sort by Descending, Single-Purpose Action Classes, Use Atomic Locks for Race Conditions, Use `Concurrency::run()` for Parallel Execution, Use `Context` for Request-Scoped Data (+3 more)

### Community 46 - "Security Best Practices"
Cohesion: 0.17
Nodes (11): Audit Dependencies, Authorize Every Action, CSRF Protection, Encrypt Sensitive Database Fields, Escape Output to Prevent XSS, Keep Secrets Out of Code, Mass Assignment Protection, Prevent SQL Injection (+3 more)

### Community 47 - "Queue & Job Best Practices"
Cohesion: 0.18
Nodes (10): Always Implement `failed()`, Batch Related Jobs, Implement `ShouldBeUnique`, Queue & Job Best Practices, Rate Limit External API Calls in Jobs, `retryUntil()` Needs `$tries = 0`, Set `retry_after` Greater Than `timeout`, Use Exponential Backoff (+2 more)

### Community 48 - "Queue & Job Best Practices"
Cohesion: 0.18
Nodes (10): Always Implement `failed()`, Batch Related Jobs, Implement `ShouldBeUnique`, Queue & Job Best Practices, Rate Limit External API Calls in Jobs, `retryUntil()` Needs `$tries = 0`, Set `retry_after` Greater Than `timeout`, Use Exponential Backoff (+2 more)

### Community 49 - "Advanced Query Patterns"
Cohesion: 0.20
Nodes (9): Advanced Query Patterns, Create Dynamic Relationships via Subquery FK, Prefer `whereIn` + Subquery Over `whereHas`, Sometimes Two Simple Queries Beat One Complex Query, Use `addSelect()` Subqueries for Single Values from Has-Many, Use Compound Indexes Matching `orderBy` Column Order, Use Conditional Aggregates Instead of Multiple Count Queries, Use Correlated Subqueries for Has-Many Ordering (+1 more)

### Community 50 - "Database Performance Best Practices"
Cohesion: 0.20
Nodes (9): Add Database Indexes, Always Eager Load Relationships, Chunk Large Datasets, Database Performance Best Practices, No Queries in Blade Templates, Prevent Lazy Loading in Development, Select Only Needed Columns, Use `cursor()` for Memory-Efficient Iteration (+1 more)

### Community 51 - "Events & Notifications Best Practices"
Cohesion: 0.20
Nodes (9): Always Queue Notifications, Events & Notifications Best Practices, Implement `HasLocalePreference` on Notifiable Models, Rely on Event Discovery, Route Notification Channels to Dedicated Queues, Run `event:cache` in Production Deploy, Use `afterCommit()` on Notifications in Transactions, Use On-Demand Notifications for Non-User Recipients (+1 more)

### Community 52 - "beverages/⚡index.blade.php"
Cohesion: 0.20
Nodes (9): {{ $beverage->trashed() ? , cancelEditStokAwal, closeExportModal, editStokAwal({{ $beverage->id }}), forceDelete({{ $beverage->id }}), openExportModal, exportExcel, saveStokAwal({{ $beverage->id }}) (+1 more)

### Community 53 - "Advanced Query Patterns"
Cohesion: 0.20
Nodes (9): Advanced Query Patterns, Create Dynamic Relationships via Subquery FK, Prefer `whereIn` + Subquery Over `whereHas`, Sometimes Two Simple Queries Beat One Complex Query, Use `addSelect()` Subqueries for Single Values from Has-Many, Use Compound Indexes Matching `orderBy` Column Order, Use Conditional Aggregates Instead of Multiple Count Queries, Use Correlated Subqueries for Has-Many Ordering (+1 more)

### Community 54 - "Database Performance Best Practices"
Cohesion: 0.20
Nodes (9): Add Database Indexes, Always Eager Load Relationships, Chunk Large Datasets, Database Performance Best Practices, No Queries in Blade Templates, Prevent Lazy Loading in Development, Select Only Needed Columns, Use `cursor()` for Memory-Efficient Iteration (+1 more)

### Community 55 - "Events & Notifications Best Practices"
Cohesion: 0.20
Nodes (9): Always Queue Notifications, Events & Notifications Best Practices, Implement `HasLocalePreference` on Notifiable Models, Rely on Event Discovery, Route Notification Channels to Dedicated Queues, Run `event:cache` in Production Deploy, Use `afterCommit()` on Notifications in Transactions, Use On-Demand Notifications for Non-User Recipients (+1 more)

### Community 56 - "require-dev"
Cohesion: 0.20
Nodes (10): require-dev, fakerphp/faker, laravel/boost, laravel-lang/common, laravel/pail, laravel/pint, laravel/sail, mockery/mockery (+2 more)

### Community 57 - "Illuminate\Database\Eloquent\Factories\Factory"
Cohesion: 0.29
Nodes (4): BeverageFactory, UserFactory, Illuminate\Database\Eloquent\Factories\Factory, static

### Community 58 - "command"
Cohesion: 0.20
Nodes (9): command, enabled, type, mcp, laravel-boost, $schema, artisan, boost:mcp (+1 more)

### Community 59 - "Caching Best Practices"
Cohesion: 0.22
Nodes (8): Caching Best Practices, Configure Failover Cache Stores in Production, Use `Cache::add()` for Atomic Conditional Writes, Use `Cache::flexible()` for Stale-While-Revalidate, Use `Cache::memo()` to Avoid Redundant Hits Within a Request, Use `Cache::remember()` Instead of Manual Get/Put, Use Cache Tags to Invalidate Related Groups, Use `once()` for Per-Request Memoization

### Community 60 - "Eloquent Best Practices"
Cohesion: 0.22
Nodes (8): Apply Global Scopes Sparingly, Avoid Hardcoded Table Names in Queries, Cast Date Columns Properly, Define Attribute Casts, Eloquent Best Practices, Use Correct Relationship Types, Use Local Scopes for Reusable Queries, Use `whereBelongsTo()` for Relationship Queries

### Community 61 - "Migration Best Practices"
Cohesion: 0.22
Nodes (8): Add Indexes in the Migration, Generate Migrations with Artisan, Keep Migrations Focused, Migration Best Practices, Mirror Defaults in Model `$attributes`, Never Modify Deployed Migrations, Use `constrained()` for Foreign Keys, Write Reversible `down()` Methods by Default

### Community 62 - "⚡restock.blade.php"
Cohesion: 0.22
Nodes (8): cancelEdit, clearProduct, confirmDelete({{ $restock->id }}), edit({{ $restock->id }}), executeDelete, cancelDelete, selectProduct({{ $product->id }}), update

### Community 63 - "Caching Best Practices"
Cohesion: 0.22
Nodes (8): Caching Best Practices, Configure Failover Cache Stores in Production, Use `Cache::add()` for Atomic Conditional Writes, Use `Cache::flexible()` for Stale-While-Revalidate, Use `Cache::memo()` to Avoid Redundant Hits Within a Request, Use `Cache::remember()` Instead of Manual Get/Put, Use Cache Tags to Invalidate Related Groups, Use `once()` for Per-Request Memoization

### Community 64 - "Eloquent Best Practices"
Cohesion: 0.22
Nodes (8): Apply Global Scopes Sparingly, Avoid Hardcoded Table Names in Queries, Cast Date Columns Properly, Define Attribute Casts, Eloquent Best Practices, Use Correct Relationship Types, Use Local Scopes for Reusable Queries, Use `whereBelongsTo()` for Relationship Queries

### Community 65 - "Migration Best Practices"
Cohesion: 0.22
Nodes (8): Add Indexes in the Migration, Generate Migrations with Artisan, Keep Migrations Focused, Migration Best Practices, Mirror Defaults in Model `$attributes`, Never Modify Deployed Migrations, Use `constrained()` for Foreign Keys, Write Reversible `down()` Methods by Default

### Community 66 - "graphify reference: extra exports and benchmark"
Cohesion: 0.22
Nodes (8): graphify reference: extra exports and benchmark, Step 6b - Wiki (only if --wiki flag), Step 7 - Neo4j export (only if --neo4j or --neo4j-push flag), Step 7a - FalkorDB export (only if --falkordb or --falkordb-push flag), Step 7b - SVG export (only if --svg flag), Step 7c - GraphML export (only if --graphml flag), Step 7d - MCP server (only if --mcp flag), Step 8 - Token reduction benchmark (only if total_words > 5000)

### Community 67 - "README.md"
Cohesion: 0.22
Nodes (8): About Laravel, Code of Conduct, Contributing, Laravel Sponsors, Learning Laravel, License, Premium Partners, Security Vulnerabilities

### Community 68 - "Blade & Views Best Practices"
Cohesion: 0.25
Nodes (7): Blade & Views Best Practices, Prefer Blade Components Over `@include`, Use `$attributes->merge()` in Component Templates, Use `@aware` for Deeply Nested Component Props, Use Blade Fragments for Partial Re-Renders (htmx/Turbo), Use `@pushOnce` for Per-Component Scripts, Use View Composers for Shared View Data

### Community 69 - "Error Handling Best Practices"
Cohesion: 0.25
Nodes (7): Add Context to Exception Classes, Enable `dontReportDuplicates()`, Error Handling Best Practices, Exception Reporting and Rendering, Force JSON Error Rendering for API Routes, Throttle High-Volume Exceptions, Use `ShouldntReport` for Exceptions That Should Never Log

### Community 70 - "Task Scheduling Best Practices"
Cohesion: 0.25
Nodes (7): Task Scheduling Best Practices, Use `environments()` to Restrict Tasks, Use `onOneServer()` on Multi-Server Deployments, Use `runInBackground()` for Concurrent Long Tasks, Use Schedule Groups for Shared Configuration, Use `takeUntilTimeout()` for Time-Bounded Processing, Use `withoutOverlapping()` on Variable-Duration Tasks

### Community 71 - "Testing Best Practices"
Cohesion: 0.25
Nodes (7): Call `Event::fake()` After Factory Setup, Testing Best Practices, Use `Exceptions::fake()` to Assert Exception Reporting, Use Factory States and Sequences, Use `LazilyRefreshDatabase` Over `RefreshDatabase`, Use Model Assertions Over Raw Database Assertions, Use `recycle()` to Share Relationship Instances Across Factories

### Community 73 - "Blade & Views Best Practices"
Cohesion: 0.25
Nodes (7): Blade & Views Best Practices, Prefer Blade Components Over `@include`, Use `$attributes->merge()` in Component Templates, Use `@aware` for Deeply Nested Component Props, Use Blade Fragments for Partial Re-Renders (htmx/Turbo), Use `@pushOnce` for Per-Component Scripts, Use View Composers for Shared View Data

### Community 74 - "Error Handling Best Practices"
Cohesion: 0.25
Nodes (7): Add Context to Exception Classes, Enable `dontReportDuplicates()`, Error Handling Best Practices, Exception Reporting and Rendering, Force JSON Error Rendering for API Routes, Throttle High-Volume Exceptions, Use `ShouldntReport` for Exceptions That Should Never Log

### Community 75 - "Task Scheduling Best Practices"
Cohesion: 0.25
Nodes (7): Task Scheduling Best Practices, Use `environments()` to Restrict Tasks, Use `onOneServer()` on Multi-Server Deployments, Use `runInBackground()` for Concurrent Long Tasks, Use Schedule Groups for Shared Configuration, Use `takeUntilTimeout()` for Time-Bounded Processing, Use `withoutOverlapping()` on Variable-Duration Tasks

### Community 76 - "Testing Best Practices"
Cohesion: 0.25
Nodes (7): Call `Event::fake()` After Factory Setup, Testing Best Practices, Use `Exceptions::fake()` to Assert Exception Reporting, Use Factory States and Sequences, Use `LazilyRefreshDatabase` Over `RefreshDatabase`, Use Model Assertions Over Raw Database Assertions, Use `recycle()` to Share Relationship Instances Across Factories

### Community 77 - "member/⚡index.blade.php"
Cohesion: 0.25
Nodes (7): closeBulkSyncModal, closeSyncModal, lanjutkanCheckout, openBulkSyncModal, openSyncModal({{ $user->id }}), refreshMembershipStatus({{ $user->id }}), exportExcel

### Community 78 - "penjualan/⚡index.blade.php"
Cohesion: 0.25
Nodes (7): closeIncomeModal, openIncomeModal, resetUserSelection, exportExcel, setFilterTime(, selectUser({{ $u->id }}, , sendWhatsApp({{ $transaction->id }})

### Community 79 - "require"
Cohesion: 0.25
Nodes (8): require, barryvdh/laravel-dompdf, laravel/framework, laravel/tinker, livewire/livewire, maatwebsite/excel, php, simplesoftwareio/simple-qrcode

### Community 80 - "setup"
Cohesion: 0.25
Nodes (8): post-root-package-install, setup, composer install, npm install, npm run build, @php artisan key:generate, @php artisan migrate --force, @php -r \"file_exists('.env') || copy('.env.example', '.env');\

### Community 81 - "admin/membership/⚡index.blade.php"
Cohesion: 0.25
Nodes (5): delete({{ $this->selectedMembership->id }}), exportExcel, openCoachModalFromDetail({{ $this->selectedMembership->id }}), openDetailModal({{ $membership->id }}), setFilterTime(

### Community 84 - "⚡gabung.blade.php"
Cohesion: 0.29
Nodes (3): activateFromAction, chooseCoachFromAction, openActionModal({{ $membership->id }})

### Community 85 - "Collection Best Practices"
Cohesion: 0.29
Nodes (6): Choose `cursor()` vs. `lazy()` Correctly, Collection Best Practices, Use `#[CollectedBy]` for Custom Collection Classes, Use Higher-Order Messages for Simple Operations, Use `lazyById()` When Updating Records While Iterating, Use `toQuery()` for Bulk Operations on Collections

### Community 86 - "HTTP Client Best Practices"
Cohesion: 0.29
Nodes (6): Always Set Explicit Timeouts, Fake HTTP Calls in Tests, Handle Errors Explicitly, HTTP Client Best Practices, Use Request Pooling for Concurrent Requests, Use Retry with Backoff for External APIs

### Community 87 - "Mail Best Practices"
Cohesion: 0.29
Nodes (6): Implement `ShouldQueue` on the Mailable Class, Mail Best Practices, Separate Content Tests from Sending Tests, Use `afterCommit()` on Mailables Inside Transactions, Use `assertQueued()` Not `assertSent()` for Queued Mailables, Use Markdown Mailables for Transactional Emails

### Community 88 - "Routing & Controllers Best Practices"
Cohesion: 0.29
Nodes (6): Keep Controllers Thin, Routing & Controllers Best Practices, Type-Hint Form Requests, Use Implicit Route Model Binding, Use Resource Controllers, Use Scoped Bindings for Nested Resources

### Community 89 - "Conventions & Style"
Cohesion: 0.29
Nodes (6): Conventions & Style, Follow Laravel Naming Conventions, No Inline JS/CSS in Blade, No Unnecessary Comments, Prefer Shorter Readable Syntax, Use Laravel String & Array Helpers

### Community 90 - "Validation & Forms Best Practices"
Cohesion: 0.29
Nodes (6): Always Use `validated()`, Array vs. String Notation for Rules, Use Form Request Classes, Use `Rule::when()` for Conditional Validation, Use the `after()` Method for Custom Validation, Validation & Forms Best Practices

### Community 92 - "admin/jadwal-pt/⚡index.blade.php"
Cohesion: 0.29
Nodes (6): approveSchedule({{ $membership->id }}), closeScheduleModal, deleteSchedule({{ $membership->id }}), export, openScheduleModal({{ $membership->id }}), rejectSchedule({{ $membership->id }})

### Community 93 - "Collection Best Practices"
Cohesion: 0.29
Nodes (6): Choose `cursor()` vs. `lazy()` Correctly, Collection Best Practices, Use `#[CollectedBy]` for Custom Collection Classes, Use Higher-Order Messages for Simple Operations, Use `lazyById()` When Updating Records While Iterating, Use `toQuery()` for Bulk Operations on Collections

### Community 94 - "HTTP Client Best Practices"
Cohesion: 0.29
Nodes (6): Always Set Explicit Timeouts, Fake HTTP Calls in Tests, Handle Errors Explicitly, HTTP Client Best Practices, Use Request Pooling for Concurrent Requests, Use Retry with Backoff for External APIs

### Community 95 - "Mail Best Practices"
Cohesion: 0.29
Nodes (6): Implement `ShouldQueue` on the Mailable Class, Mail Best Practices, Separate Content Tests from Sending Tests, Use `afterCommit()` on Mailables Inside Transactions, Use `assertQueued()` Not `assertSent()` for Queued Mailables, Use Markdown Mailables for Transactional Emails

### Community 96 - "Routing & Controllers Best Practices"
Cohesion: 0.29
Nodes (6): Keep Controllers Thin, Routing & Controllers Best Practices, Type-Hint Form Requests, Use Implicit Route Model Binding, Use Resource Controllers, Use Scoped Bindings for Nested Resources

### Community 97 - "Conventions & Style"
Cohesion: 0.29
Nodes (6): Conventions & Style, Follow Laravel Naming Conventions, No Inline JS/CSS in Blade, No Unnecessary Comments, Prefer Shorter Readable Syntax, Use Laravel String & Array Helpers

### Community 98 - "Validation & Forms Best Practices"
Cohesion: 0.29
Nodes (6): Always Use `validated()`, Array vs. String Notation for Rules, Use Form Request Classes, Use `Rule::when()` for Conditional Validation, Use the `after()` Method for Custom Validation, Validation & Forms Best Practices

### Community 99 - "sales.blade.php"
Cohesion: 0.29
Nodes (6): closeDeleteModal, confirmDelete({{ $sale->id }}), deleteSale, exportExcelDetail, exportExcel, setFilterTime(

### Community 100 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 101 - "[2026-05-31] Task: Route + Dashboard + Sidebar"
Cohesion: 0.29
Nodes (6): [2026-05-31] Task: Route + Dashboard + Sidebar, Commit, Dashboard Chart Learnings, Files Modified, Key Decisions, What Worked

### Community 102 - "member/jadwal-pt/⚡index.blade.php"
Cohesion: 0.29
Nodes (6): clearFilters, closeDetailModal, nextWeek, openDetailModal({{ $booking->id }}), previousWeek, thisWeek

### Community 104 - "Configuration Best Practices"
Cohesion: 0.33
Nodes (5): Configuration Best Practices, `env()` Only in Config Files, Use `App::environment()` for Environment Checks, Use Constants and Language Files, Use Encrypted Env or External Secrets

### Community 106 - "Configuration Best Practices"
Cohesion: 0.33
Nodes (5): Configuration Best Practices, `env()` Only in Config Files, Use `App::environment()` for Environment Checks, Use Constants and Language Files, Use Encrypted Env or External Secrets

### Community 107 - "dashboard/⚡navbar.blade.php"
Cohesion: 0.33
Nodes (5): closeShiftModal, openShiftModal, logout, $set(, saveShift

### Community 108 - "graphify reference: query, path, explain"
Cohesion: 0.33
Nodes (5): For /graphify explain, For /graphify path, graphify reference: query, path, explain, Step 0 — Constrained query expansion (REQUIRED before traversal), Step 1 — Traversal

### Community 109 - "pt-berjalan/⚡index.blade.php"
Cohesion: 0.33
Nodes (3): delete({{ $this->selectedMembership->id }}), openCoachModalFromDetail({{ $this->selectedMembership->id }}), openDetailModal({{ $membership->id }})

### Community 112 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 113 - "invoice.blade.php"
Cohesion: 0.40
Nodes (4): confirmDelete({{ $invoice->id }}), deleteInvoice, cancelDelete, exportExcel

### Community 116 - "membership/⚡edit.blade.php"
Cohesion: 0.50
Nodes (3): cancelPartialToPaidWarning, cancelPaymentWarning, save

### Community 117 - "graphify reference: add a URL and watch a folder"
Cohesion: 0.50
Nodes (3): For /graphify add, For --watch, graphify reference: add a URL and watch a folder

### Community 118 - "graphify reference: commit hook and native CLAUDE.md integration"
Cohesion: 0.50
Nodes (3): For git commit hook, For native CLAUDE.md integration, graphify reference: commit hook and native CLAUDE.md integration

### Community 119 - "graphify reference: incremental update and cluster-only"
Cohesion: 0.50
Nodes (3): For --cluster-only, For --update (incremental re-extraction), graphify reference: incremental update and cluster-only

### Community 120 - "⚡hutang.blade.php"
Cohesion: 0.50
Nodes (3): confirmLunas, deleteHutang({{ $sale->id }}), openConfirmModal({{ $sale->id }})

### Community 121 - "custom-pagination.blade.php"
Cohesion: 0.50
Nodes (3): gotoPage({{ $page }}), nextPage, previousPage

### Community 123 - "pt/booking-jadwal/⚡index.blade.php"
Cohesion: 0.50
Nodes (3): nextWeek, previousWeek, thisWeek

### Community 126 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 127 - "post-autoload-dump"
Cohesion: 0.67
Nodes (3): post-autoload-dump, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump, @php artisan package:discover --ansi

## Knowledge Gaps
- **778 isolated node(s):** `php`, `$schema`, `name`, `type`, `description` (+773 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **44 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `DeviceEventWebhookTest`, `WhatsAppIntegration`, `AdminMembershipAdminFeeTest`, `HikvisionUserService`, `Membership`, `Maatwebsite\Excel\Concerns\Exportable`, `Illuminate\Database\Eloquent\Relations\BelongsTo`, `PtPaymentBatch`, `Illuminate\Database\Seeder`, `BonusPaymentTest`, `Illuminate\Database\Eloquent\Factories\HasFactory`, `TestCase`, `DeviceEventMonitoringTest`, `RekapBonusDetailTableTest`, `Membership.php`, `BeverageSaleExportDetail`, `AdminBookingAttendanceTest`, `PtMembershipRemainingSessionsTest`, `AdminAttendanceTableTest`?**
  _High betweenness centrality (0.046) - this node is a cross-community bridge._
- **Why does `Membership` connect `Membership` to `DeviceEventWebhookTest`, `AdminMembershipAdminFeeTest`, `Illuminate\Database\Eloquent\Relations\BelongsTo`, `Membership.php`, `BeverageSaleExportDetail`, `User`, `PtPaymentBatch`, `AdminBookingAttendanceTest`, `PtMembershipRemainingSessionsTest`, `BonusPaymentTest`, `TestCase`, `RekapBonusDetailTableTest`, `Illuminate\Database\Eloquent\Model`?**
  _High betweenness centrality (0.025) - this node is a cross-community bridge._
- **Why does `TestCase` connect `TestCase` to `DeviceEventWebhookTest`, `WhatsAppIntegration`, `AdminMembershipAdminFeeTest`, `HikvisionUserService`, `Membership`, `AdminAttendanceTableTest`, `Illuminate\Database\Eloquent\Relations\BelongsTo`, `Membership.php`, `User`, `PtPaymentBatch`, `AdminBookingAttendanceTest`, `PtMembershipRemainingSessionsTest`, `BonusPaymentTest`, `rekap-bonus/⚡detail.blade.php`, `DeviceEventMonitoringTest`, `RekapBonusDetailTableTest`?**
  _High betweenness centrality (0.008) - this node is a cross-community bridge._
- **Are the 24 inferred relationships involving `User` (e.g. with `.query()` and `.query()`) actually correct?**
  _`User` has 24 INFERRED edges - model-reasoned connections that need verification._
- **Are the 13 inferred relationships involving `Membership` (e.g. with `.handle()` and `.query()`) actually correct?**
  _`Membership` has 13 INFERRED edges - model-reasoned connections that need verification._
- **What connects `php`, `$schema`, `name` to the rest of the system?**
  _778 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `DeviceEventWebhookTest` be split into smaller, more focused modules?**
  _Cohesion score 0.05143638850889193 - nodes in this community are weakly interconnected._