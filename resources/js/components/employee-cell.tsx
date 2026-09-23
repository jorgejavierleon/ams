import { Link } from '@inertiajs/react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';

type Props = {
    name: string | null;
    avatar?: string | null;
    /** Employee show page URL. Omit to render a plain, unlinked cell. */
    href?: string;
    className?: string;
};

/**
 * Avatar + name cell for an employee column, linking to the employee's show
 * page when `href` is given. Matches the Employees list's own column so the
 * pattern reads the same everywhere an employee is listed.
 */
export function EmployeeCell({ name, avatar, href, className }: Props) {
    const content = (
        <>
            <Avatar className="size-8">
                {avatar ? <AvatarImage src={avatar} alt="" /> : null}
                <AvatarFallback>
                    {(name ?? '—').charAt(0).toUpperCase()}
                </AvatarFallback>
            </Avatar>
            <span className="font-medium">{name ?? '—'}</span>
        </>
    );

    if (href) {
        return (
            <Link
                href={href}
                className={cn(
                    'flex items-center gap-3 hover:cursor-pointer',
                    className,
                )}
            >
                {content}
            </Link>
        );
    }

    return (
        <div className={cn('flex items-center gap-3', className)}>
            {content}
        </div>
    );
}
