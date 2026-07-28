import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';

export type AvatarGroupUser = {
    id: number;
    name: string;
    avatar: string | null;
};

type Props = {
    users: AvatarGroupUser[];
    /** Total number of users, used to compute the "+N" overflow bubble. */
    total: number;
    /** Maximum avatars to render before collapsing into the overflow bubble. */
    max?: number;
    className?: string;
    emptyLabel?: string;
};

/**
 * Overlapping stack of user avatars with a trailing "+N" bubble when there are
 * more users than avatars shown.
 */
export function AvatarGroup({
    users,
    total,
    max = 5,
    className,
    emptyLabel = '—',
}: Props) {
    if (total === 0) {
        return (
            <span className="text-sm text-muted-foreground">{emptyLabel}</span>
        );
    }

    const shown = users.slice(0, max);
    const overflow = total - shown.length;

    return (
        <div className={cn('flex items-center -space-x-2', className)}>
            {shown.map((user) => (
                <Avatar
                    key={user.id}
                    title={user.name}
                    className="size-8 ring-2 ring-background"
                >
                    {user.avatar ? (
                        <AvatarImage src={user.avatar} alt={user.name} />
                    ) : null}
                    <AvatarFallback className="text-xs">
                        {user.name.charAt(0).toUpperCase()}
                    </AvatarFallback>
                </Avatar>
            ))}
            {overflow > 0 ? (
                <span className="flex size-8 items-center justify-center rounded-full bg-muted text-xs font-medium text-muted-foreground ring-2 ring-background">
                    +{overflow}
                </span>
            ) : null}
        </div>
    );
}
