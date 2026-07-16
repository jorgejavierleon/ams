<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\DocumentVar;
use App\Models\Premise;
use App\Models\User;
use Illuminate\Database\Seeder;

class DocumentVarSeeder extends Seeder
{
    /**
     * Global document placeholders an admin can drop into a template document
     * (e.g. an employment contract) and have resolved at render time. The list
     * is grounded in the fields the system already stores: the employee
     * ({@see User}), the employer company ({@see Company}),
     * the workplace ({@see Premise}), the position, and the tenant
     * organization. Labels are Spanish to match the Chilean labor-law domain;
     * keys follow the `{{snake_case}}` convention validated by the controller.
     *
     * @var list<array{0: string, 1: string, 2: string}> [key, name, description]
     */
    private const VARIABLES = [
        // --- Trabajador (employee) ---
        ['{{employee_name}}', 'Nombre del trabajador', 'Nombre completo del trabajador.'],
        ['{{employee_first_name}}', 'Nombres del trabajador', 'Nombres de pila del trabajador.'],
        ['{{employee_last_name}}', 'Apellido paterno', 'Apellido paterno del trabajador.'],
        ['{{employee_second_last_name}}', 'Apellido materno', 'Apellido materno del trabajador.'],
        ['{{employee_rut}}', 'RUT del trabajador', 'Rol Único Tributario del trabajador.'],
        ['{{employee_nationality}}', 'Nacionalidad', 'Nacionalidad del trabajador.'],
        ['{{employee_email}}', 'Correo del trabajador', 'Correo electrónico corporativo del trabajador.'],
        ['{{employee_personal_email}}', 'Correo personal del trabajador', 'Correo electrónico personal del trabajador.'],
        ['{{employee_phone}}', 'Teléfono del trabajador', 'Número de teléfono de contacto del trabajador.'],
        ['{{employee_position}}', 'Cargo del trabajador', 'Cargo o posición que ocupa el trabajador.'],
        ['{{emergency_contact_name}}', 'Contacto de emergencia', 'Nombre del contacto de emergencia del trabajador.'],
        ['{{emergency_contact_phone}}', 'Teléfono de emergencia', 'Teléfono del contacto de emergencia del trabajador.'],

        // --- Contrato (contract) ---
        ['{{contract_start_date}}', 'Fecha de inicio del contrato', 'Fecha en que se inician los servicios del trabajador.'],
        ['{{contract_end_date}}', 'Fecha de término del contrato', 'Fecha de término del contrato (contratos a plazo fijo).'],
        ['{{vacation_days}}', 'Días de vacaciones', 'Días de vacaciones legales anuales del trabajador.'],

        // --- Empresa empleadora (employer company) ---
        ['{{company_social_reason}}', 'Razón social de la empresa', 'Razón social de la empresa empleadora.'],
        ['{{company_rut}}', 'RUT de la empresa', 'Rol Único Tributario de la empresa empleadora.'],
        ['{{company_business_line}}', 'Giro de la empresa', 'Giro o actividad económica de la empresa.'],
        ['{{company_address}}', 'Dirección de la empresa', 'Dirección del domicilio de la empresa empleadora.'],
        ['{{company_email}}', 'Correo de la empresa', 'Correo electrónico de contacto de la empresa.'],
        ['{{company_phone}}', 'Teléfono de la empresa', 'Número de teléfono de la empresa empleadora.'],

        // --- Representante legal (legal representative) ---
        ['{{legal_rep_name}}', 'Representante legal', 'Nombre del representante legal de la empresa.'],
        ['{{legal_rep_rut}}', 'RUT del representante legal', 'RUT del representante legal de la empresa.'],

        // --- Lugar de trabajo (workplace / premise) ---
        ['{{premise_name}}', 'Establecimiento', 'Nombre del establecimiento o lugar de trabajo.'],
        ['{{premise_address}}', 'Dirección del lugar de trabajo', 'Dirección del lugar de prestación de servicios.'],
        ['{{premise_commune}}', 'Comuna del lugar de trabajo', 'Comuna del lugar de trabajo.'],
        ['{{premise_region}}', 'Región del lugar de trabajo', 'Región del lugar de trabajo.'],

        // --- Organización y documento (organization & document) ---
        ['{{organization_name}}', 'Organización', 'Nombre de la organización en el sistema.'],
        ['{{document_date}}', 'Fecha del documento', 'Fecha de emisión del documento (se resuelve al generarlo).'],
    ];

    public function run(): void
    {
        foreach (self::VARIABLES as [$key, $name, $description]) {
            DocumentVar::updateOrCreate(
                ['key' => $key],
                ['name' => $name, 'description' => $description],
            );
        }
    }
}
