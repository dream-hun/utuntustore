import { Link, router, usePage } from '@inertiajs/react';
import { Minus, Plus } from 'lucide-react';
import { useEffect } from 'react';
import { Image } from '@/components/image';
import { Money } from '@/components/money';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { closeCartDrawer, useCartDrawerOpen } from '@/hooks/use-cart-drawer';
import { shop } from '@/routes';
import cart, {
    destroy as cartDestroy,
    update as cartUpdate,
} from '@/routes/cart';
import { index as checkoutIndex } from '@/routes/checkout';
import { show as productShow } from '@/routes/products';
import type { StorefrontCartPreview } from '@/types/marketplace';

/**
 * Pages that render the basket from their own props rather than from `cartPreview`.
 *
 * These cannot take a filtered response to a cart write: `storefront/cart` reads
 * `groups`/`subtotal`/`itemCount` and `storefront/checkout` reads `quote`, none of
 * which are in the filter, so they would keep rendering pre-change figures behind
 * the open drawer.
 */
const PAGES_RENDERING_OWN_CART_STATE = [
    'storefront/cart',
    'storefront/checkout',
];

/**
 * The bag, one click from anywhere on the storefront.
 *
 * The basket itself is an optional shared prop, so it is fetched when the drawer
 * opens rather than shipped with every page. Quantity changes go through the same
 * cart routes the cart page uses, then re-request the preview so the drawer and the
 * header badge agree with the server.
 */
