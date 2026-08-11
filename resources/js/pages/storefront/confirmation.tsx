import { Head, Link } from '@inertiajs/react';
import { Banknote, CheckCircle2, Store } from 'lucide-react';
import { Money } from '@/components/money';
import { OrderStatusBadge } from '@/components/status-badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import StorefrontLayout from '@/layouts/storefront-layout';
import { formatDate } from '@/lib/format';
import { shop } from '@/routes';
import { index as accountOrders } from '@/routes/account/orders';
import type { StorefrontOrder } from '@/types/marketplace';

export default function Confirmation({ order }: { order: StorefrontOrder }) {
    return (
        <StorefrontLayout>
            <Head title={`Order ${order.order_number}`} />

            <div className="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6">
                <div className="mb-8 text-center">
                    <CheckCircle2 className="mx-auto mb-3 size-12 text-emerald-500" />
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Order placed
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {order.order_number} · {formatDate(order.placed_at)}
                    </p>
                </div>

                <Alert className="mb-6">
                    <Banknote className="size-4" />
                    <AlertDescription>
                        {order.vendor_orders.length === 1
                            ? 'Pay the shop in cash when they deliver.'
                            : `Your order is split across ${order.vendor_orders.length} shops. Each one delivers separately, and you pay each of them in cash at the door.`}
                    </AlertDescription>
                </Alert>

                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Delivering to
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm">
                        <p className="font-medium">
                            {order.shipping_address.first_name}{' '}
                            {order.shipping_address.last_name}
                        </p>
                        <p className="text-muted-foreground">
                            {order.shipping_address.district.name} ·{' '}
                            {order.shipping_address.sector.name}
                        </p>
                        {order.shipping_address.address_line ? (
                            <p className="text-muted-foreground">
                                {order.shipping_address.address_line}
                            </p>
                        ) : null}
                        {order.shipping_address.landmark ? (
                            <p className="text-muted-foreground">
                                Near {order.shipping_address.landmark}
                            </p>
                        ) : null}
                        <p className="text-muted-foreground">
                            {order.shipping_address.phone}
                        </p>
                    </CardContent>
                </Card>

                <div className="space-y-4">
                    {order.vendor_orders.map((vendorOrder) => (
                        <Card key={vendorOrder.id}>
                            <CardHeader>
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Store className="size-4" />
                                        {vendorOrder.vendor.shop_name}
                                    </CardTitle>
                                    <OrderStatusBadge
                                        status={vendorOrder.status}
                                    />
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {vendorOrder.order_number} ·{' '}
                                    {vendorOrder.vendor.phone}
                                </p>
                            </CardHeader>

                            <CardContent className="space-y-3">
                                {vendorOrder.items.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex items-start justify-between gap-3 text-sm"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {item.product_name}
                                            </p>
                                            {item.variant_name ? (
                                                <p className="text-xs text-muted-foreground">
                                                    {item.variant_name}
                                                </p>
                                            ) : null}
                                            <p className="text-xs text-muted-foreground">
                                                <Money
                                                    amount={item.unit_price}
                                                />{' '}
                                                × {item.quantity}
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

                                <div className="flex justify-between rounded-md bg-muted/50 p-3 text-sm font-semibold">
                                    <span>Cash due to this shop</span>
                                    <Money amount={vendorOrder.total} />
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card className="mt-6">
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
                            <span>Total cash due</span>
                            <Money
                                amount={order.total}
                                currency={order.currency}
                            />
                        </div>
                    </CardContent>
                </Card>

                <div className="mt-8 flex flex-wrap justify-center gap-3">
                    <Button asChild variant="outline">
                        <Link href={shop.url()}>Keep shopping</Link>
                    </Button>
                    <Button asChild>
                        <Link href={accountOrders.url()}>View my orders</Link>
                    </Button>
                </div>
            </div>
        </StorefrontLayout>
    );
}
