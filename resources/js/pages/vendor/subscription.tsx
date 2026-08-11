import { Deferred, Head } from '@inertiajs/react';
import { Banknote, Info } from 'lucide-react';
import { Money } from '@/components/money';
import { SubscriptionStatusBadge } from '@/components/status-badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { VendorNav } from '@/components/vendor/vendor-nav';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatRelativeDays } from '@/lib/format';
import type {
    SubscriptionPaymentMethod,
    SubscriptionStatus,
    VendorSubscriptionStatus,
} from '@/types/marketplace';

interface Payment {
    id: string;
    amount: number;
    currency: string;
    status: VendorSubscriptionStatus;
    starts_at: string;
    ends_at: string;
    payment_method: SubscriptionPaymentMethod;
    reference: string | null;
    paid_at: string | null;
}

const methodLabels: Record<SubscriptionPaymentMethod, string> = {
    mobile_money: 'Mobile money',
    bank_transfer: 'Bank transfer',
    cash: 'Cash',
};

export default function VendorSubscriptionPage({
    subscription,
    terms,
    payments,
}: {
    subscription: {
        status: SubscriptionStatus;
        ends_at: string | null;
        can_sell: boolean;
        is_platform_owned: boolean;
    };
    terms: { fee: number; currency: string; days: number; grace_days: number };
    payments?: Payment[];
}) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Vendor', href: '/vendor' },
                { title: 'Subscription', href: '/vendor/subscription' },
            ]}
        >
            <Head title="Subscription" />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Subscription
                </h1>

                <VendorNav />

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <CardTitle className="text-base">
                                    Your selling status
                                </CardTitle>
                                <SubscriptionStatusBadge
                                    status={subscription.status}
                                />
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-4">
                            {subscription.is_platform_owned ? (
                                <Alert>
                                    <Info className="size-4" />
                                    <AlertDescription>
                                        This is the platform's own store and is
                                        exempt from the subscription.
                                    </AlertDescription>
                                </Alert>
                            ) : (
                                <>
                                    <div className="text-sm">
                                        {subscription.ends_at ? (
                                            <p>
                                                Your current period ends{' '}
                                                <span className="font-medium">
                                                    {formatDate(
                                                        subscription.ends_at,
                                                    )}
                                                </span>{' '}
                                                (
                                                {formatRelativeDays(
                                                    subscription.ends_at,
                                                )}
                                                ).
                                            </p>
                                        ) : (
                                            <p>
                                                You do not have an active
                                                subscription yet.
                                            </p>
                                        )}
                                    </div>

                                    {!subscription.can_sell ? (
                                        <Alert className="border-destructive/30 bg-destructive/5">
                                            <AlertDescription>
                                                Your shop is currently hidden
                                                from the storefront. Nothing has
                                                been deleted — your products,
                                                orders and history are exactly
                                                as you left them, and paying
                                                again restores the shop
                                                immediately.
                                            </AlertDescription>
                                        </Alert>
                                    ) : null}
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                How to pay
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div className="flex items-baseline gap-2">
                                <Banknote className="size-4 shrink-0" />
                                <Money
                                    amount={terms.fee}
                                    currency={terms.currency}
                                    className="text-lg font-semibold"
                                />
                                <span className="text-xs text-muted-foreground">
                                    / {terms.days} days
                                </span>
                            </div>

                            <p className="text-muted-foreground">
                                Pay by mobile money, bank transfer or cash, then
                                send the reference to the platform admin. They
                                record the payment and your shop is restored.
                            </p>

                            <p className="text-xs text-muted-foreground">
                                After a period ends you keep selling for{' '}
                                {terms.grace_days} more days before the shop is
                                hidden.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Payment history
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Deferred
                            data="payments"
                            fallback={
                                <div className="space-y-2">
                                    {Array.from({ length: 3 }).map(
                                        (_, index) => (
                                            <Skeleton
                                                key={index}
                                                className="h-10 w-full"
                                            />
                                        ),
                                    )}
                                </div>
                            }
                        >
                            {(payments ?? []).length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No payments recorded yet.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Period</TableHead>
                                                <TableHead>Method</TableHead>
                                                <TableHead>Reference</TableHead>
                                                <TableHead>Paid</TableHead>
                                                <TableHead className="text-right">
                                                    Amount
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {(payments ?? []).map((payment) => (
                                                <TableRow key={payment.id}>
                                                    <TableCell className="text-xs whitespace-nowrap">
                                                        {formatDate(
                                                            payment.starts_at,
                                                        )}{' '}
                                                        –{' '}
                                                        {formatDate(
                                                            payment.ends_at,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-xs">
                                                        {
                                                            methodLabels[
                                                                payment
                                                                    .payment_method
                                                            ]
                                                        }
                                                    </TableCell>
                                                    <TableCell className="text-xs">
                                                        {payment.reference ??
                                                            '—'}
                                                    </TableCell>
                                                    <TableCell className="text-xs">
                                                        {formatDate(
                                                            payment.paid_at,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Money
                                                            amount={
                                                                payment.amount
                                                            }
                                                            currency={
                                                                payment.currency
                                                            }
                                                        />
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}
                        </Deferred>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
