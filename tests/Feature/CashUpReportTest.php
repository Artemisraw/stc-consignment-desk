<?php

namespace Tests\Feature;

use App\Enums\ConsignmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CashUpReportTest extends TestCase
{
    private Branch $nairobi;
    private Branch $wajir;
    private User $manager;
    private User $nairobiClerk;
    private User $wajirClerk;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 18:00:00'));

        $this->nairobi = Branch::create(['name' => 'Nairobi', 'route_order' => 1]);
        $this->wajir = Branch::create(['name' => 'Wajir', 'route_order' => 2]);
        Branch::create(['name' => 'Mandera', 'route_order' => 3]);
        $this->manager = $this->user(Role::Manager, 'Mary Manager', $this->nairobi);
        $this->nairobiClerk = $this->user(Role::Clerk, 'Amina Clerk', $this->nairobi);
        $this->wajirClerk = $this->user(Role::Clerk, 'Hassan Clerk', $this->wajir);
    }

    public function test_totals_by_method_branch_and_staff_for_a_day(): void
    {
        $this->payment($this->nairobiClerk, 1500, PaymentMethod::Cash, '2026-11-20 08:15');
        $this->payment($this->nairobiClerk, 2500, PaymentMethod::Mpesa, '2026-11-20 09:40', 'SKL7Q2M4XP');
        $this->payment($this->wajirClerk, 700, PaymentMethod::BalanceOnDelivery, '2026-11-20 14:05');
        $this->payment($this->wajirClerk, 300, PaymentMethod::Cash, '2026-11-20 23:59:30');
        // Outside the day, or never completed: not counted.
        $this->payment($this->nairobiClerk, 9999, PaymentMethod::Cash, '2026-11-19 23:59:59');
        $this->payment($this->nairobiClerk, 8888, PaymentMethod::Cash, '2026-11-21 00:00:00');
        $this->payment($this->nairobiClerk, 7777, PaymentMethod::Mpesa, '2026-11-20 10:00', status: PaymentStatus::Pending);
        $this->payment($this->nairobiClerk, 6666, PaymentMethod::Mpesa, '2026-11-20 10:00', status: PaymentStatus::Failed);

        $this->actingAs($this->manager)->get('/reports/cash-up?from=2026-11-20')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Reports/CashUp')
                ->where('filters.from', '2026-11-20')->where('filters.to', '2026-11-20')
                ->where('report.totals', ['payments' => 4, 'received' => 5000, 'reversals' => 0, 'reversed' => 0, 'net' => 5000])
                ->where('report.byMethod', [
                    ['key' => 'cash', 'label' => 'Cash', 'payments' => 2, 'received' => 1800, 'reversals' => 0, 'reversed' => 0, 'net' => 1800],
                    ['key' => 'balance_on_delivery', 'label' => 'Balance on delivery', 'payments' => 1, 'received' => 700, 'reversals' => 0, 'reversed' => 0, 'net' => 700],
                    ['key' => 'mpesa', 'label' => 'M-Pesa', 'payments' => 1, 'received' => 2500, 'reversals' => 0, 'reversed' => 0, 'net' => 2500],
                ])
                ->where('report.byBranch.0.label', 'Nairobi')->where('report.byBranch.0.received', 4000)
                ->where('report.byBranch.1.label', 'Wajir')->where('report.byBranch.1.received', 1000)
                ->where('report.byStaff.0.label', 'Amina Clerk')->where('report.byStaff.0.received', 4000)
                ->where('report.byStaff.1.label', 'Hassan Clerk')->where('report.byStaff.1.received', 1000)
                ->has('report.payments', 4)
                ->where('report.payments.1.reference', 'SKL7Q2M4XP')
                ->where('report.payments.1.amount', 2500));
    }

    public function test_reversals_are_listed_on_the_day_they_were_made_and_not_netted_silently(): void
    {
        $this->payment($this->nairobiClerk, 1200, PaymentMethod::Cash, '2026-11-19 11:00');
        $this->payment($this->nairobiClerk, 800, PaymentMethod::Cash, '2026-11-20 09:00');

        // A manager reverses yesterday's payment today through the normal flow.
        $yesterdays = Payment::where('amount', 1200)->firstOrFail();
        $this->actingAs($this->manager)
            ->post("/consignments/{$yesterdays->consignment_id}/payments/{$yesterdays->id}/reverse", ['reason' => 'Refunded at counter'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->manager)->get('/reports/cash-up?from=2026-11-20')
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.totals', ['payments' => 1, 'received' => 800, 'reversals' => 1, 'reversed' => 1200, 'net' => -400])
                ->where('report.byMethod.0.received', 800)->where('report.byMethod.0.reversed', 1200)->where('report.byMethod.0.net', -400)
                ->has('report.reversals', 1)
                ->where('report.reversals.0.amount', 1200)
                ->where('report.reversals.0.reason', 'Refunded at counter')
                ->where('report.reversals.0.reversed_by', 'Mary Manager')
                ->where('report.reversals.0.staff', 'Amina Clerk'));

        // On the day it was taken the payment still counts as received, marked reversed.
        $this->actingAs($this->manager)->get('/reports/cash-up?from=2026-11-19')
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.totals', ['payments' => 1, 'received' => 1200, 'reversals' => 0, 'reversed' => 0, 'net' => 1200])
                ->where('report.payments.0.status', 'reversed'));

        // Over both days the reversal cancels the original out, and both still appear.
        $this->actingAs($this->manager)->get('/reports/cash-up?from=2026-11-19&to=2026-11-20')
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.totals', ['payments' => 2, 'received' => 2000, 'reversals' => 1, 'reversed' => 1200, 'net' => 800])
                ->has('report.payments', 2)->has('report.reversals', 1));
    }

    public function test_payments_stay_with_the_branch_that_took_them_after_a_staff_transfer(): void
    {
        $consignment = $this->consignment(3000);

        $this->actingAs($this->wajirClerk)->post("/consignments/{$consignment->id}/payments", ['amount' => 1000, 'method' => 'cash'])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->wajir->id, Payment::firstOrFail()->branch_id);

        $this->wajirClerk->update(['branch_id' => $this->nairobi->id]);

        $this->actingAs($this->manager)->get("/reports/cash-up?branch_id={$this->wajir->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.branch_id', $this->wajir->id)
                ->where('report.totals.received', 1000)
                ->where('report.byBranch.0.label', 'Wajir'));
        $this->actingAs($this->manager)->get("/reports/cash-up?branch_id={$this->nairobi->id}")
            ->assertInertia(fn (Assert $page) => $page->where('report.totals.received', 0)->has('report.payments', 0));
    }

    public function test_the_report_defaults_to_today(): void
    {
        $this->payment($this->nairobiClerk, 450, PaymentMethod::Cash, '2026-11-20 07:00');

        $this->actingAs($this->manager)->get('/reports/cash-up')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters', ['from' => '2026-11-20', 'to' => '2026-11-20', 'branch_id' => null])
                ->where('report.totals.received', 450));
    }

    public function test_only_managers_and_owners_can_see_the_cash_up(): void
    {
        $this->actingAs($this->nairobiClerk)->get('/reports/cash-up')->assertForbidden();
        $this->actingAs($this->user(Role::BranchAgent, 'Agent', $this->wajir))->get('/reports/cash-up')->assertForbidden();
        $this->actingAs($this->user(Role::Owner, 'Owner', $this->nairobi))->get('/reports/cash-up')->assertOk();
    }

    public function test_the_period_is_validated(): void
    {
        $this->actingAs($this->manager)->from('/reports')->get('/reports/cash-up?from=2026-11-20&to=2026-11-19')->assertSessionHasErrors('to');
        $this->actingAs($this->manager)->from('/reports')->get('/reports/cash-up?from=2026-10-01&to=2026-11-20')->assertSessionHasErrors('to');
        $this->actingAs($this->manager)->from('/reports')->get('/reports/cash-up?from=20-11-2026')->assertSessionHasErrors('from');
        $this->actingAs($this->manager)->get('/reports/cash-up?from=2026-10-21&to=2026-11-20')->assertOk(); // 31 days
    }

    private function user(Role $role, string $name, Branch $branch): User
    {
        return User::create([
            'name' => $name, 'email' => str($name)->slug().'-'.(++$this->sequence).'@example.test',
            'password' => 'password', 'role' => $role, 'branch_id' => $branch->id,
        ]);
    }

    private function consignment(int $total): Consignment
    {
        $consignment = Consignment::create([
            'tracking_number' => 'STC-CU-'.(++$this->sequence), 'origin_branch_id' => $this->nairobi->id,
            'destination_branch_id' => $this->wajir->id, 'receiver_id' => Customer::create(['name' => 'Receiver', 'phone' => '0700 000 000'])->id,
            'created_by' => $this->manager->id, 'status' => ConsignmentStatus::Received, 'total_amount' => $total, 'paid_amount' => 0,
        ]);
        $consignment->items()->create(['quantity' => 1, 'description' => 'Parcel', 'amount' => $total]);

        return $consignment;
    }

    private function payment(User $by, int $amount, PaymentMethod $method, string $paidAt, ?string $reference = null, PaymentStatus $status = PaymentStatus::Completed): Payment
    {
        $consignment = $this->consignment($amount);
        $payment = $consignment->payments()->create([
            'recorded_by' => $by->id, 'branch_id' => $by->branch_id, 'amount' => $amount, 'method' => $method,
            'status' => $status, 'reference' => $reference ?? 'REF-'.(++$this->sequence), 'paid_at' => CarbonImmutable::parse($paidAt),
        ]);
        if ($status === PaymentStatus::Completed) {
            $consignment->update(['paid_amount' => $amount]);
        }

        return $payment;
    }
}
