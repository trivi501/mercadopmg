import type { ImgHTMLAttributes } from 'react';

import { cn } from '@/lib/utils';

/** Escudo del Ayuntamiento de Guadalupe (sin texto), para espacios reducidos. */
export default function AppLogoIcon({
    className,
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/images/escudo-guadalupe.png"
            alt="Ayuntamiento de Guadalupe"
            {...props}
            className={cn('object-contain', className)}
        />
    );
}
