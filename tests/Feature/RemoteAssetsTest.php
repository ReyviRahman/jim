<?php

namespace Tests\Feature;

use Illuminate\Foundation\Vite;
use Tests\TestCase;

class RemoteAssetsTest extends TestCase
{
    public function test_tunnel_uses_https_built_assets_even_when_vite_is_running(): void
    {
        $vite = app(Vite::class);
        $hotFile = tempnam(sys_get_temp_dir(), 'jim-vite-');
        file_put_contents($hotFile, 'http://[::1]:5173');
        $originalHotFile = $vite->hotFile();
        $vite->useHotFile($hotFile);

        try {
            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
                ->withHeader('X-Forwarded-Proto', 'https')
                ->get('http://gym-preview.ngrok-free.app/login')
                ->assertOk()
                ->assertSee('https://gym-preview.ngrok-free.app/build/assets/', false)
                ->assertDontSee('5173')
                ->assertDontSee('@vite/client');
            $this->assertSame($hotFile, $vite->hotFile());
            $this->assertFileExists($hotFile);
        } finally {
            $vite->useHotFile($originalHotFile);
            unlink($hotFile);
        }
    }

    public function test_localhost_keeps_vite_hot_reload(): void
    {
        $vite = app(Vite::class);
        $hotFile = tempnam(sys_get_temp_dir(), 'jim-vite-');
        file_put_contents($hotFile, 'http://[::1]:5173');
        $originalHotFile = $vite->hotFile();
        $vite->useHotFile($hotFile);

        try {
            $this->get('http://localhost/login')
                ->assertOk()
                ->assertSee('http://[::1]:5173/@vite/client', false);
        } finally {
            $vite->useHotFile($originalHotFile);
            unlink($hotFile);
        }
    }
}
