<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Actions\Vendor\AddDeliveryCoverage;
use App\Actions\Vendor\LoadDeliveryReferenceData;
use App\Actions\Vendor\UpdateDeliveryArea;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\DeliveryCoverageRequest;
use App\Http\Requests\Vendor\UpdateDeliveryAreaRequest;
use App\Models\District;
use App\Models\Vendor;
use App\Models\VendorDeliveryArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where the vendor delivers, and what they charge to get there.
 *
 * This is the screen that decides whether a shop can be ordered from at all, so it is
 * built around the answer almost every vendor gives first: "everywhere in my
 * district". That is one row with a null sector and one action, not thirty rows.
 * Individual sectors are then added on top and override the district-wide row.
 *
 * Rwanda has 416 sectors. They are never all sent to the browser: the page ships the
 * 30 districts, and one district's sectors arrive through a partial reload when the
 * vendor opens it.
 *
 * None of this is gated on selling eligibility — coverage is shop configuration, and
 * an expired vendor must be able to have it ready for the day they renew.
 */
final class DeliveryAreaController extends Controller
{
    public function index(Request $request, Vendor $vendor, LoadDeliveryReferenceData $reference): Response
    {
        $this->authorize('manageDelivery', $vendor);

        $district = $this->requestedDistrict($request);

        return Inertia::render('vendor/delivery', [
            'provinces' => $reference->districts(),

            // Coverage is deliberately not paginated: it is configuration a vendor reads
            // as a whole to spot the gap in it, and it is bounded by the country's own
            // 30 districts and 416 sectors.
            'areas' => VendorDeliveryArea::query()
                ->where('vendor_id', $vendor->id)
                ->with(['district', 'sector'])
                ->join('districts', 'districts.id', '=', 'vendor_delivery_areas.district_id')
                ->leftJoin('sectors', 'sectors.id', '=', 'vendor_delivery_areas.sector_id')
                ->orderBy('districts.name')
                // The district-wide row heads its own group; the sectors that override it
                // follow underneath.
                ->orderByRaw('sectors.name IS NULL DESC')
                ->orderBy('sectors.name')
                ->select('vendor_delivery_areas.*')
                ->get()
                ->map(fn (VendorDeliveryArea $area): array => [
                    'id' => $area->uuid,
                    'district' => [
                        'id' => $area->district->uuid,
                        'name' => $area->district->name,
                    ],
                    'sector' => $area->sector === null ? null : [
                        'id' => $area->sector->uuid,
                        'name' => $area->sector->name,
                    ],
                    'delivery_fee' => $area->delivery_fee,
                    'estimated_days_min' => $area->estimated_days_min,
                    'estimated_days_max' => $area->estimated_days_max,
                    'is_active' => $area->is_active,
                ])
                ->all(),

            // Resolved only when the browser asks for it by name, so opening the page
            // never carries a sector list.
            'sectors' => Inertia::optional(
                fn (): array => $district instanceof District ? $reference->sectors($district) : [],
            ),
            'sectorsDistrictId' => $district?->uuid,
        ]);
    }

    public function store(DeliveryCoverageRequest $request, Vendor $vendor, AddDeliveryCoverage $addDeliveryCoverage): RedirectResponse
    {
        $this->authorize('manageDelivery', $vendor);

        $district = District::query()->where('uuid', $request->validated('district_id'))->firstOrFail();

        $areas = $addDeliveryCoverage->handle(
            $vendor,
            $district,
            $request->sectorUuids(),
            $request->integer('delivery_fee'),
            $request->integer('estimated_days_min'),
            $request->integer('estimated_days_max'),
            $request->boolean('is_active', true),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $request->sectorUuids() === []
                ? __('You now deliver across the whole of :district.', ['district' => $district->name])
                : trans_choice('{1} 1 sector added to your coverage.|[2,*] :count sectors added to your coverage.', $areas->count()),
        ]);

        return to_route('vendor.delivery.index');
    }

    public function update(UpdateDeliveryAreaRequest $request, VendorDeliveryArea $deliveryArea, Vendor $vendor, UpdateDeliveryArea $updateDeliveryArea): RedirectResponse
    {
        $this->authorize('manageDelivery', $vendor);
        $this->guardOwnership($vendor, $deliveryArea);

        $updateDeliveryArea->handle(
            $deliveryArea,
            $request->integer('delivery_fee'),
            $request->integer('estimated_days_min'),
            $request->integer('estimated_days_max'),
            $request->boolean('is_active', true),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Delivery area updated.')]);

        return to_route('vendor.delivery.index');
    }

    public function destroy(VendorDeliveryArea $deliveryArea, Vendor $vendor): RedirectResponse
    {
        $this->authorize('manageDelivery', $vendor);
        $this->guardOwnership($vendor, $deliveryArea);

        $deliveryArea->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Delivery area removed.')]);

        return to_route('vendor.delivery.index');
    }

    /**
     * Coverage rows have no policy of their own, so ownership is asserted here: a
     * guessed UUID must never let one shop reprice another shop's deliveries.
     */
    private function guardOwnership(Vendor $vendor, VendorDeliveryArea $area): void
    {
        abort_unless($area->vendor_id === $vendor->id, 404);
    }

    private function requestedDistrict(Request $request): ?District
    {
        $uuid = (string) $request->query('district', '');

        if ($uuid === '') {
            return null;
        }

        return District::query()->where('uuid', $uuid)->first();
    }
}
