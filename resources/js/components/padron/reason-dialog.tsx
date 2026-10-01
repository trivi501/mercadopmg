import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/**
 * Diálogo que exige capturar un motivo antes de enviar (reimpresión, cancelación).
 */
export function ReasonDialog({
    trigger,
    title,
    description,
    action,
    submitLabel,
    destructive = false,
    suggestions = [],
    onSuccess,
}: {
    trigger: ReactNode;
    title: string;
    description: ReactNode;
    action: { url: string; method: 'post' };
    submitLabel: string;
    destructive?: boolean;
    suggestions?: string[];
    onSuccess?: () => void;
}) {
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <Form
                    action={action.url}
                    method={action.method}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        setReason('');
                        onSuccess?.();
                    }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="reason">Motivo</Label>
                                <Textarea
                                    id="reason"
                                    name="reason"
                                    required
                                    value={reason}
                                    onChange={(e) => setReason(e.target.value)}
                                    maxLength={255}
                                    autoFocus
                                />
                                {suggestions.length > 0 && (
                                    <div className="flex flex-wrap gap-1">
                                        {suggestions.map((s) => (
                                            <Button
                                                key={s}
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => setReason(s)}
                                            >
                                                {s}
                                            </Button>
                                        ))}
                                    </div>
                                )}
                                <InputError
                                    message={errors.reason ?? errors.general}
                                />
                            </div>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => setOpen(false)}
                                >
                                    Cerrar
                                </Button>
                                <Button
                                    type="submit"
                                    variant={
                                        destructive ? 'destructive' : 'default'
                                    }
                                    disabled={
                                        processing || reason.trim().length < 4
                                    }
                                >
                                    {submitLabel}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
