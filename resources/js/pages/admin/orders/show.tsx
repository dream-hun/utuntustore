import { Head, Link } from '@inertiajs/react';
import { Store } from 'lucide-react';
import { AdminNav } from '@/components/admin/admin-nav';
import type { AdminOrderDetail } from '@/components/admin/types';
import { Money } from '@/components/money';
import { OrderStatusBadge } from '@/components/status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatDateTime } from '@/lib/format';

export default function AdminOrderShow({ order }: { order: AdminOrderDetail }) {
    const address = order.shipping_address;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Orders', href: '/admin/orders' },
                {
                    title: order.order_number,
                    href: `/admin/orders/${order.id}`,
                },
            ]}
        >
            <Head title={order.order_number} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {order.order_number}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {formatDate(order.placed_at)} · Cash on delivery
                        </p>
                    </div>
                    <OrderStatusBadge status={order.status} />
                </div>

                <AdminNav />

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Customer
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <p className="font-medium">{order.customer.name}</p>
                            <p className="text-muted-foreground">
                                {order.customer.email}
                            </p>
                            {order.customer.phone ? (
                                <p className="text-muted-foreground">
                                    {order.customer.phone}
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Delivering to
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <p className="font-medium">{address.recipient}</p>
                            <p className="text-muted-foreground">
                                {address.phone}
                            </p>
                            <p className="text-muted-foreground">
                                {address.district} · {address.sector}
                                {address.cell ? `, ${address.cell}` : ''}
                                {address.village ? `, ${address.village}` : ''}
                            </p>
                            {address.address_line ? (
                                <p className="text-muted-foreground">
                                    {address.address_line}
                                </p>
                            ) : null}
                            {address.landmark ? (
                                <p className="text-muted-foreground">
                                    Near {address.landmark}
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>
                </div>

                {order.vendor_orders.map((vendorOrder) => (
                    <Card key={vendorOrder.id}>
                        <CardHeader>
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Store className="size-4" />
                                    <Link
                                        href={`/admin/vendors/${vendorOrder.vendor.id}`}
                                        className="hover:underline"
                                    >
                                        {vendorOrder.vendor.shop_name}
                                    </Link>
                                </CardTitle>
                                <OrderStatusBadge status={vendorOrder.status} />
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {vendorOrder.order_number} ·{' '}
                                {vendorOrder.vendor.phone}
                                {vendorOrder.delivered_at
                                    ? ` · Delivered ${formatDateTime(vendorOrder.delivered_at)}`
                                    : ''}
                            </p>
                        </CardHeader>

                        <CardContent className="space-y-3">
                            {vendorOrder.items.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex items-start justify-between gap-3 text-sm"
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
                                <span>Collected by this shop</span>
                                <Money amount={vendorOrder.total} />
                            </div>
                        </CardContent>
                    </Card>
                ))}

                <Card>
                    <CardContent className="space-y-2 text-sm">
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
                            <span>Gross merchandise value</span>
                            <Money
                                amount={order.total}
                                currency={order.currency}
                            />
                        </div>
                        <p className="text-xs text-muted-foreground">
                            This money went from the customer to the vendors.
                            The platform received none of it.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
