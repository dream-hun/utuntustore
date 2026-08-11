import { Head, router } from '@inertiajs/react';
import { MapPin, Phone } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Money } from '@/components/money';
import { OrderStatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { VendorNav } from '@/components/vendor/vendor-nav';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatDateTime } from '@/lib/format';
import type { OrderStatus } from '@/types/marketplace';

interface VendorOrderDetail {
    id: string;
    order_number: string;
    status: OrderStatus;
    subtotal: number;
    discount: number;
    shipping_fee: number;
    tax: number;
    total: number;
    currency: string;
    delivered_at: string | null;
    created_at: string;
    placed_at: string | null;
    customer_order_number: string;
    allowed_transitions: { value: string; label: string }[];
    customer: { name: string; phone: string };
    delivery_address: {
        district: string;
        sector: string;
        cell: string | null;
        village: string | null;
        address_line: string | null;
        landmark: string | null;
    };
    items: {
        id: string;
        product_name: string;
        variant_name: string | null;
        sku: string | null;
        unit_price: number;
        quantity: number;
        subtotal: number;
    }[];
}

export default function VendorOrderShow({
    vendorOrder,
}: {
    vendorOrder: VendorOrderDetail;
}) {
    const [confirming, setConfirming] = useState<{
        value: string;
        label: string;
    } | null>(null);

    const transition = (status: string) => {
        router.put(
            `/vendor/orders/${vendorOrder.id}/status`,
            { status },
            { preserveScroll: true, onFinish: () => setConfirming(null) },
        );
    };

    const address = vendorOrder.delivery_address;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Vendor', href: '/vendor' },
                { title: 'Orders', href: '/vendor/orders' },
                {
                    title: vendorOrder.order_number,
                    href: `/vendor/orders/${vendorOrder.id}`,
                },
            ]}
        >
            <Head title={vendorOrder.order_number} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {vendorOrder.order_number}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Customer reference{' '}
                            {vendorOrder.customer_order_number} ·{' '}
                            {formatDate(vendorOrder.placed_at)}
                        </p>
                    </div>
                    <OrderStatusBadge status={vendorOrder.status} />
                </div>

                <VendorNav />

                {vendorOrder.allowed_transitions.length > 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Next step
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-2">
                            {vendorOrder.allowed_transitions.map(
                                (transitionOption) => (
                                    <Button
                                        key={transitionOption.value}
                                        variant={
                                            transitionOption.value ===
                                            'cancelled'
                                                ? 'outline'
                                                : 'default'
                                        }
                                        onClick={() =>
                                            setConfirming(transitionOption)
                                        }
                                    >
                                        Mark{' '}
                                        {transitionOption.label.toLowerCase()}
                                    </Button>
                                ),
                            )}
                        </CardContent>
                    </Card>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Deliver to
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p className="font-medium">
                                {vendorOrder.customer.name}
                            </p>

                            <p className="flex items-center gap-2 text-muted-foreground">
                                <Phone className="size-3.5 shrink-0" />
                                {vendorOrder.customer.phone}
                            </p>

                            <p className="flex items-start gap-2 text-muted-foreground">
                                <MapPin className="mt-0.5 size-3.5 shrink-0" />
                                <span>
                                    {address.district} · {address.sector}
                                    {address.cell ? (
                                        <>, {address.cell}</>
                                    ) : null}
                                    {address.village ? (
                                        <>, {address.village}</>
                                    ) : null}
                                    {address.address_line ? (
                                        <span className="block">
                                            {address.address_line}
                                        </span>
                                    ) : null}
                                    {address.landmark ? (
                                        <span className="block font-medium text-foreground">
                                            Landmark: {address.landmark}
                                        </span>
                                    ) : null}
                                </span>
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Cash to collect
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Items
                                </span>
                                <Money
                                    amount={vendorOrder.subtotal}
                                    currency={vendorOrder.currency}
                                />
                            </div>
                            {vendorOrder.discount > 0 ? (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Discount
                                    </span>
                                    <span>
                                        −<Money amount={vendorOrder.discount} />
                                    </span>
                                </div>
                            ) : null}
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Your delivery fee
                                </span>
                                <Money amount={vendorOrder.shipping_fee} />
                            </div>
                            <Separator />
                            <div className="flex justify-between text-base font-semibold">
                                <span>Collect at the door</span>
                                <Money
                                    amount={vendorOrder.total}
                                    currency={vendorOrder.currency}
                                />
                            </div>

                            {vendorOrder.delivered_at ? (
                                <p className="pt-2 text-xs text-muted-foreground">
                                    Marked delivered{' '}
                                    {formatDateTime(vendorOrder.delivered_at)}.
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Items</CardTitle>
                    </CardHeader>
                    <CardContent className="divide-y divide-border">
                        {vendorOrder.items.map((item) => (
                            <div
                                key={item.id}
                                className="flex items-start justify-between gap-3 py-3 text-sm first:pt-0 last:pb-0"
                            >
                                <div className="min-w-0">
                                    <p className="font-medium">
                                        {item.product_name}
                                    </p>
                                    {item.variant_name ? (
                                        <p className="text-xs text-muted-foreground">
                                            {item.variant_name}
                                        </p>
                                    ) : null}
                                    {item.sku ? (
                                        <p className="text-xs text-muted-foreground">
                                            SKU {item.sku}
                                        </p>
                                    ) : null}
                                    <p className="text-xs text-muted-foreground">
                                        <Money amount={item.unit_price} /> ×{' '}
                                        {item.quantity}
                                    </p>
                                </div>
                                <Money amount={item.subtotal} />
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>

            <ConfirmDialog
                open={confirming !== null}
                onOpenChange={(open) => !open && setConfirming(null)}
                title={`Mark this order ${confirming?.label.toLowerCase()}?`}
                description={
                    confirming?.value === 'delivered'
                        ? 'This records that you handed the goods over and collected the cash. It also lets the customer review what they bought.'
                        : confirming?.value === 'cancelled'
                          ? 'The items go back into your stock and the customer is told the order will not arrive.'
                          : 'The customer will see the new status on their order.'
                }
                confirmLabel={confirming?.label ?? 'Confirm'}
                destructive={confirming?.value === 'cancelled'}
                onConfirm={() => confirming && transition(confirming.value)}
            />
        </AppLayout>
    );
}
