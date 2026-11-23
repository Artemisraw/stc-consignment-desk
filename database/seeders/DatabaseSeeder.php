<?php

namespace Database\Seeders;

use App\Enums\ConsignmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\TripStatus;
use App\Enums\VehicleStatus;
use App\Models\Branch;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $branches = collect(['Nairobi', 'Wajir', 'Elwak', 'Wargadud', 'Lafey', 'Rhamu', 'Mandera'])
            ->mapWithKeys(fn (string $name, int $index) => [$name => Branch::create(['name' => $name, 'route_order' => $index + 1])]);

        $owner = User::create([
            'name' => 'Hassan Mohamed', 'email' => 'owner@skytop.test', 'password' => Hash::make('password'),
            'role' => Role::Owner, 'branch_id' => $branches['Nairobi']->id,
            'is_active' => true,
        ]);
        User::create([
            'name' => 'Amina Clerk', 'email' => 'clerk@skytop.test', 'password' => Hash::make('password'),
            'role' => Role::Clerk, 'branch_id' => $branches['Nairobi']->id,
            'is_active' => true,
        ]);
        User::create([
            'name' => 'Abdi Manager', 'email' => 'manager@skytop.test', 'password' => Hash::make('password'),
            'role' => Role::Manager, 'branch_id' => $branches['Nairobi']->id,
            'is_active' => true,
        ]);

        $driverNames = ['Hassan K.', 'Abdi M.', 'Yusuf A.', 'Said O.', 'Ali D.', 'Omar H.', 'Mohamed N.', 'Dahir S.'];
        $drivers = collect($driverNames)->map(fn ($name, $i) => Driver::create([
            'name' => $name, 'phone' => '0712 00'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'licence_number' => 'DL-STC-'.($i + 1),
        ]));
        $plates = ['KDD 834A', 'KDA 553T', 'KCX 201M', 'KDG 118B', 'KCZ 970P', 'KDE 440J', 'KBY 316H', 'KDF 762C'];
        $vehicles = collect($plates)->map(fn ($plate, $i) => Vehicle::create([
            'registration_number' => $plate, 'make' => $i % 2 ? 'Isuzu' : 'Mitsubishi', 'model' => 'Cargo',
            'capacity_kg' => 7000, 'status' => $i > 5 ? VehicleStatus::Maintenance : VehicleStatus::Available,
            'default_driver_id' => $drivers[$i]->id,
        ]));

        $samples = [
            ['Fatuma B.', '0712 000 111', 'Mandera', 'Sacks of flour', 3, 15000, 15000, ConsignmentStatus::InTransit],
            ['Gamadu Shop', '0722 000 222', 'Rhamu', 'Boxes of goods', 5, 17250, 0, ConsignmentStatus::InTransit],
            ['Amina Y.', '0733 000 333', 'Mandera', 'Mixed goods', 4, 10000, 0, ConsignmentStatus::InTransit],
            ['Nimo T.', '0711 000 444', 'Mandera', 'Carton', 1, 2500, 2500, ConsignmentStatus::InTransit],
            ['Zeitun Shop', '0720 000 555', 'Elwak', 'Boxes of goods', 2, 3500, 0, ConsignmentStatus::InTransit],
            ['Ibrahim N.', '0701 000 666', 'Mandera', 'Parcel', 1, 1000, 1000, ConsignmentStatus::Received],
            ['Bishar A.', '0799 000 777', 'Wajir', 'Rolls of fabric', 6, 47700, 0, ConsignmentStatus::Received],
            ['Mohamed S.', '0710 000 888', 'Mandera', 'Back draw', 2, 1200, 1200, ConsignmentStatus::Received],
        ];
        $consignments = collect($samples)->map(function (array $sample, int $i) use ($branches, $owner) {
            [$name, $phone, $destination, $description, $quantity, $amount, $paid, $status] = $sample;
            $receiver = Customer::create(['name' => $name, 'phone' => $phone]);
            $consignment = Consignment::create([
                'tracking_number' => 'STC-'.(5152 + $i), 'origin_branch_id' => $branches['Nairobi']->id,
                'destination_branch_id' => $branches[$destination]->id, 'receiver_id' => $receiver->id,
                'created_by' => $owner->id, 'status' => $status, 'total_amount' => $amount, 'paid_amount' => $paid,
            ]);
            $consignment->items()->create(['quantity' => $quantity, 'description' => $description, 'amount' => $amount]);
            $consignment->trackingEvents()->create([
                'branch_id' => $branches['Nairobi']->id, 'created_by' => $owner->id,
                'status' => ConsignmentStatus::Received, 'description' => 'Received at Nairobi counter.', 'occurred_at' => now()->subDays(2),
            ]);
            if ($paid > 0) {
                $consignment->payments()->create([
                    'recorded_by' => $owner->id, 'branch_id' => $branches['Nairobi']->id, 'amount' => $paid, 'method' => PaymentMethod::Cash,
                    'status' => PaymentStatus::Completed, 'reference' => 'SEED-'.$consignment->id, 'paid_at' => now()->subDays(2),
                ]);
            }
            return $consignment;
        });

        $this->createSeedTrip('TRIP-0001', $vehicles[0], $drivers[0], $branches['Wajir'], $consignments->slice(2, 3), $branches, $owner);
        $this->createSeedTrip('TRIP-0002', $vehicles[1], $drivers[1], $branches['Lafey'], $consignments->slice(0, 2), $branches, $owner);
    }

    private function createSeedTrip(string $number, Vehicle $vehicle, Driver $driver, Branch $current, Collection $consignments, Collection $branches, User $user): void
    {
        $trip = Trip::create([
            'trip_number' => $number, 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id,
            'origin_branch_id' => $branches['Nairobi']->id, 'current_branch_id' => $current->id,
            'status' => TripStatus::InTransit, 'departed_at' => now()->subDay(),
        ]);
        $trip->consignments()->attach($consignments->pluck('id'));
        foreach ($consignments as $consignment) {
            $consignment->trackingEvents()->create([
                'branch_id' => $branches['Nairobi']->id, 'created_by' => $user->id,
                'status' => ConsignmentStatus::InTransit,
                'description' => "Loaded on {$vehicle->registration_number} and departed.", 'occurred_at' => now()->subDay(),
            ]);
            $consignment->trackingEvents()->create([
                'branch_id' => $current->id, 'created_by' => $user->id,
                'status' => ConsignmentStatus::InTransit,
                'description' => "Passed through {$current->name}.", 'occurred_at' => now()->subHours(2),
            ]);
        }
    }
}
