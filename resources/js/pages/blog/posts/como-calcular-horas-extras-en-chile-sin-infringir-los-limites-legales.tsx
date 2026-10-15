import { Link } from '@inertiajs/react';
import BlogPostLayout from '@/components/blog/post-layout';
import { home } from '@/routes';
import type { BlogPostMeta } from '@/types/blog';

type Props = {
    post: BlogPostMeta;
};

export default function ComoCalcularHorasExtrasEnChileSinInfringirLosLimitesLegales({
    post,
}: Props) {
    return (
        <BlogPostLayout post={post}>
            <p>
                Pagar mal una hora extra —de más o de menos— es uno de los
                errores más caros y más comunes en las liquidaciones chilenas.
                Pasa casi siempre por lo mismo: nadie lleva el control de
                cuántas horas extraordinarias se han acumulado en la semana,
                o el pacto que las autoriza ya venció sin que nadie se diera
                cuenta. Esto es lo que dice el Código del Trabajo sobre cómo
                calcularlas y qué deberías tener resuelto para no infringir
                los topes legales.
            </p>

            <h2>Qué cuenta como hora extraordinaria</h2>
            <p>
                Es jornada extraordinaria toda la que excede el máximo legal
                o la jornada pactada en el contrato, si esta última es menor
                (Art. 30º). Solo pueden pactarse en faenas que, por su
                naturaleza, no perjudiquen la salud del trabajador, y la
                Inspección del Trabajo puede prohibirlas —de oficio o a
                petición de parte— en faenas que no cumplan esa exigencia
                (Art. 31º).
            </p>

            <h2>El tope diario: dos horas, no más</h2>
            <p>
                El límite es estricto: <strong>máximo 2 horas extraordinarias
                al día</strong> (Art. 31º). No es un promedio ni algo que se
                pueda compensar entre días: cada jornada se mide por
                separado. Como la jornada ordinaria diaria tiene su propio
                tope de 10 horas (Art. 28º), el día completo —ordinaria más
                extraordinaria— no puede superar las 12 horas.
            </p>

            <h2>El tope semanal: una jornada ordinaria que va bajando</h2>
            <p>
                La jornada ordinaria semanal no es un número fijo. La Ley
                Nº21.561 la reduce en tres etapas desde su publicación el 26
                de abril de 2023: a 44 horas al primer año, a 42 horas al
                tercer año y a 40 horas al quinto año. Hoy —desde el 26 de
                abril de 2026— la jornada ordinaria es de{' '}
                <strong>42 horas semanales</strong>, y bajará a 40 horas el 26
                de abril de 2028. Como el tope de horas extra es de 2 por día,
                el techo semanal de horas extraordinarias queda, en la
                práctica, en 12 horas (2 horas × 6 días).
            </p>
            <p>
                Un sistema que calcule las horas extra contra la jornada
                ordinaria vigente hace un año, sin actualizarse con cada
                etapa de la ley, está calculando mal el recargo desde ese
                momento.
            </p>

            <h2>El pacto de horas extraordinarias y su vencimiento a los tres meses</h2>
            <p>
                Las horas extraordinarias solo pueden pactarse para atender
                necesidades o situaciones temporales de la empresa. El pacto
                debe constar por escrito y{' '}
                <strong>
                    tiene una vigencia transitoria máxima de tres meses
                </strong>
                , renovable por acuerdo de las partes (Art. 32º). Dejar que
                un pacto venza sin renovarlo es más común de lo que parece,
                porque no hay ninguna alerta automática en una planilla que
                avise que un acuerdo está por caducar.
            </p>
            <p>
                Eso no significa que las horas dejen de ser extraordinarias:
                el mismo Art. 32º establece el criterio de la realidad —la
                falta de pacto escrito no impide que se consideren
                extraordinarias las horas trabajadas en exceso de la jornada
                pactada, con conocimiento del empleador. El pacto ordena y
                deja evidencia escrita del acuerdo, pero no es lo que decide
                si una hora trabajada se paga.
            </p>

            <h2>Cómo se pagan: el recargo del 50%</h2>
            <p>
                Las horas extraordinarias se pagan con un recargo del{' '}
                <strong>50% sobre el sueldo convenido</strong> para la
                jornada ordinaria, liquidado junto con la remuneración
                ordinaria del mismo período. Si no existe un sueldo convenido,
                o es inferior al ingreso mínimo mensual, el ingreso mínimo es
                la base de cálculo del recargo (Art. 32º).
            </p>
            <p>
                Como alternativa al pago, las partes pueden acordar por
                escrito compensar las horas extra con días adicionales de
                feriado: hasta 5 días hábiles al año, con el mismo recargo
                (1 hora extra = 1,5 horas de feriado), que el trabajador debe
                usar dentro de los 6 meses siguientes al ciclo en que se
                originaron, avisando con 48 horas de anticipación. Si no se
                solicitan a tiempo, corresponde pagarlos en la remuneración
                del período siguiente.
            </p>

            <h2>Qué pasa si te equivocas</h2>
            <p>
                Un error de cálculo no es solo un problema de planilla: la
                Resolución 38 exige que el sistema de registro de asistencia
                muestre una alerta en pantalla cuando se cargue una jornada
                que exceda los límites diarios o semanales de la ley (Art.
                45.2º), y que avise cada 30 días cuántas horas de descanso
                compensatorio tiene acumuladas cada trabajador y la fecha en
                que prescriben (Art. 45.3º). Ignorar esas alertas, o no
                tenerlas, deja a la empresa expuesta a que la Inspección del
                Trabajo detecte el exceso en una fiscalización y a reclamos
                por horas mal pagadas o días de descanso perdidos.
            </p>

            <h2>Cómo Kolvi ayuda a calcular bien</h2>
            <p>
                Kolvi calcula el tope legal diario y semanal contra la
                jornada ordinaria vigente en la fecha trabajada —no contra la
                de hoy— y nunca bloquea una hora efectivamente trabajada:
                si una aprobación excede un tope legal o no tiene un pacto
                vigente que la cubra, exige una justificación escrita antes
                de aprobarse, igual que el pago nunca se decide por la
                ausencia de pacto. Los pactos de horas extraordinarias se
                gestionan con su fecha de término y una alerta antes de que
                venzan, y el saldo de días de descanso compensatorio se
                notifica automáticamente antes de que prescriba. Si quieres
                ver el resto de las funciones de cumplimiento que Kolvi
                automatiza, puedes revisar{' '}
                <Link href={`${home.url()}#funciones`}>
                    todas las funciones de Kolvi
                </Link>
                .
            </p>
        </BlogPostLayout>
    );
}
