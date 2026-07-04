import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

type FlashProps = {
    success?: string;
    error?: string;
    warning?: string;
};

export function useFlashToast(): void {
    useEffect(() => {
        const unsubscribeFlash = router.on('flash', (event) => {
            const flashData = (event as CustomEvent).detail?.flash;
            const data = flashData?.toast as FlashToast | undefined;

            if (data) {
                toast[data.type](data.message);
            }
        });

        const unsubscribeNavigate = router.on('navigate', (event) => {
            const flash = (event.detail.page.props as { flash?: FlashProps }).flash;

            if (flash?.success) {
                toast.success(flash.success);
            }
            if (flash?.error) {
                toast.error(flash.error);
            }
            if (flash?.warning) {
                toast.warning(flash.warning);
            }
        });

        return () => {
            unsubscribeFlash();
            unsubscribeNavigate();
        };
    }, []);
}
