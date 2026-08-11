import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { buttonVariants } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

/**
 * Confirmation for destructive or irreversible actions.
 *
 * The description should state the consequence in concrete terms — "this vendor's
 * shop will be hidden from the storefront" beats "are you sure?".
 */
export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    onConfirm,
    processing = false,
    destructive = true,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    onConfirm: () => void;
    processing?: boolean;
    destructive?: boolean;
}) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {description}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>
                        {cancelLabel}
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={(event) => {
                            // Keep the dialog open while the request is in flight so
                            // the spinner is actually visible and the action cannot be
                            // fired twice.
                            event.preventDefault();
                            onConfirm();
                        }}
                        disabled={processing}
                        className={cn(
                            destructive &&
                                buttonVariants({ variant: 'destructive' }),
                        )}
                    >
                        {processing ? <Spinner /> : null}
                        {confirmLabel}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
