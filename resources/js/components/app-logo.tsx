import AppLogoMark from '@/components/app-logo-mark';
import { cn } from '@/lib/utils';

export default function AppLogo({ variant = 'default' }: { variant?: 'default' | 'light' }) {
    return (
        <>
            <AppLogoMark variant={variant} />
            <div className="ml-2 grid flex-1 text-left">
                <span
                    className={cn(
                        'truncate text-lg leading-tight font-semibold tracking-tight lowercase',
                        variant === 'light' ? 'text-white' : 'text-foreground',
                    )}
                >
                    Kolvi
                </span>
            </div>
        </>
    );
}
