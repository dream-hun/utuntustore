import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Banknote, Plus, Store, Truck } from 'lucide-react';
import { useState } from 'react';
import { FormModal } from '@/components/form-modal';
import InputError from '@/components/input-error';
import { Money } from '@/components/money';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import StorefrontLayout from '@/layouts/storefront-layout';
import { formatDeliveryEstimate } from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    index as checkoutIndex,
    store as checkoutStore,
} from '@/routes/checkout';
import { store as storeAddress } from '@/routes/checkout/addresses';
import type {
    StorefrontAddress,
    StorefrontCheckoutProblem,
    StorefrontCheckoutQuote,
} from '@/types/marketplace';

function ProblemList({ problems }: { problems: StorefrontCheckoutProblem[] }) {
    if (problems.length === 0) {
        return null;
    }

    return (
        <div className="space-y-1">
            {problems.map((problem) => (
                <p
                    key={problem.code}
                    className={cn(
                        'flex items-center gap-1.5 text-xs',
                        problem.blocking ? 'text-destructive' : 'text-gold',
                    )}
                >
                    <AlertTriangle className="size-3.5 shrink-0" />
                    {problem.message}
                </p>
            ))}
        </div>
    );
}

export default function Checkout({
    quote,
    addresses,
    selectedAddressId,
    coupon,
    districts,
    sectors,
}: {
    quote: StorefrontCheckoutQuote;
    addresses: StorefrontAddress[];
    selectedAddressId: string | null;
    coupon: {
        code: string;
        applied: boolean;
        discount: number;
        message: string;
    } | null;
    districts: { id: string; name: string }[];
    sectors: { id: string; name: string }[];
}) {
    const [addressModal, setAddressModal] = useState(false);
    const [couponCode, setCouponCode] = useState(coupon?.code ?? '');

    const selectAddress = (id: string) => {
        router.get(
            checkoutIndex.url(),
            { address: id, coupon: couponCode || undefined },
            { preserveScroll: true, preserveState: true },
        );
    };

    const applyCoupon = (event: React.FormEvent) => {
        event.preventDefault();

        router.get(
            checkoutIndex.url(),
            {
                address: selectedAddressId ?? undefined,
                coupon: couponCode || undefined,
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    const placeOrder = useForm({
        address_id: selectedAddressId ?? '',
        coupon_code: coupon?.applied ? coupon.code : '',
        expected_total: quote.total,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        placeOrder.transform((data) => ({
            ...data,
            address_id: selectedAddressId ?? '',
            coupon_code: coupon?.applied ? coupon.code : '',
            // The total as rendered right now, so the server can refuse to place an
            // order whose price moved since. Read from the current quote rather than
            // from form state, which would still hold the figure from first render.
            expected_total: quote.total,
        }));

        placeOrder.post(checkoutStore.url());
    };

    const addressForm = useForm({
        first_name: '',
        last_name: '',
        phone: '',
        district_id: '',
        sector_id: '',
        cell: '',
        village: '',
        address_line: '',
        landmark: '',
    });

    const chooseDistrict = (districtId: string) => {
        addressForm.setData((data) => ({
            ...data,
            district_id: districtId,
            sector_id: '',
        }));

        // Sectors are fetched for the chosen district only — there are 416 of them
        // nationally and shipping the whole list would be wasteful on a phone.
        router.reload({ data: { district: districtId }, only: ['sectors'] });
    };

    const submitAddress = (event: React.FormEvent) => {
        event.preventDefault();

        addressForm.post(storeAddress.url(), {
            onSuccess: () => {
                setAddressModal(false);
                addressForm.reset();
            },
        });
    };

    return (
        <StorefrontLayout>
            <Head title="Checkout" />

            <div className="mx-auto w-full max-w-[1400px] px-4 py-12 lg:px-8 lg:py-16">
                <header className="mb-12 border-b border-border pb-8">
                    <span className="eyebrow text-muted-foreground">
                        Almost there
                    </span>
                    <h1 className="mt-3 text-4xl md:text-5xl">Checkout</h1>
                    <p className="mt-5 max-w-xl text-sm leading-relaxed text-muted-foreground">
                        Choose where your order is going. Each shop delivers its
                        own items and is paid in cash at the door — nothing is
                        charged now.
                    </p>
                </header>

                <div className="grid gap-12 lg:grid-cols-[minmax(0,1fr)_380px] lg:gap-16">
                    <div className="space-y-10">
                        {/* ─── Address ───────────────────────────────────── */}
                        <section aria-labelledby="address-heading">
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
                                <h2
                                    id="address-heading"
                                    className="text-lg sm:text-xl"
                                >
                                    Delivery address
                                </h2>
                                <button
                                    type="button"
                                    onClick={() => setAddressModal(true)}
                                    className="inline-flex items-center gap-1.5 rounded-full border border-border px-4 py-2 text-sm font-semibold transition hover:border-primary hover:bg-aqua-soft focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <Plus className="size-4" />
                                    New address
                                </button>
                            </div>

                            <div className="pt-5">
                                {addresses.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Add a delivery address to see which
                                        shops can deliver to you.
                                    </p>
                                ) : (
                                    <RadioGroup
                                        value={selectedAddressId ?? ''}
                                        onValueChange={selectAddress}
                                        className="gap-3"
                                    >
                                        {addresses.map((address) => (
                                            <Label
                                                key={address.id}
                                                htmlFor={address.id}
                                                className={cn(
                                                    'flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition',
                                                    address.id ===
                                                        selectedAddressId
                                                        ? 'border-primary bg-aqua-soft'
                                                        : 'border-border hover:border-primary',
                                                )}
                                            >
                                                <RadioGroupItem
                                                    value={address.id}
                                                    id={address.id}
                                                    className="mt-1"
                                                />
                                                <span className="text-sm font-normal">
                                                    <span className="block font-semibold">
                                                        {address.first_name}{' '}
                                                        {address.last_name}
                                                    </span>
                                                    <span className="block text-muted-foreground">
                                                        {address.district.name}{' '}
                                                        · {address.sector.name}
                                                    </span>
                                                    {address.landmark ? (
                                                        <span className="block text-muted-foreground">
                                                            Near{' '}
                                                            {address.landmark}
                                                        </span>
                                                    ) : null}
                                                    <span className="block text-muted-foreground">
                                                        {address.phone}
                                                    </span>
                                                </span>
                                            </Label>
                                        ))}
                                    </RadioGroup>
                                )}
                            </div>
                        </section>

                        <ProblemList problems={quote.problems} />

                        {/* ─── Per-shop breakdown ────────────────────────── */}
                        {quote.vendor_quotes.map((vendorQuote) => (
                            <section
                                key={vendorQuote.vendor.id}
                                aria-label={vendorQuote.vendor.shop_name}
                            >
                                <div className="border-b border-border pb-4">
                                    <h2 className="flex items-center gap-2 text-lg">
                                        <Store
                                            className="size-4 text-primary"
                                            aria-hidden="true"
                                        />
                                        {vendorQuote.vendor.shop_name}
                                    </h2>
                                    <div className="mt-2">
                                        <ProblemList
                                            problems={vendorQuote.problems}
                                        />
                                    </div>
                                </div>

                                <div className="space-y-3 pt-5">
                                    {vendorQuote.lines.map((line) => (
                                        <div
                                            key={line.id}
                                            className="space-y-1"
                                        >
                                            <div className="flex items-start justify-between gap-3 text-sm">
                                                <div className="min-w-0">
                                                    <p className="truncate font-semibold">
                                                        {line.name}
                                                    </p>
                                                    {line.variant_name ? (
                                                        <p className="text-xs text-muted-foreground">
                                                            {line.variant_name}
                                                        </p>
                                                    ) : null}
                                                    <p className="text-xs text-muted-foreground">
                                                        <Money
                                                            amount={
                                                                line.unit_price
                                                            }
                                                        />{' '}
                                                        × {line.quantity}
                                                    </p>
                                                </div>
                                                <Money amount={line.subtotal} />
                                            </div>
                                            <ProblemList
                                                problems={line.problems}
                                            />
                                        </div>
                                    ))}

                                    <div className="flex justify-between border-t border-border pt-3 text-sm">
                                        <span className="flex items-center gap-1.5 text-muted-foreground">
                                            <Truck className="size-3.5" />
                                            Delivery
                                            {vendorQuote.delivers &&
                                            formatDeliveryEstimate(
                                                vendorQuote.estimated_days_min,
                                                vendorQuote.estimated_days_max,
                                            )
                                                ? ` (${formatDeliveryEstimate(vendorQuote.estimated_days_min, vendorQuote.estimated_days_max)})`
                                                : ''}
                                        </span>
                                        <Money
                                            amount={vendorQuote.shipping_fee}
                                        />
                                    </div>

                                    {vendorQuote.discount > 0 ? (
                                        <div className="flex justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Discount
                                            </span>
                                            <span>
                                                −
                                                <Money
                                                    amount={
                                                        vendorQuote.discount
                                                    }
                                                />
                                            </span>
                                        </div>
                                    ) : null}

                                    <div className="flex justify-between gap-4 rounded-2xl bg-cream p-4 text-sm font-semibold">
                                        <span>
                                            Pay {vendorQuote.vendor.shop_name}{' '}
                                            on delivery
                                        </span>
                                        <Money amount={vendorQuote.total} />
                                    </div>
                                </div>
                            </section>
                        ))}
                    </div>

                    {/* ─── Summary ───────────────────────────────────────── */}
                    <aside className="h-fit rounded-2xl bg-cream p-8 lg:sticky lg:top-28">
                        <h2 className="mb-6 eyebrow text-muted-foreground">
                            Order summary
                        </h2>

                        <form onSubmit={applyCoupon} className="space-y-2">
                            <Label
                                htmlFor="coupon"
                                className="eyebrow text-muted-foreground"
                            >
                                Coupon code
                            </Label>
                            <div className="flex gap-2">
                                <Input
                                    id="coupon"
                                    value={couponCode}
                                    onChange={(event) =>
                                        setCouponCode(
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    placeholder="Optional"
                                    className="rounded-full bg-background"
                                />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    className="rounded-full bg-background"
                                >
                                    Apply
                                </Button>
                            </div>
                            {coupon ? (
                                <p
                                    className={cn(
                                        'text-xs',
                                        coupon.applied
                                            ? 'text-primary'
                                            : 'text-destructive',
                                    )}
                                >
                                    {coupon.message}
                                </p>
                            ) : null}
                        </form>

                        <dl className="mt-6 space-y-3 border-t border-border pt-6 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">
                                    Subtotal
                                </dt>
                                <dd>
                                    <Money
                                        amount={quote.subtotal}
                                        currency={quote.currency}
                                    />
                                </dd>
                            </div>

                            {quote.discount > 0 ? (
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">
                                        Discount
                                    </dt>
                                    <dd>
                                        −
                                        <Money
                                            amount={quote.discount}
                                            currency={quote.currency}
                                        />
                                    </dd>
                                </div>
                            ) : null}

                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">
                                    Delivery ({quote.vendor_count} shop
                                    {quote.vendor_count === 1 ? '' : 's'})
                                </dt>
                                <dd>
                                    <Money
                                        amount={quote.shipping_fee}
                                        currency={quote.currency}
                                    />
                                </dd>
                            </div>

                            <div className="flex justify-between border-t border-border pt-3 text-base font-semibold">
                                <dt>Total cash due</dt>
                                <dd>
                                    <Money
                                        amount={quote.total}
                                        currency={quote.currency}
                                    />
                                </dd>
                            </div>
                        </dl>

                        <p className="mt-6 flex items-start gap-2 text-xs text-muted-foreground">
                            <Banknote
                                className="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            Cash on delivery. You pay each shop directly when it
                            hands over your items.
                        </p>

                        <form onSubmit={submit}>
                            <button
                                type="submit"
                                disabled={
                                    !quote.is_placeable || placeOrder.processing
                                }
                                className="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary py-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
                            >
                                {placeOrder.processing ? <Spinner /> : null}
                                Place order
                            </button>
                        </form>

                        {!quote.is_placeable ? (
                            <p className="mt-4 text-center text-xs text-muted-foreground">
                                Fix the issues above to place your order.
                            </p>
                        ) : null}
                    </aside>
                </div>
            </div>

            <FormModal
                open={addressModal}
                onOpenChange={setAddressModal}
                title="New delivery address"
                description="District and sector decide which shops can deliver to you."
                onSubmit={submitAddress}
                processing={addressForm.processing}
                submitLabel="Save address"
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="first_name">First name</Label>
                        <Input
                            id="first_name"
                            value={addressForm.data.first_name}
                            onChange={(event) =>
                                addressForm.setData(
                                    'first_name',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={addressForm.errors.first_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="last_name">Last name</Label>
                        <Input
                            id="last_name"
                            value={addressForm.data.last_name}
                            onChange={(event) =>
                                addressForm.setData(
                                    'last_name',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={addressForm.errors.last_name} />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="phone">Phone</Label>
                    <Input
                        id="phone"
                        value={addressForm.data.phone}
                        onChange={(event) =>
                            addressForm.setData('phone', event.target.value)
                        }
                        placeholder="07xx xxx xxx"
                    />
                    <InputError message={addressForm.errors.phone} />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label>District</Label>
                        <Select
                            value={addressForm.data.district_id}
                            onValueChange={chooseDistrict}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Choose a district" />
                            </SelectTrigger>
                            <SelectContent>
                                {districts.map((district) => (
                                    <SelectItem
                                        key={district.id}
                                        value={district.id}
                                    >
                                        {district.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={addressForm.errors.district_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Sector</Label>
                        <Select
                            value={addressForm.data.sector_id}
                            onValueChange={(value) =>
                                addressForm.setData('sector_id', value)
                            }
                            disabled={addressForm.data.district_id === ''}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Choose a sector" />
                            </SelectTrigger>
                            <SelectContent>
                                {sectors.map((sector) => (
                                    <SelectItem
                                        key={sector.id}
                                        value={sector.id}
                                    >
                                        {sector.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={addressForm.errors.sector_id} />
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="cell">Cell</Label>
                        <Input
                            id="cell"
                            value={addressForm.data.cell}
                            onChange={(event) =>
                                addressForm.setData('cell', event.target.value)
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="village">Village</Label>
                        <Input
                            id="village"
                            value={addressForm.data.village}
                            onChange={(event) =>
                                addressForm.setData(
                                    'village',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="landmark">Landmark</Label>
                    <Input
                        id="landmark"
                        value={addressForm.data.landmark}
                        onChange={(event) =>
                            addressForm.setData('landmark', event.target.value)
                        }
                        placeholder="Near the church, behind the market…"
                    />
                    <p className="text-xs text-muted-foreground">
                        This is usually what actually gets a delivery to your
                        door.
                    </p>
                </div>
            </FormModal>
        </StorefrontLayout>
    );
}
