---
paths:
  - 'resources/js/pages/**'
---

# Pages

## Never wrap a page in AppLayout — app.tsx already applies it
`createInertiaApp({ layout })` in `resources/js/app.tsx` resolves a layout for every page: `null` for `welcome` and `storefront/*` (those self-wrap in StorefrontLayout), `AuthLayout` for `auth/*`, `[AppLayout, SettingsLayout]` for `settings/*`, and `AppLayout` for everything else.

So an admin/vendor/account page that also returns `<AppLayout>...</AppLayout>` renders **two** nested AppLayouts — two SidebarProviders, two sidebars, two header bars. This was live on all 24 admin/vendor/account pages and is invisible to the PHP suite, which asserts Inertia props and never renders React.

Pass layout props instead, the way `pages/dashboard.tsx` and `pages/settings/*` do:

    export default function AdminDashboard(props) { return (<>...</>); }
    AdminDashboard.layout = { breadcrumbs: [{ title: 'Admin', href: '/admin' }] };

When a breadcrumb needs a prop, use the resolver form — an arrow function of props returning the props object (Inertia's `isLayoutResolver` requires arity <= 1 and no prototype, so it must be an arrow function):

    AdminVendorShow.layout = ({ vendor }: { vendor: AdminVendorDetail }) => ({
        breadcrumbs: [{ title: vendor.shop_name, href: `/admin/vendors/${vendor.id}` }],
    });

To verify layout nesting without logging in, POST a page object to the SSR server (`node bootstrap/ssr/app.js`, port 13714) and count `data-slot="sidebar-wrapper"` in the returned body — it must be 1.
