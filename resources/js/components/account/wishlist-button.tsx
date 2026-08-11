import { router } from '@inertiajs/react';
import { Heart } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import wishlist from '@/routes/account/wishlist';
import { cn } from '@/lib/utils';

/**
 * "Save for later" for a signed-in customer.
 *
 * Posts back to the wishlist and lets the flash toast confirm it, rather than keeping
 * a second copy of wishlist state in the browser.
 */
export function WishlistButton({
    productId,
    label = 'Save for later',
    variant = 'outline',
    size = 'sm',
    className,
}: {
    productId: string;
    label?: string;
    variant?: 'default' | 'outline' | 'ghost' | 'secondary';
    size?: 'default' | 'sm' | 'lg' | 'icon';
    className?: string;
}) {
    const [saving, setSaving] = useState(false);

    const save = () => {
        router.post(
            wishlist.store.url(),
            { product: productId },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Button
            type="button"
            variant={variant}
            size={size}
            className={cn(className)}
            disabled={saving}
            onClick={save}
        >
            {saving ? <Spinner /> : <Heart className="size-4" />}
            {label}
        </Button>
    );
}
