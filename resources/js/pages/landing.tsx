import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Bot, ChevronDown, MapPin } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import DemoRequestController from '@/actions/App/Http/Controllers/DemoRequestController';
import AppLogo from '@/components/app-logo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { dashboard, home, login } from '@/routes';

type Tone = 'success' | 'warning' | 'danger';

const toneClasses: Record<Tone, string> = {
    success: 'bg-success-bg text-success',
    warning: 'bg-warning-bg text-warning',
    danger: 'bg-danger-bg text-danger',
};

const toneBarClasses: Record<Tone, string> = {
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
};

/** A small status pill for compliance states (Cumple / Alerta / No cumple). */
function TonePill({ tone, children }: { tone: Tone; children: ReactNode }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap',
                toneClasses[tone],
            )}
        >
            <span className="size-1.5 rounded-full bg-current" />
            {children}
        </span>
    );
}

function NavAnchor({ href, children }: { href: string; children: ReactNode }) {
    return (
        <a
            href={href}
            className="text-sm font-medium text-white/70 transition-colors hover:text-white"
        >
            {children}
        </a>
    );
}

function SectionEyebrow({ children }: { children: ReactNode }) {
    return (
        <div className="text-xs font-semibold tracking-wider text-brand-coral uppercase">
            {children}
        </div>
    );
}

/** The hero's live-looking compliance dashboard card (style reference only — every value below is illustrative, not fetched). */
function ComplianceCard() {
    const stats: { label: string; value: string; sublabel: string; tone: Tone }[] = [
        { label: 'Promedio semanal', value: '39.2h', sublabel: 'límite 44h', tone: 'success' },
        { label: 'Horas extra', value: '06:40', sublabel: 'esta semana', tone: 'warning' },
        { label: 'Marcas pendientes', value: '1', sublabel: 'por revisar', tone: 'danger' },
    ];

    const employees: { name: string; status: string; percent: number; tone: Tone }[] = [
        { name: 'Camila Rojas', status: '38.5h · Cumple', percent: 87, tone: 'success' },
        { name: 'Jorge Muñoz', status: '43.1h · Alerta', percent: 98, tone: 'warning' },
        { name: 'Valentina Soto', status: '45.8h · No cumple', percent: 100, tone: 'danger' },
    ];

    return (
        <Card className="w-full gap-4 p-5 shadow-lg">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <p className="font-semibold tracking-tight">
                        Semana 15 – 21 de julio
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Equipo Operaciones · 12 personas
                    </p>
                </div>
                <TonePill tone="success">Cumple</TonePill>
            </div>

            <div className="grid grid-cols-3 gap-2">
                {stats.map((stat) => (
                    <div
                        key={stat.label}
                        className="rounded-lg border bg-muted/40 p-3"
                    >
                        <p className="text-[11px] text-muted-foreground">
                            {stat.label}
                        </p>
                        <p className="mt-1 text-lg font-bold tabular-nums">
                            {stat.value}
                        </p>
                        <p className="text-[11px] text-muted-foreground">
                            {stat.sublabel}
                        </p>
                    </div>
                ))}
            </div>

            <div className="flex flex-col gap-3 rounded-lg border p-3.5">
                {employees.map((employee) => (
                    <div key={employee.name} className="flex flex-col gap-1.5">
                        <div className="flex items-center justify-between text-xs">
                            <span className="font-medium">{employee.name}</span>
                            <span className="text-muted-foreground">
                                {employee.status}
                            </span>
                        </div>
                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                className={cn(
                                    'h-full rounded-full',
                                    toneBarClasses[employee.tone],
                                )}
                                style={{ width: `${employee.percent}%` }}
                            />
                        </div>
                    </div>
                ))}
            </div>
        </Card>
    );
}

