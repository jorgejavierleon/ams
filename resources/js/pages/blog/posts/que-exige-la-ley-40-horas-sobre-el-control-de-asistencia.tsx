import { Link } from '@inertiajs/react';
import BlogPostLayout from '@/components/blog/post-layout';
import { home } from '@/routes';
import type { BlogPostMeta } from '@/types/blog';

type Props = {
    post: BlogPostMeta;
};

export default function QueExigeLaLey40HorasSobreElControlDeAsistencia({
    post,
}: Props) {
    return (
        <BlogPostLayout post={post}>
            <p>
                Desde el 26 de abril de 2026 la jornada ordinaria en Chile
                bajó de 44 a 42 horas semanales, el segundo escalón de la
                reducción gradual que fija la Ley Nº21.561. Cada vez que
                cambia la jornada legal, conviene revisar algo que suele
                quedar sin mirar durante años: si el mecanismo con el que se
                controla la asistencia todavía cumple lo que exige la ley.
                No es un detalle administrativo —es una obligación con
                sanciones propias, y no cualquier mecanismo la satisface.
            </p>

            <h2>La obligación: un deber del empleador, con tres mecanismos posibles</h2>
            <p>
                El Código del Trabajo es explícito: el empleador tiene el
                deber de controlar la asistencia y determinar las horas de
                trabajo, ordinarias y extraordinarias, y{' '}
                <strong>
                    está obligado a llevar un libro de asistencia del
                    personal, un reloj control con tarjetas de registro o un
                    sistema electrónico de registro
                </strong>{' '}
                (Art. 33º, inciso 1º). La ley da a elegir entre los tres —no
                hay un mecanismo obligatorio por defecto—, pero exige que
                exista uno de ellos y que mida lo que tiene que medir: la
                Dirección del Trabajo ha precisado que no se entiende
                cumplido este deber cuando el empleador usa "sistemas de
                excepción" u otros mecanismos que buscan medir el ausentismo
                y no la asistencia, las horas trabajadas y los descansos.
                No tener ningún sistema, o tener uno que mide otra cosa, es
                en la práctica lo mismo: un incumplimiento del Art. 33º,
                sancionable conforme al Art. 508º del Código del Trabajo.
            </p>

            <h2>Qué es cada uno de los tres mecanismos</h2>
            <p>
                <strong>Libro de asistencia del personal</strong>: un
                registro físico, normalmente en papel, donde cada trabajador
                firma su entrada y salida. Es el mecanismo más antiguo y el
                más simple de implementar, pero también el que menos
                garantías ofrece sobre lo que firma.
            </p>
            <p>
                <strong>Reloj control con tarjetas de registro</strong>: un
                dispositivo mecánico o electromecánico que timbra una
                tarjeta física en cada marcación. Automatiza el registro de
                la hora, pero la tarjeta sigue siendo un soporte físico que
                se pierde, se daña o se puede timbrar por otra persona.
            </p>
            <p>
                <strong>Sistema electrónico de registro</strong>: una
                plataforma de software —propia o de un proveedor externo—
                que registra cada marcación electrónicamente. Es el único de
                los tres mecanismos que tiene una regulación propia y
                detallada: la Resolución Exenta Nº38 de la Dirección del
                Trabajo, dictada en virtud del inciso 2º del mismo Art. 33º.
            </p>

            <h2>Por qué el libro de papel es, en la práctica, el más débil</h2>
            <p>
                La propia Resolución 38 marca la diferencia: su normativa{' '}
                <strong>
                    solo resulta aplicable a las plataformas electrónicas de
                    registro y control de asistencia, y no es vinculante
                    para los mecanismos de control basados en papel, como un
                    libro, ni para los dispositivos mecánicos
                </strong>{' '}
                (Art. 2º). En otras palabras: todas las exigencias de
                integridad que la resolución impone a un sistema
                electrónico —sello de tiempo en cada marcación (Art. 11º),
                un comprobante con fecha, hora, identificación del
                trabajador y un código hash o checksum que permite detectar
                si se alteró (Art. 13º), y un portal de fiscalización con
                acceso permanente para la Dirección del Trabajo— no existen
                para un libro de papel ni para un reloj control. Una firma
                en un libro se puede completar después de los hechos, un día
                en blanco se puede llenar retroactivamente, y nada en el
                mecanismo mismo deja evidencia de que eso ocurrió. Cumple la
                ley en el papel, pero no ofrece nada que resista una
                fiscalización si la Inspección del Trabajo pone en duda las
                fechas.
            </p>

            <h2>El sistema electrónico es la única opción fiscalizada en tiempo real</h2>
            <p>
                Elegir un sistema electrónico no es elegir cualquier
                software: la Resolución 38 exige que la plataforma esté
                autorizada por la Dirección del Trabajo, y usar una no
                autorizada es en sí mismo una infracción al Art. 33º (Art.
                58º letra b). Lo que se gana a cambio es justamente lo que
                le falta al libro de papel: cada marcación queda con sello
                de tiempo y hash, los reportes quedan disponibles para una
                fiscalización sin depender de que alguien encuentre el libro
                correcto, y alterar la información del sistema es una
                infracción expresamente tipificada (Art. 58º letra e). La
                trazabilidad no es una característica opcional del sistema
                electrónico: es la razón por la que existe una resolución
                completa que lo regula y las otras dos opciones no la
                tienen.
            </p>

            <h2>Cómo Kolvi cumple como sistema electrónico de registro</h2>
            <p>
                El marcaje de asistencia de Kolvi es un sistema electrónico
                de registro en el sentido del Art. 33º: cada marca se
                verifica en el servidor y queda respaldada por un
                comprobante con la información que exige el Art. 13º de la
                Resolución 38 —fecha, hora, identificación del trabajador y
                del empleador, y un código hash que permite comprobar que no
                fue alterada— más un número de comprobante que el
                trabajador puede leer o informar sin ambigüedad. Si tu
                empresa todavía controla la asistencia con un libro o un
                reloj control, el cambio de jornada de este año es un buen
                momento para revisar si ese mecanismo sigue siendo el más
                conveniente. Puedes revisar{' '}
                <Link href={`${home.url()}#funciones`}>
                    todas las funciones de Kolvi
                </Link>{' '}
                para ver cómo funciona el registro electrónico en la
                práctica.
            </p>
        </BlogPostLayout>
    );
}
