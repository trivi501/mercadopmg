export type Option = { value: string; label: string };
export type NamedItem = { id: number; name: string };

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        per_page: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
};

/** Paginador de Laravel sin API Resource (`->through()`). */
export type SimplePaginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

export type Merchant = {
    id: number;
    uuid: string;
    folio: string;
    taxpayer_name: string;
    trade_name: string | null;
    business_type_id: number;
    business_type?: string;
    business_description: string | null;
    phone: string | null;
    address: string | null;
    neighborhood: string | null;
    municipality: string | null;
    state: string | null;
    location: string | null;
    location_street: string | null;
    location_number: string | null;
    location_neighborhood: string | null;
    location_reference: string | null;
    latitude: string | null;
    longitude: string | null;
    route_id: number | null;
    route?: string | null;
    tianguis_id: number | null;
    tianguis?: string | null;
    meters: string;
    commerce_type: string;
    commerce_type_label: string;
    status: 'active' | 'inactive' | 'suspended';
    status_label: string;
    registered_at: string;
    notes: string | null;
    qr: string;
    measurements?: {
        id: number;
        meters: string;
        start_date: string;
        end_date: string | null;
        reason: string | null;
        user: string | null;
    }[];
};

export type ReceiptItem = {
    id: number;
    concept_id: number;
    concept_code: string;
    description: string;
    quantity: number;
    unit_price: string;
    amount: string;
};

export type Reprint = {
    id: number;
    receipt_id: number;
    reprint_number: number;
    reason: string;
    date: string;
    time: string;
    user?: string;
};

export type Receipt = {
    id: number;
    uuid: string;
    folio: string;
    merchant_id: number;
    merchant?: { id: number; folio: string; taxpayer_name: string };
    issued_at: string;
    date: string;
    time: string;
    total: string;
    payment_method: string;
    payment_method_label: string;
    status: 'issued' | 'cancelled' | 'reprinted';
    status_label: string;
    printed_at: string | null;
    reprint_count: number;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    canceller?: string | null;
    user?: string;
    tianguis?: string | null;
    route?: string | null;
    source: string;
    concepts?: string;
    items?: ReceiptItem[];
    reprints?: Reprint[];
};

export type Account = {
    fiscal_year: number;
    merchant: {
        folio: string;
        taxpayer_name: string;
        business_type: string;
        location: string | null;
        tianguis: string | null;
        route: string | null;
        meters: string;
    };
    annuality: {
        concept: string;
        amount: string;
        fiscal_year: number;
        status: 'pending' | 'paid';
        status_label: string;
        receipt_folio: string | null;
        paid_at: string | null;
    } | null;
    annualities: {
        id: number;
        concept: string;
        fiscal_year: number;
        amount: string;
        status: 'pending' | 'paid';
        receipt_folio: string | null;
        paid_at: string | null;
    }[];
    tickets: { quantity: number; unit_price: string; total: string };
    other_total: string;
    total_paid: string;
    debt: { count: number; total: string; years: number[] };
};

export type Concept = {
    id: number;
    code: string;
    name: string;
    type: 'annuality' | 'ticket' | 'other';
    amount: string;
};
