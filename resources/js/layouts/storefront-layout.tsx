import { Link, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    LogIn,
    Menu,
    Package,
    Search,
    ShoppingCart,
    Store,
    Truck,
    User,
    Heart,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { login, register, home, shop } from '@/routes';
import { index as accountOrders } from '@/routes/account/orders';
import { index as wishlistIndex } from '@/routes/account/wishlist';
import cart from '@/routes/cart';
import { show as categoryShow } from '@/routes/categories';
import type { StorefrontCategoryLink } from '@/types/marketplace';

interface StorefrontLayoutProps {
    children: React.ReactNode;
    /** Pass categories to populate the horizontal category nav bar. */
    categories?: StorefrontCategoryLink[];
    /** Active category slug for highlighting in the nav bar. */
    activeCategorySlug?: string;
}

/**
 * The storefront shell — intentionally nothing like the admin sidebar layout.
 *
 * Structure:
 *   - Sticky top bar: logo + search + account/cart nav
 *   - Category nav bar: horizontal scrollable strip (hidden on mobile, collapsible)
 *   - Page content
 *   - Multi-column footer
 *
 * Mobile-first: search collapses below the icon row on small screens;
 * the hamburger opens a sheet with full navigation.
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

    return (
        <div className="flex min-h-screen flex-col bg-[#fcfdfd] text-foreground">
            {/* ─────────────────────── TOP BAR ─────────────────────── */}
            <header className="sticky top-0 z-40 border-b border-border/70 bg-[#fcfdfd]/95 backdrop-blur supports-[backdrop-filter]:bg-[#fcfdfd]/85">
                <div className="hidden bg-[#102d36] text-[11px] text-white/75 sm:block">
                    <div className="mx-auto flex h-8 w-full max-w-[1400px] items-center justify-between px-6 lg:px-8">
                        <p>Discover and support Rwanda’s local shops</p>
                        <div className="flex items-center gap-5">
                            <span className="flex items-center gap-1.5">
                                <Truck className="size-3 text-[#73d6ca]" />{' '}
                                Local delivery
                            </span>
                            <span className="flex items-center gap-1.5">
                                <Package className="size-3 text-[#73d6ca]" />{' '}
                                Cash on delivery
                            </span>
                        </div>
                    </div>
                </div>
                <div className="mx-auto w-full max-w-[1400px] px-4 sm:px-6 lg:px-8">
                    {/* Main row */}
                    <div className="flex h-[4.5rem] items-center gap-3">
                        {/* Mobile hamburger */}
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="shrink-0 sm:hidden"
                                    aria-label="Open navigation menu"
                                >
                                    <Menu className="size-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" className="w-72">
                                <SheetHeader className="border-b border-border pb-4">
                                    <SheetTitle className="flex items-center gap-2 text-left">
                                        <span className="flex size-7 items-center justify-center rounded-md bg-primary text-primary-foreground">
                                            <AppLogoIcon className="size-4 fill-current" />
                                        </span>
                                        {appName}
                                    </SheetTitle>
                                </SheetHeader>

                                <nav
                                    className="flex flex-col gap-1 p-4"
                                    aria-label="Mobile navigation"
                                >
                                    <p className="mb-1 px-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Browse
                                    </p>
                                    <MobileLink
                                        href={home.url()}
                                        label="Home"
                                        icon={<Store className="size-4" />}
                                    />
                                    <MobileLink
                                        href={shop.url()}
                                        label="All Products"
                                        icon={<Package className="size-4" />}
                                    />
                                    <MobileLink
                                        href={cart.index.url()}
                                        label={`Cart${cartCount > 0 ? ` (${cartCount})` : ''}`}
                                        icon={
                                            <ShoppingCart className="size-4" />
                                        }
                                    />

                                    {categories && categories.length > 0 ? (
                                        <>
                                            <p className="mt-4 mb-1 px-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                                Categories
                                            </p>
                                            {categories.map((cat) => (
                                                <MobileLink
                                                    key={cat.id}
                                                    href={categoryShow.url(cat)}
                                                    label={cat.name}
                                                />
                                            ))}
                                        </>
                                    ) : null}

                                    <div
                                        className="my-4 h-px bg-border"
                                        role="separator"
                                    />

                                    <p className="mb-1 px-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Account
                                    </p>
                                    {user ? (
                                        <>
                                            <MobileLink
                                                href={accountOrders.url()}
                                                label="My orders"
                                                icon={
                                                    <Package className="size-4" />
                                                }
                                            />
                                            <MobileLink
                                                href={wishlistIndex.url()}
                                                label="Wishlist"
                                                icon={
                                                    <Heart className="size-4" />
                                                }
                                            />
                                        </>
                                    ) : (
                                        <>
                                            <MobileLink
                                                href={login.url()}
                                                label="Log in"
                                                icon={
                                                    <LogIn className="size-4" />
                                                }
                                            />
                                            <MobileLink
                                                href={register.url()}
                                                label="Create account"
                                                icon={
                                                    <User className="size-4" />
                                                }
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
                            <span className="flex size-8 items-center justify-center rounded-xl bg-[#42c4c6] text-[#11333a]">
                                <AppLogoIcon className="size-4 fill-current" />
                            </span>
                            <span className="hidden text-lg font-semibold tracking-tight sm:inline">
                                {appName}
                            </span>
                        </Link>

                        {/* Desktop search — centered in the bar */}
                        <div className="hidden max-w-xl flex-1 sm:block">
                            <SearchForm />
                        </div>

                        {/* Right-side controls */}
                        <div className="ml-auto flex items-center gap-1">
                            {/* Mobile search toggle */}
                            <Button
                                variant="ghost"
                                size="icon"
                                className="sm:hidden"
                                aria-label="Toggle search"
                                aria-expanded={searchOpen}
                                onClick={() => setSearchOpen((v) => !v)}
                            >
                                <Search className="size-5" />
                            </Button>

                            {/* Cart */}
                            <Button variant="ghost" size="sm" asChild>
                                <Link
                                    href={cart.index.url()}
                                    aria-label={`Cart, ${cartCount} item${cartCount === 1 ? '' : 's'}`}
                                >
                                    <span className="relative">
                                        <ShoppingCart className="size-5" />
                                        {cartCount > 0 ? (
                                            <span
                                                className="absolute -top-2 -right-2.5 flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground tabular-nums"
                                                aria-hidden="true"
                                            >
                                                {cartCount > 99
                                                    ? '99+'
                                                    : cartCount}
                                            </span>
                                        ) : null}
                                    </span>
                                    <span className="hidden sm:inline">
                                        Cart
                                    </span>
                                </Link>
                            </Button>

                            {/* Account */}
                            {user ? (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="hidden gap-1 sm:inline-flex"
                                            aria-label={`Account menu for ${user.name}`}
                                        >
                                            <User className="size-4" />
                                            {user.name.split(' ')[0]}
                                            <ChevronDown className="size-3 opacity-60" />
                                        </Button>
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
                                <div className="hidden items-center gap-1 sm:flex">
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href={login.url()}>
                                            <LogIn className="size-4" />
                                            Log in
                                        </Link>
                                    </Button>
                                    <Button size="sm" asChild>
                                        <Link href={register.url()}>
                                            Sign up
                                        </Link>
                                    </Button>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Mobile search — slides in below the main row */}
                    {searchOpen ? (
                        <div className="pb-3 sm:hidden">
                            <SearchForm inputRef={searchRef} />
                        </div>
                    ) : null}
                </div>

                {/* ─── Category nav bar ─────────────────────────────── */}
                {categories && categories.length > 0 ? (
                    <CategoryNavBar
                        categories={categories}
                        activeCategorySlug={activeCategorySlug}
                    />
                ) : null}
            </header>

            {/* ─────────────────────── CONTENT ─────────────────────── */}
            <main className="flex-1" id="main-content">
                {children}
            </main>

            {/* ─────────────────────── FOOTER ─────────────────────── */}
            <footer className="mt-16 border-t border-[#d5edeb] bg-[#effaf9]">
                <div className="border-b border-[#d5edeb] bg-[#dff3f0]">
                    <div className="mx-auto grid w-full max-w-[1400px] gap-5 px-4 py-8 sm:px-6 lg:grid-cols-[1fr_auto] lg:px-8">
                        <div>
                            <p className="text-lg font-semibold text-[#17353c]">
                                We’re always here to help
                            </p>
                            <p className="mt-1 text-sm text-[#527177]">
                                Friendly support for every order from local
                                shops.
                            </p>
                        </div>
                        <div className="flex items-center gap-2 text-sm font-medium text-[#168b8f]">
                            <Truck className="size-4" /> Local delivery, cash on
                            arrival
                        </div>
                    </div>
                </div>
                <div className="mx-auto w-full max-w-[1400px] px-4 py-12 sm:px-6 lg:px-8">
                    <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                        {/* Brand */}
                        <div className="lg:col-span-2">
                            <Link
                                href={home.url()}
                                className="inline-flex items-center gap-2 rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <span className="flex size-7 items-center justify-center rounded-md bg-primary text-primary-foreground">
                                    <AppLogoIcon className="size-4 fill-current" />
                                </span>
                                <span className="font-semibold">{appName}</span>
                            </Link>
                            <p className="mt-3 max-w-sm text-sm text-muted-foreground">
                                A marketplace of independent local shops. Browse
                                and order from multiple vendors in one checkout.
                                Every shop delivers its own orders and is paid
                                in cash at the door.
                            </p>
                        </div>

                        {/* Shop */}
                        <div>
                            <p className="mb-3 text-sm font-semibold text-foreground">
                                Shop
                            </p>
                            <ul className="space-y-2 text-sm text-muted-foreground">
                                <li>
                                    <Link
                                        href={shop.url()}
                                        className="transition-colors hover:text-foreground hover:underline"
                                    >
                                        All products
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href={cart.index.url()}
                                        className="transition-colors hover:text-foreground hover:underline"
                                    >
                                        Your cart
                                    </Link>
                                </li>
                                {categories?.slice(0, 4).map((cat) => (
                                    <li key={cat.id}>
                                        <Link
                                            href={categoryShow.url(cat)}
                                            className="transition-colors hover:text-foreground hover:underline"
                                        >
                                            {cat.name}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        {/* Account */}
                        <div>
                            <p className="mb-3 text-sm font-semibold text-foreground">
                                Account
                            </p>
                            <ul className="space-y-2 text-sm text-muted-foreground">
                                {user ? (
                                    <>
                                        <li>
                                            <Link
                                                href={accountOrders.url()}
                                                className="transition-colors hover:text-foreground hover:underline"
                                            >
                                                My orders
                                            </Link>
                                        </li>
                                        <li>
                                            <Link
                                                href={wishlistIndex.url()}
                                                className="transition-colors hover:text-foreground hover:underline"
                                            >
                                                Wishlist
                                            </Link>
                                        </li>
                                        <li>
                                            <Link
                                                href="/settings/profile"
                                                className="transition-colors hover:text-foreground hover:underline"
                                            >
                                                Profile settings
                                            </Link>
                                        </li>
                                    </>
                                ) : (
                                    <>
                                        <li>
                                            <Link
                                                href={login.url()}
                                                className="transition-colors hover:text-foreground hover:underline"
                                            >
                                                Log in
                                            </Link>
                                        </li>
                                        <li>
                                            <Link
                                                href={register.url()}
                                                className="transition-colors hover:text-foreground hover:underline"
                                            >
                                                Create account
                                            </Link>
                                        </li>
                                    </>
                                )}
                            </ul>
                        </div>
                    </div>

                    <div className="mt-10 flex flex-col gap-3 border-t border-border pt-6 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                        <p>
                            &copy; {new Date().getFullYear()} {appName}. All
                            rights reserved.
                        </p>
                        <p>
                            Cash on delivery only — you pay each shop directly
                            when it delivers.
                        </p>
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

function SearchForm({ className, inputRef }: SearchFormProps) {
    return (
        <form
            action={shop.url()}
            method="get"
            className={className}
            role="search"
        >
            <div className="relative">
                <Search
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    ref={inputRef}
                    type="search"
                    name="search"
                    placeholder="Search products, shops…"
                    aria-label="Search products"
                    className="h-9 pr-4 pl-9"
                />
            </div>
        </form>
    );
}

function CategoryNavBar({
    categories,
    activeCategorySlug,
}: {
    categories: StorefrontCategoryLink[];
    activeCategorySlug?: string;
}) {
    return (
        <nav
            aria-label="Product categories"
            className="border-t border-border bg-background/95"
        >
            <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex scrollbar-none gap-1 overflow-x-auto py-2.5">
                    <Link
                        href={shop.url()}
                        aria-current={!activeCategorySlug ? 'page' : undefined}
                        className={
                            'inline-flex shrink-0 items-center rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none ' +
                            (!activeCategorySlug
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-accent hover:text-foreground')
                        }
                    >
                        All
                    </Link>

                    {categories.map((cat) => {
                        const isActive = cat.slug === activeCategorySlug;

                        return (
                            <Link
                                key={cat.id}
                                href={categoryShow.url(cat)}
                                aria-current={isActive ? 'page' : undefined}
                                className={
                                    'inline-flex shrink-0 items-center rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none ' +
                                    (isActive
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-accent hover:text-foreground')
                                }
                            >
                                {cat.name}
                            </Link>
                        );
                    })}
                </div>
            </div>
        </nav>
    );
}

function MobileLink({
    href,
    label,
    icon,
}: {
    href: string;
    label: string;
    icon?: React.ReactNode;
}) {
    return (
        <Link
            href={href}
            className="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-foreground transition-colors hover:bg-accent"
        >
            {icon ? (
                <span className="text-muted-foreground" aria-hidden="true">
                    {icon}
                </span>
            ) : null}
            {label}
        </Link>
    );
}
