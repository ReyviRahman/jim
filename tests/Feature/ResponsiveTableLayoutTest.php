<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class ResponsiveTableLayoutTest extends TestCase
{
    public function test_booking_schedule_cards_wrap_arbitrarily_long_names(): void
    {
        $contents = file_get_contents(resource_path('views/pages/dashboard/admin/booking-jadwal/⚡index.blade.php'));

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('truncate', $contents);
        $this->assertStringContainsString(
            'class="w-full min-w-0 max-w-full overflow-hidden cursor-pointer p-2 rounded border text-xs transition-colors',
            $contents,
        );
        $this->assertSame(
            3,
            substr_count($contents, 'w-full min-w-0 max-w-full whitespace-normal wrap-anywhere'),
            'Member and trainer names must wrap even when they contain no natural break points.',
        );
        $this->assertStringContainsString(
            'class="mt-1 flex min-w-0 max-w-full flex-wrap items-center gap-1"',
            $contents,
        );
    }

    public function test_booking_schedule_day_filter_uses_responsive_defaults(): void
    {
        $contents = file_get_contents(resource_path('views/pages/dashboard/admin/booking-jadwal/⚡index.blade.php'));
        $javascript = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($contents);
        $this->assertIsString($javascript);
        $this->assertStringContainsString('x-data="bookingDayFilter"', $contents);
        $this->assertStringContainsString('>Today</button>', preg_replace('/\s+/', '', $contents));
        $this->assertStringContainsString('>AllDay</button>', preg_replace('/\s+/', '', $contents));
        $this->assertSame(2, substr_count($contents, 'x-show="isDayVisible($el.dataset.today)"'));
        $this->assertStringContainsString("window.matchMedia('(min-width: 40rem)')", $javascript);
        $this->assertStringContainsString(
            "dayView: bookingScheduleDesktopBreakpoint.matches ? 'all' : 'today'",
            $javascript,
        );
    }

    public function test_booking_schedule_today_filter_groups_mobile_rows_into_one_card(): void
    {
        $contents = file_get_contents(resource_path('views/pages/dashboard/admin/booking-jadwal/⚡index.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($contents);
        $this->assertIsString($css);
        $this->assertStringContainsString('data-booking-schedule', $contents);
        $this->assertStringContainsString('x-bind:data-day-view="dayView"', $contents);
        $this->assertSame(1, substr_count($contents, 'data-booking-schedule-today-header'));
        $this->assertStringContainsString(
            'data-booking-schedule-today-header x-show="dayView === \'today\'"',
            $contents,
        );
        $this->assertStringContainsString("{{ now()->locale('id')->isoFormat('dddd, D MMMM YYYY') }}", $contents);
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 39\.999rem\).*?\[data-booking-schedule\]\[data-day-view="today"\] tbody:not\(\.hidden\)\s*\{.*?gap: 0;.*?border-radius: 0 0 0\.5rem 0\.5rem;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\[data-booking-schedule\]\[data-day-view="today"\] tbody > tr:not\(\.hidden\)\s*\{.*?border-radius: 0;.*?box-shadow: none;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\[data-booking-schedule\]\[data-day-view="today"\] td::before\s*\{\s*display: none;/s',
            $css,
        );
    }

    public function test_card_layout_is_limited_to_phone_widths_and_number_columns_are_compact(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $javascript = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($css);
        $this->assertIsString($javascript);
        $this->assertMatchesRegularExpression(
            '/@media \(min-width: 40rem\).*?table\[data-responsive-table\]\s*\{\s*display: table;/s',
            $css,
            'Every responsive table must return to table layout at the sm/tablet breakpoint.',
        );
        $this->assertStringContainsString('[data-responsive-compact-column]', $css);
        $this->assertStringContainsString('width: 2.25rem !important;', $css);
        $this->assertStringContainsString('const responsiveCompactColumnPattern = /^(?:no\.?|nomor|#)$/i;', $javascript);
        $this->assertStringContainsString("'data-responsive-compact-column'", $javascript);
    }

    #[DataProvider('interactiveTableViews')]
    public function test_interactive_tables_use_the_responsive_layout_contract(string $view): void
    {
        $contents = file_get_contents(resource_path('views/pages/'.$view));

        $this->assertIsString($contents);
        $this->assertMatchesRegularExpression('/<table\b/i', $contents);
        $this->assertDoesNotMatchRegularExpression(
            '/<div[^>]*class="[^"]*\boverflow-x-auto\b[^"]*"[^>]*>/i',
            $contents,
            "Table shell in [$view] must not use horizontal scrolling.",
        );
        $this->assertStringNotContainsString(
            'min-w-[',
            $contents,
            "View [$view] must not force an arbitrary minimum table width.",
        );

        preg_match_all('/<table\b[^>]*>/i', $contents, $matches);

        foreach ($matches[0] as $tableTag) {
            $this->assertStringContainsString(
                'table-fixed',
                $tableTag,
                "Every table in [$view] must use fixed column layout.",
            );

            $this->assertStringContainsString('data-responsive-table', $tableTag);
            $this->assertMatchesRegularExpression(
                '/data-responsive-breakpoint="(?:sm|lg|xl)"/',
                $tableTag,
                "Responsive table in [$view] must declare its layout metadata.",
            );
        }
    }

    public function test_the_responsive_table_inventory_covers_every_interactive_page_table(): void
    {
        $expectedViews = array_map(
            static fn (array $arguments): string => $arguments[0],
            array_values(self::interactiveTableViews()),
        );
        $discoveredViews = [];
        $discoveredTableCount = 0;
        $pagesPath = resource_path('views/pages');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pagesPath));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            if (preg_match('/(?:pdf|print)/i', $file->getFilename()) === 1) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (! is_string($contents) || ! str_contains($contents, '<table')) {
                continue;
            }

            $discoveredTableCount += preg_match_all('/<table\b/i', $contents);
            $discoveredViews[] = str_replace('\\', '/', substr($file->getPathname(), strlen($pagesPath) + 1));
        }

        sort($expectedViews);
        sort($discoveredViews);

        $this->assertSame($expectedViews, $discoveredViews);
        $this->assertCount(37, $discoveredViews);
        $this->assertSame(49, $discoveredTableCount);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function interactiveTableViews(): array
    {
        $views = [
            'dashboard/admin/⚡index.blade.php',
            'dashboard/admin/absensi/⚡index.blade.php',
            'dashboard/admin/akun/admin/⚡index.blade.php',
            'dashboard/admin/akun/member/⚡index.blade.php',
            'dashboard/admin/akun/sales/⚡index.blade.php',
            'dashboard/admin/akun/trainer/⚡index.blade.php',
            'dashboard/admin/beverages/invoice.blade.php',
            'dashboard/admin/beverages/sales.blade.php',
            'dashboard/admin/beverages/⚡deposit.blade.php',
            'dashboard/admin/beverages/⚡hutang.blade.php',
            'dashboard/admin/beverages/⚡index.blade.php',
            'dashboard/admin/beverages/⚡invoice-create.blade.php',
            'dashboard/admin/beverages/⚡invoice-edit.blade.php',
            'dashboard/admin/beverages/⚡pos.blade.php',
            'dashboard/admin/beverages/⚡restock.blade.php',
            'dashboard/admin/booking-jadwal/⚡index.blade.php',
            'dashboard/admin/cicilan/⚡index.blade.php',
            'dashboard/admin/jadwal-pt/⚡index.blade.php',
            'dashboard/admin/membership/⚡gabung.blade.php',
            'dashboard/admin/membership/⚡non-member.blade.php',
            'dashboard/admin/package/⚡index.blade.php',
            'dashboard/admin/pengeluaran/⚡index.blade.php',
            'dashboard/admin/penjualan/⚡index.blade.php',
            'dashboard/admin/pt-booking/⚡index.blade.php',
            'dashboard/admin/rekap-bonus/⚡detail.blade.php',
            'dashboard/admin/rekap-bonus/⚡index.blade.php',
            'dashboard/admin/renew/⚡index.blade.php',
            'dashboard/admin/rentang-bonus/⚡index.blade.php',
            'dashboard/admin/riwayat/⚡detail.blade.php',
            'dashboard/admin/riwayat/⚡index.blade.php',
            'dashboard/admin/sesi-pt/⚡detail.blade.php',
            'dashboard/admin/sesi-pt/⚡index.blade.php',
            'dashboard/admin/sesi-pt/⚡membership-detail.blade.php',
            'dashboard/member/kehadiran/⚡index.blade.php',
            'dashboard/member/membership/⚡index.blade.php',
            'dashboard/pt/kehadiran/⚡index.blade.php',
            'device-events/⚡index.blade.php',
        ];

        return array_combine($views, array_map(static fn (string $view): array => [$view], $views));
    }
}
