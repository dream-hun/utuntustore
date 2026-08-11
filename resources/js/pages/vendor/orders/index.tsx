import { Head, Link, router } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Money } from '@/components/money';
import { PaginationNav } from '@/components/pagination-nav';
import { OrderStatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { SubscriptionBanner } from '@/components/vendor/subscription-banner';
import { VendorNav } from '@/components/vendor/vendor-nav';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import type { OrderStatus, Paginated } from '@/types/marketplace';

interface VendorOrderRow {
    id: string;
    order_number: string;
    status: OrderStatus;
    subtotal: number;
    shipping_fee: number;
    total: number;
    item_count: number;
    delivered_at: string | null;
    created_at: string;
    customer_name: string;
    allowed_transitions: { value: string; label: string }[];
}

const ANY = 'any';

export default function VendorOrders({
    orders,
    filters,
}: {
    orders: Paginated<VendorOrderRow>;
    filters: { search: string | null; status: string | null };
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const apply = (next: Record<string, string | undefined>) => {
        router.get(
            '/vendor/orders',
            {
                search: search || undefined,
                status: filters.status ?? undefined,
                ...next,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Vendor', href: '/vendor' },
                { title: 'Orders', href: '/vendor/orders' },
            ]}
        >
            <Head title="Orders" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Orders
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Only your own items. You collect the cash on delivery.
                    </p>
                </div>

                <SubscriptionBanner />
                <VendorNav />

                <div className="flex flex-wrap gap-3">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            apply({});
                        }}
                        className="flex gap-2"
                    >
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Order number or customer"
                            className="w-60"
                        />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>

                    <Select
                        value={filters.status ?? ANY}
                        onValueChange={(value) =>
                            apply({ status: value === ANY ? undefined : value })
                        }
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder="Any status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>Any status</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                            <SelectItem value="confirmed">Confirmed</SelectItem>
                            <SelectItem value="processing">
                                Processing
                            </SelectItem>
                            <SelectItem value="shipped">Shipped</SelectItem>
                            <SelectItem value="delivered">Delivered</SelectItem>
                            <SelectItem value="cancelled">Cancelled</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {orders.data.length === 0 ? (
                    <EmptyState
                        icon={Receipt}
                        title="No orders yet"
                        description="Orders for your products will appear here as customers place them."
                    />
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Order</TableHead>
                                        <TableHead>Customer</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Placed</TableHead>
                                        <TableHead className="text-right">
                                            Cash due
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {orders.data.map((order) => (
                                        <TableRow key={order.id}>
                                            <TableCell>
                                                <Link
                                                    href={`/vendor/orders/${order.id}`}
                                                    className="text-sm font-medium hover:underline"
                                                >
                                                    {order.order_number}
                                                </Link>
                                                <p className="text-xs text-muted-foreground">
                                                    {order.item_count} item
                                                    {order.item_count === 1
                                                        ? ''
                                                        : 's'}
                                                </p>
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {order.customer_name}
                                            </TableCell>
                                            <TableCell>
                                                <OrderStatusBadge
                                                    status={order.status}
                                                />
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {formatDate(order.created_at)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Money amount={order.total} />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <PaginationNav paginator={orders} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
