import { money } from '@/lib/format';

export type Ticket = {
    header: { municipality: string; system: string; title: string };
    folio: string;
    date: string;
    time: string;
    taxpayer: string;
    merchant_folio: string;
    business_type: string;
    tianguis: string | null;
    route: string | null;
    items: {
        concept: string;
        quantity: number;
        unit_price: string;
        amount: string;
    }[];
    total: string;
    currency: string;
    payment_method: string;
    user: string;
    is_cancelled: boolean;
    footer: string;
};

function Pair({
    label,
    value,
    bold,
}: {
    label: string;
    value: string | number;
    bold?: boolean;
}) {
    return (
        <div
            className={`flex justify-between gap-2 ${bold ? 'font-bold' : ''}`}
        >
            <span>{label}</span>
            <span className="text-right">{value}</span>
        </div>
    );
}

/** Vista previa del ticket térmico (papel blanco en ambos temas). */
export function TicketPreview({
    ticket: t,
    qr,
}: {
    ticket: Ticket;
    qr: string;
}) {
    return (
        <div className="mx-auto w-full max-w-[20rem] rounded-sm bg-white p-4 font-mono text-[12px] leading-snug text-black shadow-md ring-1 ring-black/10">
            <div className="text-center font-bold">
                <div>{t.header.municipality}</div>
                <div>{t.header.system}</div>
                <div>{t.header.title}</div>
            </div>
            {t.is_cancelled && (
                <div className="my-2 border-2 border-black text-center text-base font-bold">
                    CANCELADO
                </div>
            )}
            <hr className="my-2 border-dashed border-black" />
            <Pair label="Folio:" value={t.folio} bold />
            <Pair label="Fecha:" value={t.date} />
            <Pair label="Hora:" value={t.time} />
            <hr className="my-2 border-dashed border-black" />
            <div>Contribuyente:</div>
            <div className="font-bold">{t.taxpayer}</div>
            <Pair label="Folio contribuyente:" value={t.merchant_folio} />
            <div>Giro: {t.business_type}</div>
            {t.tianguis && <div>Tianguis: {t.tianguis}</div>}
            {t.route && <div>Ruta: {t.route}</div>}
            <hr className="my-2 border-dashed border-black" />
            {t.items.map((item, i) => (
                <div key={i} className="mb-2">
                    <div>
                        Concepto:{' '}
                        <span className="font-bold">{item.concept}</span>
                    </div>
                    <Pair label="Cantidad:" value={item.quantity} />
                    <Pair
                        label="Precio unitario:"
                        value={money(item.unit_price)}
                    />
                    <Pair label="Importe:" value={money(item.amount)} />
                </div>
            ))}
            <hr className="my-2 border-dashed border-black" />
            <div className="text-[14px]">
                <Pair
                    label="TOTAL:"
                    value={`${money(t.total)} ${t.currency}`}
                    bold
                />
            </div>
            <Pair label="Forma de pago:" value={t.payment_method} />
            <Pair label="Usuario:" value={t.user} />
            <hr className="my-2 border-dashed border-black" />
            <img
                src={qr}
                alt="Código QR de verificación"
                className="mx-auto size-28 [image-rendering:pixelated]"
            />
            <div className="mt-1 text-center text-[11px]">{t.footer}</div>
        </div>
    );
}
