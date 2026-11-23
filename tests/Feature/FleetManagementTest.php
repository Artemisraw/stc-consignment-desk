<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TripStatus;
use App\Models\Branch;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FleetManagementTest extends TestCase
{
    private Branch $branch;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->branch = Branch::create(['name' => 'Nairobi', 'route_order' => 1]);
        $this->manager = $this->makeUser(Role::Manager, 'manager@example.test');
    }

    public function test_manager_can_edit_a_vehicle_and_a_driver(): void
    {
        $driver = Driver::create(['name' => 'Abdi Driver', 'phone' => '0700 000 001', 'licence_number' => 'DL-001']);
        $vehicle = Vehicle::create([
            'registration_number' => 'KAA 100A',
            'make' => 'Isuzu',
            'model' => 'NPR',
            'capacity_kg' => 5000,
            'default_driver_id' => $driver->id,
        ]);

        $this->actingAs($this->manager)->put("/fleet/vehicles/{$vehicle->id}", [
            'registration_number' => 'KBB 200B',
            'make' => 'Mitsubishi',
            'model' => 'Fuso',
            'capacity_kg' => 7000,
            'default_driver_id' => $driver->id,
        ])->assertRedirect('/fleet')->assertSessionHasNoErrors();

        $this->actingAs($this->manager)->put("/fleet/drivers/{$driver->id}", [
            'name' => 'Abdi Mohamed',
            'phone' => '0711 111 111',
            'licence_number' => 'DL-UPDATED',
        ])->assertRedirect('/fleet')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'registration_number' => 'KBB 200B',
            'make' => 'Mitsubishi',
            'model' => 'Fuso',
            'capacity_kg' => 7000,
        ]);
        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'name' => 'Abdi Mohamed',
            'phone' => '0711 111 111',
            'licence_number' => 'DL-UPDATED',
        ]);
    }

    public function test_duplicate_registration_and_licence_numbers_are_rejected(): void
    {
        $firstDriver = Driver::create(['name' => 'First Driver', 'licence_number' => 'DL-001']);
        $secondDriver = Driver::create(['name' => 'Second Driver', 'licence_number' => 'DL-002']);
        $firstVehicle = Vehicle::create(['registration_number' => 'KAA 100A']);
        $secondVehicle = Vehicle::create(['registration_number' => 'KBB 200B']);

        $this->actingAs($this->manager)->put("/fleet/vehicles/{$secondVehicle->id}", [
            'registration_number' => $firstVehicle->registration_number,
            'make' => null,
            'model' => null,
            'capacity_kg' => null,
            'default_driver_id' => null,
        ])->assertSessionHasErrors('registration_number');

        $this->actingAs($this->manager)->put("/fleet/drivers/{$secondDriver->id}", [
            'name' => $secondDriver->name,
            'phone' => null,
            'licence_number' => $firstDriver->licence_number,
        ])->assertSessionHasErrors('licence_number');

        $this->assertSame('KBB 200B', $secondVehicle->refresh()->registration_number);
        $this->assertSame('DL-002', $secondDriver->refresh()->licence_number);
    }

    public function test_clerk_and_branch_agent_cannot_create_edit_or_deactivate_fleet_records(): void
    {
        $driver = Driver::create(['name' => 'Protected Driver', 'licence_number' => 'DL-PROTECTED']);
        $vehicle = Vehicle::create(['registration_number' => 'KCC 300C']);

        foreach ([Role::Clerk, Role::BranchAgent] as $role) {
            $actor = $this->makeUser($role, $role->value.'@example.test');

            $this->actingAs($actor)->post('/fleet/vehicles', [
                'registration_number' => "KDD-{$role->value}",
            ])->assertForbidden();
            $this->actingAs($actor)->get("/fleet/vehicles/{$vehicle->id}/edit")->assertForbidden();
            $this->actingAs($actor)->put("/fleet/vehicles/{$vehicle->id}", [
                'registration_number' => $vehicle->registration_number,
            ])->assertForbidden();
            $this->actingAs($actor)->patch("/fleet/vehicles/{$vehicle->id}/toggle")->assertForbidden();

            $this->actingAs($actor)->post('/fleet/drivers', [
                'name' => 'Blocked Driver',
                'licence_number' => "DL-{$role->value}",
            ])->assertForbidden();
            $this->actingAs($actor)->get("/fleet/drivers/{$driver->id}/edit")->assertForbidden();
            $this->actingAs($actor)->put("/fleet/drivers/{$driver->id}", [
                'name' => $driver->name,
                'licence_number' => $driver->licence_number,
            ])->assertForbidden();
            $this->actingAs($actor)->patch("/fleet/drivers/{$driver->id}/toggle")->assertForbidden();
        }
    }

    public function test_driver_on_an_active_trip_cannot_be_deactivated(): void
    {
        $driver = Driver::create(['name' => 'Active Driver', 'licence_number' => 'DL-ACTIVE']);
        $vehicle = Vehicle::create(['registration_number' => 'KDD 400D']);
        Trip::create([
            'trip_number' => 'TRIP-ACTIVE',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'origin_branch_id' => $this->branch->id,
            'current_branch_id' => $this->branch->id,
            'status' => TripStatus::Loading,
        ]);

        $this->actingAs($this->manager)->patch("/fleet/drivers/{$driver->id}/toggle")
            ->assertSessionHasErrors('driver');

        $this->assertTrue($driver->refresh()->is_active);
    }

    public function test_deactivated_driver_stays_on_fleet_page_and_is_excluded_from_trip_dispatch(): void
    {
        $inactiveDriver = Driver::create(['name' => 'Leaving Driver', 'licence_number' => 'DL-LEAVING']);
        $activeDriver = Driver::create(['name' => 'Active Driver', 'licence_number' => 'DL-AVAILABLE']);

        $this->actingAs($this->manager)->patch("/fleet/drivers/{$inactiveDriver->id}/toggle")
            ->assertSessionHasNoErrors();

        $this->actingAs($this->manager)->get('/fleet')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Fleet/Index')
                ->where('drivers', fn ($drivers) => collect($drivers)->contains(
                    fn ($driver) => $driver['id'] === $inactiveDriver->id && $driver['is_active'] === false
                )));

        $this->actingAs($this->manager)->get('/trips')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Trips/Index')
                ->has('drivers', 1)
                ->where('drivers.0.id', $activeDriver->id));
    }

    private function makeUser(Role $role, string $email): User
    {
        return User::create([
            'name' => str($role->value)->replace('_', ' ')->title()->toString(),
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
    }
}
