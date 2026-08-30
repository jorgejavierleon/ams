import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';

export function initials(name: string): string {
    const parts = name.trim().split(/\s+/);

    return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase();
}

const AVATAR_TINTS = [
    'bg-[#003D5C]',
    'bg-[#0F7EA3]',
    'bg-[#3E5964]',
    'bg-[#0E7A54]',
    'bg-[#A66A0A]',
    'bg-[#C41E2E]',
];

/** A small initials avatar for an employee row/pill, tinted by a stable seed (e.g. id). */
export function EmployeeAvatar({
    name,
    seed,
    className,
}: {
    name: string;
    seed: number;
    className?: string;
}) {
    return (
        <Avatar className={cn('size-6', className)}>
            <AvatarFallback
                className={cn(
                    'text-[10px] font-semibold text-white',
                    AVATAR_TINTS[((seed % AVATAR_TINTS.length) + AVATAR_TINTS.length) % AVATAR_TINTS.length],
                )}
            >
                {initials(name)}
            </AvatarFallback>
        </Avatar>
    );
}
