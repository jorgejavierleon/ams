import { Head, useForm } from '@inertiajs/react';
import { Download, Upload as UploadIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';
import { filenameFromContentDisposition } from '@/lib/download';
import { store, template } from '@/routes/imports/employee';

export default function CreateEmployeeImport() {
    const { t } = useTranslations();
    const inputRef = useRef<HTMLInputElement>(null);
    const [pendingTemplate, setPendingTemplate] = useState(false);

    const { data, setData, post, processing, errors } = useForm<{
        file: File | null;
    }>({ file: null });

    async function handleTemplateDownload(format: 'excel' | 'csv') {
        setPendingTemplate(true);

        try {
            const response = await fetch(template(format).url);
            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filenameFromContentDisposition(
                response,
                'plantilla',
            );
            link.click();
            URL.revokeObjectURL(url);
        } finally {
            setPendingTemplate(false);
        }
    }

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        post(store().url, { forceFormData: true });
    }

    return (
        <>
            <Head title={t('ui.employees.import.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.employees.import.title')}
                    description={t('ui.employees.import.description')}
                />

                <div className="max-w-3xl space-y-6">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="justify-start"
                            disabled={pendingTemplate}
                            onClick={() => handleTemplateDownload('excel')}
                        >
                            <Download />
                            {t('ui.employees.import.template.excel')}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className="justify-start"
                            disabled={pendingTemplate}
                            onClick={() => handleTemplateDownload('csv')}
                        >
                            <Download />
                            {t('ui.employees.import.template.csv')}
                        </Button>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <Card>
                            <CardContent>
                                <div
                                    className="flex flex-col items-center gap-3 rounded-lg border-2 border-dashed border-border py-12 text-center"
                                    onDragOver={(e) => e.preventDefault()}
                                    onDrop={(e) => {
                                        e.preventDefault();
                                        const file = e.dataTransfer.files[0];

                                        if (file) {
                                            setData('file', file);
                                        }
                                    }}
                                >
                                    <UploadIcon className="size-8 text-muted-foreground" />
                                    <div className="space-y-1">
                                        <p className="font-medium">
                                            {t(
                                                'ui.employees.import.upload.dropzone_title',
                                            )}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {t(
                                                'ui.employees.import.upload.dropzone_hint',
                                            )}
                                        </p>
                                    </div>
                                    <input
                                        ref={inputRef}
                                        type="file"
                                        accept=".xlsx,.csv"
                                        className="hidden"
                                        onChange={(e) => {
                                            const file = e.target.files?.[0];

                                            if (file) {
                                                setData('file', file);
                                            }
                                        }}
                                    />
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        onClick={() =>
                                            inputRef.current?.click()
                                        }
                                    >
                                        {t('ui.employees.import.upload.browse')}
                                    </Button>
                                    {data.file && (
                                        <p className="text-sm">
                                            {t(
                                                'ui.employees.import.upload.selected',
                                                { name: data.file.name },
                                            )}
                                        </p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {errors.file && (
                            <Alert variant="destructive">
                                <AlertDescription>
                                    {errors.file}
                                </AlertDescription>
                            </Alert>
                        )}

                        <Button
                            type="submit"
                            disabled={!data.file || processing}
                        >
                            {t('ui.employees.import.upload.submit')}
                        </Button>
                    </form>
                </div>
            </div>
        </>
    );
}
