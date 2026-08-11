import { Deferred, Head, router } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { Money } from '@/components/money';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { SubscriptionBanner } from '@/components/vendor/subscription-banner';
import { VendorNav } from '@/components/vendor/vendor-nav';
import AppLayout from '@/layouts/app-layout';

interface SalesReport {
    collected: number;
    order_count: number;
    item_count: number;
    average_order: number;
    cancelled_count: number;
    cancelled_value: number;
    currency: string;
    by_month: { month: string; collected: number; order_count: number }[];
    top_products: { name: string; quantity: number; collected: number }[];
    subscription_fee: number;
    subscription_ends_at: string | null;
}

export default function VendorSales({
    period,
    report,
}: {
    period: string;
    range: { from: string; to: string };
    report?: SalesReport;
}) {
    const setPeriod = (next: string) => {
        router.get(
            '/vendor/sales',
            { period: next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Vendor', href: '/vendor' },
                { title: 'Sales', href: '/vendor/sales' },
            ]}
        >
            <Head title="Sales" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Sales
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Cash you collected on delivery.
                        </p>
                    </div>

                    <Select value={period} onValueChange={setPeriod}>
                        <SelectTrigger className="w-44">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="month">This month</SelectItem>
                            <SelectItem value="quarter">
                                This quarter
                            </SelectItem>
                            <SelectItem value="year">This year</SelectItem>
                            <SelectItem value="all">All time</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <SubscriptionBanner />
                <VendorNav />

                <Alert>
                    <Info className="size-4" />
                    <AlertDescription>
                        This is money customers handed you directly. The
                        platform never holds it and owes you nothing — there is
                        no balance or payout to wait for.
                    </AlertDescription>
                </Alert>

                <Deferred
                    data="report"
                    fallback={
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {Array.from({ length: 4 }).map((_, index) => (
                                <Skeleton key={index} className="h-24 w-full" />
                            ))}
                        </div>
                    }
                >
                    <>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Card>
                                <CardContent>
                                    <p className="text-xs text-muted-foreground">
                                        Cash collected
                                    </p>
                                    <p className="text-2xl font-semibold">
                                        <Money
                                            amount={report?.collected ?? 0}
                                            currency={report?.currency}
                                        />
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent>
                                    <p className="text-xs text-muted-foreground">
                                        Orders delivered
                                    </p>
                                    <p className="text-2xl font-semibold">
                                        {report?.order_count ?? 0}
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent>
                                    <p className="text-xs text-muted-foreground">
                                        Items sold
                                    </p>
                                    <p className="text-2xl font-semibold">
                                        {report?.item_count ?? 0}
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent>
                                    <p className="text-xs text-muted-foreground">
                                        Average order
                                    </p>
                                    <p className="text-2xl font-semibold">
                                        <Money
                                            amount={report?.average_order ?? 0}
                                            currency={report?.currency}
                                        />
                                    </p>
                                </CardContent>
                            </Card>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        By month
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {(report?.by_month ?? []).length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No deliveries in this period.
                                        </p>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>
                                                            Month
                                                        </TableHead>
                                                        <TableHead>
                                                            Orders
                                                        </TableHead>
                                                        <TableHead className="text-right">
                                                            Collected
                                                        </TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {(
                                                        report?.by_month ?? []
                                                    ).map((row) => (
                                                        <TableRow
                                                            key={row.month}
                                                        >
                                                            <TableCell className="text-sm">
                                                                {row.month}
                                                            </TableCell>
                                                            <TableCell className="text-sm">
                                                                {
                                                                    row.order_count
                                                                }
                                                            </TableCell>
                                                            <TableCell className="text-right">
                                                                <Money
                                                                    amount={
                                                                        row.collected
                                                                    }
                                                                />
                                                            </TableCell>
                                                        </TableRow>
                                                    ))}
                                                </TableBody>
                                            </Table>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Top products
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {(report?.top_products ?? []).length ===
                                    0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            Nothing sold in this period.
                                        </p>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>
                                                            Product
                                                        </TableHead>
                                                        <TableHead>
                                                            Qty
                                                        </TableHead>
                                                        <TableHead className="text-right">
                                                            Collected
                                                        </TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {(
                                                        report?.top_products ??
                                                        []
                                                    ).map((row) => (
                                                        <TableRow
                                                            key={row.name}
                                                        >
                                                            <TableCell className="text-sm">
                                                                {row.name}
                                                            </TableCell>
                                                            <TableCell className="text-sm">
                                                                {row.quantity}
                                                            </TableCell>
                                                            <TableCell className="text-right">
                                                                <Money
                                                                    amount={
                                                                        row.collected
                                                                    }
                                                                />
                                                            </TableCell>
                                                        </TableRow>
                                                    ))}
                                                </TableBody>
                                            </Table>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        {(report?.cancelled_count ?? 0) > 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {report?.cancelled_count} cancelled order
                                {report?.cancelled_count === 1
                                    ? ''
                                    : 's'} worth{' '}
                                <Money amount={report?.cancelled_value ?? 0} />{' '}
                                are not counted above.
                            </p>
                        ) : null}
                    </>
                </Deferred>
            </div>
        </AppLayout>
    );
}
