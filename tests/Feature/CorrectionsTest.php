<?php

namespace Tests\Feature;

use App\Enums\ConsignmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CorrectionsTest extends TestCase
{
    private Collection $branches;
    private User $clerk;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->branches = collect(['Nairobi', 'Wajir', 'Elwak', 'Wargadud', 'Lafey', 'Rhamu', 'Mandera'])
            ->map(fn ($name, $index) => Branch::create(['name' => $name, 'route_order' => $index + 1]));
        $this->clerk = $this->user(Role::Clerk, 'clerk@example.test');
        $this->manager = $this->user(Role::Manager, 'manager@example.test');
    }

    public function test_a_received_consignment_can_be_corrected_and_the_change_is_recorded(): void
    {
        $consignment = $this->consignment(total: 2000, paid: 500);

        $this->actingAs($this->clerk)->put("/consignments/{$consignment->id}", $this->payload([
            'destination_branch_id' => $this->branches[2]->id,
            'receiver' => ['name' => 'Corrected Name', 'phone' => '0711 111 111'],
            'items' => [
                ['quantity' => 2, 'description' => 'Boxes', 'amount' => 1500],
                ['quantity' => 1, 'description' => 'Sack', 'amount' => 700],
            ],
        ]))->assertSessionHasNoErrors()->assertRedirect("/consignments/{$consignment->id}");

        $consignment->refresh()->load('items', 'receiver');
        $this->assertSame(2200, $consignment->total_amount);
        $this->assertSame(500, $consignment->paid_amount);
        $this->assertSame(1700, $consignment->balance);
        $this->assertSame($this->branches[2]->id, $consignment->destination_branch_id);
        $this->assertSame('Corrected Name', $consignment->receiver->name);
        $this->assertCount(2, $consignment->items);
        $event = $consignment->trackingEvents()->latest('id')->first();
        $this->assertEquals(['previous_total' => 2000, 'total' => 2200], $event->metadata); // MySQL JSON does not keep key order
    }

    public function test_a_correction_cannot_bring_the_total_below_the_amount_paid(): void
    {
        $consignment = $this->consignment(total: 2000, paid: 1500);

        $this->actingAs($this->clerk)->put("/consignments/{$consignment->id}", $this->payload([
            'items' => [['quantity' => 1, 'description' => 'Parcel', 'amount' => 1000]],
        ]))->assertSessionHasErrors('items');

        $this->assertSame(2000, $consignment->refresh()->total_amount);
    }

    public function test_a_loaded_consignment_cannot_be_edited_or_cancelled(): void
    {
        $consignment = $this->consignment(status: ConsignmentStatus::InTransit);

        $this->actingAs($this->clerk)->put("/consignments/{$consignment->id}", $this->payload())->assertSessionHasErrors('consignment');
        $this->actingAs($this->clerk)->post("/consignments/{$consignment->id}/cancel", ['reason' => 'Mistake'])->assertSessionHasErrors('consignment');
        $this->actingAs($this->clerk)->get("/consignments/{$consignment->id}/edit")->assertRedirect("/consignments/{$consignment->id}");
    }

    public function test_cancelling_an_unpaid_consignment_removes_it_from_loading_and_balances(): void
    {
        $consignment = $this->consignment(total: 3000);
        [$driver, $vehicle] = $this->fleetPair();

        $this->actingAs($this->clerk)->post("/consignments/{$consignment->id}/cancel", ['reason' => 'Customer changed their mind'])
            ->assertSessionHasNoErrors();

        $consignment->refresh();
        $this->assertSame(ConsignmentStatus::Cancelled, $consignment->status);
        $this->assertSame('Customer changed their mind', $consignment->cancellation_reason);
        $this->assertSame(0, $consignment->balance);
        $this->assertDatabaseHas('tracking_events', ['consignment_id' => $consignment->id, 'status' => 'cancelled']);

        $this->actingAs($this->clerk)->post("/consignments/{$consignment->id}/payments", ['amount' => 100, 'method' => 'cash'])
            ->assertSessionHasErrors('amount');
        $this->actingAs($this->clerk)->post('/trips', ['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'consignment_ids' => [$consignment->id]])
            ->assertSessionHasErrors('consignment_ids');
        $this->actingAs($this->manager)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('stats.outstanding', 0));
    }

    public function test_a_paid_consignment_needs_its_payment_reversed_before_it_can_be_cancelled(): void
    {
        $consignment = $this->consignment(total: 2000, paid: 800);
        $payment = $consignment->payments()->firstOrFail();

        $this->actingAs($this->clerk)->post("/consignments/{$consignment->id}/cancel", ['reason' => 'Duplicate'])
            ->assertSessionHasErrors('consignment');

        $this->actingAs($this->clerk)->post("/consignments/{$consignment->id}/payments/{$payment->id}/reverse", ['reason' => 'Refund'])
            ->assertForbidden();

        $this->actingAs($this->manager)->post("/consignments/{$consignment->id}/payments/{$payment->id}/reverse", ['reason' => 'Refunded at counter'])
            ->assertSessionHasNoErrors();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Reversed, $payment->status);
        $this->assertSame($this->manager->id, $payment->reversed_by);
        $this->assertSame('Refunded at counter', $payment->reversal_reason);
        $this->assertSame(0, $consignment->refresh()->paid_amount);
        $this->assertPaidMatchesCompletedPayments($consignment);

        $this->actingAs($this->manager)->post("/consignments/{$consignment->id}/payments/{$payment->id}/reverse", ['reason' => 'Again'])
            ->assertSessionHasErrors('payment');
        $this->assertSame(0, $consignment->refresh()->paid_amount);

        $this->actingAs($this->clerk)->post("/consignments/{$consignment->id}/cancel", ['reason' => 'Duplicate'])
            ->assertSessionHasNoErrors();
        $this->assertSame(ConsignmentStatus::Cancelled, $consignment->refresh()->status);
    }

    public function test_payments_on_a_delivered_consignment_cannot_be_reversed(): void
    {
        $consignment = $this->consignment(status: ConsignmentStatus::Delivered, total: 2000, paid: 2000);
        $payment = $consignment->payments()->firstOrFail();

        $this->actingAs($this->manager)->post("/consignments/{$consignment->id}/payments/{$payment->id}/reverse", ['reason' => 'Oops'])
            ->assertSessionHasErrors('payment');
        $this->assertSame(PaymentStatus::Completed, $payment->refresh()->status);
    }

    public function test_a_payment_cannot_be_reversed_through_another_consignment(): void
    {
        $first = $this->consignment(total: 2000, paid: 500, tracking: 'STC-1');
        $second = $this->consignment(total: 2000, paid: 500, tracking: 'STC-2');
        $payment = $second->payments()->firstOrFail();

        $this->actingAs($this->manager)->post("/consignments/{$first->id}/payments/{$payment->id}/reverse", ['reason' => 'Wrong'])
            ->assertNotFound();
        $this->assertSame(500, $second->refresh()->paid_amount);
    }

    public function test_a_trip_can_be_cancelled_before_its_first_stop_only(): void
    {
        [$driver, $vehicle] = $this->fleetPair();
        $consignment = $this->consignment();

        $this->actingAs($this->clerk)->post('/trips', ['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'consignment_ids' => [$consignment->id]])
            ->assertSessionHasNoErrors();
        $trip = Trip::firstOrFail();

        $this->actingAs($this->clerk)->post("/trips/{$trip->id}/cancel")->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $trip->refresh()->status->value);
        $this->assertSame(ConsignmentStatus::Received, $consignment->refresh()->status);
        $this->assertSame('available', $vehicle->refresh()->operational_status);
        $this->assertDatabaseHas('tracking_events', ['consignment_id' => $consignment->id, 'description' => 'Trip cancelled; back at Nairobi awaiting loading.']);

        // The consignment can be loaded again, and a trip that has moved cannot be cancelled.
        $this->actingAs($this->clerk)->post('/trips', ['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'consignment_ids' => [$consignment->id]])
            ->assertSessionHasNoErrors();
        $second = Trip::latest('id')->firstOrFail();
        $this->actingAs($this->clerk)->post("/trips/{$second->id}/advance")->assertSessionHasNoErrors();
        $this->actingAs($this->clerk)->post("/trips/{$second->id}/cancel")->assertSessionHasErrors('trip');
        $this->assertSame('in_transit', $second->refresh()->status->value);
    }

    private function assertPaidMatchesCompletedPayments(Consignment $consignment): void
    {
        $completed = (int) Payment::where('consignment_id', $consignment->id)->where('status', PaymentStatus::Completed->value)->sum('amount');
        $this->assertSame($completed, $consignment->refresh()->paid_amount);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'origin_branch_id' => $this->branches[0]->id,
            'destination_branch_id' => $this->branches[6]->id,
            'sender' => ['name' => ''],
            'receiver' => ['name' => 'Receiver', 'phone' => '0700 000 000'],
            'items' => [['quantity' => 1, 'description' => 'Parcel', 'amount' => 2000]],
            'notes' => null,
        ], $overrides);
    }

    private function user(Role $role, string $email): User
    {
        return User::create(['name' => ucfirst($role->value), 'email' => $email, 'password' => 'password', 'role' => $role, 'branch_id' => $this->branches[0]->id]);
    }

    private function fleetPair(): array
    {
        $driver = Driver::create(['name' => 'Driver', 'licence_number' => 'DL-1']);

        return [$driver, Vehicle::create(['registration_number' => 'KAA 100A', 'default_driver_id' => $driver->id])];
    }

    private function consignment(ConsignmentStatus $status = ConsignmentStatus::Received, int $total = 2000, int $paid = 0, string $tracking = 'STC-TEST'): Consignment
    {
        $receiver = Customer::create(['name' => 'Receiver', 'phone' => '0700 000 000']);
        $consignment = Consignment::create([
            'tracking_number' => $tracking, 'origin_branch_id' => $this->branches[0]->id,
            'destination_branch_id' => $this->branches[6]->id, 'receiver_id' => $receiver->id,
            'created_by' => $this->clerk->id, 'status' => $status, 'total_amount' => $total, 'paid_amount' => $paid,
        ]);
        $consignment->items()->create(['quantity' => 1, 'description' => 'Parcel', 'amount' => $total]);
        if ($paid > 0) {
            $consignment->payments()->create([
                'recorded_by' => $this->clerk->id, 'amount' => $paid, 'method' => PaymentMethod::Cash,
                'status' => PaymentStatus::Completed, 'reference' => "CASH-{$tracking}", 'paid_at' => now(),
            ]);
        }

        return $consignment;
    }
}
