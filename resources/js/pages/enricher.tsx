import { Form, Head } from '@inertiajs/react';
import EnricherController from '@/actions/App/Http/Controllers/EnricherController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { download } from '@/routes';

type Stats = {
    processed: number;
    cached: number;
    api_calls: number;
    spent: number;
    avoided: number;
};

export default function Enricher({
    filename,
    headers,
    column,
    stats,
    resolved,
}: {
    filename?: string;
    headers?: string[];
    column?: string;
    stats?: Stats;
    resolved: number;
}) {
    const pending = stats ? stats.processed - resolved : 0;

    return (
        <>
            <Head title="Geocodio enricher" />

            <div className="mx-auto w-full max-w-2xl space-y-8 p-8">
                <header className="space-y-1">
                    <h1 className="text-xl font-semibold">Enrich a CSV</h1>
                    <p className="text-sm text-muted-foreground">
                        Upload a CSV, pick the address column, download it back
                        with coordinates, congressional district and census
                        tract. Addresses seen before are served from the local
                        cache instead of being bought again.
                    </p>
                </header>

                <Form
                    {...EnricherController.upload.form()}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="file">CSV file</Label>
                                <Input
                                    id="file"
                                    type="file"
                                    name="file"
                                    accept=".csv,text/csv"
                                    required
                                />
                                <InputError message={errors.file} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                Upload
                            </Button>
                        </>
                    )}
                </Form>

                {headers && (
                    <Form
                        {...EnricherController.enrich.form()}
                        className="space-y-4 border-t pt-8"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="column">
                                        Address column in {filename}
                                    </Label>
                                    <select
                                        id="column"
                                        name="column"
                                        defaultValue={column ?? headers[0]}
                                        className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                                    >
                                        {headers.map((header) => (
                                            <option key={header} value={header}>
                                                {header}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.column} />
                                </div>

                                <Button type="submit" disabled={processing}>
                                    Enrich
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                {stats && (
                    <section className="space-y-4 border-t pt-8">
                        <dl className="space-y-1 font-mono text-sm tabular-nums">
                            <Counter
                                value={stats.processed}
                                label="addresses processed"
                            />
                            <Counter
                                value={stats.cached}
                                label="served from cache"
                            />
                            <Counter
                                value={stats.api_calls}
                                label="API calls made"
                            />
                            <div className="flex gap-2 pt-2">
                                <span className="w-16 text-right font-semibold">
                                    ${stats.spent.toFixed(2)}
                                </span>
                                <span>
                                    spent, ${stats.avoided.toFixed(2)} avoided
                                </span>
                            </div>
                        </dl>

                        {pending > 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {pending} still geocoding. Refresh the page.
                            </p>
                        ) : (
                            <Button asChild variant="outline">
                                <a href={download.url()}>
                                    Download enriched CSV
                                </a>
                            </Button>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}

function Counter({ value, label }: { value: number; label: string }) {
    return (
        <div className="flex gap-2">
            <dt className="w-16 text-right font-semibold">
                {value.toLocaleString()}
            </dt>
            <dd className="text-muted-foreground">{label}</dd>
        </div>
    );
}
