import { useForm } from '@inertiajs/react';
import { RatingInput } from '@/components/account/rating-stars';
import { FormModal } from '@/components/form-modal';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import reviews from '@/routes/account/reviews';

export interface ReviewTarget {
    /** The order item's uuid — set when writing a new review. */
    orderItemId?: string;
    /** The review's uuid — set when editing one already written. */
    reviewId?: string;
    productName: string;
    rating: number;
    title: string | null;
    comment: string | null;
}

/**
 * Write or rewrite a review.
 *
 * Editing sends the review back to pending, which the modal says out loud so nobody
 * is surprised when their review disappears from the product page for a while.
 */
export function ReviewFormModal({
    open,
    onOpenChange,
    target,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    target: ReviewTarget;
}) {
    const isEdit = Boolean(target.reviewId);

    const form = useForm({
        order_item: target.orderItemId ?? '',
        rating: target.rating,
        title: target.title ?? '',
        comment: target.comment ?? '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (target.reviewId) {
            form.put(reviews.update.url(target.reviewId), options);

            return;
        }

        form.post(reviews.store.url(), options);
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? 'Edit your review' : `Review ${target.productName}`}
            description={
                isEdit
                    ? 'An edited review is checked again before it goes back on the product page.'
                    : 'Reviews are checked before they appear on the product page.'
            }
            onSubmit={submit}
            processing={form.processing}
            submitLabel={isEdit ? 'Save changes' : 'Submit review'}
        >
            <div className="grid gap-2">
                <Label>Rating</Label>
                <RatingInput
                    value={form.data.rating}
                    onChange={(rating) => form.setData('rating', rating)}
                    disabled={form.processing}
                />
                <InputError message={form.errors.rating} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="review_title">Title (optional)</Label>
                <Input
                    id="review_title"
                    value={form.data.title}
                    onChange={(event) =>
                        form.setData('title', event.target.value)
                    }
                    maxLength={255}
                />
                <InputError message={form.errors.title} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="review_comment">Your review (optional)</Label>
                <Textarea
                    id="review_comment"
                    value={form.data.comment}
                    onChange={(event) =>
                        form.setData('comment', event.target.value)
                    }
                    rows={5}
                    maxLength={2000}
                    placeholder="How was the product, and how did the delivery go?"
                />
                <InputError message={form.errors.comment} />
            </div>

            <InputError message={form.errors.order_item} />
        </FormModal>
    );
}
