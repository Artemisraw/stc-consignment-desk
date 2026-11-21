<?php

namespace Tests\Feature;

use App\Enums\ConsignmentStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CourierFlowsTest extends TestCase
{
    private User $owner;
    private $branches;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->branches = collect(['Nairobi', 'Wajir', 'Elwak', 'Wargadud', 'Lafey', 'Rhamu', 'Mandera'])
            ->map(fn ($name, $index) => Branch::create(['name' => $name, 'route_order' => $index + 1]));
        $this->owner = User::create([
            'name' => 'Test Owner',
            'email' => 'owner@example.test',
            'password' => Hash::make('password'),
            'role' => Role::Owner,
            'branch_id' => $this->branches->first()->id,
        ]);
    }

    public function test_staff_can_create_a_consignment_with_integer_money_and_initial_tracking_event(): void
    {
        $response = $this->actingAs($this->owner)->post('/consignments', [
            'origin_branch_id' => $this->branches[0]->id,
            'destination_branch_id' => $this->branches[6]->id,
            'sender' => ['name' => 'Counter sale'],
            'receiver' => ['name' => 'Amina Yusuf', 'phone' => '0712 000 000'],
            'items' => [
                ['quantity' => 2, 'description' => 'Boxes', 'amount' => 2500],
                ['quantity' => 1, 'description' => 'Parcel', 'amount' => 1200],
            ],
            'paid_amount' => 1000,
        ]);

        $consignment = Consignment::firstOrFail();
        $response->assertRedirect("/consignments/{$consignment->id}");
        $this->assertSame(3700, $consignment->total_amount);
        $this->assertSame(2700, $consignment->balance);
        $this->assertDatabaseCount('tracking_events', 1);
        $this->assertDatabaseHas('payments', ['consignment_id' => $consignment->id, 'amount' => 1000]);
    }

    public function test_a_consignment_cannot_be_loaded_on_two_active_trips(): void
    {
        [$driverOne, $vehicleOne] = $this->fleetPair('DL-1', 'KAA 100A');
        [$driverTwo, $vehicleTwo] = $this->fleetPair('DL-2', 'KBB 200B');
        $consignment = $this->receivedConsignment();

        $this->actingAs($this->owner)->post('/trips', [
            'vehicle_id' => $vehicleOne->id, 'driver_id' => $driverOne->id, 'consignment_ids' => [$consignment->id],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->post('/trips', [
            'vehicle_id' => $vehicleTwo->id, 'driver_id' => $driverTwo->id, 'consignment_ids' => [$consignment->id],
        ])->assertSessionHasErrors('consignment_ids');

        $this->assertDatabaseCount('trips', 1);
        $this->assertDatabaseCount('trip_consignments', 1);
        $this->assertSame(ConsignmentStatus::InTransit, $consignment->refresh()->status);
    }

    public function test_handover_collects_the_balance_and_records_the_delivered_event(): void
    {
        $consignment = $this->receivedConsignment(status: ConsignmentStatus::Arrived, paid: 500);

        $this->actingAs($this->owner)->post("/consignments/{$consignment->id}/deliver")
            ->assertSessionHasNoErrors();

        $consignment->refresh();
        $this->assertSame(ConsignmentStatus::Delivered, $consignment->status);
        $this->assertSame(0, $consignment->balance);
        $this->assertDatabaseHas('payments', ['consignment_id' => $consignment->id, 'amount' => 1500, 'method' => 'balance_on_delivery']);
        $this->assertDatabaseHas('tracking_events', ['consignment_id' => $consignment->id, 'status' => 'delivered']);
    }

    public function test_public_tracking_does_not_require_authentication(): void
    {
        $consignment = $this->receivedConsignment();

        $this->get('/?tracking='.$consignment->tracking_number)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Tracking/Public')
                ->where('consignment.tracking_number', $consignment->tracking_number));
    }

    public function test_owner_can_render_each_operations_module(): void
    {
        foreach (['/dashboard', '/consignments', '/consignments/create', '/fleet', '/trips', '/reports'] as $uri) {
            $this->actingAs($this->owner)->get($uri)->assertOk();
        }
    }

    private function fleetPair(string $licence, string $plate): array
    {
        $driver = Driver::create(['name' => $licence, 'licence_number' => $licence]);
        $vehicle = Vehicle::create(['registration_number' => $plate, 'default_driver_id' => $driver->id]);

        return [$driver, $vehicle];
    }

    private function receivedConsignment(ConsignmentStatus $status = ConsignmentStatus::Received, int $paid = 0): Consignment
    {
        $receiver = Customer::create(['name' => 'Receiver', 'phone' => '0700 000 000']);
        $consignment = Consignment::create([
            'tracking_number' => 'STC-TEST-1', 'origin_branch_id' => $this->branches[0]->id,
            'destination_branch_id' => $this->branches[6]->id, 'receiver_id' => $receiver->id,
            'created_by' => $this->owner->id, 'status' => $status, 'total_amount' => 2000, 'paid_amount' => $paid,
        ]);
        $consignment->items()->create(['quantity' => 1, 'description' => 'Parcel', 'amount' => 2000]);
        $consignment->trackingEvents()->create([
            'branch_id' => $this->branches[0]->id, 'created_by' => $this->owner->id,
            'status' => $status, 'description' => 'Test event', 'occurred_at' => now(),
        ]);

        return $consignment;
    }
}
