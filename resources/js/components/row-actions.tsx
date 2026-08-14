import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { MoreHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type RowActionBase = {
    label: string;
    icon?: LucideIcon;
    /** Styles the item as destructive and, by convention, pairs it with a ConfirmDialog. */
    destructive?: boolean;
    disabled?: boolean;
};

/**
 * An action either navigates or does something, never both.
 *
 * Navigation must stay a real `<a>`: an item that only calls `router.visit` in a
 * click handler cannot be opened in a new tab, middle-clicked, or copied, which
 * is exactly what someone working a list of shops wants to do with "View".
 */
export type RowAction =
    | (RowActionBase & { onSelect: () => void; href?: never })
    | (RowActionBase & { href: string; onSelect?: never });

export type RowActionGroup = {
    /** Optional heading rendered above the group. */
    label?: string;
    actions: RowAction[];
};

/**
 * The actions menu at the end of a table row.
 *
 * Actions are grouped rather than listed flat, which keeps a destructive item
 * from sitting one pixel below a routine one in a menu the user is clicking
 * through quickly.
 *
 * `rowLabel` is what makes this usable with a screen reader. A page of rows whose
 * every action button is named "Actions" gives no way to tell which row is about
 * to be acted on; naming the button "Actions for Jane Doe" does.
 *
 * Empty groups and fully-empty menus render nothing, so a caller can build the
 * list conditionally without guarding each branch.
 */
export function RowActions({
    groups,
    rowLabel,
    align = 'end',
}: {
    groups: RowActionGroup[];
    rowLabel: string;
    align?: 'start' | 'center' | 'end';
}) {
    const populated = groups.filter((group) => group.actions.length > 0);

    if (populated.length === 0) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon">
                    <MoreHorizontal className="size-4" />
                    <span className="sr-only">Actions for {rowLabel}</span>
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align={align}>
                {populated.map((group, index) => (
                    <DropdownMenuGroup key={group.label ?? index}>
                        {index > 0 ? <DropdownMenuSeparator /> : null}

                        {group.label ? (
                            <DropdownMenuLabel>{group.label}</DropdownMenuLabel>
                        ) : null}

                        {group.actions.map((action) => (
                            <DropdownMenuItem
                                key={action.label}
                                variant={
                                    action.destructive
                                        ? 'destructive'
                                        : 'default'
                                }
                                disabled={action.disabled}
                                asChild={action.href !== undefined}
                                onSelect={action.onSelect}
                            >
                                {action.href !== undefined ? (
                                    <Link href={action.href}>
                                        {action.icon ? (
                                            <action.icon className="size-4" />
                                        ) : null}
                                        {action.label}
                                    </Link>
                                ) : (
                                    <>
                                        {action.icon ? (
                                            <action.icon className="size-4" />
                                        ) : null}
                                        {action.label}
                                    </>
                                )}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuGroup>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
