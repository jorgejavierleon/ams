export default function AppLogo() {
    return (
        <>
            <svg
                viewBox="0 0 288 288"
                className="h-9! w-9! shrink-0"
                xmlns="http://www.w3.org/2000/svg"
            >
                <rect x="46" y="39" width="46" height="210" rx="23" fill="var(--brand-navy-deep)" />
                <path
                    d="M202 39 L112 144 L202 249 L202 185 L151 144 L202 103 Z"
                    fill="var(--brand-coral)"
                />
            </svg>
            <div className="ml-2 grid flex-1 text-left">
                <span className="truncate text-lg leading-tight font-semibold tracking-tight text-[var(--brand-navy-deep)] lowercase">
                    Kolvi
                </span>
            </div>
        </>
    );
}