/** Feature row 1's visual: a simplified punch-clock card (same vocabulary as the authenticated dashboard's ClockCard). */
function ClockPreviewCard() {
    return (
        <Card className="w-72 gap-4 p-5 shadow-lg">
            <div className="flex items-center justify-between">
                <p className="text-xs font-medium text-muted-foreground">
                    Martes 22 de julio
                </p>
                <TonePill tone="success">En turno</TonePill>
            </div>
            <p className="text-5xl font-bold tracking-tight tabular-nums">
                08:58
            </p>
            <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span className="inline-flex items-center gap-1.5">
                    <MapPin className="size-3.5" />
                    Sucursal Providencia
                </span>
                <span>4.5h trabajadas</span>
            </div>
            <Button className="w-full">Marcar salida</Button>
        </Card>
    );
}

/** Feature row 2's visual: overtime/compliance alerts, same tone system as the hero card. */
function OvertimeAlertsCard() {
    const alerts: { tone: Tone; label: string; title: string; sub: string }[] = [
        {
            tone: 'danger',
            label: 'No cumple',
            title: 'Jorge Muñoz supera las 45h semanales',
            sub: 'Semana del 15 al 21 de julio',
        },
        {
            tone: 'warning',
            label: 'Alerta',
            title: 'Horas extra de hoy sin autorización',
            sub: 'Pendiente de aprobación del supervisor',
        },
        {
            tone: 'success',
            label: 'Cumple',
            title: 'Equipo Ventas dentro del límite legal',
            sub: 'Promedio 38.6h esta semana',
        },
    ];

    return (
        <div className="flex w-full flex-col gap-3 sm:w-96">
            {alerts.map((alert) => (
                <Card key={alert.title} className="gap-2 p-4">
                    <TonePill tone={alert.tone}>{alert.label}</TonePill>
                    <p className="text-sm font-semibold">{alert.title}</p>
                    <p className="text-xs text-muted-foreground">{alert.sub}</p>
                </Card>
            ))}
        </div>
    );
}

/** Feature row 3's visual: the DT-ready report exports, one row per real report type. */
function ReportsCard() {
    const reports = [
        { name: 'Maestro de Trabajadores', status: 'Listo' },
        { name: 'Resumen de Remuneraciones', status: 'Listo' },
        { name: 'Movimientos del Período', status: 'Listo' },
        { name: 'Detalle Semanal', status: 'Listo' },
        { name: 'Excesos de Jornada y HHEE', status: 'Listo' },
    ];

    return (
        <Card className="w-full gap-3 p-5 shadow-lg sm:w-[26rem]">
            <div className="flex items-center justify-between border-b pb-2 text-xs font-semibold text-muted-foreground">
                <span>Reporte</span>
                <span>Estado</span>
            </div>
            {reports.map((report) => (
                <div
                    key={report.name}
                    className="flex items-center justify-between border-b py-2 text-sm last:border-b-0"
                >
                    <span className="font-medium">{report.name}</span>
                    <TonePill tone="success">{report.status}</TonePill>
                </div>
            ))}
            <Button variant="default" className="mt-1 w-full">
                Exportar para la Dirección del Trabajo
            </Button>
        </Card>
    );
}

/** Feature row 4's visual: pending and approved leave requests, same vocabulary as the leaves calendar. */
function LeaveRequestsCard() {
    const requests: { name: string; type: string; range: string; status: string; tone: Tone }[] = [
        { name: 'Camila Rojas', type: 'Vacaciones', range: '28 jul – 1 ago', status: 'Aprobada', tone: 'success' },
        { name: 'Diego Fuentes', type: 'Licencia médica', range: '22 jul', status: 'Pendiente', tone: 'warning' },
        { name: 'Valentina Soto', type: 'Permiso sin goce', range: '30 jul', status: 'Pendiente', tone: 'warning' },
    ];

    return (
        <Card className="w-full gap-3 p-5 shadow-lg sm:w-[26rem]">
            <div className="flex items-center justify-between border-b pb-2 text-xs font-semibold text-muted-foreground">
                <span>Solicitud</span>
                <span>Estado</span>
            </div>
            {requests.map((request) => (
                <div
                    key={request.name}
                    className="flex items-center justify-between border-b py-2 text-sm last:border-b-0"
                >
                    <div>
                        <p className="font-medium">{request.name}</p>
                        <p className="text-xs text-muted-foreground">
                            {request.type} · {request.range}
                        </p>
                    </div>
                    <TonePill tone={request.tone}>{request.status}</TonePill>
                </div>
            ))}
            <Button variant="default" className="mt-1 w-full">
                Ver calendario de licencias
            </Button>
        </Card>
    );
}

