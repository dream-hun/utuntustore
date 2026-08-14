import { Head, Link } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
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
import { SubscriptionBanner } from '@/components/vendor/subscription-banner';
import { VendorNav } from '@/components/vendor/vendor-nav';
import { useTableFilters } from '@/hooks/use-table-filters';
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

const columns: DataTableColumn<VendorOrderRow>[] = [
    {
        id: 'order',
        header: 'Order',
        cell: (order) => (
            <>
                <Link
                    href={`/vendor/orders/${order.id}`}
                    className="text-sm font-medium hover:underline"
                >
                    {order.order_number}
                </Link>
                <p className="text-xs text-muted-foreground">
                    {order.item_count} item{order.item_count === 1 ? '' : 's'}
                </p>
            </>
        ),
    },
    {
        id: 'customer',
        header: 'Customer',
        cellClassName: 'text-sm',
        cell: (order) => order.customer_name,
    },
    {
        id: 'status',
        header: 'Status',
        cell: (order) => <OrderStatusBadge status={order.status} />,
    },
    {
        id: 'placed',
        header: 'Placed',
        cellClassName: 'text-xs text-muted-foreground',
        cell: (order) => formatDate(order.created_at),
    },
    {
        id: 'cash_due',
        header: 'Cash due',
        align: 'end',
        cell: (order) => <Money amount={order.total} />,
    },
];

export default function VendorOrders({
    orders,
    filters,
}: {
    orders: Paginated<VendorOrderRow>;
    filters: { search: string | null; status: string | null };
}) {
    const { values, set, commit, clear, isFiltered } = useTableFilters({
        url: '/vendor/orders',
        filters,
    });

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
                            commit();
                        }}
                        className="flex gap-2"
                    >
                        <label
                            htmlFor="vendor-order-search"
                            className="sr-only"
                        >
                            Search orders
                        </label>
                        <Input
                            id="vendor-order-search"
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
                            title={
                                isFiltered ? 'No orders found' : 'No orders yet'
                            }
                            description={
                                isFiltered
                                    ? 'No order matches these filters.'
                                    : 'Orders for your products will appear here as customers place them.'
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
