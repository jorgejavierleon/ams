import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import PositionFormDialog from '@/components/position-form-dialog';
import type { PositionFormTarget } from '@/components/position-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslations } from '@/hooks/use-translations';
import { destroy, index, show } from '@/routes/positions';

type Position = {
    id: number;
    name: string;
    active_users_count: number;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    positions: Paginated<Position>;
    filters: { search: string | null };
};

export default function PositionsIndex({ positions, filters }: Props) {
    const { t } = useTranslations();
    const [search, setSearch] = useState(filters.search ?? '');
    const [formOpen, setFormOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<PositionFormTarget>(null);
    const [deleteTarget, setDeleteTarget] = useState<Position | null>(null);
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                index().url,
                { search: search || undefined },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['positions', 'filters'],
                },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [search]);

    function openCreate() {
        setEditTarget(null);
        setFormOpen(true);
    }

    function openEdit(position: Position) {
        setEditTarget({ id: position.id, name: position.name });
        setFormOpen(true);
    }

    function confirmDelete() {
        if (!deleteTarget) {
            return;
        }

        router.delete(destroy(deleteTarget.id).url, {
            preserveScroll: true,
            onFinish: () => setDeleteTarget(null),
        });
    }

    return (
        <>
            <Head title={t('ui.positions.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.positions.title')}
                        description={t('ui.positions.description')}
                    />
                    <Button onClick={openCreate}>
                        <Plus className="size-4" />
                        {t('ui.positions.new')}
                    </Button>
                </div>

                <Input
                    type="search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder={t('ui.positions.search_placeholder')}
                    className="max-w-sm"
                />

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    {t('ui.positions.columns.name')}
                                </TableHead>
                                <TableHead>
                                    {t('ui.positions.columns.employees')}
                                </TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {positions.data.map((position) => (
                                <TableRow key={position.id}>
                                    <TableCell className="font-medium">
                                        <Link
                                            href={show(position.id)}
                                            className="text-primary underline-offset-4 hover:underline"
                                        >
                                            {position.name}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">
                                            {position.active_users_count}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    openEdit(position)
                                                }
                                                className="text-sm text-primary underline-offset-4 hover:underline"
                                            >
                                                {t('ui.positions.actions.edit')}
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setDeleteTarget(position)
                                                }
                                                className="text-sm text-destructive underline-offset-4 hover:underline"
                                            >
                                                {t(
                                                    'ui.positions.actions.delete',
                                                )}
                                            </button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {positions.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={3}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        {t('ui.positions.empty')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-sm text-muted-foreground">
                        {positions.total > 0
                            ? t('ui.positions.pagination.showing', {
                                  from: positions.from ?? 0,
                                  to: positions.to ?? 0,
                                  total: positions.total,
                              })
                            : t('ui.positions.pagination.none')}
                    </p>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!positions.prev_page_url}
                            asChild={Boolean(positions.prev_page_url)}
                        >
                            {positions.prev_page_url ? (
                                <Link
                                    href={positions.prev_page_url}
                                    preserveScroll
                                    preserveState
                                >
                                    {t('ui.positions.pagination.previous')}
                                </Link>
                            ) : (
                                <span>
                                    {t('ui.positions.pagination.previous')}
                                </span>
                            )}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!positions.next_page_url}
                            asChild={Boolean(positions.next_page_url)}
                        >
                            {positions.next_page_url ? (
                                <Link
                                    href={positions.next_page_url}
                                    preserveScroll
                                    preserveState
                                >
                                    {t('ui.positions.pagination.next')}
                                </Link>
                            ) : (
                                <span>{t('ui.positions.pagination.next')}</span>
                            )}
                        </Button>
                    </div>
                </div>
            </div>

            <PositionFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                position={editTarget}
            />

            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('ui.positions.delete_dialog.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('ui.positions.delete_dialog.description', {
                                name: deleteTarget?.name ?? '',
                            })}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteTarget(null)}
                        >
                            {t('ui.common.cancel')}
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            {t('ui.positions.delete_dialog.confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
