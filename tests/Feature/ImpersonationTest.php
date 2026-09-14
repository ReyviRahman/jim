<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('dashboardRoles')]
    public function test_admin_can_impersonate_and_return(string $role, string $route, bool $headCoach = false): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $target = User::factory()->create([
            'role' => $role,
            'is_active' => true,
            ...($headCoach ? ['email' => User::HEAD_COACH_EMAIL] : []),
        ]);
        $adminToken = $admin->remember_token;
        $targetToken = $target->remember_token;
        Log::spy();

        $this->actingAs($admin)->withSession(['private_draft' => 'admin data', '_token' => 'old-admin-token']);
        $adminSessionId = session()->getId();

        $this->post(route('impersonation.start', $target))
            ->assertRedirect(route($route))
            ->assertSessionHas('impersonator_id', $admin->id)
            ->assertSessionMissing('private_draft');
        $this->assertAuthenticatedAs($target);
        $this->assertNotSame($adminSessionId, session()->getId());
        $this->assertNotSame('old-admin-token', session()->token());

        $this->get(route($route))->assertOk()
            ->assertSee('Anda masuk sebagai')->assertSee('Kembali ke admin')
            ->assertSee(route('impersonation.stop'));
        if ($role === 'member' || ($role === 'pt' && ! $headCoach)) {
            $this->get(route('admin.akun.member.index'))->assertRedirect(route('home'));
        }

        $this->withSession(['private_draft' => 'target data']);
        $targetSessionId = session()->getId();
        $csrf = session()->token();
        $this->post(route('impersonation.stop'))
            ->assertRedirect(route('admin.absensi.index'))
            ->assertSessionMissing('impersonator_id')
            ->assertSessionMissing('private_draft');
        $this->assertAuthenticatedAs($admin);
        $this->assertNotSame($targetSessionId, session()->getId());
        $this->assertNotSame($csrf, session()->token());
        $this->assertSame($adminToken, $admin->fresh()->remember_token);
        $this->assertSame($targetToken, $target->fresh()->remember_token);

        Log::shouldHaveReceived('info')->with('impersonation.started', \Mockery::on(
            fn (array $context): bool => $context['admin_id'] === $admin->id && $context['target_id'] === $target->id && isset($context['at'])
        ))->once();
        Log::shouldHaveReceived('info')->with('impersonation.stopped', \Mockery::on(
            fn (array $context): bool => $context['admin_id'] === $admin->id && $context['target_id'] === $target->id && $context['reason'] === 'restored'
        ))->once();
    }

    public static function dashboardRoles(): array
    {
        return [
            ['member', 'member.dashboard'],
            ['pt', 'pt.absensi'],
            ['kasir_gym', 'admin.absensi.index'],
            ['kasir_minum', 'admin.beverages.index'],
            ['pt', 'admin.cicilan.index', true],
        ];
    }

    public function test_guests_and_non_admins_cannot_start_or_stop_impersonation(): void
    {
        $target = User::factory()->create(['role' => 'member', 'is_active' => true]);
        $this->post(route('impersonation.start', $target))->assertRedirect(route('login'));
        $this->post(route('impersonation.stop'))->assertRedirect(route('login'));

        foreach (['member', 'pt', 'kasir_gym', 'kasir_minum', 'sales', 'cleaning_service'] as $role) {
            $actor = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($actor)->post(route('impersonation.start', $target))->assertForbidden();
            $this->post(route('impersonation.stop'))->assertForbidden();
            $this->assertAuthenticatedAs($actor);
        }

        $this->actingAs(User::factory()->headCoach()->create(['is_active' => true]))
            ->post(route('impersonation.start', $target))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => false]))
            ->post(route('impersonation.start', $target))->assertForbidden();
    }

    public function test_admin_cannot_impersonate_forbidden_targets_or_nest_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin);
        $this->post(route('impersonation.start', $admin))->assertForbidden();
        foreach ([['admin', true], ['sales', true], ['cleaning_service', true], ['member', false]] as [$role, $active]) {
            $target = User::factory()->create(['role' => $role, 'is_active' => $active]);
            $this->post(route('impersonation.start', $target))->assertForbidden();
            $this->assertAuthenticatedAs($admin);
        }
        $this->post(route('impersonation.start', 999999))->assertNotFound();

        $target = User::factory()->create(['role' => 'member', 'is_active' => true]);
        $this->withSession(['impersonator_id' => $admin->id])
            ->post(route('impersonation.start', $target))->assertForbidden();
        $this->assertAuthenticatedAs($admin);
    }

    #[DataProvider('unavailableAdmins')]
    public function test_unavailable_admin_ends_session(string $change): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'member', 'is_active' => true]);
        $this->actingAs($admin)->post(route('impersonation.start', $target))->assertRedirect();

        match ($change) {
            'deleted' => $admin->delete(),
            'inactive' => $admin->update(['is_active' => false]),
            'demoted' => $admin->update(['role' => 'member']),
        };

        $this->post(route('impersonation.stop'))->assertRedirect(route('login'))
            ->assertSessionMissing('impersonator_id');
        $this->assertGuest();
    }

    public static function unavailableAdmins(): array
    {
        return [['deleted'], ['inactive'], ['demoted']];
    }

    public function test_logout_discards_impersonation_and_logs_completion(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'member', 'is_active' => true]);
        $this->actingAs($admin)->post(route('impersonation.start', $target));
        Log::spy();

        Livewire::test('dashboard.navbar')->call('logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertFalse(session()->has('impersonator_id'));
        Log::shouldHaveReceived('info')->with('impersonation.stopped', \Mockery::on(
            fn (array $context): bool => $context['admin_id'] === $admin->id && $context['target_id'] === $target->id && $context['reason'] === 'logout'
        ))->once();
    }

    public function test_buttons_only_appear_for_authorized_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $member = User::factory()->create(['role' => 'member', 'is_active' => true]);
        $trainer = User::factory()->create(['role' => 'pt', 'is_active' => true]);
        $cashier = User::factory()->create(['role' => 'kasir_gym', 'is_active' => true]);
        $this->actingAs($admin);
        foreach (['member' => $member, 'admin' => $cashier, 'trainer' => $trainer] as $page => $target) {
            $this->get(route("admin.akun.{$page}.index"))->assertOk()
                ->assertSee(route('impersonation.start', $target))
                ->assertDontSee(route('impersonation.start', $admin))
                ->assertDontSee('Kembali ke admin');
        }
        $this->actingAs($cashier)->get(route('admin.akun.member.index'))->assertOk()
            ->assertDontSee('Masuk sebagai pengguna');
        $this->actingAs($admin)->withSession(['impersonator_id' => $admin->id])
            ->get(route('admin.akun.member.index'))->assertOk()->assertDontSee('Masuk sebagai pengguna');
    }

    public function test_switching_accounts_requires_post_and_csrf(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'member', 'is_active' => true]);
        $this->actingAs($admin)->get(route('impersonation.start', $target))->assertStatus(405);
        $this->get(route('impersonation.stop'))->assertStatus(405);

        $this->app['env'] = 'local';
        $this->post(route('impersonation.start', $target))->assertStatus(419);
        $this->withSession(['impersonator_id' => $admin->id])
            ->post(route('impersonation.stop'))->assertStatus(419);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_remember_cookie_is_cleared_without_remembering_target(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'member', 'is_active' => true]);
        $cookie = Auth::guard('web')->getRecallerName();

        $this->actingAs($admin)->withCookie($cookie, 'old-remember-cookie')
            ->post(route('impersonation.start', $target))->assertCookieExpired($cookie);
        $this->assertAuthenticatedAs($target);
    }
}
