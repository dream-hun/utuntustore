import { Deferred } from '@inertiajs/react';
import { useId, useState } from 'react';
import { Button } from '@/components/ui/button';
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

const ANY = 'any';

export interface CatalogFilters {
    search: string | null;
    category: string | null;
    vendor: string | null;
    min_price: string | null;
    max_price: string | null;
    sort: string;
}

interface CatalogFilterReference {
    id: string;
    name?: string;
    shop_name?: string;
    slug: string;
}

interface CatalogFilterPanelProps {
    filters: CatalogFilters;
    categories?: CatalogFilterReference[];
    vendors?: CatalogFilterReference[];
    isLoading?: boolean;
    onFiltersChange: (filters: CatalogFilters) => void;
    onApply: (filters: Partial<CatalogFilters>) => void;
}

/**
 * Filter controls shared by the catalog's desktop sidebar and mobile sheet.
 * The parent owns the draft state so both instances always operate on the same
 * URL-backed set of filters.
 */
export function CatalogFilterPanel({
    filters,
    categories,
    vendors,
    isLoading = false,
    onFiltersChange,
    onApply,
}: CatalogFilterPanelProps) {
    const id = useId();
    const [priceError, setPriceError] = useState<string | null>(null);
    const searchId = `catalog-search-${id}`;
    const categoryId = `catalog-category-${id}`;
    const vendorId = `catalog-vendor-${id}`;
    const priceErrorId = `catalog-price-error-${id}`;

    const updateFilters = (next: Partial<CatalogFilters>) => {
        onFiltersChange({ ...filters, ...next });
    };

    const applyPriceRange = () => {
        const min = filters.min_price?.trim() ?? '';
        const max = filters.max_price?.trim() ?? '';
        const minValue = min === '' ? null : Number(min);
        const maxValue = max === '' ? null : Number(max);

        if (
            (minValue !== null &&
                (!Number.isInteger(minValue) || minValue < 0)) ||
            (maxValue !== null && (!Number.isInteger(maxValue) || maxValue < 0))
        ) {
            setPriceError('Enter whole prices of zero or more.');

            return;
        }

        if (minValue !== null && maxValue !== null && minValue > maxValue) {
            setPriceError('The minimum price must not exceed the maximum.');

            return;
        }

        setPriceError(null);
        onApply({ min_price: min || null, max_price: max || null });
    };

    return (
        <div className="space-y-6">
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    onApply({ search: filters.search?.trim() || null });
                }}
                className="space-y-2"
                role="search"
            >
                <Label htmlFor={searchId}>Search</Label>
                <div className="flex gap-2">
                    <Input
                        id={searchId}
                        value={filters.search ?? ''}
                        placeholder="What are you looking for?"
                        onChange={(event) =>
                            updateFilters({ search: event.target.value })
                        }
                        disabled={isLoading}
                    />
                    <Button
                        type="submit"
                        size="sm"
                        variant="secondary"
                        disabled={isLoading}
                    >
                        Search
                    </Button>
                </div>
            </form>

            <div className="space-y-2">
                <Label htmlFor={categoryId}>Category</Label>
                <Deferred
                    data="categories"
                    fallback={<Skeleton className="h-9 w-full" />}
                >
                    <Select
                        value={filters.category ?? ANY}
                        onValueChange={(value) =>
                            onApply({
                                category: value === ANY ? null : value,
                            })
                        }
                        disabled={isLoading}
                    >
                        <SelectTrigger id={categoryId}>
                            <SelectValue placeholder="All categories" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>All categories</SelectItem>
                            {(categories ?? []).map((category) => (
                                <SelectItem
                                    key={category.id}
                                    value={category.slug}
                                >
                                    {category.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Deferred>
            </div>

            <div className="space-y-2">
                <Label htmlFor={vendorId}>Shop</Label>
                <Deferred
                    data="vendors"
                    fallback={<Skeleton className="h-9 w-full" />}
                >
                    <Select
                        value={filters.vendor ?? ANY}
                        onValueChange={(value) =>
                            onApply({ vendor: value === ANY ? null : value })
                        }
                        disabled={isLoading}
                    >
                        <SelectTrigger id={vendorId}>
                            <SelectValue placeholder="All shops" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>All shops</SelectItem>
                            {(vendors ?? []).map((vendor) => (
                                <SelectItem key={vendor.id} value={vendor.slug}>
                                    {vendor.shop_name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Deferred>
            </div>

            <div className="space-y-2">
                <Label>Price (FRW)</Label>
                <div className="flex items-center gap-2">
                    <Input
                        type="number"
                        min={0}
                        step={1}
                        inputMode="numeric"
                        placeholder="Min"
                        aria-label="Minimum price"
                        aria-describedby={priceError ? priceErrorId : undefined}
                        aria-invalid={priceError ? true : undefined}
                        value={filters.min_price ?? ''}
                        onChange={(event) => {
                            setPriceError(null);
                            updateFilters({ min_price: event.target.value });
                        }}
                        disabled={isLoading}
                    />
                    <span className="text-muted-foreground" aria-hidden="true">
                        –
                    </span>
                    <Input
                        type="number"
                        min={0}
                        step={1}
                        inputMode="numeric"
                        placeholder="Max"
                        aria-label="Maximum price"
                        aria-describedby={priceError ? priceErrorId : undefined}
                        aria-invalid={priceError ? true : undefined}
                        value={filters.max_price ?? ''}
                        onChange={(event) => {
                            setPriceError(null);
                            updateFilters({ max_price: event.target.value });
                        }}
                        disabled={isLoading}
                    />
                </div>
                {priceError ? (
                    <p
                        id={priceErrorId}
                        className="text-sm text-destructive"
                        role="alert"
                    >
                        {priceError}
                    </p>
                ) : null}
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="w-full"
                    onClick={applyPriceRange}
                    disabled={isLoading}
                >
                    Apply price range
                </Button>
            </div>
        </div>
    );
}
