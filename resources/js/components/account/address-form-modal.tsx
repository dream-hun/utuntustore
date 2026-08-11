import { useForm } from '@inertiajs/react';
import { FormModal } from '@/components/form-modal';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import addresses from '@/routes/account/addresses';
import type { District, Sector } from '@/types/marketplace';

export interface AccountAddress {
    id: string;
    type: 'shipping' | 'billing';
    first_name: string;
    last_name: string;
    phone: string;
    country: string;
    district: District;
    sector: Sector;
    cell: string | null;
    village: string | null;
    address_line: string | null;
    landmark: string | null;
    is_default: boolean;
}

/**
 * Create/edit an address.
 *
 * The sector list is never held in full — Rwanda has 416 of them — so it arrives one
 * district at a time from the parent page, which is also why the sector field shows a
 * skeleton while a district's sectors are on the way.
 */
export function AddressFormModal({
    open,
    onOpenChange,
    address,
    districts,
    sectors,
    sectorsLoading,
    onDistrictChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    address: AccountAddress | null;
    districts: District[];
    sectors: Sector[];
    sectorsLoading: boolean;
    onDistrictChange: (districtId: string) => void;
}) {
    const form = useForm({
        type: address?.type ?? 'shipping',
        first_name: address?.first_name ?? '',
        last_name: address?.last_name ?? '',
        phone: address?.phone ?? '',
        district: address?.district.id ?? '',
        sector: address?.sector.id ?? '',
        cell: address?.cell ?? '',
        village: address?.village ?? '',
        address_line: address?.address_line ?? '',
        landmark: address?.landmark ?? '',
        is_default: address?.is_default ?? false,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (address) {
            form.put(addresses.update.url(address.id), options);

            return;
        }

        form.post(addresses.store.url(), options);
    };

    const selectDistrict = (districtId: string) => {
        form.setData((current) => ({
            ...current,
            district: districtId,
            sector: '',
        }));

        onDistrictChange(districtId);
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={address ? 'Edit address' : 'Add an address'}
            description="Deliveries are matched to vendors by district and sector, so both are required."
            onSubmit={submit}
            processing={form.processing}
            submitLabel={address ? 'Save changes' : 'Add address'}
            size="lg"
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="first_name">First name</Label>
                    <Input
                        id="first_name"
                        value={form.data.first_name}
                        onChange={(event) =>
                            form.setData('first_name', event.target.value)
                        }
                        autoComplete="given-name"
                        required
                    />
                    <InputError message={form.errors.first_name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="last_name">Last name</Label>
                    <Input
                        id="last_name"
                        value={form.data.last_name}
                        onChange={(event) =>
                            form.setData('last_name', event.target.value)
                        }
                        autoComplete="family-name"
                        required
                    />
                    <InputError message={form.errors.last_name} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="phone">Phone</Label>
                <Input
                    id="phone"
                    value={form.data.phone}
                    onChange={(event) =>
                        form.setData('phone', event.target.value)
                    }
                    placeholder="+250 7.. ... ..."
                    autoComplete="tel"
                    inputMode="tel"
                    required
                />
                <p className="text-xs text-muted-foreground">
                    The vendor calls this number when they are close.
                </p>
                <InputError message={form.errors.phone} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="district">District</Label>
                    <Select
                        value={form.data.district}
                        onValueChange={selectDistrict}
                    >
                        <SelectTrigger id="district" className="w-full">
                            <SelectValue placeholder="Choose a district" />
                        </SelectTrigger>
                        <SelectContent>
                            {districts.map((district) => (
                                <SelectItem
                                    key={district.id}
                                    value={district.id}
                                >
                                    {district.name}
                                    {district.province
                                        ? ` · ${district.province.name}`
                                        : ''}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.district} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="sector">Sector</Label>
                    {sectorsLoading ? (
                        <Skeleton className="h-9 w-full" />
                    ) : (
                        <Select
                            value={form.data.sector}
                            onValueChange={(value) =>
                                form.setData('sector', value)
                            }
                            disabled={form.data.district === ''}
                        >
                            <SelectTrigger id="sector" className="w-full">
                                <SelectValue
                                    placeholder={
                                        form.data.district === ''
                                            ? 'Choose a district first'
                                            : 'Choose a sector'
                                    }
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {sectors.map((sector) => (
                                    <SelectItem
                                        key={sector.id}
                                        value={sector.id}
                                    >
                                        {sector.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                    <InputError message={form.errors.sector} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="cell">Cell</Label>
                    <Input
                        id="cell"
                        value={form.data.cell}
                        onChange={(event) =>
                            form.setData('cell', event.target.value)
                        }
                    />
                    <InputError message={form.errors.cell} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="village">Village</Label>
                    <Input
                        id="village"
                        value={form.data.village}
                        onChange={(event) =>
                            form.setData('village', event.target.value)
                        }
                    />
                    <InputError message={form.errors.village} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="address_line">Street or house</Label>
                <Input
                    id="address_line"
                    value={form.data.address_line}
                    onChange={(event) =>
                        form.setData('address_line', event.target.value)
                    }
                />
                <InputError message={form.errors.address_line} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="landmark">Landmark</Label>
                <Input
                    id="landmark"
                    value={form.data.landmark}
                    onChange={(event) =>
                        form.setData('landmark', event.target.value)
                    }
                    placeholder="e.g. behind Simba Supermarket, blue gate"
                />
                <p className="text-xs text-muted-foreground">
                    This is usually what actually gets a delivery to your door —
                    worth more than the street name.
                </p>
                <InputError message={form.errors.landmark} />
            </div>

            <div className="flex items-start gap-3">
                <Checkbox
                    id="is_default"
                    checked={form.data.is_default}
                    onCheckedChange={(checked) =>
                        form.setData('is_default', checked === true)
                    }
                />
                <Label htmlFor="is_default" className="text-sm font-normal">
                    Use this as my default delivery address
                </Label>
            </div>
        </FormModal>
    );
}
