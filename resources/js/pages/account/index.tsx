import { Deferred, Head, Link } from '@inertiajs/react';
import { Heart, MapPin, Package, Star } from 'lucide-react';
import { AccountNav } from '@/components/account/account-nav';
import { EmptyState } from '@/components/empty-state';
import { Money } from '@/components/money';
import { OrderStatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import account from '@/routes/account';
import type { OrderStatus } from '@/types/marketplace';

interface RecentOrder {
    id: string;
    order_number: string;
    status: OrderStatus;
    total: number;
    currency: string;
    placed_at: string | null;
    shops: string[];
}

interface DefaultAddress {
    id: string;
    recipient: string;
    phone: string;
    district: string;
    sector: string;
    landmark: string | null;
}

interface AwaitingReview {
    count: number;
    items: {
        id: string;
        product_name: string;
        variant_name: string | null;
        delivered_at: string | null;
    }[];
}

export default function AccountOverview({
    recentOrders,
    defaultAddress,
    wishlistCount,
    awaitingReview,
}: {
    recentOrders?: RecentOrder[];
    defaultAddress?: DefaultAddress | null;
    wishlistCount?: number;
    awaitingReview?: AwaitingReview;
}) {
    return (
        <AppLayout
            breadcrumbs={[{ title: 'Account', href: account.index.url() }]}
        >
            <Head title="My account" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        My account
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Your orders, delivery addresses and saved products.
                    </p>
                </div>

                <AccountNav />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <MapPin className="size-4" />
                                Default delivery address
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3 text-sm">
                            <Deferred
                                data="defaultAddress"
                                fallback={<Skeleton className="h-16 w-full" />}
                            >
                                {defaultAddress ? (
                                    <div className="text-muted-foreground">
                                        <p className="font-medium text-foreground">
                                            {defaultAddress.recipient}
                                        </p>
                                        <p>
                                            {defaultAddress.sector},{' '}
                                            {defaultAddress.district}
                                        </p>
                                        {defaultAddress.landmark ? (
                                            <p>{defaultAddress.landmark}</p>
                                        ) : null}
                                        <p>{defaultAddress.phone}</p>
                                    </div>
                                ) : (
                                    <p className="text-muted-foreground">
                                        You have not saved an address yet.
                                        Checkout needs one.
                                    </p>
                                )}
                            </Deferred>

                            <Button
                                variant="outline"
                                size="sm"
                                asChild
                                className="w-fit"
                            >
                                <Link href={account.addresses.index.url()}>
                                    Manage addresses
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Heart className="size-4" />
                                Wishlist
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3 text-sm">
                            <Deferred
                                data="wishlistCount"
                                fallback={<Skeleton className="h-8 w-16" />}
                            >
                                <p className="text-2xl font-semibold">
                                    {wishlistCount ?? 0}
                                </p>
                            </Deferred>
                            <p className="text-muted-foreground">
                                products saved for later
                            </p>
                            <Button
                                variant="outline"
                                size="sm"
                                asChild
                                className="w-fit"
                            >
                                <Link href={account.wishlist.index.url()}>
                                    View wishlist
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Star className="size-4" />
                                Waiting for your review
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3 text-sm">
                            <Deferred
                                data="awaitingReview"
                                fallback={<Skeleton className="h-16 w-full" />}
                            >
                                <div className="flex flex-col gap-1">
                                    <p className="text-2xl font-semibold">
                                        {awaitingReview?.count ?? 0}
                                    </p>
                                    <ul className="list-inside list-disc text-muted-foreground">
                                        {(awaitingReview?.items ?? []).map(
                                            (item) => (
                                                <li
                                                    key={item.id}
                                                    className="truncate"
                                                >
                                                    {item.product_name}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </div>
                            </Deferred>

                            <Button
                                variant="outline"
                                size="sm"
                                asChild
                                className="w-fit"
                            >
                                <Link href={account.reviews.index.url()}>
                                    Write a review
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Package className="size-4" />
                                Recent orders
                            </CardTitle>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={account.orders.index.url()}>
                                    All orders
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Deferred
                            data="recentOrders"
                            fallback={
                                <div className="flex flex-col gap-3">
                                    <Skeleton className="h-12 w-full" />
                                    <Skeleton className="h-12 w-full" />
                                    <Skeleton className="h-12 w-full" />
                                </div>
                            }
                        >
                            {(recentOrders ?? []).length === 0 ? (
                                <EmptyState
                                    icon={Package}
                                    title="No orders yet"
                                    description="When you order, each shop delivers separately and you pay them in cash at the door."
                                />
                            ) : (
                                <ul className="divide-y divide-border">
                                    {(recentOrders ?? []).map((order) => (
                                        <li
                                            key={order.id}
                                            className="flex flex-wrap items-center justify-between gap-3 py-3"
                                        >
                                            <div className="min-w-0">
                                                <Link
                                                    href={account.orders.show.url(
                                                        order.id,
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {order.order_number}
                                                </Link>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {formatDate(
                                                        order.placed_at,
                                                    )}{' '}
                                                    · {order.shops.join(', ')}
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
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Deferred>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
