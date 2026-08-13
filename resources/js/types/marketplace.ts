/**
 * Domain types shared by the storefront, customer, vendor and admin areas.
 *
 * Every money value is a whole number of Rwandan Francs. RWF has no minor unit in
 * daily use, so there are no cents anywhere in this system — never divide by 100.
 */

export type UserRole = 'customer' | 'vendor' | 'admin';

export type VendorStatus = 'pending' | 'approved' | 'rejected' | 'suspended';

/** The vendor's denormalized selling eligibility. */
export type SubscriptionStatus = 'none' | 'active' | 'grace' | 'expired';

/** The status of one row in the platform's revenue ledger. */
export type VendorSubscriptionStatus =
    'pending' | 'active' | 'expired' | 'cancelled';

export type SubscriptionPaymentMethod =
    'mobile_money' | 'bank_transfer' | 'cash';

export type ProductStatus = 'draft' | 'published' | 'archived';

export type OrderStatus =
    | 'pending'
    | 'confirmed'
    | 'processing'
    | 'shipped'
    | 'delivered'
    | 'cancelled';

export type ReviewStatus = 'pending' | 'approved' | 'rejected';

export type CouponType = 'percentage' | 'fixed';

export interface Province {
    id: string;
    name: string;
    code: string;
}

export interface District {
    id: string;
    name: string;
    code: string;
    province?: Province;
}

export interface Sector {
    id: string;
    name: string;
    code: string;
    district?: District;
}

export interface MediaImage {
    id: string;
    url: string;
    thumb_url: string;
    web_url: string;
    alt: string | null;
}

export interface VendorSummary {
    id: string;
    shop_name: string;
    slug: string;
    logo_url: string | null;
    /** Whether this shop is currently accepting orders. */
    can_sell: boolean;
}

export interface Vendor extends VendorSummary {
    description: string | null;
    banner_url: string | null;
    phone: string;
    email: string | null;
    status: VendorStatus;
    subscription_status: SubscriptionStatus;
    subscription_ends_at: string | null;
    delivery_notes: string | null;
    is_platform_owned: boolean;
    approved_at: string | null;
    created_at: string;
}

export interface Category {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    parent?: Category | null;
    children?: Category[];
    products_count?: number;
}

export interface ProductVariant {
    id: string;
    name: string;
    sku: string | null;
    /** Whole RWF. */
    price: number;
    stock_quantity: number;
    is_active: boolean;
}

export interface Product {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    short_description: string | null;
    sku: string | null;
    /** Whole RWF. */
    price: number;
    /** Whole RWF. The struck-through "was" price, when there is one. */
    compare_at_price: number | null;
    currency: string;
    stock_quantity: number;
    low_stock_threshold: number;
    status: ProductStatus;
    published_at: string | null;
    created_at: string;
    vendor?: VendorSummary;
    category?: Category;
    images?: MediaImage[];
    primary_image_url?: string | null;
    variants?: ProductVariant[];
    average_rating?: number | null;
    reviews_count?: number;
}

export interface Address {
    id: string;
    type: 'shipping' | 'billing';
    first_name: string;
    last_name: string;
    phone: string;
    country: string;
    district: District;
    sector: Sector;
    cell: string | null;
    village: string | null;
    address_line: string | null;
    /** Usually what actually gets a delivery to the door. */
    landmark: string | null;
    is_default: boolean;
}

export interface DeliveryArea {
    id: string;
    district: District;
    /** Null means the whole district. */
    sector: Sector | null;
    /** Whole RWF; may be 0 for free delivery. */
    delivery_fee: number;
    estimated_days_min: number;
    estimated_days_max: number;
    is_active: boolean;
}

export interface CartItem {
    id: string;
    quantity: number;
    /** Whole RWF. */
    unit_price: number;
    subtotal: number;
    product: Product;
    variant: ProductVariant | null;
}

export interface CartVendorGroup {
    vendor: VendorSummary;
    items: CartItem[];
    subtotal: number;
}

export interface Cart {
    id: string;
    currency: string;
    groups: CartVendorGroup[];
    subtotal: number;
    item_count: number;
}

/** Every reason a cart can fail to become an order. */
export type CheckoutProblem =
    | 'vendor_cannot_sell'
    | 'vendor_does_not_deliver'
    | 'product_unavailable'
    | 'insufficient_stock'
    | 'price_changed'
    | 'empty_cart'
    | 'coupon_unavailable';

export interface CheckoutLine {
    product: Product;
    variant: ProductVariant | null;
    quantity: number;
    unit_price: number;
    subtotal: number;
    problems: CheckoutProblem[];
}

