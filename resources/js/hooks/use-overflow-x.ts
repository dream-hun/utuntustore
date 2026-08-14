import { useEffect, useRef, useState } from 'react';

/**
 * Track whether an element is actually scrollable on the horizontal axis.
 *
 * A container that scrolls but cannot receive focus is unreachable for anyone
 * driving the page from the keyboard (WCAG 2.1.1). The fix is a tab stop — but
 * an unconditional one adds a useless stop to every table that already fits, so
 * the tab stop has to follow the real measurement rather than the assumption.
 *
 * Both the container and its content are observed: the container covers viewport
 * resizes, the content covers rows arriving after a deferred prop resolves.
 */
export function useOverflowX<T extends HTMLElement>() {
    const ref = useRef<T>(null);
    const [isOverflowing, setIsOverflowing] = useState(false);

    useEffect(() => {
        const element = ref.current;

        if (!element) {
            return;
        }

        const measure = () => {
            // Sub-pixel layout rounding leaves scrollWidth a fraction above
            // clientWidth on tables that fit exactly; 1px of tolerance keeps
            // those from claiming a phantom tab stop.
            setIsOverflowing(element.scrollWidth - element.clientWidth > 1);
        };

        measure();

        const observer = new ResizeObserver(measure);
        observer.observe(element);

        if (element.firstElementChild) {
            observer.observe(element.firstElementChild);
        }

        return () => observer.disconnect();
    }, []);

    return { ref, isOverflowing };
}
