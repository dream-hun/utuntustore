<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Actions\Account\DeleteAddress;
use App\Actions\Account\SaveAddress;
use App\Actions\Account\SetDefaultAddress;
use App\Http\Controllers\Concerns\PresentsAddresses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AddressRequest;
use App\Models\Address;
use App\Support\LocationDirectory;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer's delivery address book.
 *
 * Rwanda's 416 sectors are never all sent to the browser: the page ships the 30
 * districts, and the sector list is fetched for one district at a time through a
 * partial reload. On a phone on a slow connection that difference is the page
 * loading or not.
 */
final class AddressController extends Controller
{
    use PresentsAddresses;

    public function __construct(private readonly LocationDirectory $locations) {}

    public function index(Request $request): Response
    {
        $addresses = $this->currentUser($request)->addresses()
            ->with(['district.province', 'sector'])
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Address $address): array => $this->addressDetail($address));

        return Inertia::render('account/addresses/index', [
            'addresses' => $addresses,

            'districts' => $this->locations->districts(),

            'selectedDistrict' => $request->string('district')->toString() ?: null,

            // Re-requested on its own with `only: ['sectors']` each time the customer
            // picks a district, so the browser only ever holds one district's sectors.
            'sectors' => fn (): array => $this->locations->sectors($request->string('district')->toString()),
        ]);
    }

    public function store(AddressRequest $request, SaveAddress $saveAddress): RedirectResponse
    {
        $saveAddress->handle($this->currentUser($request), $request->addressAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address saved.')]);

        return to_route('account.addresses.index');
    }

    public function update(AddressRequest $request, Address $address, SaveAddress $saveAddress): RedirectResponse
    {
        $this->authorize('update', $address);

        $saveAddress->handle($this->currentUser($request), $request->addressAttributes(), $address);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address updated.')]);

        return to_route('account.addresses.index');
    }

    public function destroy(Address $address, DeleteAddress $deleteAddress): RedirectResponse
    {
        $this->authorize('delete', $address);

        try {
            $deleteAddress->handle($address);
        } catch (DomainException $domainException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $domainException->getMessage()]);

            return to_route('account.addresses.index');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address deleted.')]);

        return to_route('account.addresses.index');
    }

    public function setDefault(Address $address, SetDefaultAddress $setDefaultAddress): RedirectResponse
    {
        $this->authorize('update', $address);

        $setDefaultAddress->handle($address);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Default delivery address updated.')]);

        return to_route('account.addresses.index');
    }
}
