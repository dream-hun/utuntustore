import { PaginationNav } from '@/components/pagination-nav';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useOverflowX } from '@/hooks/use-overflow-x';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types/marketplace';

/**
 * One column of a `DataTable`.
 *
 * `cell` receives the whole row rather than a plucked value so a column can draw
 * from several fields — the customer column renders name, email and phone as one
 * block, which a key-path API could not express.
 */
export type DataTableColumn<T> = {
    /** Stable identity for the column; also the React key. */
    id: string;
    /** Header text. Always required — see `headerHidden` for icon-only columns. */
    header: string;
    cell: (row: T) => React.ReactNode;
    align?: 'start' | 'end';
    /**
     * Keep the header text for assistive tech but hide it visually. For action
     * columns, where a visible "Actions" heading is noise but screen reader users
     * still need the column named.
     */
    headerHidden?: boolean;
    /**
     * Drop the column below this breakpoint. Only use it for values repeated
     * elsewhere in the row — a column hidden on mobile is data a phone user never
     * gets, so it must not be the only place that value appears.
     */
    hideBelow?: 'sm' | 'md' | 'lg';
    headClassName?: string;
    cellClassName?: string;
};

/**
 * Written out in full so Tailwind's scanner sees each class literally; a template
 * string like `hidden ${bp}:table-cell` produces no CSS at all.
 */
const hideBelowClasses: Record<
    NonNullable<DataTableColumn<unknown>['hideBelow']>,
    string
> = {
    sm: 'hidden sm:table-cell',
    md: 'hidden md:table-cell',
    lg: 'hidden lg:table-cell',
};

/**
 * Fixed cycle rather than random widths: this app builds an SSR bundle, and a
 * random width renders differently on server and client, which React reports as
 * a hydration mismatch.
 */
const skeletonWidths = ['w-32', 'w-20', 'w-24', 'w-16', 'w-28'];

/**
 * The standard table for every paginated list in this application.
 *
 * It exists because thirteen index pages had each hand-rolled the same four
 * things — a scroll wrapper, a header row, an empty branch and a paginator — and
 * had already drifted apart on all four. The accessibility details in particular
 * were being lost one page at a time.
 *
 * Three states, chosen in this order:
 *
 * 1. `rows === undefined` — the deferred prop has not resolved. Skeleton rows
 *    keep the header and column widths in place so nothing shifts when data lands.
 * 2. `rows` is empty — the caller's `empty` node replaces the table entirely.
 * 3. otherwise — the table, plus a paginator when one is passed.
 */
export function DataTable<T>({
    columns,
    rows,
    getRowKey,
    caption,
    empty,
    loading,
    skeletonRows = 5,
    paginator,
    bordered = true,
    rowClassName,
    className,
}: {
    columns: DataTableColumn<T>[];
    /** `undefined` means "still loading" — the shape a deferred Inertia prop has. */
    rows: T[] | undefined;
    getRowKey: (row: T) => string | number;
    /**
     * What this table lists, e.g. "Customers". Announced to screen readers and
     * used to label the scroll region, so it should read as a name and not a
     * sentence.
     */
    caption: string;
    empty: React.ReactNode;
    /** Overrides the inferred loading state, for refetches that keep rows on screen. */
    loading?: boolean;
    skeletonRows?: number;
    /**
     * Only the paginator's metadata is read, so its element type need not match
     * the rows — the inventory table paginates products but renders one row per
     * product *and* per variant.
     */
    paginator?: Paginated<unknown>;
    /** Drop the border and radius when the table already sits inside a Card. */
    bordered?: boolean;
    /**
     * Per-row styling, for encoding row state in form as well as in a cell —
     * a low-stock tint, a nested sub-row. Colour alone never carries the meaning;
     * the cells still have to say it.
     */
    rowClassName?: (row: T) => string | undefined;
    className?: string;
}) {
    const { ref, isOverflowing } = useOverflowX<HTMLDivElement>();
    const isLoading = loading ?? rows === undefined;

    if (!isLoading && (rows === undefined || rows.length === 0)) {
        return <>{empty}</>;
    }

    return (
        <div className={cn('flex flex-col', className)}>
            <Table
                aria-busy={isLoading || undefined}
                containerProps={{
                    ref,
                    // Only a container that genuinely scrolls earns a tab stop.
                    tabIndex: isOverflowing ? 0 : undefined,
                    role: isOverflowing ? 'region' : undefined,
                    'aria-label': isOverflowing ? caption : undefined,
                    className: cn(
                        'focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                        bordered && 'rounded-lg border',
                    ),
                }}
            >
                <caption className="sr-only">{caption}</caption>

                <TableHeader>
                    <TableRow>
                        {columns.map((column) => (
                            <TableHead
                                key={column.id}
                                scope="col"
                                className={cn(
                                    column.align === 'end' && 'text-right',
                                    column.hideBelow &&
                                        hideBelowClasses[column.hideBelow],
                                    column.headClassName,
                                )}
                            >
                                <span
                                    className={cn(
                                        column.headerHidden && 'sr-only',
                                    )}
                                >
                                    {column.header}
                                </span>
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>

                <TableBody>
                    {isLoading
                        ? Array.from(
                              { length: skeletonRows },
                              (_, rowIndex) => (
                                  <TableRow key={`skeleton-${rowIndex}`}>
                                      {columns.map((column, columnIndex) => (
                                          <TableCell
                                              key={column.id}
                                              className={cn(
                                                  column.hideBelow &&
                                                      hideBelowClasses[
                                                          column.hideBelow
                                                      ],
                                                  column.cellClassName,
                                              )}
                                          >
                                              <Skeleton
                                                  className={cn(
                                                      'h-4',
                                                      skeletonWidths[
                                                          (rowIndex +
                                                              columnIndex) %
                                                              skeletonWidths.length
                                                      ],
                                                      column.align === 'end' &&
                                                          'ml-auto',
                                                  )}
                                              />
                                          </TableCell>
                                      ))}
                                  </TableRow>
                              ),
                          )
                        : rows?.map((row) => (
                              <TableRow
                                  key={getRowKey(row)}
                                  className={rowClassName?.(row)}
                              >
                                  {columns.map((column) => (
                                      <TableCell
                                          key={column.id}
                                          className={cn(
                                              column.align === 'end' &&
                                                  'text-right',
                                              column.hideBelow &&
                                                  hideBelowClasses[
                                                      column.hideBelow
                                                  ],
                                              column.cellClassName,
                                          )}
                                      >
                                          {column.cell(row)}
                                      </TableCell>
                                  ))}
                              </TableRow>
                          ))}
                </TableBody>
            </Table>

            {paginator ? <PaginationNav paginator={paginator} /> : null}
        </div>
    );
}
