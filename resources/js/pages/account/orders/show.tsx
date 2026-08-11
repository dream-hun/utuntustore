import { Head } from '@inertiajs/react';
import { Money } from '@/components/money';
import { OrderStatusBadge } from '@/components/status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatDateTime } from '@/lib/format';
import type { OrderStatus } from '@/types/marketplace';

interface OrderDetail {
    id: string;
    order_number: string;
    status: OrderStatus;
    subtotal: number;
    discount: number;
    shipping_fee: number;
    total: number;
    currency: string;
    payment_method: string;
    placed_at: string | null;
    vendor_orders: {
        id: string;
        order_number: string;
        status: OrderStatus;
        subtotal: number;
        shipping_fee: number;
        total: number;
        delivered_at: string | null;
        vendor: { shop_name: string; slug: string; phone: string };
        items: {
            id: string;
            product_name: string;
            variant_name: string | null;
            unit_price: number;
            quantity: number;
            subtotal: number;
        }[];
    }[];
}

export default function AccountOrderShow({ order }: { order: OrderDetail }) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Orders', href: '/account/orders' },
                {
                    title: order.order_number,
                    href: `/account/orders/${order.id}`,
                },
            ]}
        >
            <Head title={order.order_number} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {order.order_number}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Placed {formatDate(order.placed_at)} · Cash on
                            delivery
                        </p>
                    </div>
                    <OrderStatusBadge status={order.status} />
                </div>

                {order.vendor_orders.map((vendorOrder) => (
                    <Card key={vendorOrder.id}>
                        <CardHeader>
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <CardTitle className="text-base">
                                    {vendorOrder.vendor.shop_name}
                                </CardTitle>
                                <OrderStatusBadge status={vendorOrder.status} />
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {vendorOrder.order_number}
                                {vendorOrder.delivered_at
                                    ? ` · Delivered ${formatDateTime(vendorOrder.delivered_at)}`
                                    : null}
                            </p>
                        </CardHeader>

                        <CardContent className="flex flex-col gap-3">
                            {vendorOrder.items.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex items-start justify-between gap-4 text-sm"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {item.product_name}
                                        </p>
                                        {item.variant_name ? (
                                            <p className="text-xs text-muted-foreground">
                                                {item.variant_name}
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

                            <Separator />

                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Delivery
                                </span>
                                <Money amount={vendorOrder.shipping_fee} />
                            </div>
                            <div className="flex justify-between text-sm font-semibold">
                                <span>
                                    Pay {vendorOrder.vendor.shop_name} on
                                    delivery
                                </span>
                                <Money amount={vendorOrder.total} />
                            </div>
                        </CardContent>
                    </Card>
                ))}

                <Card>
                    <CardContent className="flex flex-col gap-2 text-sm">
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Subtotal
                            </span>
                            <Money
                                amount={order.subtotal}
                                currency={order.currency}
                            />
                        </div>
                        {order.discount > 0 ? (
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Discount
                                </span>
                                <span>
                                    −
                                    <Money
                                        amount={order.discount}
                                        currency={order.currency}
                                    />
                                </span>
                            </div>
                        ) : null}
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Delivery
                            </span>
                            <Money
                                amount={order.shipping_fee}
                                currency={order.currency}
                            />
                        </div>
                        <Separator />
                        <div className="flex justify-between text-base font-semibold">
                            <span>Total</span>
                            <Money
                                amount={order.total}
                                currency={order.currency}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