export interface CheckoutVendorQuote {
    vendor: VendorSummary;
    lines: CheckoutLine[];
    subtotal: number;
    /** This vendor's own delivery charge, resolved from their coverage. */
    shipping_fee: number;
    discount: number;
    /** Exactly what the customer hands this vendor at the door. */
    total: number;
    estimated_days_min: number | null;
    estimated_days_max: number | null;
    problems: CheckoutProblem[];
}

export interface CheckoutQuote {
    vendor_quotes: CheckoutVendorQuote[];
    subtotal: number;
    discount: number;
    /** Always the sum of the vendor quotes' shipping fees. */
    shipping_fee: number;
    tax: number;
    total: number;
    currency: string;
    is_placeable: boolean;
    problems: CheckoutProblem[];
}

export interface OrderItem {
    id: string;
    product_name: string;
    variant_name: string | null;
    sku: string | null;
    unit_price: number;
    quantity: number;
    subtotal: number;
    product_slug: string | null;
    product_image_url: string | null;
    can_review: boolean;
}

export interface VendorOrder {
    id: string;
    order_number: string;
    status: OrderStatus;
    subtotal: number;
    discount: number;
    shipping_fee: number;
    total: number;
    /** Vendor-reported, not a payment confirmation. */
    delivered_at: string | null;
    created_at: string;
    vendor?: VendorSummary;
    items?: OrderItem[];
    /** Only ever present on the vendor's own view of their vendor order. */
    shipping_address?: Address;
    customer_name?: string;
    customer_phone?: string;
}

export interface Order {
    id: string;
    order_number: string;
    status: OrderStatus;
    currency: string;
    subtotal: number;
    discount: number;
    shipping_fee: number;
    tax: number;
    total: number;
    payment_method: 'cash_on_delivery';
    placed_at: string | null;
    created_at: string;
    shipping_address?: Address;
    vendor_orders?: VendorOrder[];
}

export interface VendorSubscription {
    id: string;
    /** Whole RWF, frozen at the fee in force when it was recorded. */
    amount: number;
    currency: string;
    status: VendorSubscriptionStatus;
    starts_at: string;
    ends_at: string;
    payment_method: SubscriptionPaymentMethod;
    reference: string | null;
    paid_at: string | null;
    recorded_by: string | null;
    vendor?: VendorSummary;
}

export interface Review {
    id: string;
    rating: number;
    title: string | null;
    comment: string | null;
    status: ReviewStatus;
    created_at: string;
    author_name?: string;
    product?: Product;
}

export interface Coupon {
    id: string;
    code: string;
    type: CouponType;
    value: number;
    minimum_order_amount: number;
    maximum_discount: number | null;
    usage_limit: number | null;
    used_count: number;
    starts_at: string | null;
    expires_at: string | null;
    is_active: boolean;
    /** Null means a platform-wide coupon. */
    vendor: VendorSummary | null;
}

/** Laravel's length-aware paginator, as Inertia serializes it. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    from: number | null;
    to: number | null;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
    next_page_url: string | null;
    prev_page_url: string | null;
}

/*
|--------------------------------------------------------------------------
| Storefront view models
|--------------------------------------------------------------------------
|
| The public storefront serializes deliberately narrow shapes rather than whole
| models: a catalog page renders 24 cards on a phone over a slow connection, so
| every field it does not draw is a field it should not send.
|
*/

/** The minimum needed to attribute a product to a shop. */
export interface StorefrontVendorRef {
    id: string;
    shop_name: string;
    slug: string;
}

/** A shop as shown on a card or a shop header. */
export interface StorefrontVendorCard extends StorefrontVendorRef {
    logo_url: string | null;
    /** Whether this shop is currently accepting orders. */
    can_sell: boolean;
}

export interface StorefrontCategoryLink {
    id: string;
    name: string;
    slug: string;
}

export interface StorefrontProductCard {
    id: string;
    name: string;
    slug: string;
    /** Whole RWF. */
    price: number;
    /** Whole RWF. The struck-through "was" price, when there is one. */
    compare_at_price: number | null;
    currency: string;
    primary_image_url: string | null;
    in_stock: boolean;
    vendor: StorefrontVendorRef;
}

export interface StorefrontProductImage {
    id: string;
    thumb_url: string;
    web_url: string;
    alt: string | null;
}

export interface StorefrontProductVariant {
    id: string;
    name: string;
    sku: string | null;
    price: number;
    stock_quantity: number;
}

