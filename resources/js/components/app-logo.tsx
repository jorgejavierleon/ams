import AppLogoMark from '@/components/app-logo-mark';

export default function AppLogo() {
    return (
        <>
            <AppLogoMark />
            <div className="ml-2 grid flex-1 text-left">
                <span className="truncate text-lg leading-tight font-semibold tracking-tight text-foreground lowercase">
                    Kolvi
                </span>
            </div>
        </>
    );
}