/** Feature row 5's visual: document templates with {{variable}} placeholders, generated and sent to e-signature. */
function DocumentTemplatesCard() {
    const templates = [
        { name: 'Contrato de trabajo', type: 'Contratos' },
        { name: 'Anexo de cambio de turno', type: 'Anexos' },
        { name: 'Pacto de horas extra', type: 'Pactos' },
    ];

    return (
        <Card className="w-full gap-4 p-5 shadow-lg sm:w-[26rem]">
            <div className="flex items-center justify-between border-b pb-3">
                <p className="text-sm font-semibold">Plantillas de documentos</p>
                <Badge variant="outline">{templates.length} disponibles</Badge>
            </div>
            <div className="flex flex-col gap-2">
                {templates.map((template) => (
                    <div
                        key={template.name}
                        className="flex items-center justify-between rounded-lg border bg-muted/40 px-3 py-2 text-sm"
                    >
                        <span className="font-medium">{template.name}</span>
                        <span className="text-xs text-muted-foreground">
                            {template.type}
                        </span>
                    </div>
                ))}
            </div>
            <div className="flex items-center justify-between rounded-lg border p-3">
                <div>
                    <p className="text-sm font-medium">
                        Contrato de Camila Rojas
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Generado desde «Contrato de trabajo»
                    </p>
                </div>
                <TonePill tone="warning">Pendiente de firma</TonePill>
            </div>
        </Card>
    );
}

/** Feature row 6's visual (the MCP/AI differentiator): a short chat exchange. */
function AssistantChatCard() {
    return (
        <Card className="w-full gap-3 p-5 shadow-lg sm:w-[26rem]">
            <div className="flex items-center gap-2 border-b pb-3">
                <span className="flex size-7 items-center justify-center rounded-full bg-primary text-primary-foreground">
                    <Bot className="size-4" />
                </span>
                <p className="text-sm font-semibold">
                    Asistente de IA conectado por MCP
                </p>
            </div>
            <div className="flex flex-col gap-3 text-sm">
                <div className="self-end rounded-2xl rounded-br-sm bg-primary px-3.5 py-2 text-primary-foreground">
                    Aprueba las vacaciones de Camila para la próxima semana
                </div>
                <div className="self-start rounded-2xl rounded-bl-sm bg-muted px-3.5 py-2">
                    Listo. Aprobé la solicitud y avisé a Camila y a su
                    supervisor.
                </div>
                <div className="self-end rounded-2xl rounded-br-sm bg-primary px-3.5 py-2 text-primary-foreground">
                    Genérame el Resumen de Remuneraciones de junio
                </div>
                <div className="self-start rounded-2xl rounded-bl-sm bg-muted px-3.5 py-2">
                    Listo, el reporte queda disponible para descargar.
                </div>
            </div>
        </Card>
    );
}

type FeatureRow = {
    number: string;
    title: string;
    body: string;
    visual: ReactNode;
    reverse?: boolean;
    highlight?: boolean;
};

