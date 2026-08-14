import { Head, Link } from '@inertiajs/react';
import { Banknote, CheckCircle2, Store } from 'lucide-react';
import { Money } from '@/components/money';
import { OrderStatusBadge } from '@/components/status-badge';
import StorefrontLayout from '@/layouts/storefront-layout';
import { formatDate } from '@/lib/format';
import { shop } from '@/routes';
import { index as accountOrders } from '@/routes/account/orders';
import type { StorefrontOrder } from '@/types/marketplace';

export default function Confirmation({ order }: { order: StorefrontOrder }) {
    return (
        <StorefrontLayout>
            <Head title={`Order ${order.order_number}`} />

            <div className="mx-auto w-full max-w-3xl px-4 py-12 lg:py-16">
                <header className="text-center">
                    <CheckCircle2
                        className="mx-auto mb-5 size-12 text-primary"
                        aria-hidden="true"
                    />
                    <span className="eyebrow text-muted-foreground">
                        Order placed
                    </span>
                    <h1 className="mt-3 text-4xl md:text-5xl">Thank you.</h1>
                    <p className="mt-5 text-sm text-muted-foreground">
                        {order.order_number} · {formatDate(order.placed_at)}
                    </p>
                </header>

                <p className="mt-10 flex items-start gap-3 rounded-2xl bg-aqua-soft p-5 text-sm">
                    <Banknote
                        className="mt-0.5 size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    {order.vendor_orders.length === 1
                        ? 'Pay the shop in cash when it delivers.'
                        : `Your order is split across ${order.vendor_orders.length} shops. Each one delivers separately, and you pay each of them in cash at the door.`}
                </p>

                {/* Delivery address */}
                <section
                    className="mt-12"
                    aria-labelledby="delivering-to-heading"
                >
                    <h2
                        id="delivering-to-heading"
                        className="border-b border-border pb-4 text-lg"
                    >
                        Delivering to
                    </h2>
                    <div className="pt-5 text-sm">
                        <p className="font-semibold">
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
                    </div>
                </section>

                {/* Per-shop orders */}
                <div className="mt-12 space-y-10">
                    {order.vendor_orders.map((vendorOrder) => (
                        <section
                            key={vendorOrder.id}
                            aria-label={vendorOrder.vendor.shop_name}
                        >
                            <div className="border-b border-border pb-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <h2 className="flex items-center gap-2 text-lg">
                                        <Store
                                            className="size-4 text-primary"
                                            aria-hidden="true"
                                        />
                                        {vendorOrder.vendor.shop_name}
                                    </h2>
                                    <OrderStatusBadge
                                        status={vendorOrder.status}
                                    />
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {vendorOrder.order_number} ·{' '}
                                    {vendorOrder.vendor.phone}
                                </p>
                            </div>

                            <div className="space-y-3 pt-5">
                                {vendorOrder.items.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex items-start justify-between gap-3 text-sm"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-semibold">
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

                                <div className="flex justify-between border-t border-border pt-3 text-sm">
                                    <span className="text-muted-foreground">
                                        Delivery
                                    </span>
                                    <Money amount={vendorOrder.shipping_fee} />
                                </div>

                                <div className="flex justify-between gap-4 rounded-2xl bg-cream p-4 text-sm font-semibold">
                                    <span>Cash due to this shop</span>
                                    <Money amount={vendorOrder.total} />
                                </div>
                            </div>
                        </section>
                    ))}
                </div>

                {/* Totals */}
                <dl className="mt-12 space-y-3 rounded-2xl bg-cream p-8 text-sm">
                    <div className="flex justify-between">
                        <dt className="text-muted-foreground">Subtotal</dt>
                        <dd>
                            <Money
                                amount={order.subtotal}
                                currency={order.currency}
                            />
                        </dd>
                    </div>
                    {order.discount > 0 ? (
                        <div className="flex justify-between">
                            <dt className="text-muted-foreground">Discount</dt>
                            <dd>
                                −
                                <Money
                                    amount={order.discount}
                                    currency={order.currency}
                                />
                            </dd>
                        </div>
                    ) : null}
                    <div className="flex justify-between">
                        <dt className="text-muted-foreground">Delivery</dt>
                        <dd>
                            <Money
                                amount={order.shipping_fee}
                                currency={order.currency}
                            />
                        </dd>
                    </div>
                    <div className="flex justify-between border-t border-border pt-3 text-base font-semibold">
                        <dt>Total cash due</dt>
                        <dd>
                            <Money
                                amount={order.total}
                                currency={order.currency}
                            />
                        </dd>
                    </div>
                </dl>

                <div className="mt-10 flex flex-wrap justify-center gap-3">
                    <Link
                        href={shop.url()}
                        className="rounded-full border border-border px-6 py-3 text-sm font-semibold transition hover:border-primary hover:bg-aqua-soft"
                    >
                        Keep shopping
                    </Link>
                    <Link
                        href={accountOrders.url()}
                        className="rounded-full bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                    >
                        View my orders
                    </Link>
                </div>
            </div>
        </StorefrontLayout>
    );
}
