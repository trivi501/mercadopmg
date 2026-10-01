import { Link } from '@inertiajs/react';
import {
    BookOpenText,
    FileBarChart,
    History,
    LayoutGrid,
    MapPinned,
    Printer,
    ReceiptText,
    Route as RouteIcon,
    Settings2,
    Store,
    Tags,
    Tent,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCan } from '@/hooks/use-can';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes';
import { index as auditIndex } from '@/routes/audit';
import { index as businessTypesIndex } from '@/routes/catalogs/business-types';
import { index as conceptsIndex } from '@/routes/catalogs/concepts';
import { index as routesIndex } from '@/routes/catalogs/routes';
import { index as tianguisIndex } from '@/routes/catalogs/tianguis';
import { create as chargeCreate } from '@/routes/charges';
import {
    index as merchantsIndex,
    map as merchantsMap,
} from '@/routes/merchants';
import {
    index as receiptsIndex,
    reprints as reprintsIndex,
} from '@/routes/receipts';
import { index as reportsIndex } from '@/routes/reports';
import { general as generalSettings } from '@/routes/settings';
import { index as usersIndex } from '@/routes/users';
import type { NavGroup } from '@/types';

export function AppSidebar() {
    const can = useCan();
    const { isCurrentOrParentUrl, isCurrentUrl } = useCurrentUrl();

    const groups: NavGroup[] = [
        {
            label: 'Operación',
            items: [
                {
                    title: 'Dashboard',
                    href: dashboard(),
                    icon: LayoutGrid,
                    permission: 'dashboard.view',
                },
                {
                    title: 'Padrón',
                    href: merchantsIndex(),
                    icon: Store,
                    permission: 'merchants.view',
                    isActive:
                        isCurrentOrParentUrl(merchantsIndex()) &&
                        !isCurrentUrl(merchantsMap()),
                },
                {
                    title: 'Mapa',
                    href: merchantsMap(),
                    icon: MapPinned,
                    permission: 'merchants.view',
                },
                {
                    title: 'Cobrar',
                    href: chargeCreate(),
                    icon: Wallet,
                    permission: 'payments.create',
                },
                {
                    title: 'Recibos',
                    href: receiptsIndex(),
                    icon: ReceiptText,
                    permission: 'receipts.view',
                    isActive:
                        isCurrentOrParentUrl(receiptsIndex()) &&
                        !isCurrentUrl(reprintsIndex()),
                },
            ],
        },
        {
            label: 'Control',
            items: [
                {
                    title: 'Reportes',
                    href: reportsIndex(),
                    icon: FileBarChart,
                    permission: 'reports.view',
                    isActive: isCurrentOrParentUrl(reportsIndex()),
                },
                {
                    title: 'Reimpresiones',
                    href: reprintsIndex(),
                    icon: Printer,
                    permission: 'reprints.view',
                },
                {
                    title: 'Auditoría',
                    href: auditIndex(),
                    icon: History,
                    permission: 'audit.view',
                },
            ],
        },
        {
            label: 'Catálogos',
            items: [
                {
                    title: 'Giros',
                    href: businessTypesIndex(),
                    icon: Tags,
                    permission: 'catalogs.manage',
                },
                {
                    title: 'Tianguis',
                    href: tianguisIndex(),
                    icon: Tent,
                    permission: 'catalogs.manage',
                },
                {
                    title: 'Rutas',
                    href: routesIndex(),
                    icon: RouteIcon,
                    permission: 'catalogs.manage',
                },
                {
                    title: 'Conceptos de cobro',
                    href: conceptsIndex(),
                    icon: BookOpenText,
                    permission: 'catalogs.manage',
                },
            ],
        },
        {
            label: 'Administración',
            items: [
                {
                    title: 'Usuarios',
                    href: usersIndex(),
                    icon: Users,
                    permission: 'users.manage',
                },
                {
                    title: 'Configuración',
                    href: generalSettings(),
                    icon: Settings2,
                    permission: 'settings.manage',
                },
            ],
        },
    ];

    const visible = groups
        .map((g) => ({
            ...g,
            items: g.items.filter((i) => !i.permission || can(i.permission)),
        }))
        .filter((g) => g.items.length > 0);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {visible.map((group) => (
                    <NavMain
                        key={group.label}
                        label={group.label}
                        items={group.items}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
