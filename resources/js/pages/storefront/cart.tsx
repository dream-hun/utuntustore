import { Head, Link, router } from '@inertiajs/react';
import { ImageOff, ShoppingCart, Store, Trash2 } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { Money } from '@/components/money';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import StorefrontLayout from '@/layouts/storefront-layout';
import { shop } from '@/routes';
import { destroy as cartDestroy, update as cartUpdate } from '@/routes/cart';
import { index as checkoutIndex } from '@/routes/checkout';
import { show as vendorShow } from '@/routes/vendors';
import type {
    StorefrontCartGroup,
    StorefrontCartLine,
} from '@/types/marketplace';

export default function CartPage({
    groups,
    subtotal,
    itemCount,
    currency,
}: {
    groups: StorefrontCartGroup[];
    subtotal: number;
    itemCount: number;
    currency: string;
}) {
    const updateQuantity = (item: StorefrontCartLine, quantity: number) => {
        router.patch(
            cartUpdate.url({ item: item.id }),
            { quantity },
            { preserveScroll: true },
        );
    };

    const removeItem = (item: StorefrontCartLine) => {
        router.delete(cartDestroy.url({ item: item.id }), {
            preserveScroll: true,
        });
    };

    if (groups.length === 0) {
        return (
            <StorefrontLayout>
                <Head title="Cart" />
                <div className="mx-auto w-full max-w-3xl px-4 py-24 text-center sm:px-6">
                    <EmptyState
                        icon={ShoppingCart}
                        title="Your cart is empty"
                        description="Browse the shop and add something you like."
                        action={
                            <Button asChild>
                                <Link href={shop.url()}>Start shopping</Link>
                            </Button>
                        }
                    />
                </div>
            </StorefrontLayout>
        );
    }

    return (
        <StorefrontLayout>
            <Head title="Cart" />

            <div className="mx-auto w-full max-w-[1200px] px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
                <div className="mb-10 border-b border-border/60 pb-8">
                    <p className="text-xs font-semibold tracking-[0.18em] text-[#168b8f] uppercase">
                        Your bag
                    </p>
                    <h1 className="mt-2 text-4xl font-semibold tracking-[-0.05em] sm:text-5xl">
                        Checkout
                    </h1>
                    <p className="mt-4 text-sm text-muted-foreground">
                        {itemCount} item{itemCount === 1 ? '' : 's'} from{' '}
                        {groups.length} shop{groups.length === 1 ? '' : 's'}.
                        You pay each shop separately, in cash, when they
                        deliver.
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                    <div className="space-y-4">
                        {groups.map((group) => (
                            <Card
                                key={group.vendor.id}
                                className="overflow-hidden rounded-2xl border-border/70 py-0 shadow-sm"
                            >
                                <CardHeader>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <Store className="size-4" />
                                            <Link
                                                href={vendorShow.url({
                                                    vendor: group.vendor.slug,
                                                })}
                                                className="hover:underline"
                                            >
                                                {group.vendor.shop_name}
                                            </Link>
                                        </CardTitle>
                                        <Money
                                            amount={group.subtotal}
                                            currency={currency}
                                            className="text-sm font-semibold"
                                        />
                                    </div>

                                    {!group.vendor.can_sell ? (
                                        <p className="text-xs text-destructive">
                                            This shop is not currently accepting
                                            orders. Remove these items to check
                                            out.
                                        </p>
                                    ) : null}
                                </CardHeader>

                                <CardContent className="divide-y divide-border">
                                    {group.items.map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex items-start gap-4 py-4 first:pt-0 last:pb-0"
                                        >
                                            <div className="size-20 shrink-0 overflow-hidden rounded-xl bg-[#f1f7f6]">
                                                {item.product.image_url ? (
                                                    <img
                                                        src={
                                                            item.product
                                                                .image_url
                                                        }
                                                        alt={item.product.name}
                                                        className="size-full object-cover"
                                                    />
                                                ) : (
                                                    <div className="flex size-full items-center justify-center text-muted-foreground">
                                                        <ImageOff className="size-5" />
                                                    </div>
                                                )}
                                            </div>

                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium">
                                                    {item.product.name}
                                                </p>
                                                {item.variant ? (
                                                    <p className="text-xs text-muted-foreground">
                                                        {item.variant.name}
                                                    </p>
                                                ) : null}
                                                <Money
                                                    amount={item.unit_price}
                                                    currency={currency}
                                                    className="text-xs text-muted-foreground"
                                                />

                                                <div className="mt-2 flex items-center gap-2">
                                                    <Input
                                                        type="number"
                                                        min={1}
                                                        max={item.max_quantity}
                                                        value={item.quantity}
                                                        onChange={(event) =>
                                                            updateQuantity(
                                                                item,
                                                                Number(
                                                                    event.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                        className="h-8 w-18"
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            removeItem(item)
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                        <span className="sr-only">
                                                            Remove
                                                        </span>
                                                    </Button>
                                                </div>

                                                {item.available_stock <
                                                item.quantity ? (
                                                    <p className="mt-1 text-xs text-destructive">
                                                        Only{' '}
                                                        {item.available_stock}{' '}
                                                        left in stock.
                                                    </p>
                                                ) : null}
                                            </div>

                                            <Money
                                                amount={item.subtotal}
                                                currency={currency}
                                                className="text-sm font-medium"
                                            />
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        ))}
                    </div>

                    <div>
                        <Card className="rounded-2xl border-0 bg-[#e9f8f6] shadow-none lg:sticky lg:top-28">
                            <CardContent className="space-y-4">
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        Items subtotal
                                    </span>
                                    <Money
                                        amount={subtotal}
                                        currency={currency}
                                    />
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    Delivery is charged per shop and is worked
                                    out at checkout, once you choose where it is
                                    going.
                                </p>

                                <Separator />

                                <Button asChild className="w-full">
                                    <Link href={checkoutIndex.url()}>
                                        Continue to checkout
                                    </Link>
                                </Button>

                                <p className="text-center text-xs text-muted-foreground">
                                    Cash on delivery only.
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </StorefrontLayout>
    );
}
