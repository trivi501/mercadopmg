import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

type PageLink = { url: string | null; label: string; active: boolean };

function label(raw: string): string {
    if (raw.includes('Previous') || raw.includes('Anterior')) {
        return '‹';
    }

    if (raw.includes('Next') || raw.includes('Siguiente')) {
        return '›';
    }

    return raw;
}

export function Pagination({
    links,
    from,
    to,
    total,
}: {
    links: PageLink[];
    from: number | null;
    to: number | null;
    total: number;
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground">
            <span>
                {total === 0
                    ? 'Sin registros'
                    : `Mostrando ${from}–${to} de ${total.toLocaleString('es-MX')}`}
            </span>
            {links.length > 3 && (
                <nav className="flex flex-wrap gap-1" aria-label="Paginación">
                    {links.map((link, i) =>
                        link.url ? (
                            <Link
                                key={i}
                                href={link.url}
                                preserveScroll
                                preserveState
                                className={cn(
                                    'min-w-8 rounded-md border px-2 py-1 text-center',
                                    link.active
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'hover:bg-muted',
                                )}
                            >
                                {label(link.label)}
                            </Link>
                        ) : (
                            <span
                                key={i}
                                className="min-w-8 rounded-md border px-2 py-1 text-center opacity-40"
                            >
                                {label(link.label)}
                            </span>
                        ),
                    )}
                </nav>
            )}
        </div>
    );
}
