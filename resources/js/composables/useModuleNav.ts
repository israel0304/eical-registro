import {
    Award,
    BadgeCheck,
    BarChart3,
    BookOpen,
    CalendarCheck,
    CalendarCog,
    CalendarDays,
    ClipboardCheck,
    Home,
    LayoutTemplate,
    Mail,
    Mic,
    Presentation,
    ScanLine,
    ShieldCheck,
    Tags,
    Users,
    type LucideIcon,
} from 'lucide-vue-next';

export type AppModule = {
    title: string;
    href: string;
    icon: LucideIcon;
    permissions: string[];
};

export const mainModules: AppModule[] = [
    {
        title: 'Inicio',
        href: '/dashboard',
        icon: Home,
        permissions: ['dashboard.view'],
    },
    {
        title: 'Usuarios',
        href: '/users',
        icon: Users,
        permissions: ['users.view'],
    },
    {
        title: 'Talleres',
        href: '/workshops',
        icon: BookOpen,
        permissions: ['workshops.view'],
    },
    {
        title: 'Ponencias',
        href: '/presentations',
        icon: Mic,
        permissions: ['presentations.view'],
    },
    {
        title: 'Mis Ponencias',
        href: '/my-presentations',
        icon: Mic,
        permissions: ['presentations.my'],
    },
    {
        title: 'Conferencias',
        href: '/conferences',
        icon: Presentation,
        permissions: ['conferences.view'],
    },
    {
        title: 'Mis Talleres',
        href: '/my-workshops',
        icon: CalendarCheck,
        permissions: ['workshops.my'],
    },
    {
        title: 'Reportes',
        href: '/admin/reportes',
        icon: BarChart3,
        permissions: ['reportes.view'],
    },
    {
        title: 'Mis Constancias',
        href: '/constancias',
        icon: Award,
        permissions: ['constancias.view'],
    },
    {
        title: 'Mi Gafete',
        href: '/gafete',
        icon: BadgeCheck,
        permissions: ['gafete.view'],
    },
    {
        title: 'Mis Asignaciones',
        href: '/mis-asignaciones',
        icon: ClipboardCheck,
        permissions: ['asignaciones.view'],
    },
    {
        title: 'Check-in',
        href: '/checkin',
        icon: ScanLine,
        permissions: ['checkin.scan'],
    },
    {
        title: 'Programa',
        href: '/programa',
        icon: CalendarDays,
        permissions: ['programa.view'],
    },
    {
        title: 'Evento',
        href: '/admin/evento',
        icon: CalendarCog,
        permissions: ['constancias.evento.manage'],
    },
    {
        title: 'Plantillas',
        href: '/admin/plantillas',
        icon: LayoutTemplate,
        permissions: [
            'gafete.templates.manage',
            'constancias.templates.manage',
            'correos.templates.manage',
        ],
    },
    {
        title: 'Cartas de Invitación',
        href: '/admin/constancias/invitaciones/plantillas',
        icon: Mail,
        permissions: ['constancias.templates.manage'],
    },
    {
        title: 'Tipos',
        href: '/admin/constancias/tipos',
        icon: Tags,
        permissions: ['constancias.types.manage'],
    },
    {
        title: 'Moderadores',
        href: '/admin/constancias/moderadores',
        icon: Users,
        permissions: ['constancias.moderators.manage'],
    },
    {
        title: 'Roles',
        href: '/admin/roles',
        icon: ShieldCheck,
        permissions: ['roles.manage'],
    },
];

export const availableModules = (permissions: string[]): AppModule[] =>
    mainModules.filter((module) =>
        module.permissions.some((permission) =>
            permissions.includes(permission),
        ),
    );