import { Head, router } from '@inertiajs/react';
import { MoreHorizontal, Users } from 'lucide-react';
import { useState } from 'react';
import { AdminNav } from '@/components/admin/admin-nav';
import type { AdminCustomerRow } from '@/components/admin/types';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { Money } from '@/components/money';
import { PaginationNav } from '@/components/pagination-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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

export default function AdminCustomers({
    customers,
    filters,
}: {
    customers: Paginated<AdminCustomerRow>;
    filters: { search: string | null; status: string | null };
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [pending, setPending] = useState<AdminCustomerRow | null>(null);

    const apply = (next: Record<string, string | undefined>) => {
        router.get(
            '/admin/customers',
            {
                search: search || undefined,
                status: filters.status ?? undefined,
                ...next,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const suspending = pending?.status === 'active';

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
                            apply({});
                        }}
                        className="flex gap-2"
                    >
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Name, email or phone"
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
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Any status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>Any status</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="suspended">Suspended</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {customers.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="No customers found"
                        description="Try a different search."
                    />
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Customer</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Orders</TableHead>
                                        <TableHead>Joined</TableHead>
                                        <TableHead className="text-right">
                                            Spent with vendors
                                        </TableHead>
                                        <TableHead className="w-10" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {customers.data.map((customer) => (
                                        <TableRow key={customer.id}>
                                            <TableCell>
                                                <p className="text-sm font-medium">
                                                    {customer.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {customer.email}
                                                    {customer.phone
                                                        ? ` · ${customer.phone}`
                                                        : ''}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                {customer.status ===
                                                'active' ? (
                                                    <Badge className="border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
                                                        Active
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="destructive">
                                                        Suspended
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {customer.orders_count}
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {formatDate(
                                                    customer.created_at,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Money
                                                    amount={
                                                        customer.orders_total
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                        >
                                                            <MoreHorizontal className="size-4" />
                                                            <span className="sr-only">
                                                                Actions
                                                            </span>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            variant={
                                                                customer.status ===
                                                                'active'
                                                                    ? 'destructive'
                                                                    : 'default'
                                                            }
                                                            onSelect={() =>
                                                                setPending(
                                                                    customer,
                                                                )
                                                            }
                                                        >
                                                            {customer.status ===
                                                            'active'
                                                                ? 'Suspend'
                                                                : 'Reinstate'}
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <PaginationNav paginator={customers} />
                    </>
                )}
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
