<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffAccountsTest extends TestCase
{
    private Branch $branch;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->branch = Branch::create(['name' => 'Nairobi', 'route_order' => 1]);
        $this->owner = $this->makeUser(Role::Owner, 'owner@example.test');
    }

    public function test_owner_can_create_edit_and_deactivate_a_staff_account(): void
    {
        $this->actingAs($this->owner)->post('/staff', [
            'name' => 'Amina Clerk',
            'email' => 'amina@example.test',
            'role' => Role::Clerk->value,
            'branch_id' => $this->branch->id,
            'password' => 'initial-password',
            'password_confirmation' => 'initial-password',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $staff = User::where('email', 'amina@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('initial-password', $staff->password));
        $this->assertNotSame('initial-password', $staff->password);
        $this->assertTrue($staff->is_active);

        $this->actingAs($this->owner)->put("/staff/{$staff->id}", [
            'name' => 'Amina Manager',
            'email' => 'amina.manager@example.test',
            'role' => Role::Manager->value,
            'branch_id' => $this->branch->id,
        ])->assertRedirect('/staff')->assertSessionHasNoErrors();

        $staff->refresh();
        $this->assertSame('Amina Manager', $staff->name);
        $this->assertSame(Role::Manager, $staff->role);

        $this->actingAs($this->owner)->patch("/staff/{$staff->id}/toggle")
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse($staff->refresh()->is_active);
    }

    public function test_owner_can_reset_a_staff_password_and_blank_password_keeps_the_existing_one(): void
    {
        $staff = $this->makeUser(Role::Clerk, 'password-reset@example.test');

        $this->actingAs($this->owner)->put("/staff/{$staff->id}", [
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => $staff->role->value,
            'branch_id' => $staff->branch_id,
            'password' => '',
            'password_confirmation' => '',
        ])->assertSessionHasNoErrors();

        $this->post('/logout');
        $this->post('/login', [
            'email' => $staff->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($staff);

        $this->post('/logout');
        $this->actingAs($this->owner)->put("/staff/{$staff->id}", [
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => $staff->role->value,
            'branch_id' => $staff->branch_id,
            'password' => 'owner-reset-password',
            'password_confirmation' => 'owner-reset-password',
        ])->assertSessionHasNoErrors();

        $this->post('/logout');
        $this->post('/login', [
            'email' => $staff->email,
            'password' => 'owner-reset-password',
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($staff);
    }

    public function test_resetting_a_password_signs_out_sessions_started_with_the_old_one(): void
    {
        $staff = $this->makeUser(Role::Clerk, 'signed-in@example.test');
        $sessionBeforeReset = ['password_hash_web' => $staff->getAuthPassword()];

        $this->actingAs($staff)->withSession($sessionBeforeReset)->get('/dashboard')->assertOk();

        $this->actingAs($this->owner)->put("/staff/{$staff->id}", [
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => $staff->role->value,
            'branch_id' => $staff->branch_id,
            'password' => 'owner-reset-password',
            'password_confirmation' => 'owner-reset-password',
        ])->assertSessionHasNoErrors();

        $this->actingAs($staff->refresh())->withSession($sessionBeforeReset)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_staff_email_is_normalized_and_mixed_case_login_succeeds(): void
    {
        $this->actingAs($this->owner)->post('/staff', [
            'name' => 'Amina Clerk',
            'email' => '  Amina@Example.TEST  ',
            'role' => Role::Clerk->value,
            'branch_id' => $this->branch->id,
            'password' => 'initial-password',
            'password_confirmation' => 'initial-password',
        ])->assertSessionHasNoErrors();

        $staff = User::where('email', 'amina@example.test')->firstOrFail();

        $this->post('/logout');
        $this->post('/login', [
            'email' => 'Amina@Example.TEST',
            'password' => 'initial-password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($staff);
    }

    public function test_non_owner_roles_receive_forbidden_on_every_staff_route(): void
    {
        foreach ([Role::Manager, Role::Clerk, Role::BranchAgent] as $role) {
            $actor = $this->makeUser($role, $role->value.'@example.test');

            $this->actingAs($actor)->get('/staff')->assertForbidden();
            $this->actingAs($actor)->post('/staff', [
                'name' => 'Blocked User',
                'email' => "blocked-{$role->value}@example.test",
                'role' => Role::Clerk->value,
                'branch_id' => $this->branch->id,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertForbidden();
            $this->actingAs($actor)->get("/staff/{$this->owner->id}/edit")->assertForbidden();
            $this->actingAs($actor)->put("/staff/{$this->owner->id}", [
                'name' => $this->owner->name,
                'email' => $this->owner->email,
                'role' => Role::Owner->value,
                'branch_id' => $this->branch->id,
            ])->assertForbidden();
            $this->actingAs($actor)->patch("/staff/{$this->owner->id}/toggle")->assertForbidden();
        }
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = $this->makeUser(Role::Clerk, 'inactive@example.test', false);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logged_in_user_is_logged_out_on_the_next_request_after_deactivation(): void
    {
        $user = $this->makeUser(Role::Clerk, 'recently-disabled@example.test');
        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_owner_cannot_deactivate_or_demote_their_own_account(): void
    {
        $this->actingAs($this->owner)->patch("/staff/{$this->owner->id}/toggle")
            ->assertSessionHasErrors('is_active');
        $this->assertTrue($this->owner->refresh()->is_active);

        $this->actingAs($this->owner)->put("/staff/{$this->owner->id}", [
            'name' => $this->owner->name,
            'email' => $this->owner->email,
            'role' => Role::Manager->value,
            'branch_id' => $this->branch->id,
        ])->assertSessionHasErrors('role');

        $this->assertSame(Role::Owner, $this->owner->refresh()->role);
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $failedResponse = $this->actingAs($this->owner)->put('/account/password', [
            'current_password' => 'incorrect-password',
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password', $this->owner->refresh()->password));

        $previousSessionId = $failedResponse->getSession()->getId();
        $response = $this->actingAs($this->owner)->put('/account/password', [
            'current_password' => 'password',
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('replacement-password', $this->owner->refresh()->password));
        $this->assertNotSame($previousSessionId, $response->getSession()->getId());
        $this->assertNotNull($this->owner->refresh()->getRememberToken());
    }

    public function test_sixth_failed_login_within_a_minute_is_throttled(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', [
                'email' => 'unknown@example.test',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $response = $this->post('/login', [
            'email' => 'unknown@example.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $message = $response->getSession()->get('errors')->first('email');
        $this->assertMatchesRegularExpression('/Too many login attempts.+\d+ seconds/i', $message);
    }

    private function makeUser(Role $role, string $email, bool $active = true): User
    {
        return User::create([
            'name' => str($role->value)->replace('_', ' ')->title()->toString(),
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'branch_id' => $this->branch->id,
            'is_active' => $active,
        ]);
    }
}
