import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, PackageSearch } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PaginationNav } from '@/components/pagination-nav';
import { CategoryPill } from '@/components/storefront/category-pill';
import {
    ProductGrid,
    type StorefrontProduct,
} from '@/components/storefront/product-card';
import { SectionHeader } from '@/components/storefront/section-header';
import StorefrontLayout from '@/layouts/storefront-layout';
import { shop } from '@/routes';
import { show as categoryShow } from '@/routes/categories';
import type { Paginated } from '@/types/marketplace';

interface CategoryRef {
    id: string;
    name: string;
    slug: string;
}

export default function CategoryPage({
    category,
    children,
    products,
}: {
    category: CategoryRef & {
        description: string | null;
        parent: CategoryRef | null;
    };
    children: CategoryRef[];
    products: Paginated<StorefrontProduct>;
}) {
    return (
        <StorefrontLayout>
            <Head title={category.name} />

            <div className="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                {/* Breadcrumb */}
                <nav
                    aria-label="Breadcrumb"
                    className="mb-6 flex items-center gap-1.5 text-sm text-muted-foreground"
                >
                    <Link
                        href={shop.url()}
                        className="inline-flex items-center gap-1 transition-colors hover:text-foreground"
                    >
                        <ChevronLeft className="size-4" aria-hidden="true" />
                        Shop
                    </Link>

                    {category.parent ? (
                        <>
                            <span aria-hidden="true">/</span>
                            <Link
                                href={categoryShow.url({
                                    category: category.parent.slug,
                                })}
                                className="transition-colors hover:text-foreground"
                            >
                                {category.parent.name}
                            </Link>
                        </>
                    ) : null}

                    <span aria-hidden="true">/</span>
                    <span
                        className="font-medium text-foreground"
                        aria-current="page"
                    >
                        {category.name}
                    </span>
                </nav>

                <header className="mb-8">
                    <SectionHeader
                        as="h1"
                        title={category.name}
                        description={
                            category.description ??
                            `${products.total.toLocaleString()} product${products.total === 1 ? '' : 's'}`
                        }
                    />
                </header>

                {/* Subcategory pills */}
                {children.length > 0 ? (
                    <nav
                        aria-label="Subcategories"
                        className="mb-8 flex flex-wrap gap-2"
                    >
                        {children.map((child) => (
                            <CategoryPill
                                key={child.id}
                                name={child.name}
                                href={categoryShow.url({
                                    category: child.slug,
                                })}
                            />
                        ))}
                    </nav>
                ) : null}

                {products.data.length === 0 ? (
                    <EmptyState
                        icon={PackageSearch}
                        title="Nothing here yet"
                        description="No shop is currently selling products in this category."
                    />
                ) : (
                    <div className="space-y-6">
                        <p className="text-sm text-muted-foreground">
                            Showing {products.from}–{products.to} of{' '}
                            {products.total.toLocaleString()} product
                            {products.total === 1 ? '' : 's'}
                        </p>
                        <ProductGrid products={products.data} />
                        <PaginationNav paginator={products} />
                    </div>
                )}
            </div>
        </StorefrontLayout>
    );
}
