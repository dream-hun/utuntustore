import { Head, router } from '@inertiajs/react';
import { Ban, RotateCcw, Users } from 'lucide-react';
import { useState } from 'react';
import { AdminNav } from '@/components/admin/admin-nav';
import type { AdminCustomerRow } from '@/components/admin/types';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { DataTableColumn } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { Money } from '@/components/money';
import { RowActions } from '@/components/row-actions';
import { Badge } from '@/components/ui/badge';
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

const columns: DataTableColumn<AdminCustomerRow>[] = [
    {
        id: 'customer',
        header: 'Customer',
        cell: (customer) => (
            <>
                <p className="text-sm font-medium">{customer.name}</p>
                <p className="text-xs text-muted-foreground">
                    {customer.email}
                    {customer.phone ? ` · ${customer.phone}` : ''}
                </p>
            </>
        ),
    },
    {
        id: 'status',
        header: 'Status',
        cell: (customer) =>
            customer.status === 'active' ? (
                <Badge className="border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
                    Active
                </Badge>
            ) : (
                <Badge variant="destructive">Suspended</Badge>
            ),
    },
    {
        id: 'orders',
        header: 'Orders',
        cell: (customer) => (
            <span className="text-sm">{customer.orders_count}</span>
        ),
    },
    {
        id: 'joined',
        header: 'Joined',
        cell: (customer) => (
            <span className="text-xs text-muted-foreground">
                {formatDate(customer.created_at)}
            </span>
        ),
    },
    {
        id: 'spent',
        header: 'Spent with vendors',
        align: 'end',
        cell: (customer) => <Money amount={customer.orders_total} />,
    },
];

export default function AdminCustomers({
    customers,
    filters,
}: {
    customers: Paginated<AdminCustomerRow>;
    filters: { search: string | null; status: string | null };
}) {
    const [pending, setPending] = useState<AdminCustomerRow | null>(null);

    const { values, set, commit, clear, isFiltered } = useTableFilters({
        url: '/admin/customers',
        filters,
    });

    const suspending = pending?.status === 'active';

    const tableColumns: DataTableColumn<AdminCustomerRow>[] = [
        ...columns,
        {
            id: 'actions',
            header: 'Actions',
            headerHidden: true,
            headClassName: 'w-10',
            cell: (customer) => (
                <RowActions
                    rowLabel={customer.name}
                    groups={[
                        {
                            actions: [
                                customer.status === 'active'
                                    ? {
                                          label: 'Suspend',
                                          icon: Ban,
                                          destructive: true,
                                          onSelect: () => setPending(customer),
                                      }
                                    : {
                                          label: 'Reinstate',
                                          icon: RotateCcw,
                                          onSelect: () => setPending(customer),
                                      },
                            ],
                        },
                    ]}
                />
            ),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Customers', href: '/admin/customers' },
            ]}
        >
            <Head title="Customers" />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Customers
                </h1>

                <AdminNav />

                <div className="flex flex-wrap gap-3">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            commit();
                        }}
                        className="flex gap-2"
                    >
                        <label htmlFor="customer-search" className="sr-only">
                            Search customers
                        </label>
                        <Input
                            id="customer-search"
                            type="search"
                            value={values.search ?? ''}
                            onChange={(event) =>
                                set('search', event.target.value)
                            }
                            placeholder="Name, email or phone"
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
                        <SelectTrigger className="w-40" aria-label="Status">
                            <SelectValue placeholder="Any status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>Any status</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="suspended">Suspended</SelectItem>
                        </SelectContent>
                    </Select>

                    {isFiltered ? (
                        <Button type="button" variant="ghost" onClick={clear}>
                            Clear filters
                        </Button>
                    ) : null}
                </div>

                <DataTable
                    caption="Customers"
                    columns={tableColumns}
                    rows={customers.data}
                    getRowKey={(customer) => customer.id}
                    paginator={customers}
                    empty={
                        <EmptyState
                            icon={Users}
                            title="No customers found"
                            description={
                                isFiltered
                                    ? 'No customer matches these filters. Try widening the search.'
                                    : 'Customers appear here once someone registers on the storefront.'
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

            <ConfirmDialog
                open={pending !== null}
                onOpenChange={(open) => !open && setPending(null)}
                title={
                    suspending
                        ? 'Suspend this customer?'
                        : 'Reinstate this customer?'
                }
                description={
                    suspending
                        ? 'They will be signed out and refused everywhere until reinstated. Their orders and history are untouched.'
                        : 'They will be able to sign in and order again.'
                }
                confirmLabel={suspending ? 'Suspend' : 'Reinstate'}
                destructive={suspending}
                onConfirm={() => {
                    if (!pending) {
                        return;
                    }

                    router.patch(
                        `/admin/customers/${pending.id}/status`,
                        { status: suspending ? 'suspended' : 'active' },
                        {
                            preserveScroll: true,
                            onFinish: () => setPending(null),
                        },
                    );
                }}
            />
        </AppLayout>
    );
}
