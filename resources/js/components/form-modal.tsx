import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/**
 * The standard create/edit modal for this application.
 *
 * Create, edit and delete always happen in a modal rather than on a separate page,
 * so the list the user is working through stays in view behind them.
 *
 * The submit button owns the pending state: it disables and shows a spinner while
 * the request is in flight, which is what stops double submission on a slow
 * connection — the common case for this marketplace's users.
 */
export function FormModal({
    open,
    onOpenChange,
    title,
    description,
    onSubmit,
    processing = false,
    submitLabel = 'Save',
    cancelLabel = 'Cancel',
    children,
    size = 'md',
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    onSubmit: (event: React.FormEvent) => void;
    processing?: boolean;
    submitLabel?: string;
    cancelLabel?: string;
    children: React.ReactNode;
    size?: 'md' | 'lg' | 'xl';
}) {
    const sizeClass = {
        md: 'sm:max-w-lg',
        lg: 'sm:max-w-2xl',
        xl: 'sm:max-w-4xl',
    }[size];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className={sizeClass}>
                <form onSubmit={onSubmit} className="flex flex-col gap-6">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {description ? (
                            <DialogDescription>{description}</DialogDescription>
                        ) : null}
                    </DialogHeader>

                    <div className="grid gap-4">{children}</div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                        >
                            {cancelLabel}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? <Spinner /> : null}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
