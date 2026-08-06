<?php

namespace App\Http\Controllers;

use App\Models\TravelOrder;
use App\Models\TravelOrderTransportation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TravelOrderTransportationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('manage-travel-order-transportations');

        $search = trim((string) $request->query('search', ''));

        $transportations = TravelOrderTransportation::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%' . $search . '%'))
            ->ordered()
            ->get();

        return view('travel-orders.transport-options', compact('transportations', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-travel-order-transportations');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('travel_order_transportations', 'name')],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        TravelOrderTransportation::create([
            'name' => $validated['name'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('travel-orders.transport-options.index')
            ->with('open_transport_modal', true)
            ->with('success', 'Transport option added.');
    }

    public function update(Request $request, TravelOrderTransportation $transportation): RedirectResponse
    {
        Gate::authorize('manage-travel-order-transportations');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('travel_order_transportations', 'name')->ignore($transportation->id)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $transportation->update([
            'name' => $validated['name'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('travel-orders.transport-options.index')
            ->with('open_transport_modal', true)
            ->with('success', 'Transport option updated.');
    }

    public function destroy(TravelOrderTransportation $transportation): RedirectResponse
    {
        Gate::authorize('manage-travel-order-transportations');

        $isUsed = TravelOrder::query()
            ->where('transport_mode', $transportation->name)
            ->exists();

        if ($isUsed) {
            return redirect()
                ->route('travel-orders.transport-options.index')
                ->with('open_transport_modal', true)
                ->with('error', 'Transport option is already used by travel orders. Set it inactive instead of deleting.');
        }

        $transportation->delete();

        return redirect()
            ->route('travel-orders.transport-options.index')
            ->with('open_transport_modal', true)
            ->with('success', 'Transport option deleted.');
    }
}
