import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { FormModal } from '@/components/form-modal';
import { Money } from '@/components/money';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import InputError from '@/components/input-error';
import { formatDate } from '@/lib/format';
import admin from '@/routes/admin';
import type { SubscriptionPaymentMethod } from '@/types/marketplace';
import type { PayableVendor } from './types';

const paymentMethods: { value: SubscriptionPaymentMethod; label: string }[] = [
    { value: 'mobile_money', label: 'Mobile money' },
    { value: 'bank_transfer', label: 'Bank transfer' },
    { value: 'cash', label: 'Cash' },
];

interface PaymentForm {
    vendor: string;
    payment_method: SubscriptionPaymentMethod;
    reference: string;
    paid_at: string;
    [key: string]: string;
}

/**
 * Records money the platform has already received.
 *
 * Nothing is charged here — the vendor paid by mobile money, bank transfer or cash
 * outside the system and this is the admin confirming it. The reference is required
 * because it is the platform's only trace that the payment happened.
 */
export function RecordPaymentModal({
    open,
    onOpenChange,
    vendors,
    fee,
    defaultVendorId,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    vendors: PayableVendor[];
    fee: { amount: number; currency: string; days: number };
    defaultVendorId?: string;
}) {
    const form = useForm<PaymentForm>({
        vendor: defaultVendorId ?? '',
        payment_method: 'mobile_money',
        reference: '',
        paid_at: '',
    });

    useEffect(() => {
        if (open) {
            form.setData('vendor', defaultVendorId ?? '');
        }
        // The form object is recreated on every render; keying the reset on `open`
        // alone is what makes the modal start clean each time it is opened.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, defaultVendorId]);

    const selected = vendors.find((vendor) => vendor.id === form.data.vendor);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.post(admin.subscriptions.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title="Record a subscription payment"
            description="Confirm money the platform has already received off-platform. This is the only revenue the marketplace earns."
            onSubmit={submit}
            processing={form.processing}
            submitLabel="Record payment"
        >
            <Alert>
                <AlertDescription>
                    Recording activates{' '}
                    <Money amount={fee.amount} currency={fee.currency} /> for{' '}
                    {fee.days} days. A vendor paying before their period ends
                    keeps the time they already own — the new period starts
                    where the old one finished.
                </AlertDescription>
            </Alert>

            <div className="grid gap-2">
                <Label htmlFor="vendor">Vendor</Label>
                <Select
                    value={form.data.vendor}
                    onValueChange={(value) => form.setData('vendor', value)}
                >
                    <SelectTrigger id="vendor" className="w-full">
                        <SelectValue placeholder="Choose an approved shop" />
                    </SelectTrigger>
                    <SelectContent>
                        {vendors.map((vendor) => (
                            <SelectItem key={vendor.id} value={vendor.id}>
                                {vendor.shop_name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {selected ? (
                    <p className="text-xs text-muted-foreground">
                        Currently{' '}
                        {selected.subscription_status.replace('_', ' ')}
                        {selected.subscription_ends_at
                            ? ` · runs to ${formatDate(selected.subscription_ends_at)}`
                            : ''}
                    </p>
                ) : null}
                <InputError message={form.errors.vendor} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="payment_method">Payment method</Label>
                <Select
                    value={form.data.payment_method}
                    onValueChange={(value) =>
                        form.setData(
                            'payment_method',
                            value as SubscriptionPaymentMethod,
                        )
                    }
                >
                    <SelectTrigger id="payment_method" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {paymentMethods.map((method) => (
                            <SelectItem key={method.value} value={method.value}>
                                {method.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.payment_method} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="reference">Payment reference</Label>
                <Input
                    id="reference"
                    value={form.data.reference}
                    onChange={(event) =>
                        form.setData('reference', event.target.value)
                    }
                    placeholder="MoMo transaction ID, bank slip or receipt number"
                    autoComplete="off"
                />
                <p className="text-xs text-muted-foreground">
                    Required. A subscription never goes active without one.
                </p>
                <InputError message={form.errors.reference} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="paid_at">Date received</Label>
                <Input
                    id="paid_at"
                    type="date"
                    value={form.data.paid_at}
                    onChange={(event) =>
                        form.setData('paid_at', event.target.value)
                    }
                />
                <p className="text-xs text-muted-foreground">
                    Leave empty to record it as today.
                </p>
                <InputError message={form.errors.paid_at} />
            </div>
        </FormModal>
    );
}
