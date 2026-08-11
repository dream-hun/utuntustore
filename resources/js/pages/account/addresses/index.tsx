import { Head, router } from '@inertiajs/react';
import { MapPin, MoreHorizontal, Plus } from 'lucide-react';
import { useState } from 'react';
import { AccountNav } from '@/components/account/account-nav';
import { AddressFormModal } from '@/components/account/address-form-modal';
import type { AccountAddress } from '@/components/account/address-form-modal';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { PaginationNav } from '@/components/pagination-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import account from '@/routes/account';
import addresses from '@/routes/account/addresses';
import type { District, Paginated, Sector } from '@/types/marketplace';

export default function AccountAddresses({
    addresses: paginator,
    districts,
    sectors,
}: {
    addresses: Paginated<AccountAddress>;
    districts: District[];
    sectors: Sector[];
}) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<AccountAddress | null>(null);
    const [deleting, setDeleting] = useState<AccountAddress | null>(null);
    const [processing, setProcessing] = useState(false);
    const [sectorsLoading, setSectorsLoading] = useState(false);

    /**
     * Pull one district's sectors. All 416 are never sent at once — this is a partial
     * reload of the `sectors` prop only, so the rest of the page is untouched.
     */
    const loadSectors = (districtId: string) => {
        router.reload({
            only: ['sectors'],
            data: { district: districtId },
            onStart: () => setSectorsLoading(true),
            onFinish: () => setSectorsLoading(false),
        });
    };

    const openCreate = () => {
        setEditing(null);
        setFormOpen(true);
    };

    const openEdit = (address: AccountAddress) => {
        setEditing(address);
        loadSectors(address.district.id);
        setFormOpen(true);
    };

    const setDefault = (address: AccountAddress) => {
        router.post(
            addresses.default.url(address.id),
            {},
            { preserveScroll: true },
        );
    };

    const confirmDelete = () => {
        if (!deleting) {
            return;
        }

        router.delete(addresses.destroy.url(deleting.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setDeleting(null);
            },
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Account', href: account.index.url() },
                { title: 'Addresses', href: addresses.index.url() },
            ]}
        >
            <Head title="Delivery addresses" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Delivery addresses
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Vendors only deliver to districts and sectors they
                            cover, so both are required. The landmark is usually
                            what actually gets a delivery to your door.
                        </p>
                    </div>

                    <Button onClick={openCreate}>
                        <Plus className="size-4" />
                        Add address
                    </Button>
                </div>

                <AccountNav />

                {paginator.data.length === 0 ? (
                    <EmptyState
                        icon={MapPin}
                        title="No addresses yet"
                        description="Add where you want deliveries to arrive. Checkout needs one before you can order."
                        action={
                            <Button onClick={openCreate}>
                                <Plus className="size-4" />
                                Add address
                            </Button>
                        }
                    />
                ) : (
                    <Card>
                        <CardContent className="p-0">
                            <div className="w-full overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Recipient</TableHead>
                                            <TableHead>Where</TableHead>
                                            <TableHead>Landmark</TableHead>
                                            <TableHead className="w-12" />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {paginator.data.map((address) => (
                                            <TableRow key={address.id}>
                                                <TableCell>
                                                    <div className="flex flex-col gap-1">
                                                        <span className="font-medium">
                                                            {address.first_name}{' '}
                                                            {address.last_name}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {address.phone}
                                                        </span>
                                                        {address.is_default ? (
                                                            <Badge
                                                                variant="outline"
                                                                className="w-fit"
                                                            >
                                                                Default
                                                            </Badge>
                                                        ) : null}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-sm">
                                                    <div className="flex flex-col">
                                                        <span>
                                                            {
                                                                address.sector
                                                                    .name
                                                            }
                                                            ,{' '}
                                                            {
                                                                address.district
                                                                    .name
                                                            }
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {[
                                                                address.cell,
                                                                address.village,
                                                                address.address_line,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' · ') ||
                                                                '—'}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="max-w-56 text-sm text-muted-foreground">
                                                    {address.landmark ?? '—'}
                                                </TableCell>
                                                <TableCell>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Actions for ${address.first_name} ${address.last_name}`}
                                                            >
                                                                <MoreHorizontal className="size-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    openEdit(
                                                                        address,
                                                                    )
                                                                }
                                                            >
                                                                Edit
                                                            </DropdownMenuItem>
                                                            {address.is_default ? null : (
                                                                <DropdownMenuItem
                                                                    onSelect={() =>
                                                                        setDefault(
                                                                            address,
                                                                        )
                                                                    }
                                                                >
                                                                    Set as
                                                                    default
                                                                </DropdownMenuItem>
                                                            )}
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                onSelect={() =>
                                                                    setDeleting(
                                                                        address,
                                                                    )
                                                                }
                                                            >
                                                                Delete
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <PaginationNav paginator={paginator} />
            </div>

            {formOpen ? (
                <AddressFormModal
                    key={editing?.id ?? 'new'}
                    open={formOpen}
                    onOpenChange={setFormOpen}
                    address={editing}
                    districts={districts}
                    sectors={sectors}
                    sectorsLoading={sectorsLoading}
                    onDistrictChange={loadSectors}
                />
            ) : null}

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => (open ? null : setDeleting(null))}
                title="Delete this address?"
                description="It will no longer be offered at checkout. Addresses already attached to an order are kept as part of that order's history and cannot be deleted."
                confirmLabel="Delete address"
                onConfirm={confirmDelete}
                processing={processing}
            />
        </AppLayout>
    );
}
