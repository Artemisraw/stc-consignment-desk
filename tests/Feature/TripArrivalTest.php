<?php

namespace Tests\Feature;

use App\Enums\ConsignmentStatus;
use App\Enums\Role;
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

/** "Confirm arrival at X" names the stop, so repeated or stale presses cannot move a truck an extra stop. */
class TripArrivalTest extends TestCase
{
    private Collection $branches;
    private User $clerk;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->branches = collect(['Nairobi', 'Wajir', 'Elwak', 'Wargadud', 'Lafey', 'Rhamu', 'Mandera'])
            ->mapWithKeys(fn ($name, $index) => [$name => Branch::create(['name' => $name, 'route_order' => $index + 1])]);
        $this->clerk = User::create(['name' => 'Clerk', 'email' => 'clerk@example.test', 'password' => 'password', 'role' => Role::Clerk, 'branch_id' => $this->branches['Nairobi']->id]);
    }

    public function test_the_trips_page_names_the_stop_each_truck_reaches_next(): void
    {
        $trip = $this->dispatch(['Wajir', 'Mandera']);

        $this->actingAs($this->clerk)->get('/trips')
            ->assertInertia(fn (Assert $page) => $page->where('trips.0.next_stop', ['id' => $this->branches['Wajir']->id, 'name' => 'Wajir']));

        $trip->update(['status' => TripStatus::Completed]);
        $this->actingAs($this->clerk)->get('/trips')->assertInertia(fn (Assert $page) => $page->where('trips.0.next_stop', null));
    }

    public function test_a_second_press_for_the_same_stop_does_not_move_the_truck_again(): void
    {
        $trip = $this->dispatch(['Wajir', 'Mandera']);
        $wajir = $this->branches['Wajir'];

        $this->actingAs($this->clerk)->post("/trips/{$trip->id}/advance", ['stop_id' => $wajir->id])->assertSessionHasNoErrors();
        $this->actingAs($this->clerk)->post("/trips/{$trip->id}/advance", ['stop_id' => $wajir->id])
            ->assertSessionHasErrors(['trip' => "{$trip->trip_number} has already been recorded at Wajir."]);

        $this->assertSame($wajir->id, $trip->refresh()->current_branch_id);
        $toWajir = Consignment::where('destination_branch_id', $wajir->id)->firstOrFail();
        $this->assertSame(ConsignmentStatus::Arrived, $toWajir->status);
        $this->assertSame(1, $toWajir->trackingEvents()->where('status', ConsignmentStatus::Arrived->value)->count());
    }

    public function test_a_press_from_a_stale_page_is_refused_once_the_truck_has_moved_on(): void
    {
        $trip = $this->dispatch(['Elwak', 'Mandera']);
        $staleStop = $this->branches['Wajir']->id; // what an hour-old page still shows

        $this->actingAs($this->clerk)->post("/trips/{$trip->id}/advance", ['stop_id' => $this->branches['Wajir']->id])->assertSessionHasNoErrors();
        $this->actingAs($this->clerk)->post("/trips/{$trip->id}/advance", ['stop_id' => $this->branches['Elwak']->id])->assertSessionHasNoErrors();

        $this->actingAs($this->clerk)->post("/trips/{$trip->id}/advance", ['stop_id' => $staleStop])
            ->assertSessionHasErrors(['trip' => "{$trip->trip_number} is now at Elwak. Refresh the page before recording an arrival."]);
        $this->assertSame($this->branches['Elwak']->id, $trip->refresh()->current_branch_id);
    }

    public function test_a_repeat_press_at_the_final_stop_reports_it_already_recorded(): void
    {
        $trip = $this->dispatch(['Wajir']);
        $wajir = $this->branches['Wajir'];

        $this->actingAs($this->clerk)->post("/trips/{$trip->id}/advance", ['stop_id' => $wajir->id])->assertSessionHasNoErrors();
        $this->assertSame(TripStatus::Completed, $trip->refresh()->status);

        $this->actingAs($this->clerk)->post("/trips/{$trip->id}/advance", ['stop_id' => $wajir->id])
            ->assertSessionHasErrors(['trip' => "{$trip->trip_number} has already been recorded at Wajir."]);
    }

    /** Dispatch from Nairobi through the real flow, with one consignment to each named branch. */
    private function dispatch(array $destinations): Trip
    {
        $ids = collect($destinations)->map(function (string $to) {
            $consignment = Consignment::create([
                'tracking_number' => 'STC-ARR-'.(++$this->sequence), 'origin_branch_id' => $this->branches['Nairobi']->id,
                'destination_branch_id' => $this->branches[$to]->id, 'receiver_id' => Customer::create(['name' => 'Receiver', 'phone' => '0700 000 000'])->id,
                'created_by' => $this->clerk->id, 'status' => ConsignmentStatus::Received, 'total_amount' => 1000, 'paid_amount' => 0,
            ]);
            $consignment->items()->create(['quantity' => 1, 'description' => 'Parcel', 'amount' => 1000]);

            return $consignment->id;
        })->all();
        $driver = Driver::create(['name' => 'Driver', 'licence_number' => 'DL-'.(++$this->sequence)]);
        $vehicle = Vehicle::create(['registration_number' => 'KAA '.$this->sequence.'A', 'default_driver_id' => $driver->id]);

        $this->actingAs($this->clerk)->post('/trips', ['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'consignment_ids' => $ids])
            ->assertSessionHasNoErrors();

        return Trip::latest('id')->firstOrFail();
    }
}
