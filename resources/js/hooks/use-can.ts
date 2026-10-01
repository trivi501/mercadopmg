import { usePage } from '@inertiajs/react';

/**
 * Verifica permisos del usuario en el frontend (solo para mostrar u ocultar
 * elementos; la autorización real siempre ocurre en el servidor).
 */
export function useCan(): (permission: string) => boolean {
    const { auth } = usePage().props;

    return (permission: string) =>
        auth.isAdmin || auth.permissions.includes(permission);
}
