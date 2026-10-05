import { Link } from '@inertiajs/react';
import BlogPostLayout from '@/components/blog/post-layout';
import { home } from '@/routes';
import type { BlogPostMeta } from '@/types/blog';

type Props = {
    post: BlogPostMeta;
};

export default function BeneficiosDeConectarUnMcpATuSistemaDeRrhh({
    post,
}: Props) {
    return (
        <BlogPostLayout post={post}>
            <p>
                MCP (Model Context Protocol) es el estándar que le permite a un
                asistente de IA —el que ya usas para escribir código, resumir
                correos o investigar un tema— conectarse directamente a un
                sistema de software y usarlo, en vez de solo leer capturas de
                pantalla o archivos exportados. Cuando ese sistema es tu
                plataforma de RRHH, conectar un servidor MCP significa que el
                asistente puede consultar licencias, horas extra o
                remuneraciones y responderte al instante, sin que tú tengas que
                abrir un panel, filtrar una tabla o exportar un Excel.
            </p>

            <h2>De "entra al sistema y revisa" a "pregúntale al asistente"</h2>
            <p>
                Hoy, buena parte de las preguntas de RRHH se resuelven igual:
                alguien abre el sistema, busca a la persona o el período
                correcto, filtra, y a veces exporta un archivo para revisarlo
                aparte. Con un servidor MCP conectado, esa misma pregunta
                —"¿cuántos días de licencia le quedan a Camila?", "¿quién tiene
                horas extra pendientes de aprobar?"— se responde en una
                conversación, porque el asistente tiene acceso directo a los
                mismos datos que vería un usuario en la pantalla.
            </p>

            <h2>Así se ve en Kolvi</h2>
            <p>
                Kolvi ya expone un servidor MCP autenticado con el mismo token
                de acceso que usa la API del producto, y cada herramienta que
                ofrece se autoriza con los permisos exactos que el usuario ya
                tiene en la web — nunca más de lo que podría hacer ahí. Algunos
                ejemplos concretos:
            </p>
            <ul>
                <li>
                    Un empleado le pide a su asistente que solicite una
                    licencia, revise el estado de sus solicitudes o cancele una
                    que quedó pendiente — exactamente lo que podría hacer desde
                    la app, con las mismas restricciones (por ejemplo, las
                    licencias médicas siguen fuera del autoservicio).
                </li>
                <li>
                    Un supervisor le pide que le muestre las licencias
                    pendientes de su equipo, y aprueba o rechaza directamente
                    desde la conversación, con la misma regla de que un rechazo
                    necesita un motivo.
                </li>
                <li>
                    Un administrador le pide el reporte de remuneraciones de un
                    período para pasárselo a su contador externo, y recibe el
                    CSV al instante — con una advertencia si ese período todavía
                    tiene hallazgos sin resolver (marcas con anomalías,
                    modificaciones pendientes), para que decida con esa
                    información a la vista.
                </li>
            </ul>

            <h2>Beneficios concretos de conectar MCP a tu sistema de RRHH</h2>
            <p>
                Ninguno de estos beneficios depende de aprender una herramienta
                nueva ni de automatizar "a ciegas": el asistente solo puede
                hacer lo que la persona detrás ya podía hacer.
            </p>
            <ul>
                <li>
                    <strong>Menos pasos para la misma respuesta.</strong> Las
                    preguntas frecuentes de RRHH y nómina se resuelven en una
                    conversación, sin planillas intermedias ni exportaciones
                    manuales.
                </li>
                <li>
                    <strong>Seguridad sin atajos.</strong> Cada llamada se
                    autentica con token y se autoriza contra el mismo permiso
                    que ya protege esa acción en la web — conectar un asistente
                    no abre una puerta nueva, usa la que ya existe.
                </li>
                <li>
                    <strong>Contexto completo, no solo el dato.</strong> El
                    reporte de remuneraciones no se entrega "limpio" cuando no
                    lo está: llega con las advertencias que un humano también
                    debería ver antes de enviarlo.
                </li>
                <li>
                    <strong>
                        Un punto de entrada, no una integración por equipo.
                    </strong>{' '}
                    El mismo servidor sirve tanto para quien pregunta por su
                    propia licencia como para quien aprueba las de su equipo o
                    exporta la nómina del mes.
                </li>
            </ul>

            <p>
                Si quieres ver qué más automatiza Kolvi además de este acceso
                por MCP —control de asistencia, horas extra bajo la Ley de 40
                horas y reportes para la Dirección del Trabajo— puedes revisar{' '}
                <Link href={`${home.url()}#funciones`}>
                    todas las funciones de Kolvi
                </Link>
                .
            </p>
        </BlogPostLayout>
    );
}
