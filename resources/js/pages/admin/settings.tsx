import { Head, useForm } from '@inertiajs/react';
import { Info } from 'lucide-react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function AdminSettings({
    settings,
    liveSubscriptions,
}: {
    settings: {
        vendor_subscription_fee: number;
        vendor_subscription_currency: string;
        vendor_subscription_days: number;
        vendor_subscription_grace_days: number;
    };
    liveSubscriptions: number;
}) {
    const form = useForm({ ...settings });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.put('/admin/settings', { preserveScroll: true });
    };

    return (
        <>
            <Head title="Platform settings" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Platform settings
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Change the subscription terms without a deployment.
                    </p>
                </div>

                <form onSubmit={submit} className="max-w-2xl">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Vendor subscription
                            </CardTitle>
                        </CardHeader>

                        <CardContent className="grid gap-4">
                            <Alert>
                                <Info className="size-4" />
                                <AlertDescription>
                                    Changing the fee only affects payments
                                    recorded from now on. The{' '}
                                    {liveSubscriptions} subscription
                                    {liveSubscriptions === 1 ? '' : 's'}{' '}
                                    currently live keep the amount they were
                                    sold at — historical revenue is never
                                    rewritten.
                                </AlertDescription>
                            </Alert>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="fee">
                                        Annual fee (whole FRW)
                                    </Label>
                                    <Input
                                        id="fee"
                                        type="number"
                                        min={0}
                                        value={
                                            form.data.vendor_subscription_fee
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                'vendor_subscription_fee',
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            form.errors.vendor_subscription_fee
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="currency">Currency</Label>
                                    <Input
                                        id="currency"
                                        maxLength={3}
                                        value={
                                            form.data
                                                .vendor_subscription_currency
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                'vendor_subscription_currency',
                                                event.target.value.toUpperCase(),
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            form.errors
                                                .vendor_subscription_currency
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="days">
                                        Period length (days)
                                    </Label>
                                    <Input
                                        id="days"
                                        type="number"
                                        min={1}
                                        value={
                                            form.data.vendor_subscription_days
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                'vendor_subscription_days',
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            form.errors.vendor_subscription_days
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="grace">
                                        Grace period (days)
                                    </Label>
                                    <Input
                                        id="grace"
                                        type="number"
                                        min={0}
                                        value={
                                            form.data
                                                .vendor_subscription_grace_days
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                'vendor_subscription_grace_days',
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        How long a lapsed shop keeps selling
                                        before it is hidden from the storefront.
                                    </p>
                                    <InputError
                                        message={
                                            form.errors
                                                .vendor_subscription_grace_days
                                        }
                                    />
                                </div>
                            </div>

                            <div>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing ? <Spinner /> : null}
                                    Save settings
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </>
    );
}

AdminSettings.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Settings', href: '/admin/settings' },
    ],
};
