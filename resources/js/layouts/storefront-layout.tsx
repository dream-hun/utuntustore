import { Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    ChevronDown,
    Facebook,
    Instagram,
    LifeBuoy,
    Mail,
    Menu,
    Package,
    Search,
    ShieldCheck,
    ShoppingBag,
    Store,
    Truck,
    User,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { CartDrawer } from '@/components/storefront/cart-drawer';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { openCartDrawer } from '@/hooks/use-cart-drawer';
import { cn } from '@/lib/utils';
import { home, login, register, shop } from '@/routes';
import { index as accountOrders } from '@/routes/account/orders';
import { index as wishlistIndex } from '@/routes/account/wishlist';
import cart from '@/routes/cart';
import { show as categoryShow } from '@/routes/categories';
import type { StorefrontCategoryLink } from '@/types/marketplace';

interface StorefrontLayoutProps {
    children: React.ReactNode;
    /** Pass categories to populate the desktop nav row and the mobile menu. */
    categories?: StorefrontCategoryLink[];
    /** Active category slug, highlighted in the nav row. */
    activeCategorySlug?: string;
}

/**
 * The storefront shell — intentionally nothing like the admin sidebar layout.
 *
 * Everything inside the `.storefront` wrapper reads the shop palette declared in
 * `app.css`: a teal primary, cream product surfaces and the Plus Jakarta display
 * face. The dashboards sit outside it and keep the neutral app theme.
 *
 * Structure:
 *   - Ink announcement bar with the delivery/payment promises
 *   - Sticky header: logo + pill search + account/cart
 *   - Category row (desktop) / sheet menu (mobile)
 *   - Page content
 *   - Help band + multi-column footer
 */
export default function StorefrontLayout({
    children,
    categories,
    activeCategorySlug,
}: StorefrontLayoutProps) {
    const props = usePage().props;
    const user = (props.auth as { user: { name: string } | null } | undefined)
        ?.user;
    const cartCount = Number(props.cartCount ?? 0);
    const appName = String(props.name ?? 'Shop');
    const [searchOpen, setSearchOpen] = useState(false);
    const searchRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (searchOpen) {
            searchRef.current?.focus();
        }
    }, [searchOpen]);

    const navCategories = (categories ?? []).slice(0, 6);

    return (
        <div className="storefront flex min-h-screen flex-col bg-background">
            {/* ─────────────────────── ANNOUNCEMENT BAR ─────────────────── */}
            <div className="bg-ink py-2 text-[0.7rem] text-white">
                <div className="mx-auto flex w-full max-w-[1400px] items-center justify-between gap-4 px-4 lg:px-8">
                    <span className="truncate">
                        Discover and support Rwanda’s local shops
                    </span>
                    <div className="hidden shrink-0 items-center gap-6 sm:flex">
                        <span className="flex items-center gap-1.5">
                            <Truck className="size-3.5 text-aqua" />
                            Delivered by the shop
                        </span>
                        <span className="flex items-center gap-1.5">
                            <Banknote className="size-3.5 text-aqua" />
                            Cash on delivery
                        </span>
                        <span className="flex items-center gap-1.5">
                            <ShieldCheck className="size-3.5 text-aqua" />
                            Verified vendors
                        </span>
                    </div>
                </div>
            </div>

            {/* ─────────────────────── HEADER ────────────────────────────── */}
            <header className="sticky top-0 z-40 border-b border-border bg-background/90 backdrop-blur-md">
                <div className="mx-auto grid h-16 w-full max-w-[1400px] grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 px-4 lg:h-20 lg:gap-8 lg:px-8">
                    <div className="flex min-w-0 items-center gap-3">
                        {/* Mobile menu */}
                        <Sheet>
                            <SheetTrigger asChild>
                                <button
                                    type="button"
                                    className="rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none lg:hidden"
                                    aria-label="Open navigation menu"
                                >
                                    <Menu className="size-5" />
                                </button>
                            </SheetTrigger>
                            <SheetContent
                                side="left"
                                className="w-80 overflow-y-auto"
                            >
                                <SheetHeader className="border-b border-border pb-4">
                                    <SheetTitle className="flex items-center gap-2 text-left">
                                        <span className="grid size-8 shrink-0 place-items-center rounded-xl bg-primary text-primary-foreground">
                                            <AppLogoIcon className="size-4 fill-current" />
                                        </span>
                                        <span className="font-heading text-xl">
                                            {appName}
                                        </span>
                                    </SheetTitle>
                                </SheetHeader>

                                <nav
                                    className="flex flex-col gap-1 p-4"
                                    aria-label="Mobile navigation"
                                >
                                    <MobileLink
                                        href={home.url()}
                                        label="Home"
                                    />
                                    <MobileLink
                                        href={shop.url()}
                                        label="Shop all"
                                    />
                                    <MobileLink
                                        href={cart.index.url()}
                                        label={`Cart${cartCount > 0 ? ` (${cartCount})` : ''}`}
                                    />

                                    {(categories ?? []).length > 0 ? (
                                        <>
                                            <p className="mt-6 mb-2 px-1 eyebrow text-muted-foreground">
                                                Categories
                                            </p>
                                            {(categories ?? []).map((cat) => (
                                                <MobileLink
                                                    key={cat.id}
                                                    href={categoryShow.url(cat)}
                                                    label={cat.name}
                                                    size="sm"
                                                />
                                            ))}
                                        </>
                                    ) : null}

                                    <p className="mt-6 mb-2 px-1 eyebrow text-muted-foreground">
                                        Account
                                    </p>
                                    {user ? (
                                        <>
                                            <MobileLink
                                                href={accountOrders.url()}
                                                label="My orders"
                                                size="sm"
                                            />
                                            <MobileLink
                                                href={wishlistIndex.url()}
                                                label="Wishlist"
                                                size="sm"
                                            />
                                        </>
                                    ) : (
                                        <>
                                            <MobileLink
                                                href={login.url()}
                                                label="Log in"
                                                size="sm"
                                            />
                                            <MobileLink
                                                href={register.url()}
                                                label="Create account"
                                                size="sm"
                                            />
                                        </>
                                    )}
                                </nav>
                            </SheetContent>
                        </Sheet>

                        {/* Logo */}
                        <Link
                            href={home.url()}
                            className="flex shrink-0 items-center gap-2 rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            aria-label={`${appName} — Home`}
                        >
                            <span className="grid size-8 place-items-center rounded-xl bg-primary text-primary-foreground">
                                <AppLogoIcon className="size-4 fill-current" />
                            </span>
                            <span className="font-heading text-xl">
                                {appName}
                            </span>
                        </Link>
                    </div>

                    {/* Desktop search */}
                    <div className="min-w-0">
                        <SearchForm className="hidden md:block" />
                    </div>

                    {/* Right-side controls */}
                    <div className="flex shrink-0 items-center gap-3 sm:gap-5">
                        <button
                            type="button"
                            aria-label="Toggle search"
                            aria-expanded={searchOpen}
                            onClick={() => setSearchOpen((v) => !v)}
                            className="rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none md:hidden"
                        >
                            <Search className="size-[18px]" />
                        </button>

                        {user ? (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button
                                        type="button"
                                        className="hidden items-center gap-1 rounded-md text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:flex"
                                        aria-label={`Account menu for ${user.name}`}
                                    >
                                        <User className="size-[18px]" />
                                        <span className="hidden lg:inline">
                                            {user.name.split(' ')[0]}
                                        </span>
                                        <ChevronDown className="size-3 opacity-60" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="w-48"
                                >
                                    <DropdownMenuItem asChild>
                                        <Link href={accountOrders.url()}>
                                            My orders
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href={wishlistIndex.url()}>
                                            Wishlist
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem asChild>
                                        <Link href="/settings/profile">
                                            Settings
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : (
                            <div className="hidden items-center gap-3 sm:flex">
                                <Link
                                    href={login.url()}
                                    className="link-underline text-sm font-semibold"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register.url()}
                                    className="rounded-full bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                                >
                                    Sign up
                                </Link>
                            </div>
                        )}

                        <button
                            type="button"
                            onClick={openCartDrawer}
                            className="relative rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            aria-label={`Open your bag, ${cartCount} item${cartCount === 1 ? '' : 's'}`}
                        >
                            <ShoppingBag className="size-[18px]" />
                            {cartCount > 0 ? (
                                <span
                                    className="absolute -top-2 -right-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground tabular-nums"
                                    aria-hidden="true"
                                >
                                    {cartCount > 99 ? '99+' : cartCount}
                                </span>
                            ) : null}
                        </button>
                    </div>
                </div>

                {/* Mobile search — slides in below the main row */}
                {searchOpen ? (
                    <div className="mx-auto w-full max-w-[1400px] px-4 pb-3 md:hidden lg:px-8">
                        <SearchForm inputRef={searchRef} />
                    </div>
                ) : null}

                {/* Category row */}
                {navCategories.length > 0 ? (
                    <nav
                        aria-label="Product categories"
                        className="mx-auto hidden w-full max-w-[1400px] items-center gap-8 px-4 pb-3 text-sm lg:flex lg:px-8"
                    >
                        <Link
                            href={shop.url()}
                            aria-current={
                                !activeCategorySlug ? 'page' : undefined
                            }
                            className={cn(
                                'link-underline',
                                !activeCategorySlug
                                    ? 'font-semibold text-primary'
                                    : null,
                            )}
                        >
                            Shop all
                        </Link>
                        {navCategories.map((cat) => (
                            <Link
                                key={cat.id}
                                href={categoryShow.url(cat)}
                                aria-current={
                                    cat.slug === activeCategorySlug
                                        ? 'page'
                                        : undefined
                                }
                                className={cn(
                                    'link-underline truncate',
                                    cat.slug === activeCategorySlug
                                        ? 'font-semibold text-primary'
                                        : null,
                                )}
                            >
                                {cat.name}
                            </Link>
                        ))}
                    </nav>
                ) : null}
            </header>

            <CartDrawer />

            {/* ─────────────────────── CONTENT ───────────────────────────── */}
            <main className="flex-1" id="main-content">
                {children}
            </main>

            {/* ─────────────────────── FOOTER ────────────────────────────── */}
            <footer className="mt-20">
                <div className="bg-aqua-soft">
                    <div className="mx-auto grid w-full max-w-[1400px] gap-6 px-4 py-10 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center lg:px-8">
                        <div>
                            <h2 className="text-xl">
                                We’re always here to help
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Friendly support for every order from local
                                shops.
                            </p>
                        </div>
                        <div className="grid gap-4 text-sm sm:grid-cols-3">
                            <HelpItem
                                icon={<LifeBuoy className="size-4" />}
                                label="Help centre"
                                value="Ask the shop directly"
                            />
                            <HelpItem
                                icon={<Truck className="size-4" />}
                                label="Delivery"
                                value="By the shop you ordered from"
                            />
                            <HelpItem
                                icon={<Banknote className="size-4" />}
                                label="Payment"
                                value="Cash, at your door"
                            />
                        </div>
                    </div>
                </div>

                <div className="border-t border-border bg-cream">
                    <div className="mx-auto grid w-full max-w-[1400px] gap-10 px-4 py-14 md:grid-cols-12 lg:px-8">
                        <div className="md:col-span-5">
                            <Link
                                href={home.url()}
                                className="inline-flex items-center gap-2 rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <span className="grid size-8 place-items-center rounded-xl bg-primary text-primary-foreground">
                                    <AppLogoIcon className="size-4 fill-current" />
                                </span>
                                <span className="font-heading text-xl">
                                    {appName}
                                </span>
                            </Link>
                            <p className="mt-5 max-w-sm text-sm leading-relaxed text-muted-foreground">
                                A marketplace of independent local shops. Browse
                                and order from several vendors in one checkout —
                                every shop delivers its own orders and is paid
                                in cash at the door.
                            </p>
                            <div className="mt-6 flex gap-3">
                                {[
                                    { Icon: Instagram, label: 'Instagram' },
                                    { Icon: Facebook, label: 'Facebook' },
                                    { Icon: Mail, label: 'Email' },
                                ].map(({ Icon, label }) => (
                                    <span
                                        key={label}
                                        className="grid size-9 place-items-center rounded-full border border-border bg-background text-muted-foreground"
                                        aria-hidden="true"
                                    >
                                        <Icon className="size-4" />
                                    </span>
                                ))}
                            </div>
                        </div>

                        <div className="md:col-span-2">
                            <p className="mb-4 eyebrow text-muted-foreground">
                                Shop
                            </p>
                            <ul className="space-y-2.5 text-sm text-muted-foreground">
                                <li>
                                    <FooterLink
                                        href={shop.url()}
                                        label="All products"
                                    />
                                </li>
                                <li>
                                    <FooterLink
                                        href={cart.index.url()}
                                        label="Your cart"
                                    />
                                </li>
                                {(categories ?? []).slice(0, 4).map((cat) => (
                                    <li key={cat.id}>
                                        <FooterLink
                                            href={categoryShow.url(cat)}
                                            label={cat.name}
                                        />
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="md:col-span-2">
                            <p className="mb-4 eyebrow text-muted-foreground">
                                Account
                            </p>
                            <ul className="space-y-2.5 text-sm text-muted-foreground">
                                {user ? (
                                    <>
                                        <li>
                                            <FooterLink
                                                href={accountOrders.url()}
                                                label="My orders"
                                            />
                                        </li>
                                        <li>
                                            <FooterLink
                                                href={wishlistIndex.url()}
                                                label="Wishlist"
                                            />
                                        </li>
                                        <li>
                                            <FooterLink
                                                href="/settings/profile"
                                                label="Profile settings"
                                            />
                                        </li>
                                    </>
                                ) : (
                                    <>
                                        <li>
                                            <FooterLink
                                                href={login.url()}
                                                label="Log in"
                                            />
                                        </li>
                                        <li>
                                            <FooterLink
                                                href={register.url()}
                                                label="Create account"
                                            />
                                        </li>
                                    </>
                                )}
                            </ul>
                        </div>

                        <div className="md:col-span-3">
                            <p className="mb-4 eyebrow text-muted-foreground">
                                How ordering works
                            </p>
                            <ul className="space-y-3 text-sm text-muted-foreground">
                                <li className="flex gap-2.5">
                                    <Store className="mt-0.5 size-4 shrink-0 text-primary" />
                                    Fill one cart from as many shops as you
                                    like.
                                </li>
                                <li className="flex gap-2.5">
                                    <Package className="mt-0.5 size-4 shrink-0 text-primary" />
                                    Each shop packs and delivers its own items.
                                </li>
                                <li className="flex gap-2.5">
                                    <Banknote className="mt-0.5 size-4 shrink-0 text-primary" />
                                    Pay each shop in cash when it arrives.
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div className="mx-auto flex w-full max-w-[1400px] flex-col justify-between gap-3 border-t border-border px-4 py-6 text-xs text-muted-foreground md:flex-row lg:px-8">
                        <span>
                            &copy; {new Date().getFullYear()} {appName}. All
                            rights reserved.
                        </span>
                        <span>
                            Cash on delivery only — you pay each shop directly
                            when it delivers.
                        </span>
                    </div>
                </div>
            </footer>
        </div>
    );
}

// ─── Sub-components ───────────────────────────────────────────────────────────

interface SearchFormProps {
    className?: string;
    inputRef?: React.RefObject<HTMLInputElement | null>;
}

/** The kit's pill search field, submitting straight to the catalog. */
function SearchForm({ className, inputRef }: SearchFormProps) {
    return (
        <form
            action={shop.url()}
            method="get"
            className={className}
            role="search"
        >
            <label className="flex items-center gap-2 rounded-full border border-border bg-secondary px-4 py-2.5 focus-within:border-primary">
                <Search
                    className="size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <input
                    ref={inputRef}
                    type="search"
                    name="search"
                    placeholder="What are you looking for?"
                    aria-label="Search products"
                    className="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                />
            </label>
        </form>
    );
}

function HelpItem({
    icon,
    label,
    value,
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
}) {
    return (
        <div className="flex items-start gap-2">
            <span className="mt-0.5 shrink-0 text-primary" aria-hidden="true">
                {icon}
            </span>
            <div className="min-w-0">
                <div className="text-xs text-muted-foreground">{label}</div>
                <span>{value}</span>
            </div>
        </div>
    );
}

function FooterLink({ href, label }: { href: string; label: string }) {
    return (
        <Link
            href={href}
            className="rounded transition-colors hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            {label}
        </Link>
    );
}

function MobileLink({
    href,
    label,
    size = 'lg',
}: {
    href: string;
    label: string;
    size?: 'sm' | 'lg';
}) {
    return (
        <Link
            href={href}
            className={cn(
                'rounded-md px-1 py-1.5 transition-colors hover:text-primary',
                size === 'lg'
                    ? 'font-heading text-xl'
                    : 'text-sm font-medium text-muted-foreground',
            )}
        >
            {label}
        </Link>
    );
}
