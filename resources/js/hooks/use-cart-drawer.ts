import { useSyncExternalStore } from 'react';

/**
 * Open/closed state for the storefront cart drawer.
 *
 * Module scope rather than React state on purpose: adding to the cart is a server
 * round trip, and the page component that triggered it is re-rendered by the time
 * the drawer should appear. State held here survives that, so "add to bag" can open
 * the drawer from anywhere without the layout having to own the trigger.
 */
let isOpen = false;

const listeners = new Set<() => void>();

function emit(): void {
    for (const listener of listeners) {
        listener();
    }
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

export function openCartDrawer(): void {
    isOpen = true;
    emit();
}

export function closeCartDrawer(): void {
    isOpen = false;
    emit();
}

export function useCartDrawerOpen(): boolean {
    return useSyncExternalStore(
        subscribe,
        () => isOpen,
        () => false,
    );
}
