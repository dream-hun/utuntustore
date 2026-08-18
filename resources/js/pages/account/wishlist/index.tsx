import { Head, Link, router } from '@inertiajs/react';
import { Heart, ShoppingCart, Trash2 } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { Image } from '@/components/image';
import { Money } from '@/components/money';
import { PaginationNav } from '@/components/pagination-nav';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { shop } from '@/routes';
import { show as productShow } from '@/routes/products';
import type { Paginated } from '@/types/marketplace';

interface WishlistRow {
    id: string;
    saved_at: string;
    product: {
        id: string;
        name: string;
        slug: string;
        price: number;
        compare_at_price: number | null;
        currency: string;
        primary_image_url: string | null;
        in_stock: boolean;
        vendor: {
            id: string;
            shop_name: string;
            slug: string;
            can_sell: boolean;
        };
    };
}

export default function AccountWishlist({
    items,
}: {
    items: Paginated<WishlistRow>;
}) {
    return (
        <>
            <Head title="Wishlist" />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Wishlist
                </h1>

                {items.data.length === 0 ? (
                    <EmptyState
                        icon={Heart}
                        title="Nothing saved yet"
                        description="Save products you are thinking about and come back to them later."
                        action={
                            <Button asChild>
                                <Link href={shop.url()}>Browse the shop</Link>
                            </Button>
                        }
                    />
                ) : (
                    <div className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {items.data.map((item) => (
                                <Card
                                    key={item.id}
                                    className="overflow-hidden py-0"
                                >
                                    <CardContent className="p-0">
                                        <Link
                                            href={productShow.url({
                                                product: item.product.slug,
                                            })}
                                        >
                                            <div className="aspect-square w-full bg-muted">
                                                <Image
                                                    src={
                                                        item.product
                                                            .primary_image_url
                                                    }
                                                    alt={item.product.name}
                                                    iconClassName="size-8"
                                                    className="size-full object-cover"
                                                />
                                            </div>
                                        </Link>

                                        <div className="space-y-2 p-4">
                                            <Link
                                                href={productShow.url({
                                                    product: item.product.slug,
                                                })}
                                                className="line-clamp-2 text-sm font-medium hover:underline"
                                            >
                                                {item.product.name}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {item.product.vendor.shop_name}
                                            </p>
                                            <Money
                                                amount={item.product.price}
                                                currency={item.product.currency}
                                                className="block text-sm font-semibold"
                                            />

                                            {!item.product.vendor.can_sell ? (
                                                <p className="text-xs text-muted-foreground">
                                                    This shop is not accepting
                                                    orders right now.
                                                </p>
                                            ) : !item.product.in_stock ? (
                                                <p className="text-xs text-muted-foreground">
                                                    Out of stock.
                                                </p>
                                            ) : null}

                                            <div className="flex gap-2 pt-1">
                                                <Button
                                                    size="sm"
                                                    className="flex-1"
                                                    disabled={
                                                        !item.product
                                                            .in_stock ||
                                                        !item.product.vendor
                                                            .can_sell
                                                    }
                                                    onClick={() =>
                                                        router.post(
                                                            `/account/wishlist/${item.product.id}/cart`,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <ShoppingCart className="size-4" />
                                                    Move to cart
                                                </Button>

                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        router.delete(
                                                            `/account/wishlist/${item.product.id}`,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                    <span className="sr-only">
                                                        Remove
                                                    </span>
                                                </Button>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        <PaginationNav paginator={items} />
                    </div>
                )}
            </div>
        </>
    );
}

AccountWishlist.layout = {
    breadcrumbs: [
        { title: 'Account', href: '/account' },
        { title: 'Wishlist', href: '/account/wishlist' },
    ],
};
