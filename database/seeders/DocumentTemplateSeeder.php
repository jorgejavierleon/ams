<?php

namespace Database\Seeders;

use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class DocumentTemplateSeeder extends Seeder
{
    /**
     * Seed a starter library of reusable document templates for every tenant.
     *
     * The set is grounded in the documents Chilean HR teams draft most often
     * (contracts, annexes, certificates, warnings, termination notices, leave
     * requests, overtime pacts and finiquitos), and every body is written with
     * the `{{variable}}` placeholders defined by {@see DocumentVarSeeder} so it
     * resolves against the employee, employer company, workplace and tenant
     * once loaded into a document and published. Content is Spanish and follows
     * the Código del Trabajo, matching the Chilean labour-law domain.
     */
    public function run(): void
    {
        $templates = $this->templates();

        Organization::query()->each(function (Organization $organization) use ($templates): void {
            foreach ($templates as [$title, $type, $body]) {
                DocumentTemplate::updateOrCreate(
                    ['organization_id' => $organization->id, 'title' => $title],
                    ['type' => $type, 'body' => $body],
                );
            }
        });
    }

    /**
     * The template definitions shared by every organization.
     *
     * @return list<array{0: string, 1: DocumentType, 2: string}> [title, type, body]
     */
    private function templates(): array
    {
        return [
            [
                'Contrato de Trabajo Indefinido',
                DocumentType::Contracts,
                <<<'HTML'
                <h1>Contrato de Trabajo</h1>
                <p>En {{premise_commune}}, a {{document_date}}, entre <strong>{{company_social_reason}}</strong>, RUT {{company_rut}}, giro {{company_business_line}}, con domicilio en {{company_address}}, representada legalmente por don(ña) {{legal_rep_name}}, cédula de identidad N° {{legal_rep_rut}}, en adelante "el empleador"; y don(ña) <strong>{{employee_name}}</strong>, cédula de identidad N° {{employee_rut}}, de nacionalidad {{employee_nationality}}, con domicilio para estos efectos en {{premise_address}}, en adelante "el trabajador", se ha convenido el siguiente contrato de trabajo:</p>
                <p><strong>PRIMERO: Naturaleza de los servicios.</strong> El trabajador se obliga a desempeñar el cargo de {{employee_position}}, en el establecimiento {{premise_name}}, ubicado en {{premise_address}}, comuna de {{premise_commune}}, región de {{premise_region}}, sin perjuicio de la facultad del empleador de alterar la naturaleza de los servicios en los términos del artículo 12 del Código del Trabajo.</p>
                <p><strong>SEGUNDO: Jornada de trabajo.</strong> La jornada ordinaria será la máxima legal, distribuida de lunes a viernes, respetando los descansos que establece la ley.</p>
                <p><strong>TERCERO: Remuneración.</strong> El empleador pagará al trabajador una remuneración mensual, la que se cancelará por mes vencido, con las deducciones legales que correspondan.</p>
                <p><strong>CUARTO: Duración.</strong> El presente contrato es de carácter indefinido y comenzará a regir a contar del {{contract_start_date}}.</p>
                <p><strong>QUINTO: Feriado legal.</strong> El trabajador tendrá derecho a {{vacation_days}} días hábiles de feriado anual, conforme a los artículos 67 y siguientes del Código del Trabajo.</p>
                <p>Se firma en dos ejemplares, quedando uno en poder de cada parte.</p>
                <p>_______________________________<br>{{legal_rep_name}}<br>p. {{company_social_reason}}</p>
                <p>_______________________________<br>{{employee_name}}<br>RUT {{employee_rut}}</p>
                HTML,
            ],
            [
                'Contrato de Trabajo a Plazo Fijo',
                DocumentType::Contracts,
                <<<'HTML'
                <h1>Contrato de Trabajo a Plazo Fijo</h1>
                <p>En {{premise_commune}}, a {{document_date}}, entre <strong>{{company_social_reason}}</strong>, RUT {{company_rut}}, giro {{company_business_line}}, con domicilio en {{company_address}}, representada legalmente por don(ña) {{legal_rep_name}}, en adelante "el empleador"; y don(ña) <strong>{{employee_name}}</strong>, cédula de identidad N° {{employee_rut}}, de nacionalidad {{employee_nationality}}, en adelante "el trabajador", se conviene lo siguiente:</p>
                <p><strong>PRIMERO.</strong> El trabajador prestará servicios como {{employee_position}} en {{premise_name}}, ubicado en {{premise_address}}, comuna de {{premise_commune}}.</p>
                <p><strong>SEGUNDO: Duración.</strong> El presente contrato tendrá una vigencia determinada, comenzando el {{contract_start_date}} y terminando el {{contract_end_date}}, fecha en que las partes lo darán por concluido sin necesidad de aviso previo.</p>
                <p><strong>TERCERO: Remuneración.</strong> El trabajador percibirá una remuneración mensual, pagadera por mes vencido, con las deducciones legales pertinentes.</p>
                <p><strong>CUARTO.</strong> En todo lo no previsto se estará a las disposiciones del Código del Trabajo.</p>
                <p>_______________________________<br>{{legal_rep_name}}<br>p. {{company_social_reason}}</p>
                <p>_______________________________<br>{{employee_name}}<br>RUT {{employee_rut}}</p>
                HTML,
            ],
            [
                'Contrato de Trabajo Jornada Parcial (Part-Time)',
                DocumentType::Contracts,
                <<<'HTML'
                <h1>Contrato de Trabajo de Jornada Parcial</h1>
                <p>En {{premise_commune}}, a {{document_date}}, entre <strong>{{company_social_reason}}</strong>, RUT {{company_rut}}, representada por don(ña) {{legal_rep_name}}, y don(ña) <strong>{{employee_name}}</strong>, RUT {{employee_rut}}, se acuerda el siguiente contrato de trabajo de jornada parcial, regido por los artículos 40 bis y siguientes del Código del Trabajo:</p>
                <p><strong>PRIMERO.</strong> El trabajador desempeñará el cargo de {{employee_position}} en {{premise_name}}, {{premise_address}}, comuna de {{premise_commune}}.</p>
                <p><strong>SEGUNDO: Jornada.</strong> Se pacta una jornada parcial que no excederá de dos tercios de la jornada ordinaria máxima legal, según la distribución horaria informada al trabajador.</p>
                <p><strong>TERCERO: Remuneración.</strong> La remuneración será proporcional a la jornada pactada y se pagará por mes vencido.</p>
                <p><strong>CUARTO: Vigencia.</strong> El contrato regirá a contar del {{contract_start_date}}.</p>
                <p>_______________________________<br>{{legal_rep_name}}</p>
                <p>_______________________________<br>{{employee_name}}</p>
                HTML,
            ],
            [
                'Anexo de Contrato - Modificación de Remuneración',
                DocumentType::Annexes,
                <<<'HTML'
                <h1>Anexo de Contrato de Trabajo</h1>
                <h2>Modificación de Remuneración</h2>
                <p>En {{premise_commune}}, a {{document_date}}, entre <strong>{{company_social_reason}}</strong>, RUT {{company_rut}}, representada por don(ña) {{legal_rep_name}}, y don(ña) <strong>{{employee_name}}</strong>, cédula de identidad N° {{employee_rut}}, quien se desempeña como {{employee_position}}, se acuerda modificar el contrato de trabajo suscrito con fecha {{contract_start_date}}, en los siguientes términos:</p>
                <p><strong>PRIMERO.</strong> A contar de la fecha del presente anexo, las partes acuerdan modificar la cláusula de remuneración del contrato de trabajo, según los nuevos valores convenidos entre ellas.</p>
                <p><strong>SEGUNDO.</strong> En todo lo no modificado por el presente instrumento, se mantienen plenamente vigentes las demás cláusulas del contrato de trabajo original.</p>
                <p>Se firma en dos ejemplares del mismo tenor.</p>
                <p>_______________________________<br>{{legal_rep_name}}<br>p. {{company_social_reason}}</p>
                <p>_______________________________<br>{{employee_name}}<br>RUT {{employee_rut}}</p>
                HTML,
            ],
            [
                'Anexo de Contrato - Cambio de Cargo',
                DocumentType::Annexes,
                <<<'HTML'
                <h1>Anexo de Contrato de Trabajo</h1>
                <h2>Cambio de Cargo y Funciones</h2>
                <p>En {{premise_commune}}, a {{document_date}}, {{company_social_reason}}, RUT {{company_rut}}, representada por don(ña) {{legal_rep_name}}, y don(ña) <strong>{{employee_name}}</strong>, RUT {{employee_rut}}, vienen en modificar el contrato de trabajo vigente entre las partes en lo siguiente:</p>
                <p><strong>PRIMERO.</strong> A partir de esta fecha, el trabajador pasará a desempeñar el cargo de <strong>{{employee_position}}</strong>, asumiendo las funciones propias de dicho cargo en el establecimiento {{premise_name}}.</p>
                <p><strong>SEGUNDO.</strong> Las demás estipulaciones del contrato de trabajo permanecen sin alteración y plenamente vigentes.</p>
                <p>_______________________________<br>{{legal_rep_name}}</p>
                <p>_______________________________<br>{{employee_name}}</p>
                HTML,
            ],
            [
                'Certificado de Antigüedad Laboral',
                DocumentType::Certificates,
                <<<'HTML'
                <h1>Certificado de Antigüedad Laboral</h1>
                <p>{{company_social_reason}}, RUT {{company_rut}}, con domicilio en {{company_address}}, por intermedio de su representante legal don(ña) {{legal_rep_name}}, certifica que:</p>
                <p>Don(ña) <strong>{{employee_name}}</strong>, cédula de identidad N° {{employee_rut}}, presta servicios para esta empresa desempeñando el cargo de {{employee_position}}, desde el {{contract_start_date}} a la fecha, con contrato de trabajo vigente.</p>
                <p>Se extiende el presente certificado a solicitud del interesado, para los fines que estime convenientes, en {{premise_commune}}, a {{document_date}}.</p>
                <p>_______________________________<br>{{legal_rep_name}}<br>Representante Legal<br>{{company_social_reason}}<br>{{company_email}} — {{company_phone}}</p>
                HTML,
            ],
            [
                'Certificado de Feriado Legal (Vacaciones)',
                DocumentType::Certificates,
                <<<'HTML'
                <h1>Certificado de Feriado Legal</h1>
                <p>{{company_social_reason}}, RUT {{company_rut}}, certifica que don(ña) <strong>{{employee_name}}</strong>, cédula de identidad N° {{employee_rut}}, quien se desempeña como {{employee_position}}, tiene derecho a <strong>{{vacation_days}} días hábiles</strong> de feriado legal anual, conforme a los artículos 67 y siguientes del Código del Trabajo.</p>
                <p>Se deja constancia de la situación de feriado del trabajador a la fecha de emisión del presente documento.</p>
                <p>Se extiende el presente certificado en {{premise_commune}}, a {{document_date}}.</p>
                <p>_______________________________<br>{{legal_rep_name}}<br>p. {{company_social_reason}}</p>
                HTML,
            ],
            [
                'Carta de Amonestación',
                DocumentType::Notifications,
                <<<'HTML'
                <h1>Carta de Amonestación</h1>
                <p>{{premise_commune}}, {{document_date}}</p>
                <p>Señor(a)<br><strong>{{employee_name}}</strong><br>RUT {{employee_rut}}<br>Cargo: {{employee_position}}</p>
                <p>Estimado(a) {{employee_first_name}}:</p>
                <p>Por medio de la presente, {{company_social_reason}} le comunica formalmente una <strong>amonestación por escrito</strong>, en atención a la conducta observada que constituye un incumplimiento de las obligaciones establecidas en su contrato de trabajo y en el Reglamento Interno de Orden, Higiene y Seguridad de la empresa.</p>
                <p>Le solicitamos rectificar la situación descrita, teniendo presente que la reiteración de este tipo de conductas podrá dar lugar a las medidas que la ley franquea al empleador.</p>
                <p>Copia de la presente carta se remite a su carpeta personal y, cuando corresponda, a la Inspección del Trabajo respectiva.</p>
                <p>Sin otro particular, saluda atentamente,</p>
                <p>_______________________________<br>{{legal_rep_name}}<br>p. {{company_social_reason}}</p>
                <p>Tomé conocimiento: _______________________________<br>{{employee_name}}</p>
                HTML,
            ],
            [
                'Carta de Aviso de Término de Contrato (Art. 161)',
                DocumentType::Notifications,
                <<<'HTML'
                <h1>Aviso de Término de Contrato de Trabajo</h1>
                <p>{{premise_commune}}, {{document_date}}</p>
                <p>Señor(a)<br><strong>{{employee_name}}</strong><br>RUT {{employee_rut}}<br>Presente</p>
                <p>De nuestra consideración:</p>
                <p>Comunicamos a usted que {{company_social_reason}}, RUT {{company_rut}}, ha resuelto poner término a su contrato de trabajo, en el cual usted se desempeña como {{employee_position}}, en virtud de la causal establecida en el <strong>artículo 161 inciso primero del Código del Trabajo</strong> ("necesidades de la empresa, establecimiento o servicio").</p>
                <p>Su contrato terminará en la fecha que indica esta comunicación. Se pagarán las indemnizaciones y prestaciones que legalmente correspondan, encontrándose el respectivo finiquito a su disposición.</p>
                <p>Se deja constancia del estado de las cotizaciones previsionales conforme al artículo 162 del Código del Trabajo. Copia de esta carta se remite a la Inspección del Trabajo.</p>
                <p>Saluda atentamente,</p>
                <p>_______________________________<br>{{legal_rep_name}}<br>p. {{company_social_reason}}<br>{{company_address}}</p>
                HTML,
            ],
            [
                'Aviso de Vencimiento de Plazo del Contrato',
                DocumentType::Notifications,
                <<<'HTML'
                <h1>Aviso de Vencimiento de Plazo</h1>
                <p>{{premise_commune}}, {{document_date}}</p>
                <p>Señor(a) <strong>{{employee_name}}</strong>, RUT {{employee_rut}}:</p>
                <p>Comunicamos a usted que su contrato de trabajo a plazo fijo, suscrito para desempeñar el cargo de {{employee_position}}, con vigencia hasta el <strong>{{contract_end_date}}</strong>, llegará a su término en dicha fecha por vencimiento del plazo convenido, causal establecida en el artículo 159 N° 4 del Código del Trabajo.</p>
                <p>El finiquito y las prestaciones que correspondan se encontrarán a su disposición conforme a la ley.</p>
                <p>Atentamente,</p>
                <p>_______________________________<br>{{legal_rep_name}}<br>p. {{company_social_reason}}</p>
                HTML,
            ],
            [
                'Solicitud de Feriado Legal (Vacaciones)',
                DocumentType::Requests,
                <<<'HTML'
                <h1>Solicitud de Feriado Legal</h1>
                <p>{{premise_commune}}, {{document_date}}</p>
                <p>Señores<br>{{company_social_reason}}<br>Presente</p>
                <p>Yo, <strong>{{employee_name}}</strong>, cédula de identidad N° {{employee_rut}}, quien se desempeña como {{employee_position}} en {{premise_name}}, vengo en solicitar hacer uso de mi feriado legal correspondiente, del cual dispongo de {{vacation_days}} días hábiles anuales.</p>
                <p>Solicito su autorización para el período que se indique de común acuerdo, comprometiéndome a dejar mis funciones debidamente coordinadas antes de mi ausencia.</p>
                <p>Agradeciendo su buena disposición, saluda atentamente,</p>
                <p>_______________________________<br>{{employee_name}}<br>RUT {{employee_rut}}<br>{{employee_email}}</p>
                <p><em>Autorizado por: _______________________________ &nbsp; Fecha: __________</em></p>
                HTML,
            ],
            [
                'Pacto de Horas Extraordinarias',
                DocumentType::Pacts,
                <<<'HTML'
                <h1>Pacto de Horas Extraordinarias</h1>
                <p>En {{premise_commune}}, a {{document_date}}, entre <strong>{{company_social_reason}}</strong>, RUT {{company_rut}}, representada por don(ña) {{legal_rep_name}}, y don(ña) <strong>{{employee_name}}</strong>, RUT {{employee_rut}}, cargo {{employee_position}}, se acuerda el siguiente pacto de horas extraordinarias, conforme a los artículos 30 a 32 del Código del Trabajo:</p>
                <p><strong>PRIMERO.</strong> Las partes convienen la posibilidad de realizar horas extraordinarias para atender necesidades o situaciones temporales de la empresa, las que no podrán exceder de dos por día.</p>
                <p><strong>SEGUNDO.</strong> Las horas extraordinarias se pagarán con el recargo legal del 50% sobre el sueldo convenido para la jornada ordinaria y se liquidarán conjuntamente con la remuneración del respectivo período.</p>
                <p><strong>TERCERO.</strong> El presente pacto tiene una vigencia máxima de tres meses, pudiendo renovarse por acuerdo de las partes.</p>
                <p>_______________________________<br>{{legal_rep_name}}</p>
                <p>_______________________________<br>{{employee_name}}</p>
                HTML,
            ],
            [
                'Finiquito de Contrato de Trabajo',
                DocumentType::Others,
                <<<'HTML'
                <h1>Finiquito de Contrato de Trabajo</h1>
                <p>En {{premise_commune}}, a {{document_date}}, entre <strong>{{company_social_reason}}</strong>, RUT {{company_rut}}, con domicilio en {{company_address}}, representada por don(ña) {{legal_rep_name}}, en adelante "el empleador", y don(ña) <strong>{{employee_name}}</strong>, cédula de identidad N° {{employee_rut}}, en adelante "el trabajador", quienes dejan constancia de lo siguiente:</p>
                <p><strong>PRIMERO.</strong> El trabajador prestó servicios como {{employee_position}} desde el {{contract_start_date}} hasta la fecha de término de la relación laboral.</p>
                <p><strong>SEGUNDO.</strong> El empleador paga en este acto al trabajador las sumas que corresponden por concepto de remuneraciones pendientes, feriado proporcional y demás prestaciones legales que procedan.</p>
                <p><strong>TERCERO.</strong> El trabajador declara recibir conforme el pago señalado y otorga al empleador el más amplio y total finiquito, declarando no tener cargo ni cobro alguno que formular por concepto de la relación laboral que las une.</p>
                <p>Para constancia firman, ratificándose ante ministro de fe conforme al artículo 177 del Código del Trabajo.</p>
                <p>_______________________________<br>{{legal_rep_name}}<br>p. {{company_social_reason}}</p>
                <p>_______________________________<br>{{employee_name}}<br>RUT {{employee_rut}}</p>
                HTML,
            ],
            [
                'Reglamento Interno de Orden, Higiene y Seguridad (Extracto)',
                DocumentType::Regulations,
                <<<'HTML'
                <h1>Reglamento Interno de Orden, Higiene y Seguridad</h1>
                <p>{{company_social_reason}}, RUT {{company_rut}}, giro {{company_business_line}}, con domicilio en {{company_address}}, dicta el presente Reglamento Interno de Orden, Higiene y Seguridad, de conformidad con el Título III del Libro I del Código del Trabajo y la Ley N° 16.744.</p>
                <h2>Título I: Normas de Orden</h2>
                <ol>
                <li>El presente reglamento regula las condiciones, obligaciones y prohibiciones a que deben sujetarse los trabajadores de {{organization_name}} en relación con sus labores, permanencia y vida en el establecimiento {{premise_name}}.</li>
                <li>El trabajador deberá cumplir la jornada de trabajo convenida y registrar diariamente su asistencia según los medios de control dispuestos por la empresa.</li>
                <li>El trabajador deberá desempeñar sus funciones con la diligencia y cuidado debidos, cuidando los bienes e instalaciones de la empresa.</li>
                </ol>
                <h2>Título II: Normas de Higiene y Seguridad</h2>
                <ol>
                <li>Todo trabajador deberá dar cumplimiento a las instrucciones de prevención de riesgos y usar los elementos de protección personal que la empresa le proporcione.</li>
                <li>Frente a cualquier accidente del trabajo o enfermedad profesional, el trabajador deberá dar aviso inmediato a su jefatura directa.</li>
                <li>La empresa mantendrá información de contacto de emergencia del trabajador, quien registra como contacto a {{emergency_contact_name}}, teléfono {{emergency_contact_phone}}.</li>
                </ol>
                <p>El presente extracto se entrega al trabajador en {{premise_commune}}, a {{document_date}}. Un ejemplar del reglamento completo se encuentra a disposición en dependencias de la empresa.</p>
                HTML,
            ],
        ];
    }
}
