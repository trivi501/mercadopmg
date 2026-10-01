import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Datum = { label: string; value: number };

function ChartTooltip({
    active,
    payload,
    label,
    format,
}: {
    active?: boolean;
    payload?: { value: number }[];
    label?: string;
    format: (v: number) => string;
}) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="rounded-md border bg-popover px-3 py-2 text-sm text-popover-foreground shadow-md">
            <div className="text-muted-foreground">{label}</div>
            <div className="font-semibold tabular-nums">
                {format(payload[0].value)}
            </div>
        </div>
    );
}

/**
 * Gráfica de barras de una sola serie (magnitud por categoría o periodo).
 * Una sola serie ⇒ un solo tono; el título nombra la serie, sin leyenda.
 */
export function BarChartCard({
    title,
    data,
    format = (v) => v.toLocaleString('es-MX'),
    horizontal = false,
    height = 260,
    summary,
}: {
    title: string;
    data: Datum[];
    format?: (v: number) => string;
    horizontal?: boolean;
    height?: number;
    summary?: string;
}) {
    const tickStyle = { fill: 'var(--muted-foreground)', fontSize: 12 };
    const empty = data.every((d) => d.value === 0);
    const chartHeight = horizontal
        ? Math.max(160, data.length * 28 + 40)
        : height;

    return (
        <Card className="gap-2">
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
                {summary && (
                    <p className="text-sm text-muted-foreground">{summary}</p>
                )}
            </CardHeader>
            <CardContent>
                {empty ? (
                    <p className="py-10 text-center text-sm text-muted-foreground">
                        Sin datos para el periodo.
                    </p>
                ) : (
                    <div
                        style={{ height: chartHeight }}
                        role="img"
                        aria-label={title}
                    >
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart
                                data={data}
                                layout={horizontal ? 'vertical' : 'horizontal'}
                                margin={{
                                    top: 4,
                                    right: 12,
                                    bottom: 0,
                                    left: horizontal ? 8 : 0,
                                }}
                                barCategoryGap={2}
                            >
                                <CartesianGrid
                                    stroke="var(--viz-grid)"
                                    strokeDasharray="0"
                                    vertical={horizontal}
                                    horizontal={!horizontal}
                                />
                                {horizontal ? (
                                    <>
                                        <XAxis
                                            type="number"
                                            tick={tickStyle}
                                            axisLine={false}
                                            tickLine={false}
                                            allowDecimals={false}
                                        />
                                        <YAxis
                                            type="category"
                                            dataKey="label"
                                            tick={tickStyle}
                                            axisLine={false}
                                            tickLine={false}
                                            width={120}
                                        />
                                    </>
                                ) : (
                                    <>
                                        <XAxis
                                            dataKey="label"
                                            tick={tickStyle}
                                            axisLine={false}
                                            tickLine={false}
                                            minTickGap={12}
                                        />
                                        <YAxis
                                            tick={tickStyle}
                                            axisLine={false}
                                            tickLine={false}
                                            width={64}
                                            tickFormatter={(v: number) =>
                                                format(v).replace(/\.00$/, '')
                                            }
                                        />
                                    </>
                                )}
                                <Tooltip
                                    cursor={{
                                        fill: 'var(--muted)',
                                        opacity: 0.6,
                                    }}
                                    content={<ChartTooltip format={format} />}
                                />
                                <Bar
                                    dataKey="value"
                                    fill="var(--viz-series-1)"
                                    radius={
                                        horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]
                                    }
                                    maxBarSize={horizontal ? 18 : 28}
                                />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
