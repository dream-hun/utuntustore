import { Head } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { useState } from 'react';
import { AccountNav } from '@/components/account/account-nav';
import { RatingStars } from '@/components/account/rating-stars';
import {
    ReviewFormModal,
    type ReviewTarget,
} from '@/components/account/review-form-modal';
import { EmptyState } from '@/components/empty-state';
import { PaginationNav } from '@/components/pagination-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import type { Paginated, ReviewStatus } from '@/types/marketplace';

interface MyReview {
    id: string;
    rating: number;
    title: string | null;
    comment: string | null;
    status: ReviewStatus;
    created_at: string;
    product_name: string;
    shop_name: string;
}

interface AwaitingItem {
    id: string;
    product_name: string;
    variant_name: string | null;
    shop_name: string;
    delivered_at: string | null;
}

export default function AccountReviews({
    reviews,
    awaitingReview,
}: {
    reviews: Paginated<MyReview>;
    awaitingReview: Paginated<AwaitingItem>;
}) {
    const [target, setTarget] = useState<ReviewTarget | null>(null);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Account', href: '/account' },
                { title: 'Reviews', href: '/account/reviews' },
            ]}
        >
            <Head title="My reviews" />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold tracking-tight">
                    My reviews
                </h1>

                <AccountNav />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Waiting for a review
                        </CardTitle>
                        <p className="text-xs text-muted-foreground">
                            You can review anything a shop has marked delivered.
                        </p>
                    </CardHeader>
                    <CardContent>
                        {awaitingReview.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nothing waiting. Reviews open up once a delivery
                                is complete.
                            </p>
                        ) : (
                            <div className="divide-y divide-border">
                                {awaitingReview.data.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0"
                                    >
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium">
                                                {item.product_name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.shop_name} · delivered{' '}
                                                {formatDate(item.delivered_at)}
                                            </p>
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                setTarget({
                                                    orderItemId: item.id,
                                                    productName:
                                                        item.product_name,
                                                    rating: 5,
                                                    title: null,
                                                    comment: null,
                                                })
                                            }
                                        >
                                            <Star className="size-4" />
                                            Write a review
                                        </Button>
                                    </div>
                                ))}

                                <PaginationNav paginator={awaitingReview} />
                            </div>
                        )}
                    </CardContent>
                </Card>

                {reviews.data.length === 0 ? (
                    <EmptyState
                        icon={Star}
                        title="You have not reviewed anything yet"
                        description="Once a shop delivers your order, you can say how it went."
                    />
                ) : (
                    <div className="space-y-4">
                        {reviews.data.map((review) => (
                            <Card key={review.id}>
                                <CardContent className="space-y-2">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium">
                                                {review.product_name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {review.shop_name} ·{' '}
                                                {formatDate(review.created_at)}
                                            </p>
                                        </div>

                                        <div className="flex items-center gap-2">
                                            {review.status === 'pending' ? (
                                                <Badge variant="secondary">
                                                    Awaiting approval
                                                </Badge>
                                            ) : review.status === 'rejected' ? (
                                                <Badge variant="destructive">
                                                    Rejected
                                                </Badge>
                                            ) : (
                                                <Badge className="border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
                                                    Published
                                                </Badge>
                                            )}

                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    setTarget({
                                                        reviewId: review.id,
                                                        productName:
                                                            review.product_name,
                                                        rating: review.rating,
                                                        title: review.title,
                                                        comment: review.comment,
                                                    })
                                                }
                                            >
                                                Edit
                                            </Button>
                                        </div>
                                    </div>

                                    <RatingStars rating={review.rating} />

                                    {review.title ? (
                                        <p className="text-sm font-medium">
                                            {review.title}
                                        </p>
                                    ) : null}
                                    {review.comment ? (
                                        <p className="text-sm text-muted-foreground">
                                            {review.comment}
                                        </p>
                                    ) : null}
                                </CardContent>
                            </Card>
                        ))}

                        <PaginationNav paginator={reviews} />
                    </div>
                )}
            </div>

            {target ? (
                <ReviewFormModal
                    open
                    onOpenChange={(open) => !open && setTarget(null)}
                    target={target}
                />
            ) : null}
        </AppLayout>
    );
}