function FeatureRow({ number, title, body, visual, reverse, highlight }: FeatureRow) {
    return (
        <div
            className={cn(
                'mx-auto flex max-w-6xl flex-col items-center gap-10 px-6 py-10 md:flex-row md:gap-14',
                reverse && 'md:flex-row-reverse',
                highlight && 'rounded-2xl border border-primary/20 bg-primary/5',
            )}
        >
            <div className="flex max-w-md flex-1 flex-col gap-3">
                <div className="flex items-center gap-2">
                    <span className="text-sm font-bold text-muted-foreground">
                        {number}
                    </span>
                    {highlight && (
                        <Badge className="bg-brand-coral text-brand-coral-foreground">
                            Exclusivo de Kolvi
                        </Badge>
                    )}
                </div>
                <h3 className="text-2xl font-bold tracking-tight">{title}</h3>
                <p className="text-base leading-relaxed text-muted-foreground">
                    {body}
                </p>
            </div>
            <div className="flex flex-1 items-center justify-center">
                {visual}
            </div>
        </div>
    );
}

function Step({ number, title, body }: { number: number; title: string; body: string }) {
    return (
        <div className="flex flex-col gap-2.5 border-t-2 border-primary pt-5">
            <p className="text-sm font-bold text-primary">Paso {number}</p>
            <h3 className="text-lg font-bold tracking-tight">{title}</h3>
            <p className="text-sm leading-relaxed text-muted-foreground">
                {body}
            </p>
        </div>
    );
}

function FaqItem({ question, answer }: { question: string; answer: string }) {
    return (
        <Collapsible className="border-b">
            <h3>
                <CollapsibleTrigger className="group flex w-full items-center justify-between gap-4 py-5 text-left text-base font-semibold">
                    <span>{question}</span>
                    <ChevronDown className="size-4 shrink-0 text-muted-foreground transition-transform duration-200 group-data-[state=open]:rotate-180" />
                </CollapsibleTrigger>
            </h3>
            <CollapsibleContent>
                <p className="pb-5 text-sm leading-relaxed text-muted-foreground">
                    {answer}
                </p>
            </CollapsibleContent>
        </Collapsible>
    );
}

const FAQS = [
    {
        question: '¿Kolvi cumple con la Ley de 40 horas?',
        answer: 'Sí. Kolvi aplica los límites semanales vigentes en cada etapa de la reducción legal de 44 a 40 horas, sin que la organización tenga que configurar nada cuando la ley cambia.',
    },
    {
        question: '¿Sirve como respaldo ante la Dirección del Trabajo?',
        answer: 'Cada marca queda con hora, sucursal y un comprobante firmado con folio (Resolución 38, Art. 13), y los reportes se exportan en los formatos que la fiscalización solicita.',
    },
    {
        question: '¿Mi equipo necesita instalar algo?',
        answer: 'No. Se marca desde el navegador del celular o el computador, dentro de la geocerca de cada sucursal. Solo se necesita un correo para ingresar.',
    },
    {
        question: '¿Puedo gestionar Kolvi sin entrar al panel?',
        answer: 'Sí. El servidor MCP de Kolvi conecta tu organización a un asistente de inteligencia artificial para aprobar licencias, autorizar horas extra, generar reportes o enviar documentos a firma con instrucciones en lenguaje natural.',
    },
    {
        question: '¿Puedo importar mi nómina actual?',
        answer: 'Sí. Kolvi incluye una carga masiva de trabajadores desde planilla, con revisión de errores antes de confirmar la importación.',
    },
    {
        question: '¿Puedo dar roles distintos a cada persona?',
        answer: 'Sí. Kolvi define los roles Dueño, Administrador, Supervisor y Empleado, y soporta múltiples sucursales y turnos dentro de la misma organización.',
    },
];

