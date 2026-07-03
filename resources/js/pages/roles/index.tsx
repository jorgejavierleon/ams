import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { index, show } from '@/routes/roles';

type Role = {
    id: number;
    name: string;
    permissions_count: number;
};

type Props = {
    roles: Role[];
};

export default function RolesIndex({ roles }: Props) {
    return (
        <>
            <Head title="Roles" />

            <div className="space-y-6 p-6">
                <Heading title="Roles" description="Manage roles and their permissions" />

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Role</TableHead>
                                <TableHead>Permissions</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {roles.map((role) => (
                                <TableRow key={role.id}>
                                    <TableCell className="font-medium capitalize">{role.name}</TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">{role.permissions_count}</Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Link
                                            href={show(role)}
                                            className="text-sm text-primary underline-offset-4 hover:underline"
                                        >
                                            Manage permissions
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {roles.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={3} className="py-8 text-center text-muted-foreground">
                                        No roles found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Roles',
            href: index(),
        },
    ],
};
