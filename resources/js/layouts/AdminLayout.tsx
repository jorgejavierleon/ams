import type { ReactNode } from 'react';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';

export default function AdminLayout({ children }: { children: ReactNode }) {
    return <AppSidebarLayout>{children}</AppSidebarLayout>;
}
