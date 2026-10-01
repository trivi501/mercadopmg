import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';

export function StatCard({
    label,
    value,
    hint,
    icon: Icon,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    icon?: LucideIcon;
}) {
    return (
        <Card className="gap-0 py-4">
            <CardContent className="px-4">
                <div className="flex items-center justify-between gap-2 text-sm text-muted-foreground">
                    <span>{label}</span>
                    {Icon && <Icon className="size-4" aria-hidden />}
                </div>
                <div className="mt-1 text-2xl font-semibold tabular-nums">
                    {value}
                </div>
                {hint && (
                    <div className="mt-0.5 text-xs text-muted-foreground">
                        {hint}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
