import { Deferred, Head, Link } from '@inertiajs/react';
import { AlertTriangle, Banknote, PackageCheck, Truck } from 'lucide-react';
import { Money } from '@/components/money';
import { OrderStatusBadge } from '@/components/status-badge';
import { SubscriptionBanner } from '@/components/vendor/subscription-banner';
import { VendorNav } from '@/components/vendor/vendor-nav';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import type { OrderStatus } from '@/types/marketplace';

interface Stats {
    orders_needing_action: number;
    orders_in_delivery: number;
    low_stock_count: number;
    out_of_stock_count: number;
    published_count: number;
    draft_count: number;
    cash_collected_this_month: number;
    delivered_this_month: number;
    active_delivery_areas: number;
    currency: string;
}

interface ActionableOrder {
    id: string;
    order_number: string;
    status: OrderStatus;
    total: number;
    item_count: number;
    created_at: string;
}

interface LowStockProduct {
    id: string;
    name: string;
    stock_quantity: number;
    low_stock_threshold: number;
    status: string;
}

function StatTile({
    label,
    value,
    hint,
    icon: Icon,
}: {
    label: string;
    value: React.ReactNode;
    hint?: string;
    icon: typeof Banknote;
}) {
    return (
        <Card>
            <CardContent className="flex items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted">
                    <Icon className="size-4 text-muted-foreground" />
                </div>
                <div className="min-w-0">
                    <p className="text-xs text-muted-foreground">{label}</p>
                    <p className="text-xl font-semibold">{value}</p>
                    {hint ? (
                        <p className="text-xs text-muted-foreground">{hint}</p>
                    ) : null}
                </div>
            </CardContent>
        </Card>
    );
}

export default function VendorDashboard({
    shopName,
    stats,
    actionableOrders,
    lowStockProducts,
}: {
    shopName: string;
    subscriptionFee: number;
    stats?: Stats;
    actionableOrders?: ActionableOrder[];
    lowStockProducts?: LowStockProduct[];
}) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Vendor', href: '/vendor' }]}>
            <Head title="Vendor dashboard" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {shopName}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        You deliver your own orders and collect the cash
                        yourself.
                    </p>
                </div>

                <SubscriptionBanner />
                <VendorNav />

                <Deferred
                    data="stats"
                    fallback={
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {Array.from({ length: 4 }).map((_, index) => (
                                <Skeleton key={index} className="h-24 w-full" />
                            ))}
                        </div>
                    }
                >
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatTile
                            icon={PackageCheck}
                            label="Orders needing action"
                            value={stats?.orders_needing_action ?? 0}
                            hint="Accept, pack or send"
                        />
                        <StatTile
                            icon={Truck}
                            label="Out for delivery"
                            value={stats?.orders_in_delivery ?? 0}
                        />
                        <StatTile
                            icon={Banknote}
                            label="Cash collected this month"
                            value={
                                <Money
                                    amount={
                                        stats?.cash_collected_this_month ?? 0
                                    }
                                    currency={stats?.currency}
                                />
                            }
                            hint={`${stats?.delivered_this_month ?? 0} delivered`}
                        />
                        <StatTile
                            icon={AlertTriangle}
                            label="Low stock"
                            value={stats?.low_stock_count ?? 0}
                            hint={`${stats?.out_of_stock_count ?? 0} out of stock`}
                        />
                    </div>
                </Deferred>

                {stats && stats.active_delivery_areas === 0 ? (
                    <Card className="border-amber-500/30 bg-amber-500/5">
                        <CardContent className="text-sm">
                            You have no active delivery areas, so nobody can
                            order from you yet.{' '}
                            <Link
                                href="/vendor/delivery"
                                className="font-medium underline"
                            >
                                Set where you deliver
                            </Link>
                            .
                        </CardContent>
                    </Card>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Orders to act on
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Deferred
                                data="actionableOrders"
                                fallback={
                                    <div className="space-y-2">
                                        {Array.from({ length: 3 }).map(
                                            (_, i) => (
                                                <Skeleton
                                                    key={i}
                                                    className="h-10 w-full"
                                                />
                                            ),
                                        )}
                                    </div>
                                }
                            >
                                {(actionableOrders ?? []).length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Nothing waiting. Good place to be.
                                    </p>
                                ) : (
                                    <div className="divide-y divide-border">
                                        {(actionableOrders ?? []).map(
                                            (order) => (
                                                <Link
                                                    key={order.id}
                                                    href={`/vendor/orders/${order.id}`}
                                                    className="-mx-2 flex items-center justify-between gap-3 rounded-md px-2 py-2 text-sm transition-colors hover:bg-accent/50"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate font-medium">
                                                            {order.order_number}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {order.item_count}{' '}
                                                            item
                                                            {order.item_count ===
                                                            1
                                                                ? ''
                                                                : 's'}{' '}
                                                            ·{' '}
                                                            {formatDate(
                                                                order.created_at,
                                                            )}
                                                        </p>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <OrderStatusBadge
                                                            status={
                                                                order.status
                                                            }
                                                        />
                                                        <Money
                                                            amount={order.total}
                                                        />
                                                    </div>
                                                </Link>
                                            ),
                                        )}
                                    </div>
                                )}
                            </Deferred>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Running low
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Deferred
                                data="lowStockProducts"
                                fallback={
                                    <div className="space-y-2">
                                        {Array.from({ length: 3 }).map(
                                            (_, i) => (
                                                <Skeleton
                                                    key={i}
                                                    className="h-10 w-full"
                                                />
                                            ),
                                        )}
                                    </div>
                                }
                            >
                                {(lowStockProducts ?? []).length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Stock levels are healthy.
                                    </p>
                                ) : (
                                    <div className="divide-y divide-border">
                                        {(lowStockProducts ?? []).map(
                                            (product) => (
                                                <div
                                                    key={product.id}
                                                    className="flex items-center justify-between gap-3 py-2 text-sm"
                                                >
                                                    <span className="min-w-0 truncate">
                                                        {product.name}
                                                    </span>
                                                    <span
                                                        className={
                                                            product.stock_quantity ===
                                                            0
                                                                ? 'font-medium text-destructive'
                                                                : 'font-medium text-amber-600 dark:text-amber-400'
                                                        }
                                                    >
                                                        {product.stock_quantity}{' '}
                                                        left
                                                    </span>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                )}
                            </Deferred>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