function DemoRequestForm() {
    const { data, setData, post, processing, errors } = useForm({ email: '' });
    const [submitted, setSubmitted] = useState(false);

    function submit(event: FormEvent) {
        event.preventDefault();

        post(DemoRequestController.store().url, {
            preserveScroll: true,
            onSuccess: () => setSubmitted(true),
        });
    }

    if (submitted) {
        return (
            <div className="rounded-lg bg-success-bg px-5 py-4 text-sm font-medium text-success">
                Listo. Te escribiremos a {data.email} para coordinar la demo.
            </div>
        );
    }

    return (
        <form onSubmit={submit} className="flex flex-col gap-2 sm:flex-row sm:items-start">
            <div className="flex-1">
                <Label htmlFor="demo-email" className="sr-only">
                    Correo electrónico
                </Label>
                <Input
                    id="demo-email"
                    type="email"
                    required
                    value={data.email}
                    onChange={(event) => setData('email', event.target.value)}
                    placeholder="correo@empresa.cl"
                    className="bg-background text-foreground"
                />
                {errors.email && (
                    <p className="mt-1 text-xs text-destructive">
                        {errors.email}
                    </p>
                )}
            </div>
            <Button
                type="submit"
                disabled={processing}
                className="bg-brand-coral text-brand-coral-foreground hover:bg-brand-coral/90"
            >
                {processing && <Spinner />}
                Agendar demo
            </Button>
        </form>
    );
}

