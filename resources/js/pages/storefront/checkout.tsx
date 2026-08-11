import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Banknote, Plus, Store, Truck } from 'lucide-react';
import { useState } from 'react';
import { FormModal } from '@/components/form-modal';
import InputError from '@/components/input-error';
import { Money } from '@/components/money';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import StorefrontLayout from '@/layouts/storefront-layout';
import { formatDeliveryEstimate } from '@/lib/format';
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
                    className={
                        problem.blocking
                            ? 'flex items-center gap-1.5 text-xs text-destructive'
                            : 'flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400'
                    }
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
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        placeOrder.transform((data) => ({
            ...data,
            address_id: selectedAddressId ?? '',
            coupon_code: coupon?.applied ? coupon.code : '',
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

            <div className="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                <h1 className="mb-6 text-2xl font-semibold tracking-tight">
                    Checkout
                </h1>

                <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <CardTitle className="text-base">
                                        Delivery address
                                    </CardTitle>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setAddressModal(true)}
                                    >
                                        <Plus className="size-4" />
                                        New address
                                    </Button>
                                </div>
                            </CardHeader>

                            <CardContent>
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
                                                className="flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3 transition-colors hover:bg-accent/50"
                                            >
                                                <RadioGroupItem
                                                    value={address.id}
                                                    id={address.id}
                                                    className="mt-1"
                                                />
                                                <span className="text-sm font-normal">
                                                    <span className="block font-medium">
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
                            </CardContent>
                        </Card>

                        <ProblemList problems={quote.problems} />

                        {quote.vendor_quotes.map((vendorQuote) => (
                            <Card key={vendorQuote.vendor.id}>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Store className="size-4" />
                                        {vendorQuote.vendor.shop_name}
                                    </CardTitle>
                                    <ProblemList
                                        problems={vendorQuote.problems}
                                    />
                                </CardHeader>

                                <CardContent className="space-y-3">
                                    {vendorQuote.lines.map((line) => (
                                        <div
                                            key={line.id}
                                            className="space-y-1"
                                        >
                                            <div className="flex items-start justify-between gap-3 text-sm">
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium">
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

                                    <Separator />

                                    <div className="flex justify-between text-sm">
                                        <span className="flex items-center gap-1.5 text-muted-foreground">
                                            <Truck className="size-3.5" />
                                            Delivery
                                            {vendorQuote.delivers
                                                ? formatDeliveryEstimate(
                                                      vendorQuote.estimated_days_min,
                                                      vendorQuote.estimated_days_max,
                                                  )
                                                    ? ` (${formatDeliveryEstimate(vendorQuote.estimated_days_min, vendorQuote.estimated_days_max)})`
                                                    : ''
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

                                    <div className="flex justify-between rounded-md bg-muted/50 p-3 text-sm font-semibold">
                                        <span>
                                            Pay {vendorQuote.vendor.shop_name}{' '}
                                            on delivery
                                        </span>
                                        <Money amount={vendorQuote.total} />
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>

                    <div>
                        <Card className="lg:sticky lg:top-6">
                            <CardContent className="space-y-4">
                                <form
                                    onSubmit={applyCoupon}
                                    className="space-y-2"
                                >
                                    <Label htmlFor="coupon">Coupon code</Label>
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
                                        />
                                        <Button type="submit" variant="outline">
                                            Apply
                                        </Button>
                                    </div>
                                    {coupon ? (
                                        <p
                                            className={
                                                coupon.applied
                                                    ? 'text-xs text-emerald-600 dark:text-emerald-400'
                                                    : 'text-xs text-destructive'
                                            }
                                        >
                                            {coupon.message}
                                        </p>
                                    ) : null}
                                </form>

                                <Separator />

                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        Subtotal
                                    </span>
                                    <Money
                                        amount={quote.subtotal}
                                        currency={quote.currency}
                                    />
                                </div>

                                {quote.discount > 0 ? (
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            Discount
                                        </span>
                                        <span>
                                            −
                                            <Money
                                                amount={quote.discount}
                                                currency={quote.currency}
                                            />
                                        </span>
                                    </div>
                                ) : null}

                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        Delivery ({quote.vendor_count} shop
                                        {quote.vendor_count === 1 ? '' : 's'})
                                    </span>
                                    <Money
                                        amount={quote.shipping_fee}
                                        currency={quote.currency}
                                    />
                                </div>

                                <Separator />

                                <div className="flex justify-between text-base font-semibold">
                                    <span>Total</span>
                                    <Money
                                        amount={quote.total}
                                        currency={quote.currency}
                                    />
                                </div>

                                <Alert>
                                    <Banknote className="size-4" />
                                    <AlertDescription>
                                        Cash on delivery. You pay each shop
                                        directly when they hand over your items.
                                    </AlertDescription>
                                </Alert>

                                <form onSubmit={submit}>
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={
                                            !quote.is_placeable ||
                                            placeOrder.processing
                                        }
                                    >
                                        {placeOrder.processing ? (
                                            <Spinner />
                                        ) : null}
                                        Place order
                                    </Button>
                                </form>

                                {!quote.is_placeable ? (
                                    <p className="text-center text-xs text-muted-foreground">
                                        Fix the issues above to place your
                                        order.
                                    </p>
                                ) : null}
                            </CardContent>
                        </Card>
                    </div>
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
