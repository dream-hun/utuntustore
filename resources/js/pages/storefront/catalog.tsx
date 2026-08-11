import { Head, router } from '@inertiajs/react';
import { Filter, PackageSearch, SlidersHorizontal, X } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PaginationNav } from '@/components/pagination-nav';
import { CatalogFilterPanel } from '@/components/storefront/catalog-filter-panel';
import type { CatalogFilters } from '@/components/storefront/catalog-filter-panel';
import { ProductGrid } from '@/components/storefront/product-card';
import type { StorefrontProduct } from '@/components/storefront/product-card';
import { SectionHeader } from '@/components/storefront/section-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import StorefrontLayout from '@/layouts/storefront-layout';
import { shop } from '@/routes';
import type { Paginated, StorefrontCategoryLink } from '@/types/marketplace';

interface Ref {
    id: string;
    name?: string;
    shop_name?: string;
    slug: string;
}

export default function Catalog({
    products,
    filters,
    categories,
    vendors,
    allCategories,
}: {
    products: Paginated<StorefrontProduct>;
    filters: CatalogFilters;
    categories?: Ref[];
    vendors?: Ref[];
    /** All categories for the layout nav bar */
    allCategories?: StorefrontCategoryLink[];
}) {
    const [draft, setDraft] = useState<CatalogFilters>(filters);
    const [isLoading, setIsLoading] = useState(false);

    /** Filters use server visits so every result set has a shareable URL. */
    const apply = (next: Partial<CatalogFilters>) => {
        const merged = { ...draft, ...next };
        setDraft(merged);

        router.get(
            shop.url(),
            {
                search: merged.search || undefined,
                category: merged.category || undefined,
                vendor: merged.vendor || undefined,
                min_price: merged.min_price || undefined,
                max_price: merged.max_price || undefined,
                sort: merged.sort === 'newest' ? undefined : merged.sort,
            },
            {
                preserveScroll: true,
                replace: true,
                onStart: () => setIsLoading(true),
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const clearFilter = (key: keyof CatalogFilters) => {
        apply({ [key]: key === 'sort' ? 'newest' : null });
    };

    const clearAll = () => {
        apply({
            search: null,
            category: null,
            vendor: null,
            min_price: null,
            max_price: null,
            sort: 'newest',
        });
    };

    const activeFilters = [
        filters.search
            ? { key: 'search' as const, label: `"${filters.search}"` }
            : null,
        filters.category
            ? {
                  key: 'category' as const,
                  label:
                      (categories ?? []).find(
                          (c) => c.slug === filters.category,
                      )?.name ?? filters.category,
              }
            : null,
        filters.vendor
            ? {
                  key: 'vendor' as const,
                  label:
                      (vendors ?? []).find((v) => v.slug === filters.vendor)
                          ?.shop_name ?? filters.vendor,
              }
            : null,
        filters.min_price
            ? {
                  key: 'min_price' as const,
                  label: `From ${filters.min_price} FRW`,
              }
            : null,
        filters.max_price
            ? {
                  key: 'max_price' as const,
                  label: `Up to ${filters.max_price} FRW`,
              }
            : null,
    ].filter(Boolean) as { key: keyof CatalogFilters; label: string }[];

    const filterPanel = (
        <CatalogFilterPanel
            filters={draft}
            onFiltersChange={setDraft}
            onApply={apply}
            categories={categories}
            vendors={vendors}
            isLoading={isLoading}
        />
    );

    return (
        <StorefrontLayout
            categories={allCategories}
            activeCategorySlug={filters.category ?? undefined}
        >
            <Head title="Shop" />

            <div className="mx-auto w-full max-w-[1400px] px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
                <div className="mb-10 border-b border-border/60 pb-10 sm:mb-12 sm:flex sm:items-end sm:justify-between">
                    <SectionHeader
                        as="h1"
                        title="Shop"
                        description={`${products.total.toLocaleString()} product${products.total === 1 ? '' : 's'} available`}
                    />

                    {/* Mobile filter trigger */}
                    <Sheet>
                        <SheetTrigger asChild>
                            <Button
                                variant="outline"
                                size="sm"
                                className="w-full gap-2 sm:hidden"
                            >
                                <SlidersHorizontal className="size-4" />
                                Filters
                                {activeFilters.length > 0 ? (
                                    <Badge className="ml-auto h-5 min-w-5 px-1.5 tabular-nums">
                                        {activeFilters.length}
                                    </Badge>
                                ) : null}
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="left"
                            className="w-80 overflow-y-auto"
                        >
                            <SheetHeader className="border-b border-border pb-4">
                                <SheetTitle className="flex items-center gap-2">
                                    <Filter className="size-4" />
                                    Filters
                                </SheetTitle>
                            </SheetHeader>
                            <div className="p-4">{filterPanel}</div>
                        </SheetContent>
                    </Sheet>
                </div>

                {/* Active filter chips */}
                {activeFilters.length > 0 ? (
                    <div
                        className="mb-4 flex flex-wrap items-center gap-2"
                        aria-label="Active filters"
                    >
                        <span className="text-xs font-medium text-muted-foreground">
                            Active:
                        </span>
                        {activeFilters.map((f) => (
                            <button
                                key={f.key}
                                type="button"
                                onClick={() => clearFilter(f.key)}
                                disabled={isLoading}
                                className="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-primary transition-colors hover:bg-primary/20 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                aria-label={`Remove filter: ${f.label}`}
                            >
                                {f.label}
                                <X className="size-3" aria-hidden="true" />
                            </button>
                        ))}
                        <button
                            type="button"
                            onClick={clearAll}
                            disabled={isLoading}
                            className="rounded text-xs text-muted-foreground underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            Clear all
                        </button>
                    </div>
                ) : null}

                <div className="grid gap-8 lg:grid-cols-[240px_1fr]">
                    {/* Desktop sidebar */}
                    <aside
                        className="hidden lg:block"
                        aria-label="Product filters"
                    >
                        <div className="sticky top-28 space-y-6 rounded-2xl border border-border/70 bg-card p-5">
                            <div className="flex items-center justify-between">
                                <p className="text-sm font-semibold">Filters</p>
                                {activeFilters.length > 0 ? (
                                    <button
                                        type="button"
                                        onClick={clearAll}
                                        disabled={isLoading}
                                        className="rounded text-xs text-muted-foreground underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        Clear all
                                    </button>
                                ) : null}
                            </div>
                            {filterPanel}
                        </div>
                    </aside>

                    {/* Product grid */}
                    <div
                        className="space-y-5"
                        aria-live="polite"
                        aria-label="Product results"
                        aria-busy={isLoading}
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                {isLoading ? (
                                    <Spinner className="size-3.5" />
                                ) : null}
                                <p>
                                    {isLoading ? 'Updating results · ' : ''}
                                    {products.total.toLocaleString()} product
                                    {products.total === 1 ? '' : 's'}
                                    {products.last_page > 1
                                        ? ` · Page ${products.current_page} of ${products.last_page}`
                                        : ''}
                                </p>
                            </div>

                            <Select
                                value={draft.sort}
                                onValueChange={(value) =>
                                    apply({ sort: value })
                                }
                                disabled={isLoading}
                            >
                                <SelectTrigger
                                    className="w-[180px]"
                                    aria-label="Sort products"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="newest">
                                        Newest
                                    </SelectItem>
                                    <SelectItem value="price_asc">
                                        Price: low to high
                                    </SelectItem>
                                    <SelectItem value="price_desc">
                                        Price: high to low
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {products.data.length === 0 ? (
                            <EmptyState
                                icon={PackageSearch}
                                title="No products found"
                                description="Try removing a filter or searching for something else."
                                action={
                                    activeFilters.length > 0 ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={clearAll}
                                            disabled={isLoading}
                                        >
                                            Clear filters
                                        </Button>
                                    ) : undefined
                                }
                            />
                        ) : (
                            <>
                                <ProductGrid products={products.data} />
                                <PaginationNav paginator={products} />
                            </>
                        )}
                    </div>
                </div>
            </div>
        </StorefrontLayout>
    );
}
