<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\MembershipService;
use App\Support\Branding;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private readonly MembershipService $memberships) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Payment::class);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $from = $filters['from'] ?? today()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? today()->toDateString();

        $query = Payment::query()
            ->with(['member', 'plan', 'receiver'])
            ->between($from, $to)
            ->when($filters['method'] ?? null, fn ($q, $m) => $q->where('method', $m))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('receipt_no', 'like', "%{$term}%")
                ->orWhereHas('member', fn ($m) => $m->search($term))));

        return view('payments.index', [
            'payments' => (clone $query)->latest('paid_on')->latest('id')->paginate(25)->withQueryString(),
            'total' => (clone $query)->sum('amount'),
            'count' => (clone $query)->count(),
            'from' => $from,
            'to' => $to,
            'filters' => $filters,
            'methods' => PaymentMethod::cases(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('create', Payment::class);

        $member = $request->filled('member') ? Member::with('currentMembership.plan')->find($request->integer('member')) : null;

        if ($member && ! $member->is_active) {
            return redirect()->route('members.show', $member)->with('error', 'This member is deactivated. Ask the admin to reactivate the member before recording a payment.');
        }

        $plans = Plan::active()->forMembers()->orderBy('price')->get();
        $selectedPlan = $plans->firstWhere('id', $request->integer('plan')) ?? ($member?->currentMembership ? $plans->firstWhere('id', $member->currentMembership->plan_id) : null);

        return view('payments.create', [
            'member' => $member,
            'memberStatus' => $member ? $this->memberships->status($member) : null,
            'plans' => $plans,
            'selectedPlan' => $selectedPlan,
            'methods' => PaymentMethod::cases(),
            'renewalMode' => $this->memberships->renewalStartMode(),
            'previews' => $member ? $this->previews($member, $plans) : [],
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $payment = $this->memberships->recordPayment(
            $request->member(),
            $request->plan(),
            $request->validated(),
            $request->user(),
        );

        return redirect()->route('payments.show', $payment)
            ->with('success', "Payment saved. Receipt {$payment->receipt_no}.");
    }

    public function show(Payment $payment): View
    {
        Gate::authorize('view', $payment);

        $payment->load(['member', 'plan', 'membership', 'receiver']);

        return view('payments.show', [
            'payment' => $payment,
            'canDelete' => $this->memberships->canDeletePayment($payment),
        ]);
    }

    /** Printable receipt (PDF, 80 mm wide for small receipt printers; prints fine on A4 too). */
    public function receipt(Payment $payment): Response
    {
        Gate::authorize('view', $payment);

        $payment->load(['member', 'plan', 'membership', 'receiver']);

        return Pdf::loadView('payments.receipt', [
            'payment' => $payment,
            'settings' => app(Settings::class)->all(),
            'logoDataUri' => Branding::logoDataUri(),
        ])
            ->setPaper([0, 0, 226.77, 600]) // 80 mm x ~212 mm in points
            ->stream("receipt-{$payment->receipt_no}.pdf");
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        Gate::authorize('delete', $payment);

        if (! $this->memberships->canDeletePayment($payment)) {
            return back()->with('error', 'Only the member\'s latest payment can be deleted, so the membership history stays correct.');
        }

        $member = $payment->member;
        $this->memberships->deletePayment($payment);

        return redirect()->route('members.show', $member)
            ->with('success', "Payment {$payment->receipt_no} and the membership period it created were deleted.");
    }

    /**
     * What each plan would give this member if paid today (start, expiry), so staff can see it before saving.
     *
     * @return array<int, array{start: string, expiry: string}>
     */
    private function previews(Member $member, $plans): array
    {
        $previous = $member->currentMembership;

        return $plans->mapWithKeys(function (Plan $plan) use ($previous) {
            $start = $this->memberships->startDateFor($previous, $plan, today());

            return [$plan->id => [
                'start' => $start->format('M j, Y'),
                'expiry' => $this->memberships->expiryDateFor($plan, $start)->format('M j, Y'),
            ]];
        })->all();
    }
}
