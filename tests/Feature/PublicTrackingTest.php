<?php

namespace Tests\Feature;

use App\Enums\ConsignmentStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    public function test_public_tracking_exposes_only_customer_safe_fields(): void
    {
        $nairobi = Branch::create(['name' => 'Nairobi', 'route_order' => 1]);
        $mandera = Branch::create(['name' => 'Mandera', 'route_order' => 7]);
        $staff = User::create([
            'name' => 'Internal Staff',
            'email' => 'staff@example.test',
            'password' => 'password',
            'role' => Role::Clerk,
            'branch_id' => $nairobi->id,
            'is_active' => true,
        ]);
        $receiver = Customer::create(['name' => 'Amina Y.', 'phone' => '0700 000 000']);
        $consignment = Consignment::create([
            'tracking_number' => 'STC-PUBLIC-1',
            'origin_branch_id' => $nairobi->id,
            'destination_branch_id' => $mandera->id,
            'receiver_id' => $receiver->id,
            'created_by' => $staff->id,
            'status' => ConsignmentStatus::Received,
            'total_amount' => 2000,
            'paid_amount' => 500,
        ]);
        $consignment->trackingEvents()->create([
            'branch_id' => $nairobi->id,
            'created_by' => $staff->id,
            'status' => ConsignmentStatus::Received,
            'description' => 'Received at Nairobi counter.',
            'metadata' => ['internal_note' => 'must not leak'],
            'occurred_at' => now(),
        ]);

        $this->get('/?tracking=STC-PUBLIC-1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tracking/Public')
                ->where('consignment.tracking_number', 'STC-PUBLIC-1')
                ->where('consignment.balance', 1500)
                ->missing('consignment.id')
                ->missing('consignment.paid_amount')
                ->has('consignment.events', 1)
                ->where('consignment.events.0.branch', 'Nairobi')
                ->where('consignment.events.0.status', ConsignmentStatus::Received->value)
                ->where('consignment.events.0.description', 'Received at Nairobi counter.')
                ->has('consignment.events.0.occurred_at')
                ->missing('consignment.events.0.id')
                ->missing('consignment.events.0.consignment_id')
                ->missing('consignment.events.0.branch_id')
                ->missing('consignment.events.0.created_by')
                ->missing('consignment.events.0.metadata')
                ->missing('consignment.events.0.created_at')
                ->missing('consignment.events.0.updated_at')
                ->where('branches.0.name', 'Nairobi')
                ->missing('branches.0.id'));
    }
}
