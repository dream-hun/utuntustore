import { Head, useForm } from '@inertiajs/react';
import { ImageOff } from 'lucide-react';
import { SubscriptionBanner } from '@/components/vendor/subscription-banner';
import { VendorNav } from '@/components/vendor/vendor-nav';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';

interface Shop {
    id: string;
    shop_name: string;
    slug: string;
    description: string | null;
    phone: string;
    email: string | null;
    delivery_notes: string | null;
    status: string;
    logo_url: string | null;
    banner_url: string | null;
}

export default function VendorShopProfile({ shop }: { shop: Shop }) {
    const form = useForm<{
        shop_name: string;
        description: string;
        phone: string;
        email: string;
        delivery_notes: string;
        logo: File | null;
        banner: File | null;
    }>({
        shop_name: shop.shop_name,
        description: shop.description ?? '',
        phone: shop.phone,
        email: shop.email ?? '',
        delivery_notes: shop.delivery_notes ?? '',
        logo: null,
        banner: null,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        // POST rather than PUT: a multipart body carrying the logo and banner cannot
        // be sent reliably over PUT from a browser.
        form.post('/vendor/shop', {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Vendor', href: '/vendor' },
                { title: 'Shop profile', href: '/vendor/shop' },
            ]}
        >
            <Head title="Shop profile" />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Shop profile
                </h1>

                <SubscriptionBanner />
                <VendorNav />

                <form onSubmit={submit} className="grid max-w-3xl gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Shop details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="shop_name">Shop name</Label>
                                <Input
                                    id="shop_name"
                                    value={form.data.shop_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'shop_name',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.shop_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    rows={4}
                                    value={form.data.description}
                                    onChange={(event) =>
                                        form.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.description} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Phone</Label>
                                    <Input
                                        id="phone"
                                        value={form.data.phone}
                                        onChange={(event) =>
                                            form.setData(
                                                'phone',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={form.errors.phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={form.data.email}
                                        onChange={(event) =>
                                            form.setData(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={form.errors.email} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="delivery_notes">
                                    Delivery notes
                                </Label>
                                <Textarea
                                    id="delivery_notes"
                                    rows={3}
                                    value={form.data.delivery_notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'delivery_notes',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Orders before noon go out the same day…"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Free text for terms that do not fit your
                                    delivery areas. Where you deliver and what
                                    you charge is set on the delivery areas
                                    page.
                                </p>
                                <InputError
                                    message={form.errors.delivery_notes}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Artwork</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-6 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="logo">Logo</Label>
                                <div className="flex size-24 items-center justify-center overflow-hidden rounded-lg bg-muted">
                                    {shop.logo_url ? (
                                        <img
                                            src={shop.logo_url}
                                            alt="Shop logo"
                                            className="size-full object-cover"
                                        />
                                    ) : (
                                        <ImageOff className="size-6 text-muted-foreground" />
                                    )}
                                </div>
                                <Input
                                    id="logo"
                                    type="file"
                                    accept="image/*"
                                    onChange={(event) =>
                                        form.setData(
                                            'logo',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError message={form.errors.logo} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="banner">Banner</Label>
                                <div className="flex h-24 w-full items-center justify-center overflow-hidden rounded-lg bg-muted">
                                    {shop.banner_url ? (
                                        <img
                                            src={shop.banner_url}
                                            alt="Shop banner"
                                            className="size-full object-cover"
                                        />
                                    ) : (
                                        <ImageOff className="size-6 text-muted-foreground" />
                                    )}
                                </div>
                                <Input
                                    id="banner"
                                    type="file"
                                    accept="image/*"
                                    onChange={(event) =>
                                        form.setData(
                                            'banner',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError message={form.errors.banner} />
                            </div>
                        </CardContent>
                    </Card>

                    <div>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? <Spinner /> : null}
                            Save changes
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
