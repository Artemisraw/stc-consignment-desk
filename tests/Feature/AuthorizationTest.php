<?php

namespace Tests\Feature;

use App\Enums\ConsignmentStatus;
use App\Enums\Role;
use App\Enums\TripDirection;
use App\Enums\TripStatus;
use App\Models\Branch;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Who may do what, branch by branch. Clerks, managers and owners work across all branches;
 * a branch agent works only with cargo to or from their own branch and cannot load or cancel trips.
 */
class AuthorizationTest extends TestCase
{
    private Collection $branches;
    private User $wajirAgent;
    private User $manderaAgent;
    private User $counter;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->branches = collect(['Nairobi', 'Wajir', 'Elwak', 'Wargadud', 'Lafey', 'Rhamu', 'Mandera'])
            ->mapWithKeys(fn ($name, $index) => [$name => Branch::create(['name' => $name, 'route_order' => $index + 1])]);
        $this->wajirAgent = $this->user(Role::BranchAgent, 'Wajir');
        $this->manderaAgent = $this->user(Role::BranchAgent, 'Mandera');
        $this->counter = $this->user(Role::Clerk, 'Nairobi'); // books the fixture consignments
    }

    public function test_a_branch_agent_sees_only_cargo_to_or_from_their_branch(): void
    {
        $toMandera = $this->consignment('Nairobi', 'Mandera');
        $toWajir = $this->consignment('Nairobi', 'Wajir');
        $fromWajir = $this->consignment('Wajir', 'Nairobi');

        $this->actingAs($this->wajirAgent)->get('/consignments')
            ->assertInertia(fn (Assert $page) => $page->has('consignments.data', 2)
                ->where('consignments.data', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all() === [$toWajir->id, $fromWajir->id]));
        $this->actingAs($this->wajirAgent)->get("/consignments/{$toWajir->id}")->assertOk();
        $this->actingAs($this->wajirAgent)->get("/consignments/{$toMandera->id}")->assertForbidden();
        $this->actingAs($this->wajirAgent)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('stats.waiting', 2)->where('stats.outstanding', 4000)->has('consignments', 2));

        $clerk = $this->user(Role::Clerk, 'Nairobi');
        $this->actingAs($clerk)->get('/consignments')->assertInertia(fn (Assert $page) => $page->has('consignments.data', 3));
        $this->actingAs($clerk)->get("/consignments/{$toMandera->id}")->assertOk();
    }

    public function test_a_branch_agent_books_only_at_their_own_counter_and_the_form_starts_there(): void
    {
        $this->actingAs($this->wajirAgent)->get('/consignments/create')
            ->assertInertia(fn (Assert $page) => $page->where('defaultOriginId', $this->branches['Wajir']->id)->where('lockOrigin', true));

        $this->actingAs($this->wajirAgent)->post('/consignments', $this->payload('Nairobi', 'Mandera'))->assertSessionHasErrors('origin_branch_id');
        $this->actingAs($this->wajirAgent)->post('/consignments', $this->payload('Wajir', 'Mandera'))->assertSessionHasNoErrors();
        $this->assertSame(1, Consignment::count());

        // Clerks default to their own branch but can book for any.
        $clerk = $this->user(Role::Clerk, 'Elwak');
        $this->actingAs($clerk)->get('/consignments/create')
            ->assertInertia(fn (Assert $page) => $page->where('defaultOriginId', $this->branches['Elwak']->id)->where('lockOrigin', false));
        $this->actingAs($clerk)->post('/consignments', $this->payload('Nairobi', 'Mandera'))->assertSessionHasNoErrors();
    }

    public function test_corrections_happen_at_the_booking_branch_and_payments_at_either_end(): void
    {
        $bookedInNairobi = $this->consignment('Nairobi', 'Wajir');
        $bookedInWajir = $this->consignment('Wajir', 'Mandera');

        $this->actingAs($this->wajirAgent)->get("/consignments/{$bookedInNairobi->id}/edit")->assertForbidden();
        $this->actingAs($this->wajirAgent)->put("/consignments/{$bookedInNairobi->id}", $this->payload('Nairobi', 'Wajir'))->assertForbidden();
        $this->actingAs($this->wajirAgent)->post("/consignments/{$bookedInNairobi->id}/cancel", ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->wajirAgent)->put("/consignments/{$bookedInWajir->id}", $this->payload('Wajir', 'Mandera'))->assertSessionHasNoErrors();

        // Money can be taken at the destination too, but not by a branch with no part in the journey.
        $this->actingAs($this->wajirAgent)->post("/consignments/{$bookedInNairobi->id}/payments", ['amount' => 500, 'method' => 'cash'])->assertSessionHasNoErrors();
        $this->actingAs($this->manderaAgent)->post("/consignments/{$bookedInNairobi->id}/payments", ['amount' => 500, 'method' => 'cash'])->assertForbidden();
        $this->assertSame(500, $bookedInNairobi->refresh()->paid_amount);

        $this->actingAs($this->wajirAgent)->get("/consignments/{$bookedInNairobi->id}")
            ->assertInertia(fn (Assert $page) => $page->where('can.edit', false)->where('can.cancel', false)->where('can.pay', true)->where('can.deliver', true));
    }

    public function test_cargo_is_handed_over_only_at_its_destination(): void
    {
        $forMandera = $this->consignment('Nairobi', 'Mandera', ConsignmentStatus::Arrived);

        $this->actingAs($this->wajirAgent)->post("/consignments/{$forMandera->id}/deliver")->assertForbidden();
        $this->assertSame(ConsignmentStatus::Arrived, $forMandera->refresh()->status);

        $this->actingAs($this->manderaAgent)->post("/consignments/{$forMandera->id}/deliver")->assertSessionHasNoErrors();
        $this->assertSame(ConsignmentStatus::Delivered, $forMandera->refresh()->status);
    }

    public function test_branch_agents_cannot_load_or_cancel_trips(): void
    {
        $waiting = $this->consignment('Wajir', 'Mandera');
        [$vehicle, $driver] = $this->fleet();

        $this->actingAs($this->wajirAgent)->post('/trips', ['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'consignment_ids' => [$waiting->id]])
            ->assertForbidden();
        $this->assertSame(0, Trip::count());
        $this->actingAs($this->wajirAgent)->get('/trips')
            ->assertInertia(fn (Assert $page) => $page->where('canDispatch', false)->has('waiting', 0)->has('vehicles', 0));

        $trip = $this->trip('Nairobi', [$this->consignment('Nairobi', 'Wajir')]);
        $this->actingAs($this->wajirAgent)->post("/trips/{$trip->id}/cancel")->assertForbidden();
        $this->assertSame(TripStatus::InTransit, $trip->refresh()->status);
    }

    public function test_a_branch_agent_records_only_the_truck_reaching_their_branch(): void
    {
        $toWajir = $this->consignment('Nairobi', 'Wajir');
        $toMandera = $this->consignment('Nairobi', 'Mandera');
        $trip = $this->trip('Nairobi', [$toWajir, $toMandera]);

        // Next stop is Wajir: Mandera cannot record it, Wajir can.
        $this->actingAs($this->manderaAgent)->get('/trips')
            ->assertInertia(fn (Assert $page) => $page->where('trips.0.can.advance', false)
                ->has('trips.0.consignments', 1)->where('trips.0.consignments.0.id', $toMandera->id));
        $this->actingAs($this->manderaAgent)->post("/trips/{$trip->id}/advance")->assertForbidden();

        $this->actingAs($this->wajirAgent)->get('/trips')->assertInertia(fn (Assert $page) => $page->where('trips.0.can.advance', true));
        $this->actingAs($this->wajirAgent)->post("/trips/{$trip->id}/advance")->assertSessionHasNoErrors();
        $this->assertSame($this->branches['Wajir']->id, $trip->refresh()->current_branch_id);
        $this->assertSame(ConsignmentStatus::Arrived, $toWajir->refresh()->status);

        // Now the next stop is Elwak, so Wajir is done.
        $this->actingAs($this->wajirAgent)->post("/trips/{$trip->id}/advance")->assertForbidden();
        $this->assertSame($this->branches['Wajir']->id, $trip->refresh()->current_branch_id);

        // A clerk can move it on from anywhere.
        $this->actingAs($this->user(Role::Clerk, 'Nairobi'))->post("/trips/{$trip->id}/advance")->assertSessionHasNoErrors();
        $this->assertSame($this->branches['Elwak']->id, $trip->refresh()->current_branch_id);
    }

    public function test_a_branch_agent_sees_only_trips_through_their_branch_and_only_their_cargo_on_the_manifest(): void
    {
        $toWajir = $this->consignment('Nairobi', 'Wajir');
        $toElwak = $this->consignment('Nairobi', 'Elwak');
        $outbound = $this->trip('Nairobi', [$toWajir, $toElwak]);
        $inboundFromElwak = $this->trip('Elwak', [$this->consignment('Elwak', 'Nairobi')], TripDirection::Inbound);
        $manderaInbound = $this->trip('Mandera', [$this->consignment('Mandera', 'Rhamu')], TripDirection::Inbound);

        // Mandera to Rhamu ends at Rhamu, so it reaches none of Nairobi, Wajir or Elwak.
        $nairobiAgent = $this->user(Role::BranchAgent, 'Nairobi');
        $this->assertSeesTrips($nairobiAgent, [$outbound, $inboundFromElwak]);
        $this->assertSeesTrips($this->wajirAgent, [$outbound, $inboundFromElwak]); // passes through Wajir on the way to Nairobi
        $this->assertSeesTrips($this->user(Role::BranchAgent, 'Elwak'), [$outbound, $inboundFromElwak]);
        $this->assertSeesTrips($this->user(Role::BranchAgent, 'Rhamu'), [$manderaInbound]);
        $this->assertSeesTrips($this->manderaAgent, [$manderaInbound]); // Nairobi's truck stops at Elwak

        $this->actingAs($this->wajirAgent)->get("/trips/{$outbound->id}/manifest")
            ->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.tracking_number', $toWajir->tracking_number)->where('totals.balance', 2000));
        $this->actingAs($nairobiAgent)->get("/trips/{$manderaInbound->id}/manifest")->assertForbidden();
        $this->actingAs($this->user(Role::Manager, 'Nairobi'))->get("/trips/{$outbound->id}/manifest")
            ->assertInertia(fn (Assert $page) => $page->has('rows', 2));
    }

    private function assertSeesTrips(User $user, array $expected): void
    {
        $this->actingAs($user)->get('/trips')->assertInertia(fn (Assert $page) => $page->where('trips',
            fn ($trips) => collect($trips)->pluck('id')->sort()->values()->all() === collect($expected)->pluck('id')->sort()->values()->all()));
    }

    public function test_clerks_managers_and_owners_work_across_every_branch(): void
    {
        foreach ([Role::Clerk, Role::Manager, Role::Owner] as $role) {
            $user = $this->user($role, 'Nairobi');
            $remote = $this->consignment('Wajir', 'Mandera');
            $arrived = $this->consignment('Elwak', 'Mandera', ConsignmentStatus::Arrived);

            $this->actingAs($user)->get("/consignments/{$remote->id}")->assertOk();
            $this->actingAs($user)->put("/consignments/{$remote->id}", $this->payload('Wajir', 'Mandera'))->assertSessionHasNoErrors();
            $this->actingAs($user)->post("/consignments/{$remote->id}/payments", ['amount' => 100, 'method' => 'cash'])->assertSessionHasNoErrors();
            $this->actingAs($user)->post("/consignments/{$arrived->id}/deliver")->assertSessionHasNoErrors();
            $this->assertSame(ConsignmentStatus::Delivered, $arrived->refresh()->status, $role->value);
        }
    }

    private function user(Role $role, string $branch): User
    {
        $n = ++$this->sequence;

        return User::create([
            'name' => "{$role->value} {$branch} {$n}", 'email' => "{$role->value}-{$n}@example.test", 'password' => 'password',
            'role' => $role, 'branch_id' => $this->branches[$branch]->id,
        ]);
    }

    private function consignment(string $from, string $to, ConsignmentStatus $status = ConsignmentStatus::Received): Consignment
    {
        $consignment = Consignment::create([
            'tracking_number' => 'STC-AUTH-'.(++$this->sequence), 'origin_branch_id' => $this->branches[$from]->id,
            'destination_branch_id' => $this->branches[$to]->id, 'receiver_id' => Customer::create(['name' => 'Receiver', 'phone' => '0700 000 000'])->id,
            'created_by' => $this->counter->id, 'status' => $status, 'total_amount' => 2000, 'paid_amount' => 0,
        ]);
        $consignment->items()->create(['quantity' => 1, 'description' => 'Parcel', 'amount' => 2000]);

        return $consignment;
    }

    private function payload(string $from, string $to): array
    {
        return [
            'origin_branch_id' => $this->branches[$from]->id, 'destination_branch_id' => $this->branches[$to]->id,
            'sender' => ['name' => ''], 'receiver' => ['name' => 'Receiver', 'phone' => '0700 000 000'],
            'items' => [['quantity' => 1, 'description' => 'Parcel', 'amount' => 2000]], 'notes' => null,
        ];
    }

    private function fleet(): array
    {
        $n = ++$this->sequence;
        $driver = Driver::create(['name' => "Driver {$n}", 'licence_number' => "DL-{$n}"]);

        return [Vehicle::create(['registration_number' => "KAA {$n}A", 'default_driver_id' => $driver->id]), $driver];
    }

    /** A trip already on the road, set up directly so the test does not depend on who may dispatch. */
    private function trip(string $from, array $consignments, TripDirection $direction = TripDirection::Outbound): Trip
    {
        [$vehicle, $driver] = $this->fleet();
        $trip = Trip::create([
            'trip_number' => 'TRIP-AUTH-'.(++$this->sequence), 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id,
            'origin_branch_id' => $this->branches[$from]->id, 'current_branch_id' => $this->branches[$from]->id,
            'direction' => $direction, 'status' => TripStatus::InTransit, 'departed_at' => now(),
        ]);
        $trip->consignments()->attach(collect($consignments)->pluck('id'));
        Consignment::whereIn('id', collect($consignments)->pluck('id'))->update(['status' => ConsignmentStatus::InTransit->value]);

        return $trip;
    }
}
