import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

export function useFlashToast(): void {
    const { flash } = usePage<{ flash: { success?: string; error?: string; warning?: string } }>().props;

    useEffect(() => {
        return router.on('flash', (event) => {
            const flashData = (event as CustomEvent).detail?.flash;
            const data = flashData?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            toast[data.type](data.message);
        });
    }, []);

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }
        if (flash.error) {
            toast.error(flash.error);
        }
        if (flash.warning) {
            toast.warning(flash.warning);
        }
    }, [flash]);
}
