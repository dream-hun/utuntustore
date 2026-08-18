import { useForm } from '@inertiajs/react';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import admin from '@/routes/admin';
import type { AdminCategoryRow, CategoryParentOption } from './types';

/** The select needs a non-empty value for "no parent". */
const NO_PARENT = 'none';

interface CategoryForm {
    name: string;
    parent: string;
    description: string;
    is_active: boolean;
    sort_order: number;
    [key: string]: string | number | boolean;
}

/**
 * Create and edit share one modal because they submit the same fields.
 *
 * The fields are seeded straight from `category`, and the page mounts this component
 * keyed by the row being edited, so every open starts from a fresh form. Re-seeding an
 * already-mounted form with `setDefaults()` followed by `reset()` does not work:
 * `setDefaults` schedules a state update while `reset` reads the defaults captured by
 * the current render, so the reset always applies the *previous* row's values — the
 * first edit opened a blank form and every later one showed the row edited before it.
 *
 * The slug is not editable: it is generated from the name on creation and then left
 * alone, because it is a public storefront URL that links and bookmarks already point
 * at.
 */
export function CategoryFormModal({
    open,
    onOpenChange,
    category,
    parents,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Null creates, a row edits. */
    category: AdminCategoryRow | null;
    parents: CategoryParentOption[];
}) {
    const form = useForm<CategoryForm>({
        name: category?.name ?? '',
        parent: category?.parent?.id ?? NO_PARENT,
        description: category?.description ?? '',
        is_active: category?.is_active ?? true,
        sort_order: category?.sort_order ?? 0,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            parent: data.parent === NO_PARENT ? '' : data.parent,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        };

        if (category) {
            form.put(admin.categories.update.url(category.id), options);

            return;
        }

        form.post(admin.categories.store.url(), options);
    };

    // A category may not be parented to itself; the server rejects deeper cycles too.
    const parentOptions = parents.filter(
        (parent) => parent.id !== category?.id,
    );

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={category ? 'Edit category' : 'New category'}
            description={
                category
                    ? 'Vendors choose from this list, so the name is what shoppers browse by.'
                    : 'The URL slug is generated from the name.'
            }
            onSubmit={submit}
            processing={form.processing}
            submitLabel={category ? 'Save changes' : 'Create category'}
        >
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
                <Label htmlFor="parent">Parent category</Label>
                <Select
                    value={form.data.parent}
                    onValueChange={(value) => form.setData('parent', value)}
                >
                    <SelectTrigger id="parent" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NO_PARENT}>
                            None — top level
                        </SelectItem>
                        {parentOptions.map((parent) => (
                            <SelectItem key={parent.id} value={parent.id}>
                                {parent.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.parent} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Description</Label>
                <Textarea
                    id="description"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    rows={3}
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="grid gap-2 sm:grid-cols-2 sm:items-end sm:gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="sort_order">Sort order</Label>
                    <Input
                        id="sort_order"
                        type="number"
                        min={0}
                        value={form.data.sort_order}
                        onChange={(event) =>
                            form.setData(
                                'sort_order',
                                Number(event.target.value || 0),
                            )
                        }
                    />
                    <InputError message={form.errors.sort_order} />
                </div>

                <div className="flex items-center justify-between gap-3 rounded-lg border p-3">
                    <div>
                        <Label htmlFor="is_active">Active</Label>
                        <p className="text-xs text-muted-foreground">
                            Hidden from navigation when off.
                        </p>
                    </div>
                    <Switch
                        id="is_active"
                        checked={form.data.is_active}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', checked)
                        }
                    />
                </div>
            </div>
        </FormModal>
    );
}
