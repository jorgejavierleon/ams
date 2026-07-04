import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
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
import { create, destroy, edit, index } from '@/routes/saas/organizations';

type Organization = {
    id: number;
    name: string;
    slug: string;
    plan: string;
    users_count: number;
    created_at: string | null;
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
    organizations: Paginated<Organization>;
    filters: { search: string | null };
};

export default function OrganizationsIndex({ organizations, filters }: Props) {
    const { t } = useTranslations();
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleteTarget, setDeleteTarget] = useState<Organization | null>(null);
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
                    only: ['organizations', 'filters'],
                },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [search]);

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
            <Head title={t('ui.organizations.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.organizations.title')}
                        description={t('ui.organizations.description')}
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            {t('ui.organizations.new')}
                        </Link>
                    </Button>
                </div>

                <Input
                    type="search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder={t('ui.organizations.search_placeholder')}
                    className="max-w-sm"
                />

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    {t('ui.organizations.columns.name')}
                                </TableHead>
                                <TableHead>
                                    {t('ui.organizations.columns.slug')}
                                </TableHead>
                                <TableHead>
                                    {t('ui.organizations.columns.plan')}
                                </TableHead>
                                <TableHead>
                                    {t('ui.organizations.columns.users')}
                                </TableHead>
                                <TableHead>
                                    {t('ui.organizations.columns.created')}
                                </TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {organizations.data.map((organization) => (
                                <TableRow key={organization.id}>
                                    <TableCell className="font-medium">
                                        {organization.name}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {organization.slug}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">
                                            {organization.plan}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {organization.users_count}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {organization.created_at ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Link
                                                href={edit(organization.id)}
                                                className="text-sm text-primary underline-offset-4 hover:underline"
                                            >
                                                {t(
                                                    'ui.organizations.actions.edit',
                                                )}
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setDeleteTarget(
                                                        organization,
                                                    )
                                                }
                                                className="text-sm text-destructive underline-offset-4 hover:underline"
                                            >
                                                {t(
                                                    'ui.organizations.actions.delete',
                                                )}
                                            </button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {organizations.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        {t('ui.organizations.empty')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-sm text-muted-foreground">
                        {organizations.total > 0
                            ? t('ui.organizations.pagination.showing', {
                                  from: organizations.from ?? 0,
                                  to: organizations.to ?? 0,
                                  total: organizations.total,
                              })
                            : t('ui.organizations.pagination.none')}
                    </p>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!organizations.prev_page_url}
                            asChild={Boolean(organizations.prev_page_url)}
                        >
                            {organizations.prev_page_url ? (
                                <Link
                                    href={organizations.prev_page_url}
                                    preserveScroll
                                    preserveState
                                >
                                    {t('ui.organizations.pagination.previous')}
                                </Link>
                            ) : (
                                <span>
                                    {t('ui.organizations.pagination.previous')}
                                </span>
                            )}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!organizations.next_page_url}
                            asChild={Boolean(organizations.next_page_url)}
                        >
                            {organizations.next_page_url ? (
                                <Link
                                    href={organizations.next_page_url}
                                    preserveScroll
                                    preserveState
                                >
                                    {t('ui.organizations.pagination.next')}
                                </Link>
                            ) : (
                                <span>
                                    {t('ui.organizations.pagination.next')}
                                </span>
                            )}
                        </Button>
                    </div>
                </div>
            </div>

            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('ui.organizations.delete_dialog.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('ui.organizations.delete_dialog.description', {
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
                            {t('ui.organizations.delete_dialog.confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