export default function Landing() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Kolvi — Control de asistencia y cumplimiento laboral" />

            <div className="min-h-screen bg-background text-foreground">
                <header className="sticky top-0 z-40 bg-brand-navy-deep">
                    <div className="mx-auto flex max-w-6xl items-center gap-8 px-6 py-4">
                        <Link href={home()} className="flex items-center">
                            <AppLogo variant="light" />
                        </Link>
                        <nav className="hidden flex-1 items-center gap-7 md:flex">
                            <NavAnchor href="#funciones">Funciones</NavAnchor>
                            <NavAnchor href="#como-funciona">
                                Cómo funciona
                            </NavAnchor>
                            <NavAnchor href="#planes">Planes</NavAnchor>
                            <NavAnchor href="#faq">Preguntas</NavAnchor>
                        </nav>
                        <div className="ml-auto flex items-center gap-4">
                            <Link
                                href={auth.user ? dashboard() : login()}
                                className="text-sm font-semibold text-white"
                            >
                                {auth.user ? 'Ir al panel' : 'Ingresar'}
                            </Link>
                            <Button
                                asChild
                                size="sm"
                                className="bg-brand-coral text-white hover:bg-brand-coral/90"
                            >
                                <a href="#demo">Agendar demo</a>
                            </Button>
                        </div>
                    </div>
                </header>

                <section className="bg-brand-navy-deep">
                    <div className="mx-auto flex max-w-6xl flex-col items-center gap-14 px-6 pt-16 pb-10 md:flex-row md:items-end md:pt-24">
                        <div className="flex max-w-xl flex-col gap-6">
                            <SectionEyebrow>
                                Control de asistencia · Ley de 40 horas
                            </SectionEyebrow>
                            <h1 className="text-4xl font-bold tracking-tight text-balance text-white sm:text-5xl">
                                Marca el tiempo. Cumple la ley. Sin planillas.
                            </h1>
                            <p className="text-lg leading-relaxed text-white/70 text-pretty">
                                Kolvi registra la jornada de tu equipo, controla
                                las horas extra bajo la Ley de 40 horas y entrega
                                reportes listos para la Dirección del Trabajo —
                                desde la web, el celular o un asistente de IA.
                            </p>
                            <div className="flex flex-wrap gap-3">
                                <Button
                                    asChild
                                    size="lg"
                                    className="bg-brand-coral text-brand-coral-foreground hover:bg-brand-coral/90"
                                >
                                    <a href="#demo">Agendar demo</a>
                                </Button>
                                <Button
                                    asChild
                                    size="lg"
                                    variant="outline"
                                    className="border-white/30 bg-transparent text-white hover:bg-white/10 hover:text-white"
                                >
                                    <a href="#como-funciona">Ver cómo funciona →</a>
                                </Button>
                            </div>
                        </div>
                        <div className="w-full max-w-md flex-1">
                            <ComplianceCard />
                        </div>
                    </div>
                </section>

                <section className="border-y bg-card">
                    <div className="mx-auto grid max-w-6xl grid-cols-1 gap-6 px-6 py-8 sm:grid-cols-3">
                        {[
                            {
                                n: '44h → 40h',
                                t: 'reducción legal de la jornada semanal, aplicada sin configurar nada',
                            },
                            {
                                n: '100%',
                                t: 'de las marcas con comprobante firmado y folio, listas para una fiscalización',
                            },
                            {
                                n: '0 planillas',
                                t: 'para calcular horas extra, turnos o nómina',
                            },
                        ].map((fact) => (
                            <div key={fact.n} className="flex items-baseline gap-3">
                                <span className="text-2xl font-bold text-primary">
                                    {fact.n}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {fact.t}
                                </span>
                            </div>
                        ))}
                    </div>
                </section>

                <section id="funciones" className="py-20">
                    <div className="mx-auto flex max-w-6xl flex-col gap-4 px-6 pb-6">
                        <SectionEyebrow>Funciones</SectionEyebrow>
                        <h2 className="max-w-2xl text-3xl font-bold tracking-tight text-balance sm:text-4xl">
                            Todo lo que pide la ley, en un solo lugar.
                        </h2>
                    </div>

                    <FeatureRow
                        number="01"
                        title="Marcación con geocerca"
                        body="Entrada, salida y descansos desde el celular o el computador, dentro del radio de cada sucursal. Un máximo de una marca por día y un comprobante firmado con folio respaldan cada registro (Resolución 38, Art. 13)."
                        visual={<ClockPreviewCard />}
                    />
                    <FeatureRow
                        number="02"
                        title="Horas extra bajo control"
                        body="Pactos de horas extra, el límite legal en su fase de reducción de 44 a 40 horas, y un flujo de autorización antes de que la hora se trabaje. La compensación se paga o se descansa, como la ley exige."
                        visual={<OvertimeAlertsCard />}
                        reverse
                    />
                    <FeatureRow
                        number="03"
                        title="Reportes listos para la Dirección del Trabajo"
                        body="Maestro de Trabajadores, Resumen de Remuneraciones, Movimientos del Período, Detalle Semanal y Excesos de Jornada y HHEE. Exporte el formato que pide la fiscalización sin armar una sola planilla."
                        visual={<ReportsCard />}
                    />
                    <FeatureRow
                        number="04"
                        title="Licencias y vacaciones sin cruce de planillas"
                        body="Vacaciones, licencias médicas, permisos con y sin goce de sueldo, con un flujo de aprobación del supervisor y un calendario compartido por sucursal para ver quién está disponible cada día."
                        visual={<LeaveRequestsCard />}
                        reverse
                    />
                    <FeatureRow
                        number="05"
                        title="Documentos con plantillas y firma electrónica simple"
                        body="Contratos, anexos, pactos y certificados desde una plantilla reutilizable con variables como nombre, cargo o sueldo. Se generan por trabajador y se envían a firma electrónica simple (Ley 19.799), con su estado de firma al día."
                        visual={<DocumentTemplatesCard />}
                    />
                    <FeatureRow
                        number="06"
                        title="Gestiona todo con un asistente de IA"
                        body="El servidor MCP de Kolvi conecta tu organización a un asistente de inteligencia artificial: aprueba licencias, autoriza horas extra, genera reportes de nómina o envía documentos a firma, todo con instrucciones en lenguaje natural — sin abrir el panel."
                        visual={<AssistantChatCard />}
                        reverse
                        highlight
                    />
                </section>

                <section className="border-y bg-muted/30 py-14">
                    <div className="mx-auto flex max-w-2xl flex-col items-center gap-4 px-6 text-center">
                        <h2 className="text-2xl font-bold tracking-tight text-balance sm:text-3xl">
                            ¿List@ para dejar las planillas?
                        </h2>
                        <p className="text-base leading-relaxed text-muted-foreground">
                            Agenda una demo de 20 minutos y te mostramos Kolvi
                            funcionando con datos parecidos a los de tu
                            empresa.
                        </p>
                        <Button
                            asChild
                            size="lg"
                            className="bg-brand-coral text-brand-coral-foreground hover:bg-brand-coral/90"
                        >
                            <a href="#demo">Agendar demo</a>
                        </Button>
                    </div>
                </section>

                <section id="como-funciona" className="border-y bg-card py-20">
                    <div className="mx-auto flex max-w-6xl flex-col gap-10 px-6">
                        <div className="flex flex-col gap-4">
                            <SectionEyebrow>Cómo funciona</SectionEyebrow>
                            <h2 className="max-w-2xl text-3xl font-bold tracking-tight text-balance sm:text-4xl">
                                En marcha en una tarde.
                            </h2>
                        </div>
                        <div className="grid grid-cols-1 gap-8 sm:grid-cols-3">
                            <Step
                                number={1}
                                title="Carga tu equipo"
                                body="Importa tu nómina y turnos desde una planilla, o agrégalos uno por uno. Define sucursales, turnos y roles: Dueño, Administrador, Supervisor y Empleado."
                            />
                            <Step
                                number={2}
                                title="Tu equipo marca"
                                body="Cada persona registra entrada, salida y descansos desde su celular o computador, dentro de la geocerca de su sucursal."
                            />
                            <Step
                                number={3}
                                title="Kolvi vigila el cumplimiento"
                                body="Recibe alertas de horas extra y licencias, firma documentos y exporta reportes para la Dirección del Trabajo — a mano o pidiéndoselo a un asistente de IA."
                            />
                        </div>
                    </div>
                </section>

                <section id="planes" className="py-20">
                    <div className="mx-auto flex max-w-2xl flex-col items-center gap-5 px-6 text-center">
                        <SectionEyebrow>Planes</SectionEyebrow>
                        <h2 className="text-3xl font-bold tracking-tight text-balance sm:text-4xl">
                            Un plan a la medida de tu organización.
                        </h2>
                        <p className="text-base leading-relaxed text-muted-foreground">
                            Aún no publicamos tarifas fijas — cada
                            organización tiene sucursales, turnos y un número
                            de personas distinto. Cuéntanos tu caso y te
                            armamos una propuesta a medida.
                        </p>
                        <Button asChild size="lg">
                            <a href="#demo">Hablar con ventas</a>
                        </Button>
                    </div>
                </section>

                <section id="faq" className="border-t bg-card py-20">
                    <div className="mx-auto grid max-w-6xl grid-cols-1 gap-10 px-6 sm:grid-cols-2">
                        <div className="flex flex-col gap-4">
                            <SectionEyebrow>
                                Preguntas frecuentes
                            </SectionEyebrow>
                            <h2 className="max-w-md text-3xl font-bold tracking-tight text-balance sm:text-4xl">
                                Lo que suelen preguntarnos.
                            </h2>
                        </div>
                        <div className="flex flex-col">
                            {FAQS.map((faq) => (
                                <FaqItem key={faq.question} {...faq} />
                            ))}
                        </div>
                    </div>
                </section>

                <section id="demo" className="bg-primary py-20 text-primary-foreground">
                    <div className="mx-auto flex max-w-6xl flex-col items-start gap-8 px-6 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex max-w-md flex-col gap-3">
                            <h2 className="text-3xl font-bold tracking-tight text-balance sm:text-4xl">
                                Prepárate para tu próxima fiscalización.
                            </h2>
                            <p className="text-base leading-relaxed text-primary-foreground/80">
                                Te mostramos Kolvi con datos parecidos a los
                                de tu empresa en 20 minutos.
                            </p>
                        </div>
                        <div className="w-full sm:max-w-sm">
                            <DemoRequestForm />
                        </div>
                    </div>
                </section>

                <footer className="border-t py-8">
                    <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 sm:flex-row">
                        <Link href={home()} className="flex items-center">
                            <AppLogo />
                        </Link>
                        <a
                            href="#demo"
                            className="text-sm font-medium text-muted-foreground hover:text-foreground"
                        >
                            Contacto
                        </a>
                        <p className="text-sm text-muted-foreground">
                            © 2026 Kolvi · Santiago, Chile
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
