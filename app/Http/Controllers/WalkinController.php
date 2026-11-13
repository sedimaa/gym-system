<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\StoreWalkinRequest;
use App\Models\Walkin;
use App\Services\WalkinService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WalkinController extends Controller
{
    public function __construct(private readonly WalkinService $walkins) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Walkin::class);

        $date = $request->validate(['date' => ['nullable', 'date', 'before_or_equal:today']])['date'] ?? today()->toDateString();

        $list = Walkin::with('recorder')->onDate($date)->latest('visited_at')->get();

        return view('walkins.index', [
            'date' => $date,
            'walkins' => $list,
            'total' => $list->sum('amount'),
            'dayPass' => $this->walkins->dayPass(),
            'editable' => $this->walkins->priceIsEditable(),
            'methods' => PaymentMethod::cases(),
        ]);
    }

    public function store(StoreWalkinRequest $request): RedirectResponse
    {
        $walkin = $this->walkins->record($request->validated(), $request->user());

        return redirect()->route('walkins.index')
            ->with('success', "Walk-in recorded: {$walkin->displayName()}, ".Money::peso($walkin->amount).'.');
    }

    public function destroy(Walkin $walkin): RedirectResponse
    {
        Gate::authorize('delete', $walkin);

        $walkin->delete();

        return back()->with('success', 'Walk-in deleted.');
    }
}
