import { Head, Link } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import { AdminNav } from '@/components/admin/admin-nav';
import type { AdminOrderRow } from '@/components/admin/types';
import type { DataTableColumn } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { Money } from '@/components/money';
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
import { useTableFilters } from '@/hooks/use-table-filters';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import type { Paginated } from '@/types/marketplace';

const ANY = 'any';

const columns: DataTableColumn<AdminOrderRow>[] = [
    {
        id: 'order',
        header: 'Order',
        cell: (order) => (
            <Link
                href={`/admin/orders/${order.id}`}
                className="text-sm font-medium hover:underline"
            >
                {order.order_number}
            </Link>
        ),
    },
    {
        id: 'customer',
        header: 'Customer',
        cell: (order) => <span className="text-sm">{order.customer_name}</span>,
    },
    {
        id: 'shops',
        header: 'Shops',
        cell: (order) => (
            <span className="text-xs text-muted-foreground">
                {order.vendor_orders.length}
            </span>
        ),
    },
    {
        id: 'status',
        header: 'Status',
        cell: (order) => <OrderStatusBadge status={order.status} />,
    },
    {
        id: 'placed',
        header: 'Placed',
        cell: (order) => (
            <span className="text-xs text-muted-foreground">
                {formatDate(order.placed_at)}
            </span>
        ),
    },
    {
        id: 'value',
        header: 'Value',
        align: 'end',
        cell: (order) => (
            <Money amount={order.total} currency={order.currency} />
        ),
    },
];

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
    const { values, set, commit, clear, isFiltered } = useTableFilters({
        url: '/admin/orders',
        filters,
    });

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
                            commit();
                        }}
                        className="flex gap-2"
                    >
                        <label htmlFor="order-search" className="sr-only">
                            Search orders
                        </label>
                        <Input
                            id="order-search"
                            type="search"
                            value={values.search ?? ''}
                            onChange={(event) =>
                                set('search', event.target.value)
                            }
                            placeholder="Order number or customer"
                            className="w-60"
                        />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>

                    <Select
                        value={values.status ?? ANY}
                        onValueChange={(value) =>
                            set('status', value === ANY ? null : value, true)
                        }
                    >
                        <SelectTrigger className="w-44" aria-label="Status">
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

                    <div className="flex items-center gap-2">
                        <label htmlFor="order-from" className="sr-only">
                            Placed from
                        </label>
                        <Input
                            id="order-from"
                            type="date"
                            value={values.from ?? ''}
                            onChange={(event) =>
                                set('from', event.target.value || null, true)
                            }
                            className="w-40"
                        />
                        <span
                            aria-hidden="true"
                            className="text-sm text-muted-foreground"
                        >
                            –
                        </span>
                        <label htmlFor="order-to" className="sr-only">
                            Placed until
                        </label>
                        <Input
                            id="order-to"
                            type="date"
                            value={values.to ?? ''}
                            onChange={(event) =>
                                set('to', event.target.value || null, true)
                            }
                            className="w-40"
                        />
                    </div>

                    {isFiltered ? (
                        <Button type="button" variant="ghost" onClick={clear}>
                            Clear filters
                        </Button>
                    ) : null}
                </div>

                <DataTable
                    caption="Orders"
                    columns={columns}
                    rows={orders.data}
                    getRowKey={(order) => order.id}
                    paginator={orders}
                    empty={
                        <EmptyState
                            icon={Receipt}
                            title="No orders found"
                            description={
                                isFiltered
                                    ? 'No order matches these filters. Try a different status or date range.'
                                    : 'Orders appear here as customers buy from vendors.'
                            }
                            action={
                                isFiltered ? (
                                    <Button variant="outline" onClick={clear}>
                                        Clear filters
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>
        </AppLayout>
    );
}
