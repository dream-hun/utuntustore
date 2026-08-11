import { Head, Link, router } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import { useState } from 'react';
import { AdminNav } from '@/components/admin/admin-nav';
import type { AdminOrderRow } from '@/components/admin/types';
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
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import type { Paginated } from '@/types/marketplace';

const ANY = 'any';

export default function AdminOrders({
    orders,
    filters,
}: {
    orders: Paginated<AdminOrderRow>;
    filters: {
        status: string | null;
        search: string | null;
        from: string | null;
        to: string | null;
    };
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const apply = (next: Record<string, string | undefined>) => {
        router.get(
            '/admin/orders',
            {
                search: search || undefined,
                status: filters.status ?? undefined,
                from: filters.from ?? undefined,
                to: filters.to ?? undefined,
                ...next,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Orders', href: '/admin/orders' },
            ]}
        >
            <Head title="Orders" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Orders
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Oversight only. Order value is money that moved between
                        customers and vendors — never platform revenue.
                    </p>
                </div>

                <AdminNav />

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

                    <Input
                        type="date"
                        value={filters.from ?? ''}
                        onChange={(event) =>
                            apply({ from: event.target.value || undefined })
                        }
                        className="w-40"
                    />
                    <Input
                        type="date"
                        value={filters.to ?? ''}
                        onChange={(event) =>
                            apply({ to: event.target.value || undefined })
                        }
                        className="w-40"
                    />
                </div>

                {orders.data.length === 0 ? (
                    <EmptyState
                        icon={Receipt}
                        title="No orders found"
                        description="Try a different filter or date range."
                    />
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Order</TableHead>
                                        <TableHead>Customer</TableHead>
                                        <TableHead>Shops</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Placed</TableHead>
                                        <TableHead className="text-right">
                                            Value
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {orders.data.map((order) => (
                                        <TableRow key={order.id}>
                                            <TableCell>
                                                <Link
                                                    href={`/admin/orders/${order.id}`}
                                                    className="text-sm font-medium hover:underline"
                                                >
                                                    {order.order_number}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {order.customer_name}
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {order.vendor_orders.length}
                                            </TableCell>
                                            <TableCell>
                                                <OrderStatusBadge
                                                    status={order.status}
                                                />
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {formatDate(order.placed_at)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Money
                                                    amount={order.total}
                                                    currency={order.currency}
                                                />
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
