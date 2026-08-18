/**
 * View-model shapes the admin area's controllers serialize.
 *
 * These are page payloads, not domain models — the domain unions they build on
 * (VendorStatus, OrderStatus, …) come from `@/types/marketplace` and stay the single
 * source of truth. Money is always a whole number of Rwandan Francs.
 */

import type {
    OrderStatus,
    ProductStatus,
    SubscriptionPaymentMethod,
    SubscriptionStatus,
    VendorStatus,
    VendorSubscriptionStatus,
} from '@/types/marketplace';

/** Platform revenue. Subscriptions only — order value is never counted here. */
export interface RevenueTotals {
    total: number;
    count: number;
    currency: string;
}

export interface PlatformMetrics {
    vendors: {
        total: number;
        pending: number;
        approved: number;
        rejected: number;
        suspended: number;
    };
    selling: {
        active: number;
        grace: number;
        expired: number;
        none: number;
    };
    active_subscriptions: number;
    customers: number;
}

export interface ExpiringVendor {
    id: string;
    shop_name: string;
    phone: string;
    subscription_status: SubscriptionStatus;
    subscription_ends_at: string | null;
}

export interface RecentSignups {
    vendors: {
        id: string;
        shop_name: string;
        status: VendorStatus;
        created_at: string | null;
    }[];
    customers: {
        id: string;
        name: string;
        email: string;
        created_at: string | null;
    }[];
}

export interface AdminVendorRow {
    id: string;
    shop_name: string;
    slug: string;
    phone: string;
    email: string | null;
    status: VendorStatus;
    subscription_status: SubscriptionStatus;
    subscription_ends_at: string | null;
    approved_at: string | null;
    can_sell: boolean;
    products_count: number;
    orders_count: number;
    owner_name: string;
    created_at: string | null;
}

export interface AdminVendorDetail extends AdminVendorRow {
    description: string | null;
    delivery_notes: string | null;
    is_platform_owned: boolean;
    delivery_areas_count: number;
    owner: { name: string; email: string; phone: string | null };
}

export interface AdminSubscriptionRow {
    id: string;
    amount: number;
    currency: string;
    status: VendorSubscriptionStatus;
    starts_at: string;
    ends_at: string;
    payment_method: SubscriptionPaymentMethod;
    reference: string | null;
    paid_at: string | null;
    recorded_by: string | null;
    vendor: { id: string; shop_name: string; slug: string };
}

/** A shop the record-payment modal can record against. */
export interface PayableVendor {
    id: string;
    shop_name: string;
    subscription_status: SubscriptionStatus;
    subscription_ends_at: string | null;
}

export interface AdminCategoryRow {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
    products_count: number;
    children_count: number;
    parent: { id: string; name: string } | null;
}

export interface CategoryParentOption {
    id: string;
    name: string;
}

/** A catalog product as the platform-wide list shows it: the vendor's row plus its shop. */
export interface AdminProductRow {
    id: string;
    name: string;
    slug: string;
    sku: string | null;
    description: string | null;
    short_description: string | null;
    price: number;
    compare_at_price: number | null;
    currency: string;
    stock_quantity: number;
    low_stock_threshold: number;
    weight: number | null;
    status: ProductStatus;
    published_at: string | null;
    is_low_stock: boolean;
    variants_count: number;
    category: { id: string; name: string };
    images: { id: string; url: string; thumb_url: string }[];
    vendor: { id: string; shop_name: string };
}

/**
 * A shop a product can be added to. Only approved shops are listed; `can_sell` says
 * whether it may also be published straight away.
 */
export interface AdminVendorOption {
    id: string;
    shop_name: string;
    can_sell: boolean;
}

export interface AdminOrderRow {
    id: string;
    order_number: string;
    status: OrderStatus;
    total: number;
    currency: string;
    placed_at: string | null;
    customer_name: string;
    vendor_orders: {
        id: string;
        status: OrderStatus;
        total: number;
        shop_name: string;
    }[];
}

export interface AdminOrderDetail {
    id: string;
    order_number: string;
    status: OrderStatus;
    subtotal: number;
    discount: number;
    shipping_fee: number;
    tax: number;
    total: number;
    currency: string;
    payment_method: string;
    placed_at: string | null;
    customer: { name: string; email: string; phone: string | null };
    shipping_address: {
        recipient: string;
        phone: string;
        district: string;
        sector: string;
        cell: string | null;
        village: string | null;
        address_line: string | null;
        landmark: string | null;
    };
    vendor_orders: {
        id: string;
        order_number: string;
        status: OrderStatus;
        subtotal: number;
        discount: number;
        shipping_fee: number;
        total: number;
        delivered_at: string | null;
        vendor: { id: string; shop_name: string; phone: string };
        items: {
            id: string;
            product_name: string;
            variant_name: string | null;
            sku: string | null;
            unit_price: number;
            quantity: number;
            subtotal: number;
        }[];
    }[];
}

export interface AdminCustomerRow {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    status: 'active' | 'suspended';
    orders_count: number;
    /** Gross merchandise value paid directly to vendors, never platform income. */
    orders_total: number;
    email_verified_at: string | null;
    created_at: string | null;
}
