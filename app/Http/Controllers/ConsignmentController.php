<?php

namespace App\Http\Controllers;

use App\Enums\ConsignmentStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\StoreConsignmentRequest;
use App\Models\Branch;
use App\Models\Consignment;
use App\Services\ConsignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsignmentController extends Controller
{
    public function __construct(private readonly ConsignmentService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Consignment::class);
        $query = Consignment::with(['receiver', 'originBranch', 'destinationBranch'])->latest();
        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(fn ($q) => $q->where('tracking_number', 'like', "%{$term}%")
                ->orWhereHas('receiver', fn ($r) => $r->where('name', 'like', "%{$term}%")));
        }

        return Inertia::render('Consignments/Index', [
            'consignments' => $query->paginate(15)->withQueryString()->through(fn (Consignment $c) => [
                'id' => $c->id, 'tracking_number' => $c->tracking_number, 'receiver' => $c->receiver->name,
                'origin' => $c->originBranch->name, 'destination' => $c->destinationBranch->name,
                'status' => $c->status->value, 'total_amount' => $c->total_amount, 'balance' => $c->balance,
            ]),
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Consignment::class);
        return Inertia::render('Consignments/Create', ['branches' => Branch::orderBy('route_order')->get()]);
    }

    public function store(StoreConsignmentRequest $request): RedirectResponse
    {
        $this->authorize('create', Consignment::class);
        $consignment = $this->service->create($request->validated(), $request->user());

        return redirect()->route('consignments.show', $consignment)->with('success', "{$consignment->tracking_number} created.");
    }

    public function show(Consignment $consignment): Response
    {
        $this->authorize('view', $consignment);
        $consignment->load(['items', 'sender', 'receiver', 'originBranch', 'destinationBranch', 'trackingEvents.branch', 'payments']);
        return Inertia::render('Consignments/Show', ['consignment' => $consignment->append('balance')]);
    }

    public function payment(Request $request, Consignment $consignment): RedirectResponse
    {
        $this->authorize('update', $consignment);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'in:cash,mpesa,balance_on_delivery'],
            'reference' => ['nullable', 'string', 'max:100', 'unique:payments,reference'],
        ]);
        $this->service->recordPayment($consignment, $data['amount'], PaymentMethod::from($data['method']), $request->user(), $data['reference'] ?? null);
        return back()->with('success', 'Payment recorded.');
    }

    public function deliver(Request $request, Consignment $consignment): RedirectResponse
    {
        $this->authorize('deliver', $consignment);
        $this->service->deliver($consignment, $request->user());
        return back()->with('success', "{$consignment->tracking_number} handed over.");
    }
}
