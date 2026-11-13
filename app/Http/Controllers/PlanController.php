<?php

namespace App\Http\Controllers;

use App\Enums\PlanType;
use App\Http\Requests\PlanRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Plan::class);

        $plans = Plan::orderByDesc('is_active')
            ->orderByRaw("case type when 'monthly' then 1 when 'quarterly' then 2 when 'yearly' then 3 when 'per_session' then 4 else 5 end")
            ->orderBy('price')
            ->get();

        return view('plans.index', compact('plans'));
    }

    public function create(): View
    {
        Gate::authorize('create', Plan::class);

        return view('plans.create', ['plan' => new Plan(['is_active' => true]), 'types' => PlanType::cases()]);
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        $plan = Plan::create($request->validated());

        return redirect()->route('plans.index')->with('success', "Plan \"{$plan->name}\" created.");
    }

    public function edit(Plan $plan): View
    {
        Gate::authorize('update', $plan);

        return view('plans.edit', ['plan' => $plan, 'types' => PlanType::cases()]);
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update($request->validated());

        return redirect()->route('plans.index')->with('success', "Plan \"{$plan->name}\" updated.");
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        Gate::authorize('delete', $plan);

        if ($plan->isInUse()) {
            return back()->with('error', "\"{$plan->name}\" has already been sold, so it can't be deleted. Edit it and untick \"Available for sale\" instead.");
        }

        $plan->delete();

        return redirect()->route('plans.index')->with('success', "Plan \"{$plan->name}\" deleted.");
    }
}
