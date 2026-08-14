import { Head, router } from '@inertiajs/react';
import { MapPin, Pencil, Plus, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AccountNav } from '@/components/account/account-nav';
import { AddressFormModal } from '@/components/account/address-form-modal';
import type { AccountAddress } from '@/components/account/address-form-modal';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { DataTableColumn } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import type { RowAction } from '@/components/row-actions';
import { RowActions } from '@/components/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

    const columns: DataTableColumn<AccountAddress>[] = [
        {
            id: 'recipient',
            header: 'Recipient',
            cell: (address) => (
                <div className="flex flex-col gap-1">
                    <span className="font-medium">
                        {address.first_name} {address.last_name}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {address.phone}
                    </span>
                    {address.is_default ? (
                        <Badge variant="outline" className="w-fit">
                            Default
                        </Badge>
                    ) : null}
                </div>
            ),
        },
        {
            id: 'where',
            header: 'Where',
            cellClassName: 'text-sm',
            cell: (address) => (
                <div className="flex flex-col">
                    <span>
                        {address.sector.name}, {address.district.name}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {[address.cell, address.village, address.address_line]
                            .filter(Boolean)
                            .join(' · ') || '—'}
                    </span>
                </div>
            ),
        },
        {
            id: 'landmark',
            header: 'Landmark',
            cellClassName: 'max-w-56 text-sm text-muted-foreground',
            cell: (address) => address.landmark ?? '—',
        },
        {
            id: 'actions',
            header: 'Actions',
            headerHidden: true,
            headClassName: 'w-12',
            cell: (address) => {
                const routine: RowAction[] = [
                    {
                        label: 'Edit',
                        icon: Pencil,
                        onSelect: () => openEdit(address),
                    },
                ];

                if (!address.is_default) {
                    routine.push({
                        label: 'Set as default',
                        icon: Star,
                        onSelect: () => setDefault(address),
                    });
                }

                return (
                    <RowActions
                        rowLabel={`${address.first_name} ${address.last_name}`}
                        groups={[
                            { actions: routine },
                            {
                                actions: [
                                    {
                                        label: 'Delete',
                                        icon: Trash2,
                                        destructive: true,
                                        onSelect: () => setDeleting(address),
                                    },
                                ],
                            },
                        ]}
                    />
                );
            },
        },
    ];

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

                <DataTable
                    caption="Delivery addresses"
                    columns={columns}
                    rows={paginator.data}
                    getRowKey={(address) => address.id}
                    paginator={paginator}
                    empty={
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
                    }
                />
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
