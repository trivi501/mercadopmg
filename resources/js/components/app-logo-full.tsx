import type { ImgHTMLAttributes } from 'react';

import { cn } from '@/lib/utils';

/** Logotipo completo del Ayuntamiento de Guadalupe 2024-2027. */
export default function AppLogoFull({
    className,
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/images/logo-guadalupe.png"
            alt="Ayuntamiento de Guadalupe 2024-2027"
            {...props}
            className={cn('object-contain', className)}
        />
    );
}
