import { Deferred, useForm } from '@inertiajs/react';
import { FormModal } from '@/components/form-modal';
import InputError from '@/components/input-error';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import admin from '@/routes/admin';
import type { AdminVendorOption } from './types';

interface ProductForm {
    vendor: string;
    name: string;
    category_id: string;
    description: string;
    short_description: string;
    sku: string;
    price: number;
    compare_at_price: string;
    stock_quantity: number;
    low_stock_threshold: number;
    weight: string;
    publish: boolean;
    images: File[];
}

/**
 * Adds a product to a shop's catalog on the operator's behalf.
 *
 * The shop comes first because it decides the rest: SKUs only have to be unique inside
 * one shop, and only a shop that may currently sell can have the product published
 * immediately. Both pickers arrive as deferred props, so each shows a skeleton until
 * its list lands.
 */
export function ProductFormModal({
    open,
    onOpenChange,
    vendors,
    categories,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    vendors?: AdminVendorOption[];
    categories?: { id: string; name: string }[];
}) {
    const form = useForm<ProductForm>({
        vendor: '',
        name: '',
        category_id: '',
        description: '',
        short_description: '',
        sku: '',
        price: 0,
        compare_at_price: '',
        stock_quantity: 0,
        low_stock_threshold: 5,
        weight: '',
        publish: false,
        images: [],
    });

    const selectedVendor = (vendors ?? []).find(
        (vendor) => vendor.id === form.data.vendor,
    );

    // Nothing is chosen yet on a fresh form, so the toggle stays available until a
    // shop that cannot sell is picked.
    const canPublish = selectedVendor === undefined || selectedVendor.can_sell;

    const selectVendor = (vendorId: string) => {
        const vendor = (vendors ?? []).find((option) => option.id === vendorId);

        form.setData((current) => ({
            ...current,
            vendor: vendorId,
            publish: vendor?.can_sell === true ? current.publish : false,
        }));
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.post(admin.products.store.url(), {
            // Multipart: the body carries the image gallery.
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title="New product"
            description="The product is added to the shop you choose, exactly as if that vendor had added it themselves."
            onSubmit={submit}
            processing={form.processing}
            submitLabel="Add product"
            size="lg"
        >
            <div className="grid gap-2">
                <Label htmlFor="vendor">Shop</Label>
                <Deferred
                    data="vendors"
                    fallback={<Skeleton className="h-9 w-full" />}
                >
                    <Select
                        value={form.data.vendor}
                        onValueChange={selectVendor}
                    >
                        <SelectTrigger id="vendor" className="w-full">
                            <SelectValue placeholder="Choose a shop" />
                        </SelectTrigger>
                        <SelectContent>
                            {(vendors ?? []).map((vendor) => (
                                <SelectItem key={vendor.id} value={vendor.id}>
                                    {vendor.shop_name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Deferred>
                <InputError message={form.errors.vendor} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    autoComplete="off"
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="category_id">Category</Label>
                <Deferred
                    data="categories"
                    fallback={<Skeleton className="h-9 w-full" />}
                >
                    <Select
                        value={form.data.category_id}
                        onValueChange={(value) =>
                            form.setData('category_id', value)
                        }
                    >
                        <SelectTrigger id="category_id" className="w-full">
                            <SelectValue placeholder="Choose a category" />
                        </SelectTrigger>
                        <SelectContent>
                            {(categories ?? []).map((category) => (
                                <SelectItem
                                    key={category.id}
                                    value={category.id}
                                >
                                    {category.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Deferred>
                <InputError message={form.errors.category_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="short_description">Short description</Label>
                <Input
                    id="short_description"
                    value={form.data.short_description}
                    onChange={(event) =>
                        form.setData('short_description', event.target.value)
                    }
                />
                <InputError message={form.errors.short_description} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Description</Label>
                <Textarea
                    id="description"
                    rows={4}
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="price">Price (FRW)</Label>
                    <Input
                        id="price"
                        type="number"
                        min={0}
                        value={form.data.price}
                        onChange={(event) =>
                            form.setData('price', Number(event.target.value))
                        }
                    />
                    <InputError message={form.errors.price} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="compare_at_price">
                        Compare-at price (FRW)
                    </Label>
                    <Input
                        id="compare_at_price"
                        type="number"
                        min={0}
                        value={form.data.compare_at_price}
                        onChange={(event) =>
                            form.setData('compare_at_price', event.target.value)
                        }
                    />
                    <InputError message={form.errors.compare_at_price} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="stock_quantity">Stock</Label>
                    <Input
                        id="stock_quantity"
                        type="number"
                        min={0}
                        value={form.data.stock_quantity}
                        onChange={(event) =>
                            form.setData(
                                'stock_quantity',
                                Number(event.target.value),
                            )
                        }
                    />
                    <InputError message={form.errors.stock_quantity} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="low_stock_threshold">Low stock at</Label>
                    <Input
                        id="low_stock_threshold"
                        type="number"
                        min={0}
                        value={form.data.low_stock_threshold}
                        onChange={(event) =>
                            form.setData(
                                'low_stock_threshold',
                                Number(event.target.value),
                            )
                        }
                    />
                    <InputError message={form.errors.low_stock_threshold} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="sku">SKU</Label>
                    <Input
                        id="sku"
                        value={form.data.sku}
                        onChange={(event) =>
                            form.setData('sku', event.target.value)
                        }
                    />
                    <InputError message={form.errors.sku} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="images">Images</Label>
                <Input
                    id="images"
                    type="file"
                    accept="image/*"
                    multiple
                    onChange={(event) =>
                        form.setData(
                            'images',
                            Array.from(event.target.files ?? []),
                        )
                    }
                />
                <InputError message={form.errors.images} />
            </div>

            <div className="flex items-center justify-between gap-3 rounded-lg border p-3">
                <div>
                    <Label htmlFor="publish">Publish now</Label>
                    <p className="text-xs text-muted-foreground">
                        {canPublish
                            ? 'Off saves it as a draft for the vendor to publish.'
                            : 'This shop cannot sell right now, so it can only be saved as a draft.'}
                    </p>
                </div>
                <Switch
                    id="publish"
                    checked={form.data.publish}
                    disabled={!canPublish}
                    onCheckedChange={(checked) =>
                        form.setData('publish', checked)
                    }
                />
            </div>
            <InputError message={form.errors.publish} />
        </FormModal>
    );
}
