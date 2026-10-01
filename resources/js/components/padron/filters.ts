import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { cleanQuery } from '@/lib/format';

/**
 * Estado de filtros sincronizado con la URL. Los cambios se aplican con una
 * pequeña espera para no saturar el servidor mientras se escribe.
 */
export function useFilters<T extends Record<string, unknown>>(
    initial: T,
    url: string,
    delay = 350,
): [T, (key: keyof T, value: unknown) => void, () => void] {
    const [filters, setFilters] = useState<T>(initial);
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = setTimeout(() => {
            router.get(url, cleanQuery(filters), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, delay);

        return () => clearTimeout(timer);
    }, [filters, url, delay]);

    const set = (key: keyof T, value: unknown) =>
        setFilters((f) => ({ ...f, [key]: value }));

    const reset = () =>
        setFilters(
            Object.fromEntries(Object.keys(filters).map((k) => [k, ''])) as T,
        );

    return [filters, set, reset];
}
