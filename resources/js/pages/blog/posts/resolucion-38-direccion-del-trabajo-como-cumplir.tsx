import { Link } from '@inertiajs/react';
import BlogPostLayout from '@/components/blog/post-layout';
import { home } from '@/routes';
import type { BlogPostMeta } from '@/types/blog';

type Props = {
    post: BlogPostMeta;
};

export default function Resolucion38DireccionDelTrabajoComoCumplir({
    post,
}: Props) {
    return (
        <BlogPostLayout post={post}>
            <p>
                Si tu empresa usa alguna plataforma digital —una app, un
                portal web, un reloj biométrico conectado a una base de
                datos, incluso un grupo de WhatsApp— para registrar la
                entrada, salida o los descansos de tus trabajadores,
                probablemente te aplica la Resolución 38 exenta de la
                Dirección del Trabajo. Entró en vigencia el 26 de abril de
                2024, junto con el primer paso de la reducción gradual de la
                jornada laboral que trajo la Ley Nº21.561, y fija los
                requisitos obligatorios para cualquier sistema electrónico
                de registro y control de asistencia. Esto es lo que exige en
                la práctica y qué deberías tener resuelto como empleador.
            </p>

            <h2>¿A qué sistemas aplica?</h2>
            <p>
                La resolución solo rige para sistemas electrónicos de
                registro y control de asistencia. Si tu empresa usa un libro
                de asistencia en papel o un reloj control puramente
                mecánico, esta norma no te aplica (Art. 2º).
            </p>
            <p>
                Ojo con la letra chica: la definición de "sistema
                electrónico" es amplia. Si, por ejemplo, un empleador le
                pide a sus trabajadores que avisen por una app de
                mensajería que llegaron o se retiraron, esa misma aplicación
                queda sujeta a la resolución (Art. 3º letra n). Y los
                "sistemas de excepción" que solo miden ausentismo —en vez de
                asistencia, horas trabajadas y descansos— no cumplen la
                obligación que impone la ley.
            </p>

            <h2>Lo que el sistema tiene que garantizar en cada marca</h2>
            <ul>
                <li>
                    <strong>Un Hash o Checksum automático.</strong> Cada
                    marcación debe generar, sin intervención manual, un
                    código que permita validar después que el registro no
                    fue alterado (Art. 8º).
                </li>
                <li>
                    <strong>Transmisión automática a una base de datos
                        central</strong>, en línea, salvo la excepción
                    acotada para zonas sin conexión permanente, donde el
                    envío se completa automáticamente al recuperar señal
                    (Arts. 9º y 10º).
                </li>
                <li>
                    <strong>Un comprobante por cada marca</strong>, enviado
                    automáticamente al correo personal del trabajador, con
                    fecha, hora, su nombre y cédula, los datos del empleador
                    y el código Hash de la operación (Arts. 12º y 13º).
                </li>
            </ul>

            <h2>Acceso: quién puede ver qué</h2>
            <ul>
                <li>
                    El <strong>trabajador</strong> tiene acceso permanente e
                    irrestricto a su propio historial, con al menos 5 años
                    hacia atrás cuando su antigüedad lo permite (Art.
                    22.1º).
                </li>
                <li>
                    El <strong>empleador</strong> tiene un perfil de
                    administrador para revisar los datos y, dentro de
                    reglas acotadas, modificar marcaciones — siempre con
                    aviso automático al trabajador y 48 horas para que se
                    oponga antes de que el cambio se consolide (Arts. 22.2º
                    y 39º a 41º).
                </li>
                <li>
                    La <strong>Dirección del Trabajo</strong> tiene acceso
                    permanente, presencial o remoto, a través de un portal
                    de fiscalización disponible las 24 horas (Art. 17º y
                    22.4º).
                </li>
            </ul>

            <h2>Los reportes que la DT puede exigir</h2>
            <p>
                El Art. 27º obliga a que el portal de fiscalización ofrezca,
                como mínimo, estos informes con la estructura exacta que la
                norma detalla:
            </p>
            <ul>
                <li>Reporte de asistencia diaria.</li>
                <li>
                    Reporte de jornada diaria, con horas extra y tiempo
                    faltante calculados automáticamente.
                </li>
                <li>
                    Reporte de domingos y festivos trabajados, hasta 24
                    meses hacia atrás.
                </li>
                <li>Reporte de modificaciones y alteraciones de turnos.</li>
                <li>
                    Un registro de incidentes técnicos que hayan dejado el
                    sistema total o parcialmente fuera de servicio.
                </li>
                <li>
                    Un reporte diario de marcaciones de cada trabajador,
                    enviado automáticamente al empleador para tenerlo a mano
                    si el sistema falla justo durante una fiscalización.
                </li>
            </ul>

            <h2>Horas extra y descansos: compensación y vencimiento</h2>
            <p>
                Las horas extraordinarias se pagan con el recargo legal en la
                liquidación mensual, salvo que exista un pacto escrito para
                compensarlas con días de descanso adicionales (Art. 43º). Si
                se compensan con descanso, el sistema está obligado a avisar
                cada 30 días cuántas horas acumuladas tiene cada trabajador y{' '}
                <strong>la fecha en que esos días prescriben</strong> (Art.
                45.3º) — un detalle que se pierde fácil en una planilla y que
                puede significar que el trabajador pierda el descanso
                acumulado si nadie avisa a tiempo.
            </p>

            <h2>Qué pasa si no cumples</h2>
            <p>
                Cumplir no es opcional desde que la resolución entró en
                vigencia. Si durante una fiscalización se detecta que la
                plataforma usada no está autorizada por la Dirección del
                Trabajo, de todas formas se considerará la información que
                el sistema entregue, pero eso no exime de las sanciones que
                correspondan (Art. 49º).
            </p>

            <h2>Cómo Kolvi ayuda a cumplir</h2>
            <p>
                Kolvi está construido pensando en esta misma norma: cada
                marca genera un comprobante firmado con un número de folio
                (Resolución 38, Art. 13º), las alertas de horas extra siguen
                el límite legal vigente, y el panel de Dirección del Trabajo
                exporta, con la misma estructura que exige el Art. 27º, el
                reporte de asistencia, el de jornada diaria, el de domingos y
                festivos, el de modificaciones de turno y el registro de
                incidentes técnicos — sin armar una sola planilla a mano. Si
                quieres ver el resto de las funciones de cumplimiento que
                Kolvi automatiza, puedes revisar{' '}
                <Link href={`${home.url()}#funciones`}>
                    todas las funciones de Kolvi
                </Link>
                .
            </p>
        </BlogPostLayout>
    );
}
