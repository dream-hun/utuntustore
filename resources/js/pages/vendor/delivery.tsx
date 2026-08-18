import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Truck } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { DataTableColumn } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FormModal } from '@/components/form-modal';
import InputError from '@/components/input-error';
import { Money } from '@/components/money';
import { RowActions } from '@/components/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { SubscriptionBanner } from '@/components/vendor/subscription-banner';
import { formatDeliveryEstimate } from '@/lib/format';

interface Ref {
    id: string;
    name: string;
}

interface Province extends Ref {
    districts: Ref[];
}

interface Area {
    id: string;
    district: Ref;
    sector: Ref | null;
    delivery_fee: number;
    estimated_days_min: number;
    estimated_days_max: number;
    is_active: boolean;
}

export default function VendorDelivery({
    provinces,
    areas,
    sectors,
    sectorsDistrictId,
}: {
    provinces: Province[];
    areas: Area[];
    sectors?: Ref[];
    sectorsDistrictId: string | null;
}) {
    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState<Area | null>(null);
    const [removing, setRemoving] = useState<Area | null>(null);

    const addForm = useForm<{
        district_id: string;
        sector_ids: string[];
        delivery_fee: number;
        estimated_days_min: number;
        estimated_days_max: number;
        is_active: boolean;
    }>({
        district_id: '',
        sector_ids: [],
        delivery_fee: 0,
        estimated_days_min: 1,
        estimated_days_max: 3,
        is_active: true,
    });

    const editForm = useForm({
        delivery_fee: 0,
        estimated_days_min: 1,
        estimated_days_max: 3,
        is_active: true,
    });

    /**
     * Sectors are fetched only for the district being configured. There are 416 in
     * the country and loading them all would make this page useless on a phone.
     */
    const chooseDistrict = (districtId: string) => {
        addForm.setData((data) => ({
            ...data,
            district_id: districtId,
            sector_ids: [],
        }));

        router.reload({
            data: { district: districtId },
            only: ['sectors', 'sectorsDistrictId'],
        });
    };

    const toggleSector = (sectorId: string, checked: boolean) => {
        addForm.setData(
            'sector_ids',
            checked
                ? [...addForm.data.sector_ids, sectorId]
                : addForm.data.sector_ids.filter((id) => id !== sectorId),
        );
    };

    const submitAdd = (event: React.FormEvent) => {
        event.preventDefault();

        addForm.post('/vendor/delivery', {
            preserveScroll: true,
            onSuccess: () => {
                setAdding(false);
                addForm.reset();
            },
        });
    };

    const openEdit = (area: Area) => {
        editForm.setData({
            delivery_fee: area.delivery_fee,
            estimated_days_min: area.estimated_days_min,
            estimated_days_max: area.estimated_days_max,
            is_active: area.is_active,
        });
        editForm.clearErrors();
        setEditing(area);
    };

    const submitEdit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!editing) {
            return;
        }

        editForm.put(`/vendor/delivery/${editing.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };

    const sectorsReady = sectorsDistrictId === addForm.data.district_id;

    const areaLabel = (area: Area) =>
        area.sector
            ? `${area.district.name} · ${area.sector.name}`
            : area.district.name;

    const columns: DataTableColumn<Area>[] = [
        {
            id: 'area',
            header: 'Area',
            cell: (area) => (
                <>
                    <span className="text-sm font-medium">
                        {area.district.name}
                    </span>
                    {area.sector ? (
                        <span className="text-sm text-muted-foreground">
                            {' '}
                            · {area.sector.name}
                        </span>
                    ) : (
                        <Badge variant="secondary" className="ml-2">
                            Whole district
                        </Badge>
                    )}
                </>
            ),
        },
        {
            id: 'delivery_time',
            header: 'Delivery time',
            cellClassName: 'text-sm text-muted-foreground',
            cell: (area) =>
                formatDeliveryEstimate(
                    area.estimated_days_min,
                    area.estimated_days_max,
                ),
        },
        {
            id: 'active',
            header: 'Active',
            cell: (area) =>
                area.is_active ? (
                    <Badge className="border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
                        Active
                    </Badge>
                ) : (
                    <Badge variant="secondary">Paused</Badge>
                ),
        },
        {
            id: 'fee',
            header: 'Fee',
            align: 'end',
            cell: (area) =>
                area.delivery_fee === 0 ? (
                    'Free'
                ) : (
                    <Money amount={area.delivery_fee} />
                ),
        },
        {
            id: 'actions',
            header: 'Actions',
            headerHidden: true,
            headClassName: 'w-10',
            cell: (area) => (
                <RowActions
                    rowLabel={areaLabel(area)}
                    groups={[
                        {
                            actions: [
                                {
                                    label: 'Edit',
                                    icon: Pencil,
                                    onSelect: () => openEdit(area),
                                },
                            ],
                        },
                        {
                            actions: [
                                {
                                    label: 'Remove',
                                    icon: Trash2,
                                    destructive: true,
                                    onSelect: () => setRemoving(area),
                                },
                            ],
                        },
                    ]}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Delivery areas" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Delivery areas
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Where you deliver and what you charge. Customers
                            outside these areas cannot order from you.
                        </p>
                    </div>
                    <Button
                        onClick={() => {
                            addForm.reset();
                            addForm.clearErrors();
                            setAdding(true);
                        }}
                    >
                        <Plus className="size-4" />
                        Add coverage
                    </Button>
                </div>

                <SubscriptionBanner />

                <Card>
                    <CardContent className="text-sm text-muted-foreground">
                        Add a district to deliver everywhere in it. Add specific
                        sectors when you want a different price for them — a
                        sector rate always overrides the district rate.
                    </CardContent>
                </Card>

                <DataTable
                    caption="Delivery areas"
                    columns={columns}
                    rows={areas}
                    getRowKey={(area) => area.id}
                    empty={
                        <EmptyState
                            icon={Truck}
                            title="You have no delivery areas"
                            description="Until you add at least one, nobody can order from your shop."
                            action={
                                <Button onClick={() => setAdding(true)}>
                                    <Plus className="size-4" />
                                    Add coverage
                                </Button>
                            }
                        />
                    }
                />
            </div>

            <FormModal
                open={adding}
                onOpenChange={setAdding}
                title="Add delivery coverage"
                description="Pick a district. Leave the sectors unticked to cover the whole district."
                onSubmit={submitAdd}
                processing={addForm.processing}
                submitLabel="Add coverage"
                size="lg"
            >
                <div className="grid gap-2">
                    <Label>District</Label>
                    <Select
                        value={addForm.data.district_id}
                        onValueChange={chooseDistrict}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Choose a district" />
                        </SelectTrigger>
                        <SelectContent>
                            {provinces.map((province) => (
                                <SelectGroup key={province.id}>
                                    <SelectLabel>{province.name}</SelectLabel>
                                    {province.districts.map((district) => (
                                        <SelectItem
                                            key={district.id}
                                            value={district.id}
                                        >
                                            {district.name}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={addForm.errors.district_id} />
                </div>

                {addForm.data.district_id !== '' ? (
                    <div className="grid gap-2">
                        <Label>Specific sectors (optional)</Label>
                        <p className="text-xs text-muted-foreground">
                            Tick sectors only if they need this rate
                            specifically. Leave all unticked to cover the entire
                            district in one row.
                        </p>

                        {!sectorsReady ? (
                            <div className="grid gap-2 sm:grid-cols-2">
                                {Array.from({ length: 6 }).map((_, index) => (
                                    <Skeleton
                                        key={index}
                                        className="h-6 w-full"
                                    />
                                ))}
                            </div>
                        ) : (
                            <div className="max-h-56 overflow-y-auto rounded-md border p-3">
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {(sectors ?? []).map((sector) => (
                                        <label
                                            key={sector.id}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <Checkbox
                                                checked={addForm.data.sector_ids.includes(
                                                    sector.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    toggleSector(
                                                        sector.id,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            {sector.name}
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}
                        <InputError message={addForm.errors.sector_ids} />
                    </div>
                ) : null}

                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="delivery_fee">Fee (FRW)</Label>
                        <Input
                            id="delivery_fee"
                            type="number"
                            min={0}
                            value={addForm.data.delivery_fee}
                            onChange={(event) =>
                                addForm.setData(
                                    'delivery_fee',
                                    Number(event.target.value),
                                )
                            }
                        />
                        <InputError message={addForm.errors.delivery_fee} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="days_min">Days (min)</Label>
                        <Input
                            id="days_min"
                            type="number"
                            min={0}
                            value={addForm.data.estimated_days_min}
                            onChange={(event) =>
                                addForm.setData(
                                    'estimated_days_min',
                                    Number(event.target.value),
                                )
                            }
                        />
                        <InputError
                            message={addForm.errors.estimated_days_min}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="days_max">Days (max)</Label>
                        <Input
                            id="days_max"
                            type="number"
                            min={0}
                            value={addForm.data.estimated_days_max}
                            onChange={(event) =>
                                addForm.setData(
                                    'estimated_days_max',
                                    Number(event.target.value),
                                )
                            }
                        />
                        <InputError
                            message={addForm.errors.estimated_days_max}
                        />
                    </div>
                </div>
            </FormModal>

            <FormModal
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
                title="Edit coverage"
                description={
                    editing
                        ? `${editing.district.name}${editing.sector ? ` · ${editing.sector.name}` : ' · whole district'}`
                        : undefined
                }
                onSubmit={submitEdit}
                processing={editForm.processing}
            >
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="edit_fee">Fee (FRW)</Label>
                        <Input
                            id="edit_fee"
                            type="number"
                            min={0}
                            value={editForm.data.delivery_fee}
                            onChange={(event) =>
                                editForm.setData(
                                    'delivery_fee',
                                    Number(event.target.value),
                                )
                            }
                        />
                        <InputError message={editForm.errors.delivery_fee} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="edit_min">Days (min)</Label>
                        <Input
                            id="edit_min"
                            type="number"
                            min={0}
                            value={editForm.data.estimated_days_min}
                            onChange={(event) =>
                                editForm.setData(
                                    'estimated_days_min',
                                    Number(event.target.value),
                                )
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="edit_max">Days (max)</Label>
                        <Input
                            id="edit_max"
                            type="number"
                            min={0}
                            value={editForm.data.estimated_days_max}
                            onChange={(event) =>
                                editForm.setData(
                                    'estimated_days_max',
                                    Number(event.target.value),
                                )
                            }
                        />
                        <InputError
                            message={editForm.errors.estimated_days_max}
                        />
                    </div>
                </div>

                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        checked={editForm.data.is_active}
                        onCheckedChange={(checked) =>
                            editForm.setData('is_active', checked === true)
                        }
                    />
                    Accept orders for this area
                </label>
            </FormModal>

            <ConfirmDialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
                title="Remove this delivery area?"
                description="Customers in this area will no longer be able to order from you. Past orders are unaffected."
                confirmLabel="Remove"
                onConfirm={() => {
                    if (!removing) {
                        return;
                    }

                    router.delete(`/vendor/delivery/${removing.id}`, {
                        preserveScroll: true,
                        onFinish: () => setRemoving(null),
                    });
                }}
            />
        </>
    );
}

VendorDelivery.layout = {
    breadcrumbs: [
        { title: 'Vendor', href: '/vendor' },
        { title: 'Delivery areas', href: '/vendor/delivery' },
    ],
};
