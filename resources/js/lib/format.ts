const moneyFormatter = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
});

const numberFormatter = new Intl.NumberFormat('es-MX');

export function money(value: string | number | null | undefined): string {
    return moneyFormatter.format(Number(value ?? 0));
}

export function number(value: string | number | null | undefined): string {
    return numberFormatter.format(Number(value ?? 0));
}

export function date(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const [y, m, d] = value.slice(0, 10).split('-');

    return `${d}/${m}/${y}`;
}

/** Quita claves vacías para no ensuciar la URL de los filtros. */
export function cleanQuery(
    query: Record<string, unknown>,
): Record<string, string> {
    return Object.fromEntries(
        Object.entries(query)
            .filter(
                ([, v]) =>
                    v !== '' && v !== null && v !== undefined && v !== 'all',
            )
            .map(([k, v]) => [k, String(v)]),
    );
}
