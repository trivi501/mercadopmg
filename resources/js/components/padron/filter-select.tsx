import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

type Item = { value: string | number; label: string };

/**
 * Select con opción "Todos" para filtros. Radix no admite value vacío,
 * así que se usa el valor centinela "all".
 */
export function FilterSelect({
    value,
    onChange,
    items,
    placeholder,
    allLabel = 'Todos',
    className,
    name,
    allowAll = true,
    id,
}: {
    value: string | number | null | undefined;
    onChange: (value: string) => void;
    items: Item[];
    placeholder: string;
    allLabel?: string;
    className?: string;
    name?: string;
    allowAll?: boolean;
    id?: string;
}) {
    const current =
        value === null || value === undefined || value === ''
            ? allowAll
                ? 'all'
                : ''
            : String(value);

    return (
        <Select
            value={current}
            onValueChange={(v) => onChange(v === 'all' ? '' : v)}
            name={name}
        >
            <SelectTrigger
                id={id}
                className={cn('w-full sm:w-44', className)}
                aria-label={placeholder}
            >
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {allowAll && <SelectItem value="all">{allLabel}</SelectItem>}
                {items.map((item) => (
                    <SelectItem key={item.value} value={String(item.value)}>
                        {item.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

export function toItems(list: { id: number; name: string }[]): Item[] {
    return list.map((i) => ({ value: i.id, label: i.name }));
}