export function CartDrawer() {
    const isOpen = useCartDrawerOpen();
    const page = usePage();
    const preview = page.props.cartPreview as StorefrontCartPreview | undefined;

    const refresh = () => router.reload({ only: ['cartPreview'] });

    // The cart can change from the cart page, another tab or a previous visit, so the
    // drawer re-reads it on every open rather than trusting what it fetched before.
    useEffect(() => {
        if (isOpen) {
            refresh();
        }
    }, [isOpen]);

    /**
     * Both mutations redirect back, and `only` rides through that redirect — so the
     * response to the write already carries the new basket, and no follow-up reload
     * is needed. Refetching afterwards instead cost two round trips per tap, and the
     * first of them re-ran the whole underlying page (a catalog's pagination, say)
     * only to discard every prop but this one.
     *
     * `cartCount` is asked for alongside, which also fixes the header badge going
     * stale until the next full visit. Flash data is not a prop, so the server's
     * toast still arrives.
     *
     * The drawer is mounted by StorefrontLayout on every storefront page, though,
     * and two of those render the basket themselves. Filtering the write response
     * there left the page behind the drawer on stale quantities and totals — and on
     * /cart, removing the last item left a phantom line with a live Checkout button,
     * because that page's empty state is keyed on its own `groups` prop. So the
     * filter is dropped on exactly those pages and the full prop set comes back.
     */
    const only = PAGES_RENDERING_OWN_CART_STATE.includes(page.component)
        ? undefined
        : ['cartPreview', 'cartCount'];

    const setQuantity = (id: string, quantity: number) => {
        router.patch(
            cartUpdate.url({ item: id }),
            { quantity },
            {
                preserveScroll: true,
                preserveState: true,
                only,
            },
        );
    };

    const remove = (id: string) => {
        router.delete(cartDestroy.url({ item: id }), {
            preserveScroll: true,
            preserveState: true,
            only,
        });
    };

    const items = preview?.items ?? [];

    return (
        <Sheet
            open={isOpen}
            onOpenChange={(open) => {
                if (!open) {
                    closeCartDrawer();
                }
            }}
        >
            <SheetContent
                side="right"
                className="w-full gap-0 p-0 sm:max-w-md"
                aria-describedby={undefined}
            >
                <div className="flex items-center justify-between border-b border-border px-6 py-5">
                    <SheetTitle className="font-sans eyebrow">
                        Your bag{preview ? ` (${preview.count})` : ''}
                    </SheetTitle>
                </div>

                {preview === undefined ? (
                    <div className="space-y-4 px-6 py-6" aria-busy="true">
                        {Array.from({ length: 3 }).map((_, index) => (
                            <div key={index} className="flex gap-4">
                                <Skeleton className="h-24 w-20 shrink-0 rounded-2xl" />
                                <div className="flex-1 space-y-2 py-1">
                                    <Skeleton className="h-4 w-3/4" />
                                    <Skeleton className="h-3 w-1/3" />
                                    <Skeleton className="h-7 w-24 rounded-full" />
                                </div>
                            </div>
                        ))}
                    </div>
                ) : items.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-4 p-8 text-center">
                        <p className="font-heading text-2xl">
                            Your bag is empty
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Find something from a shop near you.
                        </p>
                        <Link
                            href={shop.url()}
                            onClick={closeCartDrawer}
                            className="mt-4 rounded-full bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                        >
                            Start shopping
                        </Link>
                    </div>
                ) : (
                    <>
                        <div className="flex-1 overflow-y-auto px-6 py-2">
                            {items.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex gap-4 border-b border-border py-5 last:border-0"
                                >
                                    <Link
                                        href={productShow.url({
                                            product: item.slug,
                                        })}
                                        onClick={closeCartDrawer}
                                        className="h-24 w-20 shrink-0 overflow-hidden rounded-2xl bg-cream"
                                    >
                                        <Image
                                            src={item.image_url}
                                            alt={item.name}
                                            iconClassName="size-5"
                                            className="size-full object-cover"
                                        />
                                    </Link>

                                    <div className="flex min-w-0 flex-1 flex-col justify-between gap-3">
                                        <div className="flex justify-between gap-2">
                                            <div className="min-w-0">
                                                <Link
                                                    href={productShow.url({
                                                        product: item.slug,
                                                    })}
                                                    onClick={closeCartDrawer}
                                                    className="block truncate font-heading text-base leading-tight"
                                                >
                                                    {item.name}
                                                </Link>
                                                <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                    {item.vendor_name}
                                                    {item.variant_name
                                                        ? ` · ${item.variant_name}`
                                                        : ''}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => remove(item.id)}
                                                className="link-underline h-fit shrink-0 text-xs text-muted-foreground"
                                            >
                                                Remove
                                            </button>
                                        </div>

                                        <div className="flex items-center justify-between gap-2">
                                            <div className="flex items-center rounded-full border border-border">
                                                <button
                                                    type="button"
                                                    className="p-2 disabled:opacity-40"
                                                    disabled={
                                                        item.quantity <= 1
                                                    }
                                                    onClick={() =>
                                                        setQuantity(
                                                            item.id,
                                                            item.quantity - 1,
                                                        )
                                                    }
                                                    aria-label={`Decrease quantity of ${item.name}`}
                                                >
                                                    <Minus className="size-3" />
                                                </button>
                                                <span className="px-3 text-sm tabular-nums">
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
                                                        setQuantity(
                                                            item.id,
                                                            item.quantity + 1,
                                                        )
                                                    }
                                                    aria-label={`Increase quantity of ${item.name}`}
                                                >
                                                    <Plus className="size-3" />
                                                </button>
                                            </div>
                                            <Money
                                                amount={item.subtotal}
                                                currency={preview.currency}
                                                className="text-sm"
                                            />
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="space-y-4 border-t border-border px-6 py-5">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Subtotal
                                </span>
                                <Money
                                    amount={preview.subtotal}
                                    currency={preview.currency}
                                />
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Delivery is charged per shop and worked out at
                                checkout. You pay each shop in cash at the door.
                            </p>
                            <Link
                                href={checkoutIndex.url()}
                                onClick={closeCartDrawer}
                                className="block rounded-full bg-primary py-4 text-center text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                            >
                                Checkout
                            </Link>
                            <Link
                                href={cart.index.url()}
                                onClick={closeCartDrawer}
                                className="block text-center text-xs font-semibold text-muted-foreground transition-colors hover:text-primary"
                            >
                                View full bag
                            </Link>
                        </div>
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
