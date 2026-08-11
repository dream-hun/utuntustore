import { Head, Link } from '@inertiajs/react';
import { Package } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { Money } from '@/components/money';
import { PaginationNav } from '@/components/pagination-nav';
import { OrderStatusBadge } from '@/components/status-badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import type { OrderStatus, Paginated } from '@/types/marketplace';

interface AccountOrderRow {
    id: string;
    order_number: string;
    status: OrderStatus;
    total: number;
    currency: string;
    placed_at: string | null;
    vendor_orders: {
        id: string;
        order_number: string;
        status: OrderStatus;
        total: number;
        vendor: { shop_name: string; slug: string };
    }[];
}

export default function AccountOrders({
    orders,
}: {
    orders: Paginated<AccountOrderRow>;
}) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Orders', href: '/account/orders' }]}>
            <Head title="My orders" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        My orders
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        You pay each shop in cash when they deliver.
                    </p>
                </div>

                {orders.data.length === 0 ? (
                    <EmptyState
                        icon={Package}
                        title="No orders yet"
                        description="Orders you place will show up here, with a separate delivery for each shop."
                    />
                ) : (
                    <div className="flex flex-col gap-4">
                        {orders.data.map((order) => (
                            <Card key={order.id}>
                                <CardContent className="flex flex-col gap-4">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <Link
                                                href={`/account/orders/${order.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {order.order_number}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {formatDate(order.placed_at)}
                                            </p>
                                        </div>

                                        <div className="flex items-center gap-3">
                                            <OrderStatusBadge
                                                status={order.status}
                                            />
                                            <Money
                                                amount={order.total}
                                                currency={order.currency}
                                                className="font-semibold"
                                            />
                                        </div>
                                    </div>

                                    <div className="divide-y divide-border border-t border-border pt-3">
                                        {order.vendor_orders.map(
                                            (vendorOrder) => (
                                                <div
                                                    key={vendorOrder.id}
                                                    className="flex items-center justify-between gap-3 py-2 text-sm"
                                                >
                                                    <span className="text-muted-foreground">
                                                        {
                                                            vendorOrder.vendor
                                                                .shop_name
                                                        }
                                                    </span>
                                                    <div className="flex items-center gap-3">
                                                        <OrderStatusBadge
                                                            status={
                                                                vendorOrder.status
                                                            }
                                                        />
                                                        <Money
                                                            amount={
                                                                vendorOrder.total
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}

                        <PaginationNav paginator={orders} />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
