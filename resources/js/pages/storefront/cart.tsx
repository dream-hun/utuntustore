import { Head, Link, router } from '@inertiajs/react';
import { ImageOff, Minus, Plus, Store, X } from 'lucide-react';
import { Money } from '@/components/money';
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
        if (quantity < 1 || quantity > item.max_quantity) {
            return;
        }

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
                <div className="mx-auto max-w-lg px-6 py-32 text-center">
                    <span className="eyebrow text-muted-foreground">
                        Your bag
                    </span>
                    <h1 className="mt-4 text-6xl">Empty.</h1>
                    <p className="mt-6 text-muted-foreground">
                        Nothing in here yet. Let’s fix that.
                    </p>
                    <Link
                        href={shop.url()}
                        className="mt-10 inline-block rounded-full bg-primary px-8 py-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                    >
                        Browse local shops
                    </Link>
                </div>
            </StorefrontLayout>
        );
    }

    return (
        <StorefrontLayout>
            <Head title="Cart" />

            <div className="mx-auto w-full max-w-[1400px] px-4 py-12 lg:px-8 lg:py-16">
                <header className="mb-12 border-b border-border pb-8">
                    <span className="eyebrow text-muted-foreground">
                        Your bag
                    </span>
                    <h1 className="mt-3 text-4xl md:text-5xl">Your bag</h1>
                    <p className="mt-5 max-w-xl text-sm leading-relaxed text-muted-foreground">
                        {itemCount} item{itemCount === 1 ? '' : 's'} from{' '}
                        {groups.length} shop{groups.length === 1 ? '' : 's'}.
                        You pay each shop separately, in cash, when it delivers.
                    </p>
                </header>

                <div className="grid gap-12 lg:grid-cols-[minmax(0,1fr)_380px] lg:gap-16">
                    <div className="space-y-12">
                        {groups.map((group) => (
                            <section
                                key={group.vendor.id}
                                aria-label={group.vendor.shop_name}
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-4">
                                    <h2 className="flex items-center gap-2 text-lg">
                                        <Store
                                            className="size-4 text-primary"
                                            aria-hidden="true"
                                        />
                                        <Link
                                            href={vendorShow.url({
                                                vendor: group.vendor.slug,
                                            })}
                                            className="link-underline"
                                        >
                                            {group.vendor.shop_name}
                                        </Link>
                                    </h2>
                                    <Money
                                        amount={group.subtotal}
                                        currency={currency}
                                        className="font-heading text-base"
                                    />
                                </div>

                                {!group.vendor.can_sell ? (
                                    <p className="mt-3 text-xs text-destructive">
                                        This shop is not currently accepting
                                        orders. Remove these items to check out.
                                    </p>
                                ) : null}

                                {group.items.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex gap-4 border-b border-border py-6 sm:gap-6"
                                    >
                                        <div className="h-32 w-24 shrink-0 overflow-hidden rounded-2xl bg-cream sm:h-40 sm:w-32">
                                            {item.product.image_url ? (
                                                <img
                                                    src={item.product.image_url}
                                                    alt={item.product.name}
                                                    className="size-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex size-full items-center justify-center text-muted-foreground">
                                                    <ImageOff className="size-5" />
                                                </div>
                                            )}
                                        </div>

                                        <div className="flex min-w-0 flex-1 flex-col justify-between gap-4">
                                            <div className="flex justify-between gap-4">
                                                <div className="min-w-0">
                                                    <p className="truncate font-heading text-lg sm:text-xl">
                                                        {item.product.name}
                                                    </p>
                                                    {item.variant ? (
                                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                                            {item.variant.name}
                                                        </p>
                                                    ) : null}
                                                    <Money
                                                        amount={item.unit_price}
                                                        currency={currency}
                                                        className="mt-1 block text-sm text-muted-foreground"
                                                    />
                                                    {item.available_stock <
                                                    item.quantity ? (
                                                        <p className="mt-1 text-xs text-destructive">
                                                            Only{' '}
                                                            {
                                                                item.available_stock
                                                            }{' '}
                                                            left in stock.
                                                        </p>
                                                    ) : null}
                                                </div>

                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        removeItem(item)
                                                    }
                                                    className="h-fit rounded p-1 text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                    aria-label={`Remove ${item.product.name}`}
                                                >
                                                    <X className="size-4" />
                                                </button>
                                            </div>

                                            <div className="flex items-center justify-between gap-3">
                                                <div className="flex items-center rounded-full border border-border">
                                                    <button
                                                        type="button"
                                                        className="p-2 disabled:opacity-40"
                                                        disabled={
                                                            item.quantity <= 1
                                                        }
                                                        onClick={() =>
                                                            updateQuantity(
                                                                item,
                                                                item.quantity -
                                                                    1,
                                                            )
                                                        }
                                                        aria-label={`Decrease quantity of ${item.product.name}`}
                                                    >
                                                        <Minus className="size-3" />
                                                    </button>
                                                    <span className="px-4 text-sm tabular-nums">
                                                        {item.quantity}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        className="p-2 disabled:opacity-40"
                                                        disabled={
                                                            item.quantity >=
                                                            item.max_quantity
                                                        }
                                                        onClick={() =>
                                                            updateQuantity(
                                                                item,
                                                                item.quantity +
                                                                    1,
                                                            )
                                                        }
                                                        aria-label={`Increase quantity of ${item.product.name}`}
                                                    >
                                                        <Plus className="size-3" />
                                                    </button>
                                                </div>

                                                <Money
                                                    amount={item.subtotal}
                                                    currency={currency}
                                                    className="font-heading text-base"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </section>
                        ))}
                    </div>

                    <aside className="h-fit rounded-2xl bg-cream p-8 lg:sticky lg:top-28">
                        <h2 className="mb-6 eyebrow text-muted-foreground">
                            Order summary
                        </h2>
                        <dl className="space-y-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">
                                    Items subtotal
                                </dt>
                                <dd>
                                    <Money
                                        amount={subtotal}
                                        currency={currency}
                                    />
                                </dd>
                            </div>
                            <div className="flex justify-between gap-6">
                                <dt className="text-muted-foreground">
                                    Delivery
                                </dt>
                                <dd className="text-right text-muted-foreground">
                                    Per shop, worked out at checkout
                                </dd>
                            </div>
                        </dl>

                        <Link
                            href={checkoutIndex.url()}
                            className="mt-8 block rounded-full bg-primary py-4 text-center text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                        >
                            Continue to checkout
                        </Link>

                        <p className="mt-4 text-center text-xs text-muted-foreground">
                            Cash on delivery · You pay each shop at the door
                        </p>
                    </aside>
                </div>
            </div>
        </StorefrontLayout>
    );
}