export interface StorefrontProductDetail extends StorefrontProductCard {
    description: string | null;
    short_description: string | null;
    sku: string | null;
    stock_quantity: number;
    is_low_stock: boolean;
    category: StorefrontCategoryLink;
    images: StorefrontProductImage[];
    variants: StorefrontProductVariant[];
}

export interface StorefrontReview {
    id: string;
    rating: number;
    title: string | null;
    comment: string | null;
    author_name: string;
    created_at: string;
}

export interface StorefrontDeliveryArea {
    id: string;
    district: string;
    /** Null means the whole district. */
    sector: string | null;
    delivery_fee: number;
    estimated_days_min: number;
    estimated_days_max: number;
}

export interface StorefrontCartLine {
    id: string;
    quantity: number;
    unit_price: number;
    subtotal: number;
    available_stock: number;
    max_quantity: number;
    product: {
        id: string;
        name: string;
        slug: string;
        image_url: string | null;
    };
    variant: { id: string; name: string } | null;
}

/** One line of the flat basket the cart drawer renders. */
export interface StorefrontCartPreviewLine {
    id: string;
    name: string;
    slug: string;
    image_url: string | null;
    vendor_name: string;
    variant_name: string | null;
    quantity: number;
    unit_price: number;
    subtotal: number;
    max_quantity: number;
}

/**
 * The cart drawer's basket. Shared as an optional prop, so it is only present once
 * the drawer has asked for it — treat `undefined` as "not fetched yet", not "empty".
 */
export interface StorefrontCartPreview {
    items: StorefrontCartPreviewLine[];
    subtotal: number;
    count: number;
    currency: string;
}

/**
 * One shop's slice of the cart. The customer pays each of these separately, in
 * cash, when that shop delivers.
 */
export interface StorefrontCartGroup {
    vendor: StorefrontVendorCard;
    items: StorefrontCartLine[];
    subtotal: number;
}

/** A checkout problem, already translated server side by CheckoutProblem::message(). */
export interface StorefrontCheckoutProblem {
    code: CheckoutProblem;
    message: string;
    /** False only for a price change, which warns rather than blocks. */
    blocking: boolean;
}

export interface StorefrontQuoteLine {
    id: string;
    name: string;
    slug: string;
    variant_name: string | null;
    image_url: string | null;
    quantity: number;
    unit_price: number;
    subtotal: number;
    available_stock: number;
    problems: StorefrontCheckoutProblem[];
}

export interface StorefrontVendorQuote {
    vendor: StorefrontVendorCard;
    lines: StorefrontQuoteLine[];
    subtotal: number;
    discount: number;
    /** This shop's own delivery charge, resolved from their coverage. */
    shipping_fee: number;
    /** Exactly what the customer hands this shop at the door. */
    total: number;
    delivers: boolean;
    estimated_days_min: number | null;
    estimated_days_max: number | null;
    problems: StorefrontCheckoutProblem[];
}

export interface StorefrontCheckoutQuote {
    vendor_quotes: StorefrontVendorQuote[];
    subtotal: number;
    discount: number;
    /** Always the sum of the vendor quotes' shipping fees. */
    shipping_fee: number;
    tax: number;
    total: number;
    currency: string;
    item_count: number;
    vendor_count: number;
    is_placeable: boolean;
    problems: StorefrontCheckoutProblem[];
}

export interface StorefrontAddress {
    id: string;
    first_name: string;
    last_name: string;
    phone: string;
    district: { id: string; name: string };
    sector: { id: string; name: string };
    cell: string | null;
    village: string | null;
    address_line: string | null;
    landmark: string | null;
    is_default: boolean;
}

export interface StorefrontCouponState {
    code: string;
    applied: boolean;
    discount: number;
    message: string;
}

export interface StorefrontOrderItem {
    id: string;
    product_name: string;
    variant_name: string | null;
    unit_price: number;
    quantity: number;
    subtotal: number;
}

export interface StorefrontVendorOrder {
    id: string;
    order_number: string;
    status: OrderStatus;
    subtotal: number;
    discount: number;
    shipping_fee: number;
    /** The cash this shop collects at the door. */
    total: number;
    vendor: StorefrontVendorCard & { phone: string };
    items: StorefrontOrderItem[];
}

export interface StorefrontOrder {
    id: string;
    order_number: string;
    status: OrderStatus;
    currency: string;
    subtotal: number;
    discount: number;
    shipping_fee: number;
    total: number;
    payment_method: 'cash_on_delivery';
    placed_at: string | null;
    shipping_address: StorefrontAddress;
    vendor_orders: StorefrontVendorOrder[];
}
