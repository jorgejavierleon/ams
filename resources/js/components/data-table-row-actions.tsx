import { Link } from '@inertiajs/react';
import { Eye, MoreVertical, Pencil, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/hooks/use-translations';

type RowAction = {
    label: string;
    onClick?: () => void;
    href?: string;
};

type DataTableRowActionsProps = {
    view?: RowAction;
    edit?: RowAction;
    delete?: RowAction;
    /** Extra DropdownMenuItems for page-specific, non-CRUD actions (e.g. revoke/activate), rendered after edit and before delete. */
    children?: ReactNode;
};

/**
 * A MoreVertical-triggered dropdown menu for view/edit/delete row actions,
 * matching the pattern already used in leaves/index.tsx and workdays/index.tsx.
 */
export function DataTableRowActions({
    view,
    edit,
    delete: deleteAction,
    children,
}: DataTableRowActionsProps) {
    const { t } = useTranslations();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t('ui.common.actions.more')}
                >
                    <MoreVertical className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-40">
                {view && <RowActionItem action={view} icon={Eye} />}
                {edit && <RowActionItem action={edit} icon={Pencil} />}
                {children}
                {deleteAction && (
                    <RowActionItem
                        action={deleteAction}
                        icon={Trash2}
                        variant="destructive"
                    />
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function RowActionItem({
    action,
    icon: Icon,
    variant,
}: {
    action: RowAction;
    icon: typeof Pencil;
    variant?: 'destructive';
}) {
    if (action.href) {
        return (
            <DropdownMenuItem asChild variant={variant}>
                <Link href={action.href}>
                    <Icon className="size-4" />
                    {action.label}
                </Link>
            </DropdownMenuItem>
        );
    }

    return (
        <DropdownMenuItem onSelect={action.onClick} variant={variant}>
            <Icon className="size-4" />
            {action.label}
        </DropdownMenuItem>
    );
}
