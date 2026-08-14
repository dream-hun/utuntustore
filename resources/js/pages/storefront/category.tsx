import { Head, Link } from '@inertiajs/react';
import { PackageSearch } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PaginationNav } from '@/components/pagination-nav';
import { CategoryPill } from '@/components/storefront/category-pill';
import { ProductGrid } from '@/components/storefront/product-card';
import type { StorefrontProduct } from '@/components/storefront/product-card';
import StorefrontLayout from '@/layouts/storefront-layout';
import { home, shop } from '@/routes';
import { show as categoryShow } from '@/routes/categories';
import type { Paginated, StorefrontCategoryLink } from '@/types/marketplace';

interface CategoryRef {
    id: string;
    name: string;
    slug: string;
}

export default function CategoryPage({
    category,
    children,
    products,
    navCategories,
}: {
    category: CategoryRef & {
        description: string | null;
        parent: CategoryRef | null;
    };
    children: CategoryRef[];
    products: Paginated<StorefrontProduct>;
    navCategories?: StorefrontCategoryLink[];
}) {
    return (
        <StorefrontLayout
            categories={navCategories}
            activeCategorySlug={category.parent?.slug ?? category.slug}
        >
            <Head title={category.name} />

            <div className="mx-auto w-full max-w-[1400px] px-4 py-12 lg:px-8 lg:py-16">
                {/* Breadcrumb */}
                <nav
                    aria-label="Breadcrumb"
                    className="text-sm font-semibold text-muted-foreground"
                >
                    <Link href={home.url()} className="hover:text-primary">
                        Home
                    </Link>
                    <span className="mx-2" aria-hidden="true">
                        /
                    </span>
                    <Link href={shop.url()} className="hover:text-primary">
                        Shop
                    </Link>

                    {category.parent ? (
                        <>
                            <span className="mx-2" aria-hidden="true">
                                /
                            </span>
                            <Link
                                href={categoryShow.url({
                                    category: category.parent.slug,
                                })}
                                className="hover:text-primary"
                            >
                                {category.parent.name}
                            </Link>
                        </>
                    ) : null}

                    <span className="mx-2" aria-hidden="true">
                        /
                    </span>
                    <span className="text-foreground" aria-current="page">
                        {category.name}
                    </span>
                </nav>

                <header className="mt-8 mb-10 border-b border-border/60 pb-10">
                    <span className="eyebrow text-muted-foreground">
                        Category
                    </span>
                    <h1 className="mt-3 text-4xl md:text-5xl">
                        {category.name}
                    </h1>
                    <p className="mt-5 max-w-xl text-sm leading-relaxed text-muted-foreground">
                        {category.description ??
                            `${products.total.toLocaleString()} product${products.total === 1 ? '' : 's'} from local shops.`}
                    </p>
                </header>

                {/* Subcategory pills */}
                {children.length > 0 ? (
                    <nav
                        aria-label="Subcategories"
                        className="mb-10 flex flex-wrap gap-2"
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
                    <div className="space-y-8">
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
