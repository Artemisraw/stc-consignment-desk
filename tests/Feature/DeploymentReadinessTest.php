<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DeploymentReadinessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    public function test_production_seeding_refuses_the_default_demo_password_and_writes_nothing(): void
    {
        $this->app['env'] = 'production';
        config(['app.demo_password' => null]);

        try {
            $this->runDemoSeeder();
            $this->fail('Seeding production without DEMO_PASSWORD should stop.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('DEMO_PASSWORD', $e->getMessage());
        }
        $this->assertSame(0, User::count());
    }

    public function test_demo_accounts_use_the_configured_password(): void
    {
        $this->app['env'] = 'production';
        config(['app.demo_password' => 'uat-Strong-Pass-2026']);

        $this->runDemoSeeder();

        $owner = User::where('email', 'owner@skytop.test')->firstOrFail();
        $this->assertTrue(Hash::check('uat-Strong-Pass-2026', $owner->password));
        $this->assertFalse(Hash::check('password', $owner->password));
    }

    public function test_production_links_use_https(): void
    {
        $this->app['env'] = 'production';
        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', route('tracking', ['tracking' => 'STC-1']));
    }

    /** Run the seeder directly: `db:seed` in production asks for confirmation, which a test cannot give. */
    private function runDemoSeeder(): void
    {
        $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->run();
    }
}
