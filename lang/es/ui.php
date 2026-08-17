<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Líneas de idioma de la interfaz (UI)
    |--------------------------------------------------------------------------
    |
    | Cadenas visibles para el usuario en el frontend de React. Este archivo es
    | la única fuente de verdad: se expone al frontend mediante las props
    | compartidas de Inertia (ver HandleInertiaRequests) y se consume con el
    | helper t(). Agregue aquí toda cadena nueva y su equivalente en lang/en/ui.php.
    |
    */

    'nav' => [
        'organization' => 'Organización',
        'workdays' => 'Jornadas',
        'approvals' => 'Aprobaciones',
        'documents' => 'Documentos',
        'documents_list' => 'Documentos',
        'document_templates' => 'Plantillas',
        'settings' => 'Configuración',
        'dashboard' => 'Panel',
        'roles' => 'Roles',
        'organization_settings' => 'Configuración general',
        'positions' => 'Cargos',
        'company' => 'Empresa',
        'cost_centers' => 'Centros de costo',
        'premises' => 'Sucursales',
        'shifts' => 'Turnos',
        'workdays_list' => 'Jornadas',
        'employees' => 'Empleados',
        'holidays' => 'Feriados',
        'leaves' => 'Permisos',
        'leaves_calendar' => 'Calendario de permisos',
        'my_leaves' => 'Mis permisos',
        'my_workdays' => 'Mis jornadas',
        'my_documents' => 'Mis documentos',
        'team_leaves' => 'Permisos del equipo',
        'overtime' => 'Horas extra',
        'overtime_queue' => 'Cola de horas extra',
    ],

    'user_menu' => [
        'settings' => 'Configuración',
        'logout' => 'Cerrar sesión',
    ],

    'common' => [
        'save' => 'Guardar',
        'cancel' => 'Cancelar',
        'search' => 'Buscar...',
        'no_results' => 'No se encontraron resultados.',
        'yes' => 'Sí',
        'no' => 'No',

        'data_table' => [
            'empty' => 'Sin resultados.',
            'toggle_columns' => 'Columnas',
            'selected' => ':count de :total seleccionados',
            'pagination' => [
                'showing' => 'Mostrando :from–:to de :total',
                'none' => 'Sin resultados',
                'previous' => 'Anterior',
                'next' => 'Siguiente',
            ],
        ],
    ],

    'dashboard' => [
        'title' => 'Panel',
    ],

    'marks' => [
        'title' => 'Marca de asistencia',
        'subtitle' => 'Registra tu entrada y tu salida del trabajo.',
        'greeting' => 'Hola, :name',
        'no_shift' => 'No tienes un turno asignado para hoy.',
        'no_shift_chip' => 'Sin turno hoy',
        'shift_for_today' => 'Turno de hoy: :start a :end',
        'check_in' => 'Marcar entrada',
        'check_out' => 'Marcar salida',
        'complete_cta' => 'Jornada completa',
        'worked' => 'Trabajado',
        'in_progress' => 'en curso',
        'current_time' => 'Hora actual',
        'in_marked' => 'Entrada marcada',
        'in_pending' => 'Entrada pendiente',
        'out_marked' => 'Salida marcada',
        'out_pending' => 'Salida pendiente',
        'marked_at' => 'Marcada a las :time',
        'types' => [
            'in' => 'Entrada',
            'out' => 'Salida',
        ],
        'status' => [
            'idle' => 'Aún no registras tu entrada',
            'working' => 'Trabajando · :elapsed',
            'complete' => 'Jornada registrada',
        ],
        'note' => [
            'idle' => 'La hora se registra automáticamente al confirmar.',
            'working' => 'Al marcar salida se cierra tu jornada de hoy.',
            'complete' => 'Mañana podrás marcar tu próxima entrada.',
        ],
        'confirm' => [
            'check_in_title' => 'Confirmar entrada',
            'check_out_title' => 'Confirmar salida',
            'description' => 'Se registrará tu marca con la hora actual. Esta acción no se puede deshacer.',
            'action' => 'Confirmar',
        ],
        'flash' => [
            'registered' => 'Marca registrada correctamente.',
            'already_marked' => 'Ya registraste esta marca hoy.',
        ],
        // Refusals the mobile app renders as a state, not as an error.
        'api' => [
            'already_marked' => [
                'in' => 'Ya registraste tu entrada de hoy.',
                'out' => 'Ya registraste tu salida de hoy.',
            ],
            // A punch that waited in the phone's queue. Shown verbatim by the
            // app, so each one has to say what happened to the marca and what
            // the employee does next.
            'offline' => [
                'too_old' => 'Esta marca esperó más de :hours horas sin conexión, así que no podemos agregarla directamente al libro de asistencia. La enviamos a tu jefatura como marca faltante: recibirás un correo para revisarla y tienes :review_hours horas para responder.',
                'in_future' => 'La fecha y hora de tu teléfono está adelantada respecto del servidor, así que no podemos registrar esta marca. Ajusta la fecha y hora automáticas en tu dispositivo y vuelve a intentarlo.',
                'modification_notes' => 'Marca capturada sin conexión el :captured y sincronizada el :synced, fuera del plazo de envío automático.',
            ],
        ],
    ],

    'settings' => [
        'title' => 'Configuración',
        'description' => 'Administre su perfil y la configuración de su cuenta',

        'nav' => [
            'profile' => 'Perfil',
            'security' => 'Seguridad',
            'appearance' => 'Apariencia',
        ],

        'profile' => [
            'head' => 'Configuración de perfil',
            'title' => 'Perfil',
            'description' => 'Actualice su nombre, correo electrónico y avatar',
            'change_avatar' => 'Cambiar avatar',
            'avatar_hint' => 'JPG, PNG o GIF. Máximo 2 MB.',
            'name' => 'Nombre',
            'name_placeholder' => 'Nombre completo',
            'email' => 'Correo electrónico',
            'email_placeholder' => 'Correo electrónico',
            'unverified' => 'Su correo electrónico no está verificado.',
            'resend' => 'Haga clic aquí para reenviar el correo de verificación.',
            'verification_sent' => 'Se ha enviado un nuevo enlace de verificación a su correo electrónico.',
        ],

        'security' => [
            'head' => 'Configuración de seguridad',
            'title' => 'Actualizar contraseña',
            'description' => 'Asegúrese de que su cuenta use una contraseña larga y aleatoria para mantenerse segura',
            'current_password' => 'Contraseña actual',
            'new_password' => 'Nueva contraseña',
            'confirm_password' => 'Confirmar contraseña',
        ],

        'appearance' => [
            'head' => 'Configuración de apariencia',
            'title' => 'Configuración de apariencia',
            'description' => 'Actualice la configuración de apariencia de su cuenta',
            'light' => 'Claro',
            'dark' => 'Oscuro',
            'system' => 'Sistema',
        ],

        'delete' => [
            'title' => 'Eliminar cuenta',
            'description' => 'Elimine su cuenta y todos sus recursos',
            'warning' => 'Advertencia',
            'warning_body' => 'Proceda con precaución, esto no se puede deshacer.',
            'button' => 'Eliminar cuenta',
            'confirm_title' => '¿Está seguro de que desea eliminar su cuenta?',
            'confirm_description' => 'Una vez eliminada su cuenta, todos sus recursos y datos se eliminarán de forma permanente. Ingrese su contraseña para confirmar que desea eliminar su cuenta de forma permanente.',
            'password' => 'Contraseña',
        ],
    ],

    'language' => [
        'label' => 'Idioma',
        'es' => 'Español',
        'en' => 'Inglés',
    ],

    'organizations' => [
        'nav' => 'Organizaciones',
        'title' => 'Organizaciones',
        'description' => 'Administre las organizaciones inquilinas',
        'new' => 'Nueva organización',
        'search_placeholder' => 'Buscar por nombre...',
        'empty' => 'No se encontraron organizaciones.',

        'columns' => [
            'name' => 'Nombre',
            'slug' => 'Slug',
            'plan' => 'Plan',
            'users' => 'Usuarios',
            'created' => 'Creada',
        ],

        'actions' => [
            'edit' => 'Editar',
            'delete' => 'Eliminar',
        ],

        'pagination' => [
            'showing' => 'Mostrando :from–:to de :total',
            'none' => 'Sin resultados',
            'previous' => 'Anterior',
            'next' => 'Siguiente',
        ],

        'form' => [
            'name' => 'Nombre',
            'slug' => 'Slug',
            'plan' => 'Plan',
            'plan_placeholder' => 'Seleccione un plan',
        ],

        'create' => [
            'title' => 'Nueva organización',
            'description' => 'Incorpore una nueva organización inquilina',
            'submit' => 'Crear organización',
        ],

        'edit' => [
            'title' => 'Editar organización',
            'submit' => 'Guardar cambios',
        ],

        'delete_dialog' => [
            'title' => 'Eliminar organización',
            'description' => '¿Está seguro de que desea eliminar :name? Esta acción no se puede deshacer.',
            'confirm' => 'Eliminar',
        ],

        'flash' => [
            'created' => 'Organización creada.',
            'updated' => 'Organización actualizada.',
            'archived' => 'Organización archivada.',
            'deleted' => 'Organización eliminada.',
        ],
    ],

    'document_variables' => [
        'nav' => 'Variables de documento',
        'title' => 'Variables de documento',
        'description' => 'Marcadores globales que se resuelven al generar documentos',
        'new' => 'Nueva variable',
        'search_placeholder' => 'Buscar por nombre o clave...',
        'empty' => 'No se encontraron variables de documento.',

        'columns' => [
            'name' => 'Nombre',
            'key' => 'Clave',
            'description' => 'Descripción',
            'created' => 'Creada',
        ],

        'actions' => [
            'edit' => 'Editar',
            'delete' => 'Eliminar',
        ],

        'pagination' => [
            'showing' => 'Mostrando :from–:to de :total',
            'none' => 'Sin resultados',
            'previous' => 'Anterior',
            'next' => 'Siguiente',
        ],

        'form' => [
            'name' => 'Nombre',
            'key' => 'Clave',
            'key_hint' => 'Use el formato {{snake_case}}, por ejemplo {{employee_name}}.',
            'description' => 'Descripción',
        ],

        'validation' => [
            'key_format' => 'La clave debe seguir el formato {{snake_case}}, por ejemplo {{employee_name}}.',
        ],

        'create' => [
            'title' => 'Nueva variable de documento',
            'description' => 'Defina un nuevo marcador global',
            'submit' => 'Crear variable',
        ],

        'edit' => [
            'title' => 'Editar variable de documento',
            'submit' => 'Guardar cambios',
        ],

        'delete_dialog' => [
            'title' => 'Eliminar variable de documento',
            'description' => '¿Está seguro de que desea eliminar :name? Esta acción no se puede deshacer.',
            'confirm' => 'Eliminar',
        ],

        'flash' => [
            'created' => 'Variable de documento creada.',
            'updated' => 'Variable de documento actualizada.',
            'deleted' => 'Variable de documento eliminada.',
        ],
    ],

    'organization_settings' => [
        'title' => 'Configuración general',
        'description' => 'Configure las notificaciones, los documentos y la política de horas extra de su organización',
        'sections' => [
            'notifications' => 'Notificaciones',
            'documents' => 'Documentos',
            'overtime' => 'Horas extra',
        ],
        'fields' => [
            'employee_missing_in_notification' => [
                'label' => 'Entrada faltante (trabajador)',
                'hint' => 'Enviar un correo al trabajador cuando falte su marca de entrada del día.',
            ],
            'employee_missing_out_notification' => [
                'label' => 'Salida faltante (trabajador)',
                'hint' => 'Enviar un correo al trabajador cuando falte su marca de salida del día.',
            ],
            'employer_missing_in_notification' => [
                'label' => 'Entrada faltante (empleador)',
                'hint' => 'Notificar a la organización cuando un trabajador no tenga marca de entrada del día.',
            ],
            'employer_missing_out_notification' => [
                'label' => 'Salida faltante (empleador)',
                'hint' => 'Notificar a la organización cuando un trabajador no tenga marca de salida del día.',
            ],
            'leave_approval_notification' => [
                'label' => 'Aprobación de permiso',
                'hint' => 'Enviar un correo al trabajador cuando se apruebe su solicitud de permiso.',
            ],
            'documents_signature_enabled' => [
                'label' => 'Habilitar firma de documentos',
                'hint' => 'Permitir enviar contratos, anexos y pactos a firma electrónica.',
            ],
            'documents_require_ordered_signing' => [
                'label' => 'Exigir firma ordenada por defecto',
                'hint' => 'Los nuevos documentos firmables usan firma secuencial (ordenada) por defecto.',
            ],
            'overtime_authorization_mode' => [
                'label' => 'Modo de autorización',
                'hint' => 'Cómo se autorizan las horas extra: solicitud previa del trabajador, revisión posterior del exceso de jornada, o ambas.',
            ],
            'overtime_weekly_anomaly_threshold_hours' => [
                'label' => 'Umbral semanal de anomalía (horas)',
                'hint' => 'Sobre esta cantidad de horas extra en la semana se marca una anomalía para revisión. No bloquea el registro.',
            ],
            'overtime_retroactive_request_days' => [
                'label' => 'Días hacia atrás para solicitar',
                'hint' => 'Cuántos días hacia atrás puede el trabajador solicitar horas extra en el modo de autorización previa.',
            ],
            'overtime_counts_pre_shift_excess' => [
                'label' => 'Contar la llegada anticipada como hora extra',
                'hint' => 'El tiempo trabajado antes del inicio del turno suma a las horas extra calculadas. Desactivado por defecto: las horas de exceso requieren conocimiento del empleador. En ambos casos queda registrado.',
            ],
        ],
        'overtime_authorization_modes' => [
            'pre_authorization' => 'Autorización previa',
            'post_hoc' => 'Revisión posterior',
            'combined' => 'Combinado',
        ],
        'flash' => [
            'updated' => 'Configuración guardada.',
        ],
    ],

    'roles' => [
        'title' => 'Roles',
        'description' => 'Gestione los roles y sus permisos',
        'search_placeholder' => 'Buscar por nombre...',
        'empty' => 'No se encontraron roles.',

        'columns' => [
            'role' => 'Rol',
            'permissions' => 'Permisos',
        ],

        'actions' => [
            'manage' => 'Gestionar permisos',
        ],

        'detail_description' => 'Active o desactive los permisos de este rol',
        'save' => 'Guardar permisos',
        'saving' => 'Guardando…',

        'names' => [
            'admin' => 'Administrador',
            'employee' => 'Empleado',
            'supervisor' => 'Supervisor',
            'dt' => 'Dirección del Trabajo',
            'saas' => 'SaaS',
        ],

        'groups' => [
            'Mark' => 'Asistencia',
            'Workday' => 'Jornada',
            'Leave' => 'Permisos y ausencias',
            'Document' => 'Documentos',
            'MarkModification' => 'Correcciones de marca',
            'OvertimeAuthorization' => 'Horas extraordinarias',
        ],

        'permissions' => [
            'RequestOwn:Leave' => 'Solicitar permisos propios',
            'ViewOwn:Leave' => 'Ver permisos propios',
            'CancelOwn:Leave' => 'Cancelar permisos propios',
            'ClockOwn:Mark' => 'Marcar asistencia propia',
            'ViewOwn:Mark' => 'Ver marcas propias',
            'ViewOwn:Workday' => 'Ver jornada propia',
            'ViewTeam:Workday' => 'Ver jornada del equipo',
            'ApproveTeam:Workday' => 'Actuar sobre jornada y horas extra del equipo',
            'ReviewOwn:MarkModification' => 'Revisar correcciones de marca propias',
            'ViewOwn:Document' => 'Ver documentos propios',
            'SignOwn:Document' => 'Firmar documentos propios',
            'ViewTeam:Leave' => 'Ver permisos del equipo',
            'ApproveTeam:Leave' => 'Aprobar permisos del equipo',
            'RequestOwn:OvertimeAuthorization' => 'Solicitar horas extra propias',
            'ViewOwn:OvertimeAuthorization' => 'Ver horas extra propias',
            'ViewTeam:OvertimeAuthorization' => 'Ver horas extra del equipo',
            'ApproveTeam:OvertimeAuthorization' => 'Aprobar horas extra del equipo',
            'Manage:OvertimeAuthorization' => 'Administrar política y pactos de horas extra',
        ],
    ],

    'holidays' => [
        'title' => 'Feriados',
        'description' => 'Feriados oficiales más los que agregue tu organización',
        'new' => 'Nuevo feriado',
        'search_placeholder' => 'Buscar por nombre...',
        'empty' => 'No se encontraron feriados.',
        'official' => 'Oficial',
        'custom' => 'Propio',

        'columns' => [
            'date' => 'Fecha',
            'name' => 'Nombre',
            'type' => 'Tipo',
            'mandatory' => 'Obligatorio',
        ],

        'actions' => [
            'edit' => 'Editar',
            'delete' => 'Eliminar',
        ],

        'yes' => 'Sí',
        'no' => 'No',

        'form' => [
            'name' => 'Nombre',
            'name_placeholder' => 'ej. Fiestas Patrias',
            'date' => 'Fecha',
            'mandatory' => 'Obligatorio',
            'mandatory_hint' => 'Los feriados obligatorios siempre son días no laborables.',
        ],

        'create_dialog' => [
            'title' => 'Nuevo feriado',
            'submit' => 'Crear feriado',
        ],

        'edit_dialog' => [
            'title' => 'Editar feriado',
            'submit' => 'Guardar cambios',
        ],

        'delete_dialog' => [
            'title' => 'Eliminar feriado',
            'description' => '¿Estás seguro de que deseas eliminar :name? Esta acción no se puede deshacer.',
            'confirm' => 'Eliminar',
        ],

        'flash' => [
            'created' => 'Feriado creado.',
            'updated' => 'Feriado actualizado.',
            'deleted' => 'Feriado eliminado.',
        ],
    ],

    'saas_holidays' => [
        'nav' => 'Feriados',
        'title' => 'Feriados oficiales',
        'description' => 'La lista nacional de feriados compartida con todas las organizaciones',
        'empty' => 'Aún no hay feriados oficiales. Importa un año para comenzar.',

        'columns' => [
            'date' => 'Fecha',
            'name' => 'Nombre',
            'mandatory' => 'Obligatorio',
        ],

        'yes' => 'Sí',
        'no' => 'No',

        'import' => [
            'year' => 'Año',
            'submit' => 'Importar desde Boostr',
        ],

        'flash' => [
            'imported' => 'Se importaron :count feriados para :year.',
            'failed' => 'No se pudieron obtener los feriados desde Boostr. Inténtalo de nuevo.',
        ],
    ],

    'saas_legal_hour_limits' => [
        'nav' => 'Límites legales',
        'title' => 'Límites legales de jornada',
        'description' => 'Los máximos legales de jornada vigentes en Chile, aplicados a todas las organizaciones',
        'empty' => 'No hay versiones registradas.',

        'columns' => [
            'period' => 'Vigencia',
            'ordinary_weekly_hours' => 'Jornada semanal',
            'ordinary_daily_hours' => 'Jornada diaria',
            'overtime' => 'Horas extra máx.',
            'total' => 'Total máx.',
            'legal_reference' => 'Norma',
            'status' => 'Estado',
            'calculated_days' => 'Días calculados',
            'actions' => 'Acciones',
        ],

        'status' => [
            'in_force' => 'Vigente',
            'scheduled' => 'Programada',
            'superseded' => 'Histórica',
        ],

        'hours' => ':hours h',
        'hours_per_day' => ':hours h/día',
        'hours_per_week' => ':hours h/semana',
        'from' => 'Desde el :date',
        'range' => 'Del :from al :to',
        'open_ended' => 'Desde el :date, sin término',

        'current' => [
            'title' => 'Vigente hoy',
            'since' => 'En vigor desde el :date',
            'until' => 'Hasta el :date',
            'indefinite' => 'Sin fecha de término',
        ],

        'scheduled_notice' => [
            'title' => 'Cambios programados',
            'body' => 'Versiones futuras ya registradas: :count. Cada una entrará en vigencia automáticamente en su fecha.',
        ],

        'timeline' => [
            'title' => 'Línea de tiempo',
        ],

        'create' => [
            'nav' => 'Nueva versión',
            'title' => 'Nueva versión de los límites legales',
            'description' => 'Registra un cambio en la ley con su fecha de entrada en vigencia',
            'submit' => 'Registrar versión',
        ],

        'correct' => [
            'title' => 'Corregir la versión del :date',
            'description' => 'Una versión no se edita para reflejar un cambio en la ley: para eso se registra una versión nueva. Corrige solo una cifra o una fecha mal ingresada.',
            'submit' => 'Aplicar corrección y recalcular',
            'action' => 'Corregir',
            'used_warning' => 'Días ya calculados con esta versión: :count. La corrección los recalculará todos antes de terminar.',
            'unused_notice' => 'Ningún día calculado se ha juzgado todavía con esta versión, así que no hay nada que recalcular.',
            'reason' => 'Motivo de la corrección',
            'reason_hint' => 'Queda registrado en la auditoría junto a tu nombre. Explica qué estaba mal en la versión registrada.',
        ],

        'form' => [
            'effective_from' => 'Entrada en vigencia',
            'effective_from_hint' => 'La semana se juzga con la versión vigente el lunes que la abre.',
            'ordinary_weekly_hours' => 'Jornada ordinaria semanal (horas)',
            'ordinary_daily_hours' => 'Jornada ordinaria diaria (horas)',
            'max_overtime_daily_hours' => 'Máximo de horas extra por día',
            'max_overtime_weekly_hours' => 'Máximo de horas extra por semana',
            'max_total_daily_hours' => 'Máximo total por día (ordinaria + extra)',
            'max_total_weekly_hours' => 'Máximo total por semana (ordinaria + extra)',
            'legal_reference' => 'Norma legal',
            'legal_reference_placeholder' => 'Ley 21.561',
            'notes' => 'Notas',
        ],

        'global_effect' => [
            'title' => 'Esta versión rige para todas las organizaciones',
            'body' => 'Desde su fecha de entrada en vigencia, todas las organizaciones del sistema quedan medidas contra estas cifras. Los días ya calculados con versiones anteriores no cambian.',
            'acknowledge' => 'Entiendo que esta versión se aplicará a todas las organizaciones desde su fecha de entrada en vigencia',
        ],

        'append_only' => [
            'title' => 'Las versiones no se editan ni se eliminan',
            'body' => 'Un cambio en la ley se registra como una versión nueva. Una cifra mal ingresada se arregla con una corrección, que recalcula los días afectados y queda en la auditoría.',
        ],

        'validation' => [
            'acknowledge' => 'Debes confirmar que esta versión se aplicará a todas las organizaciones.',
            'duplicate_date' => 'Ya existe una versión que entra en vigencia en esa fecha.',
            'total_below_ordinary_daily' => 'El máximo total diario no puede ser menor que la jornada ordinaria diaria.',
            'total_below_ordinary_weekly' => 'El máximo total semanal no puede ser menor que la jornada ordinaria semanal.',
            'unchanged' => 'Una corrección debe cambiar al menos una de las cifras registradas.',
        ],

        'flash' => [
            'created' => 'Versión registrada, con vigencia desde el :date.',
            'corrected' => 'Corrección aplicada. Días recalculados: :count.',
        ],
    ],

    'saas_audit_log' => [
        'nav' => 'Registro de auditoría',
        'title' => 'Registro de auditoría',
        'description' => 'Historial de actividad de solo lectura en todas las organizaciones',
        'empty' => 'No hay actividad registrada para estos filtros.',

        'columns' => [
            'timestamp' => 'Fecha y hora',
            'causer' => 'Responsable',
            'event' => 'Evento',
            'subject' => 'Sujeto',
            'description' => 'Descripción',
            'changes' => 'Cambios',
        ],

        'filters' => [
            'search_placeholder' => 'Buscar descripción...',
            'date_from' => 'Desde',
            'date_to' => 'Hasta',
            'causer' => 'Responsable',
            'all_causers' => 'Todos los responsables',
            'search_causers' => 'Buscar responsable...',
            'no_causers' => 'No se encontraron responsables.',
            'organization' => 'Organización',
            'all_organizations' => 'Todas las organizaciones',
            'search_organizations' => 'Buscar organización...',
            'no_organizations' => 'No se encontraron organizaciones.',
            'clear' => 'Limpiar filtros',
        ],

        'system' => 'Sistema',
        'view_changes' => 'Ver',
        'no_changes' => 'Sin cambios registrados.',

        'changes_dialog' => [
            'title' => 'Cambios registrados',
            'description' => 'Propiedades capturadas para esta entrada de actividad.',
        ],
    ],

    'positions' => [
        'title' => 'Cargos',
        'description' => 'Cargos utilizados para agrupar a los empleados',
        'new' => 'Nuevo cargo',
        'search_placeholder' => 'Buscar por nombre...',
        'empty' => 'No se encontraron cargos.',
        'back' => 'Volver a cargos',

        'columns' => [
            'name' => 'Nombre',
            'employees' => 'Empleados',
        ],

        'actions' => [
            'edit' => 'Renombrar',
            'delete' => 'Eliminar',
        ],

        'pagination' => [
            'showing' => 'Mostrando :from–:to de :total',
            'none' => 'Sin resultados',
            'previous' => 'Anterior',
            'next' => 'Siguiente',
        ],

        'form' => [
            'name' => 'Nombre',
            'name_placeholder' => 'ej. Supervisor',
        ],

        'create_dialog' => [
            'title' => 'Nuevo cargo',
            'submit' => 'Crear cargo',
        ],

        'edit_dialog' => [
            'title' => 'Renombrar cargo',
            'submit' => 'Guardar cambios',
        ],

        'delete_dialog' => [
            'title' => 'Eliminar cargo',
            'description' => '¿Está seguro de que desea eliminar :name? Esta acción no se puede deshacer.',
            'confirm' => 'Eliminar',
        ],

        'employees' => [
            'title' => 'Empleados',
            'empty' => 'No hay empleados asignados a este cargo.',
            'columns' => [
                'name' => 'Nombre',
                'email' => 'Correo',
                'status' => 'Estado',
            ],
            'status' => [
                'active' => 'Activo',
                'inactive' => 'Inactivo',
            ],
        ],

        'flash' => [
            'created' => 'Cargo creado.',
            'updated' => 'Cargo actualizado.',
            'deleted' => 'Cargo eliminado.',
            'has_employees' => 'Este cargo no se puede eliminar mientras tenga empleados asignados.',
        ],
    ],

    'companies' => [
        'title' => 'Empresa',
        'description' => 'Datos de la entidad legal empleadora de tu organización',

        'form' => [
            'details' => 'Datos de la empresa',
            'social_reason' => 'Razón social',
            'rut' => 'RUT',
            'rut_placeholder' => '12.345.678-9',
            'business_line' => 'Giro',
            'email' => 'Correo electrónico',
            'region' => 'Región',
            'region_placeholder' => 'Selecciona una región',
            'region_search' => 'Buscar región...',
            'region_empty' => 'No se encontraron regiones.',
            'commune' => 'Comuna',
            'commune_placeholder' => 'Selecciona una comuna',
            'commune_search' => 'Buscar comuna...',
            'commune_empty' => 'No se encontraron comunas.',
            'commune_loading' => 'Cargando comunas...',
            'commune_region_first' => 'Selecciona primero una región',
            'address' => 'Dirección',
            'address_hint' => 'Nombre de la calle y número',
            'phone' => 'Teléfono',
            'company_type' => 'Tipo de empresa',
            'is_est' => 'Empresa de servicios transitorios (EST)',
            'is_active' => 'Activa',

            'representatives' => 'Representantes legales',
            'representatives_hint' => 'Cada representante se crea como usuario de la empresa.',
            'add_representative' => 'Agregar representante',
            'no_representatives' => 'Aún no se han agregado representantes.',
            'rep_rut' => 'RUT',
            'rep_first_name' => 'Nombre',
            'rep_last_name' => 'Apellido paterno',
            'rep_second_last_name' => 'Apellido materno',
            'rep_email' => 'Correo electrónico',
            'remove' => 'Eliminar',
        ],

        'edit' => [
            'title' => 'Empresa',
            'description' => 'Actualiza los datos y representantes de tu empresa',
            'submit' => 'Guardar cambios',
        ],

        'flash' => [
            'updated' => 'Empresa actualizada.',
        ],
    ],

    'cost_centers' => [
        'title' => 'Centros de costo',
        'description' => 'Centros de costo para segmentar los reportes de remuneraciones',
        'new' => 'Nuevo centro de costo',
        'search_placeholder' => 'Buscar por nombre o código...',
        'empty' => 'No se encontraron centros de costo.',

        'columns' => [
            'name' => 'Nombre',
            'code' => 'Código contable',
            'employees' => 'Empleados',
        ],

        'actions' => [
            'edit' => 'Editar',
            'delete' => 'Eliminar',
        ],

        'form' => [
            'name' => 'Nombre',
            'name_placeholder' => 'ej. Operaciones',
            'code' => 'Código contable',
            'code_placeholder' => 'ej. CC-001',
            'code_hint' => 'Código con el que identificas este centro de costo en tu sistema contable.',
        ],

        'create_dialog' => [
            'title' => 'Nuevo centro de costo',
            'submit' => 'Crear centro de costo',
        ],

        'edit_dialog' => [
            'title' => 'Editar centro de costo',
            'submit' => 'Guardar cambios',
        ],

        'delete_dialog' => [
            'title' => 'Eliminar centro de costo',
            'description' => '¿Está seguro de que desea eliminar :name? Esta acción no se puede deshacer.',
            'confirm' => 'Eliminar',
        ],

        'flash' => [
            'created' => 'Centro de costo creado.',
            'updated' => 'Centro de costo actualizado.',
            'deleted' => 'Centro de costo eliminado.',
            'has_employees' => 'Este centro de costo no se puede eliminar mientras tenga empleados asignados.',
        ],
    ],

    'premises' => [
        'title' => 'Sucursales',
        'description' => 'Ubicaciones físicas de trabajo de tus empresas',
        'new' => 'Nueva sucursal',
        'search_placeholder' => 'Buscar por nombre, código o dirección...',
        'empty' => 'No se encontraron sucursales.',

        'columns' => [
            'name' => 'Nombre',
            'company' => 'Empresa',
            'address' => 'Dirección',
            'coordinates' => 'Ubicación',
        ],

        'coordinates' => [
            'set' => 'Geolocalizada',
            'unset' => 'Sin coordenadas',
        ],

        'actions' => [
            'edit' => 'Editar',
            'delete' => 'Eliminar',
        ],

        'form' => [
            'details' => 'Datos de la sucursal',
            'company' => 'Empresa',
            'company_placeholder' => 'Selecciona una empresa',
            'company_search' => 'Buscar empresa...',
            'company_empty' => 'No se encontraron empresas.',
            'name' => 'Nombre',
            'code' => 'Código',
            'address' => 'Dirección',
            'country' => 'País',
            'region' => 'Región',
            'commune' => 'Comuna',
            'location' => 'Ubicación',
            'location_hint' => 'Haz clic en el mapa o arrastra el marcador para fijar las coordenadas.',
            'lat' => 'Latitud',
            'lng' => 'Longitud',
            'geofence_radius' => 'Radio del geocerco (metros)',
            'geofence_radius_hint' => 'Distancia máxima desde la sucursal para marcar desde la app. Déjalo vacío para no aplicar geocerco.',
            'responsable' => 'Responsable',
            'responsable_name' => 'Nombre',
            'responsable_email' => 'Email',
            'responsable_phone' => 'Teléfono',
        ],

        'map' => [
            'search' => 'Buscar',
            'search_placeholder' => 'Buscar una dirección...',
            'loading' => 'Cargando mapa...',
            'not_found' => 'Sin resultados para esa dirección.',
            'hint' => 'Haz clic en el mapa o arrastra el marcador para ubicar esta sucursal.',
            'unavailable' => 'No se pudo cargar el mapa. Ingresa las coordenadas manualmente abajo.',
        ],

        'create' => [
            'title' => 'Nueva sucursal',
            'description' => 'Registra una nueva ubicación de trabajo',
            'submit' => 'Crear sucursal',
        ],

        'edit' => [
            'title' => 'Editar sucursal',
            'description' => 'Actualiza los datos y la ubicación de la sucursal',
            'submit' => 'Guardar cambios',
        ],

        'delete_dialog' => [
            'title' => 'Eliminar sucursal',
            'description' => '¿Seguro que quieres eliminar :name? Podrá restaurarse más adelante.',
            'confirm' => 'Eliminar',
        ],

        'flash' => [
            'created' => 'Sucursal creada.',
            'updated' => 'Sucursal actualizada.',
            'deleted' => 'Sucursal eliminada.',
            'has_employees' => 'Esta sucursal tiene empleados activos asignados y no se puede eliminar.',
        ],
    ],

    'shifts' => [
        'title' => 'Turnos',
        'description' => 'Plantillas de horario de trabajo de tu organización',
        'new' => 'Nuevo turno',
        'default' => 'Predeterminado',
        'search_placeholder' => 'Buscar por nombre...',
        'empty' => 'No se encontraron turnos.',

        'columns' => [
            'name' => 'Nombre',
            'type' => 'Tipo',
            'weekly_hours' => 'Horas semanales',
            'assignments' => 'Asignaciones',
        ],

        'types' => [
            'fixed' => 'Fijo',
            'rotational' => 'Rotativo',
            'cyclic' => 'Cíclico',
            'biweekly' => 'Quincenal',
            'exceptional' => 'Excepcional',
            'partial' => 'Parcial',
        ],

        'weekdays' => [
            'Lunes',
            'Martes',
            'Miércoles',
            'Jueves',
            'Viernes',
            'Sábado',
            'Domingo',
        ],

        'weekday_range' => ':from a :to',

        'actions' => [
            'edit' => 'Editar',
            'delete' => 'Eliminar',
        ],

        'form' => [
            'details' => 'Datos del turno',
            'name' => 'Nombre',
            'type' => 'Tipo',
            'type_placeholder' => 'Selecciona un tipo',
            'type_search' => 'Buscar tipo...',
            'type_empty' => 'No se encontraron tipos.',
            'description' => 'Descripción',
            'tolerance_in' => 'Tolerancia de entrada (minutos)',
            'tolerance_out' => 'Tolerancia de salida (minutos)',
            'tolerance_placeholder' => 'ej. 30',
            'tolerance_hint' => 'Minutos de gracia antes de marcar atraso/adelanto.',
            'is_default' => 'Turno predeterminado',
            'work_on_holidays' => 'Trabaja en feriados',
            'is_archive' => 'Archivado',
            'schedule' => 'Horario semanal',
            'schedule_hint' => 'Define las horas de trabajo de cada día. Desmarca un día para dejarlo no laborable.',
        ],

        'schedule' => [
            'day' => 'Día',
            'working' => 'Laborable',
            'start' => 'Inicio',
            'end' => 'Término',
            'lunch_start' => 'Inicio colación',
            'lunch_end' => 'Fin colación',
            'hours' => 'Horas',
            'weekly_total' => 'Total semanal',
            'legal_max' => 'Máximo legal: :max horas por semana.',
            'exceeds_weekly' => 'Supera el máximo legal de :max horas por semana.',
            'exceeds_daily' => 'Supera el máximo legal de :max horas por día.',
        ],

        'validation' => [
            'negative_hours' => 'La hora de término debe ser posterior a la de inicio (y la colación debe caber en la jornada).',
            'incomplete_days' => 'Cada día laborable necesita hora de inicio, término y colación.',
        ],

        'create' => [
            'title' => 'Nuevo turno',
            'description' => 'Crea una plantilla de horario de trabajo',
            'submit' => 'Crear turno',
        ],

        'edit' => [
            'title' => 'Editar turno',
            'description' => 'Actualiza el turno y su horario semanal',
            'submit' => 'Guardar cambios',
        ],

        'delete_dialog' => [
            'title' => 'Eliminar turno',
            'description' => '¿Seguro que quieres eliminar :name? Podrá restaurarse más adelante.',
            'confirm' => 'Eliminar',
        ],

        'flash' => [
            'created' => 'Turno creado.',
            'updated' => 'Turno actualizado.',
            'deleted' => 'Turno eliminado.',
            'has_assignments' => 'Este turno tiene asignaciones activas y no se puede eliminar.',
        ],

        'shift_assignments' => [
            'title' => 'Asignaciones de turno',
            'add' => 'Agregar asignación',
            'empty' => 'Aún no hay asignaciones de turno.',
            'status_current' => 'Vigente',
            'status_ended' => 'Terminada',
            'status_upcoming' => 'Próxima',
            'permanent' => 'Permanente',

            'columns' => [
                'shift' => 'Turno',
                'start_date' => 'Fecha de inicio',
                'end_date' => 'Fecha de término',
                'status' => 'Estado',
            ],

            'actions' => [
                'end' => 'Terminar',
                'delete' => 'Eliminar',
            ],

            'dialog' => [
                'title' => 'Agregar asignación de turno',
                'description' => 'Asigna un turno a este empleado por un rango de fechas. Deja la fecha de término vacía para una asignación permanente.',
                'shift' => 'Turno',
                'shift_placeholder' => 'Selecciona un turno',
                'shift_search' => 'Buscar turno...',
                'shift_empty' => 'No se encontraron turnos.',
                'start_date' => 'Fecha de inicio',
                'end_date' => 'Fecha de término (opcional)',
                'cancel' => 'Cancelar',
                'submit' => 'Agregar asignación',
            ],

            'end_dialog' => [
                'title' => 'Terminar asignación',
                'description' => 'Esto establece la fecha de término en hoy. ¿Continuar?',
                'confirm' => 'Terminar asignación',
            ],

            'delete_dialog' => [
                'title' => 'Eliminar asignación',
                'description' => '¿Seguro que deseas eliminar esta asignación? Esta acción no se puede deshacer.',
                'confirm' => 'Eliminar',
            ],

            'validation' => [
                'overlap' => 'Este rango de fechas se superpone con una asignación existente de este empleado.',
            ],

            'flash' => [
                'created' => 'Asignación de turno creada.',
                'ended' => 'Asignación de turno terminada.',
                'deleted' => 'Asignación de turno eliminada.',
            ],
        ],
    ],

    'employees' => [
        'title' => 'Empleados',
        'description' => 'Gestiona las personas de tu organización',
        'new' => 'Nuevo empleado',
        'search_placeholder' => 'Buscar por email o RUT...',
        'empty' => 'No se encontraron empleados.',

        'vacation_balance' => [
            'title' => 'Saldo de vacaciones',
            'summary' => ':used / :total días usados',
            'available' => ':available días disponibles',
        ],

        'contract_types' => [
            'indefinido' => 'Indefinido',
            'plazo_fijo' => 'Plazo fijo',
            'por_obra_o_faena' => 'Por obra o faena',
            'honorarios' => 'Honorarios',
        ],

        'columns' => [
            'employee' => 'Empleado',
            'email' => 'Email',
            'rut' => 'RUT',
            'position' => 'Cargo',
            'premise' => 'Sucursal',
            'cost_center' => 'Centro de costo',
            'contract_type' => 'Tipo de contrato',
            'is_admin' => 'Admin',
            'is_active' => 'Activo',
            'admin_badge' => 'Admin',
        ],

        'filters' => [
            'active_all' => 'Activo: todos',
            'active_yes' => 'Activos',
            'active_no' => 'Inactivos',
            'admin_all' => 'Admin: todos',
            'admin_yes' => 'Administradores',
            'admin_no' => 'No administradores',
            'premise' => 'Sucursal',
            'position' => 'Cargo',
            'cost_center' => 'Centro de costo',
            'contract_type' => 'Tipo de contrato',
            'clear' => 'Limpiar filtros',
        ],

        'actions' => [
            'edit' => 'Editar',
            'delete' => 'Eliminar',
        ],

        'tabs' => [
            'personal' => 'Personal',
            'labor' => 'Laboral',
            'contact' => 'Contacto',
            'system' => 'Sistema',
        ],

        'form' => [
            'none' => 'Ninguno',
            'select' => 'Selecciona una opción',
            'search' => 'Buscar...',
            'no_results' => 'No se encontraron resultados.',
            'has_errors' => 'Por favor corrige los errores a continuación.',
            'avatar' => 'Avatar',
            'is_active' => 'Activo',
            'first_name' => 'Nombre',
            'last_name' => 'Apellido',
            'second_last_name' => 'Segundo apellido',
            'rut' => 'RUT',
            'email' => 'Email',
            'password' => 'Contraseña',
            'password_hint' => 'Déjalo en blanco para mantener la contraseña actual.',
            'nationality' => 'Nacionalidad',
            'gender' => 'Género',
            'employer' => 'Empleador',
            'cost_center' => 'Centro de costo',
            'premise' => 'Sucursal',
            'position' => 'Cargo',
            'supervisor' => 'Supervisor',
            'contract_start_date' => 'Inicio de contrato',
            'contract_end_date' => 'Término de contrato',
            'contract_type' => 'Tipo de contrato',
            'is_admin' => 'Administrador',
            'vacation_days' => 'Días de vacaciones',
            'additional_vacation_days' => 'Días de vacaciones adicionales',
            'administrative_days' => 'Días administrativos',
            'has_additional_sundays' => 'Tiene domingos adicionales',
            'overtime_rest_day_eligible' => 'Puede compensar horas extra con días de descanso',
            'personal_email' => 'Email personal',
            'phone' => 'Teléfono',
            'emergency_contact_name' => 'Nombre de contacto de emergencia',
            'emergency_contact_phone' => 'Teléfono de contacto de emergencia',
            'timezone' => 'Zona horaria',
            'cancel' => 'Cancelar',
        ],

        'create' => [
            'title' => 'Nuevo empleado',
            'description' => 'Agrega una nueva persona a tu organización',
            'submit' => 'Crear empleado',
        ],

        'edit' => [
            'title' => 'Editar empleado',
            'description' => 'Actualiza los datos del empleado',
            'submit' => 'Guardar cambios',
        ],

        'show' => [
            'tab_info' => 'Info',
            'tab_labor' => 'Laboral',
            'tab_shifts' => 'Turnos',
            'tab_documents' => 'Documentos',
            'yes' => 'Sí',
            'no' => 'No',
            'shifts_pending' => 'Las asignaciones de turno estarán disponibles pronto.',
            'documents_pending' => 'Los documentos estarán disponibles pronto.',
        ],

        'delete_dialog' => [
            'title' => 'Eliminar empleado',
            'description' => '¿Seguro que deseas eliminar a :name? Esta acción no se puede deshacer.',
            'confirm' => 'Eliminar',
        ],

        'flash' => [
            'created' => 'Empleado creado.',
            'updated' => 'Empleado actualizado.',
            'deleted' => 'Empleado eliminado.',
        ],
    ],

    'leaves' => [
        'title' => 'Permisos',
        'description' => 'Gestiona las solicitudes de ausencia de los empleados',
        'new' => 'Nuevo permiso',
        'empty' => 'No se encontraron solicitudes de permiso.',

        'calendar' => [
            'title' => 'Calendario de permisos',
            'description' => 'Ausencias aprobadas en toda la organización',
            'legend' => 'Tipos de permiso',
            'employee' => 'Empleado',
            'type' => 'Tipo',
            'dates' => 'Fechas',
            'approved_by' => 'Aprobado por',
            'none' => '—',
        ],

        'tabs' => [
            'all' => 'Todos',
        ],

        'columns' => [
            'employee' => 'Empleado',
            'type' => 'Tipo',
            'start_date' => 'Inicio',
            'end_date' => 'Fin',
            'half_day' => 'Medio día',
            'days' => 'Días',
            'status' => 'Estado',
            'approved_by' => 'Aprobado por',
        ],

        'filters' => [
            'employee' => 'Empleado',
            'from' => 'Desde',
            'to' => 'Hasta',
        ],

        'actions' => [
            'view' => 'Ver detalles',
            'approve' => 'Aprobar',
            'reject' => 'Rechazar',
            'delete' => 'Eliminar',
            'cancel' => 'Cancelar solicitud',
            'more' => 'Más acciones',
        ],

        'statuses' => [
            'pending' => 'Pendiente',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
        ],

        'detail' => [
            'title' => 'Detalles del permiso',
            'employee' => 'Empleado',
            'type' => 'Tipo',
            'status' => 'Estado',
            'start_date' => 'Fecha de inicio',
            'end_date' => 'Fecha de fin',
            'half_day' => 'Medio día',
            'days' => 'Días hábiles',
            'approved_by' => 'Aprobado por',
            'created_at' => 'Solicitado el',
            'medical' => 'Licencia médica',
            'medical_leave_number' => 'Número de licencia',
            'medical_leave_doctor' => 'Médico',
            'notes' => 'Notas',
            'no_notes' => 'Sin notas.',
            'none' => '—',
        ],

        'types' => [
            'vacation_lead' => 'Vacaciones',
            'medical_lead' => 'Licencia médica',
            'unpaid_lead' => 'Sin goce de sueldo',
            'paid_lead' => 'Con goce de sueldo',
            'other_lead' => 'Otro',
        ],

        'half_day_types' => [
            'morning' => 'Mañana',
            'afternoon' => 'Tarde',
        ],

        'create' => [
            'title' => 'Nuevo permiso',
            'description' => 'Registra una solicitud de ausencia para un empleado',
            'submit' => 'Crear permiso',
        ],

        'form' => [
            'employee' => 'Empleado',
            'employee_placeholder' => 'Selecciona un empleado',
            'employee_search' => 'Buscar empleados...',
            'employee_empty' => 'No se encontraron empleados.',
            'type' => 'Tipo de permiso',
            'type_placeholder' => 'Selecciona un tipo',
            'type_search' => 'Buscar tipos...',
            'type_empty' => 'No se encontraron tipos.',
            'start_date' => 'Fecha de inicio',
            'end_date' => 'Fecha de fin',
            'half_day' => 'Medio día',
            'half_day_type' => 'Período del medio día',
            'half_day_type_placeholder' => 'Selecciona un período',
            'business_days' => 'Días hábiles solicitados',
            'business_days_hint' => 'Estimado según el turno y los feriados — ajústalo si es necesario.',
            'business_days_half_hint' => 'Los permisos de medio día siempre cuentan como 0,5 días.',
            'medical_leave_number' => 'Número de licencia médica',
            'medical_leave_doctor' => 'Médico',
            'notes' => 'Notas',
        ],

        'validation' => [
            'half_day_single_day' => 'Un permiso de medio día debe empezar y terminar el mismo día.',
        ],

        'approve_dialog' => [
            'title' => 'Aprobar permiso',
            'description' => '¿Aprobar la solicitud de :name? En vacaciones, los días se descontarán de su saldo.',
        ],

        'reject_dialog' => [
            'title' => 'Rechazar permiso',
            'description' => '¿Rechazar la solicitud de :name?',
        ],

        'delete_dialog' => [
            'title' => 'Eliminar permiso',
            'description' => '¿Eliminar la solicitud de :name? Esta acción no se puede deshacer. En vacaciones aprobadas, los días se devolverán a su saldo.',
        ],

        'my' => [
            'title' => 'Mis permisos',
            'description' => 'Solicita ausencias y revisa el estado de tus solicitudes',
            'new' => 'Solicitar permiso',
            'empty' => 'Aún no tienes solicitudes de permiso.',

            'create' => [
                'title' => 'Solicitar permiso',
                'description' => 'Envía una solicitud de ausencia para su aprobación',
                'submit' => 'Enviar solicitud',
            ],

            'cancel_dialog' => [
                'title' => 'Cancelar solicitud',
                'description' => '¿Cancelar esta solicitud pendiente? Esta acción no se puede deshacer.',
            ],
        ],

        'flash' => [
            'created' => 'Solicitud de permiso creada.',
            'approved' => 'Permiso aprobado.',
            'rejected' => 'Permiso rechazado.',
            'deleted' => 'Permiso eliminado.',
        ],
    ],

    'workdays' => [
        'title' => 'Jornadas',
        'description' => 'Asistencia diaria de cada empleado',
        'empty' => 'No se encontraron jornadas para este rango.',
        'select_all' => 'Seleccionar todas las filas',
        'select_row' => 'Seleccionar fila',
        'selected' => ':count seleccionadas',
        'pending_hint' => 'Solicitudes de modificación de marca pendientes',

        'ranges' => [
            'today' => 'Hoy',
            'yesterday' => 'Ayer',
            'week' => 'Esta semana',
            'month' => 'Este mes',
        ],

        'columns' => [
            'employee' => 'Empleado',
            'date' => 'Fecha',
            'status' => 'Estado',
            'mark_in' => 'Entrada',
            'mark_out' => 'Salida',
            'worked' => 'Trabajado',
            'shift_delta' => 'Diferencia (entrada / salida)',
            'shift' => 'Turno',
            'leave' => 'Permiso',
            'overtime' => 'Horas extra',
        ],

        'filters' => [
            'status' => 'Estado',
            'employee' => 'Empleado',
            'position' => 'Cargo',
            'premise' => 'Sucursal',
            'from' => 'Desde',
            'to' => 'Hasta',
        ],

        'statuses' => [
            'regular' => 'Regular',
            'irregular' => 'Irregular',
            'absent' => 'Ausente',
            'incomplete' => 'Incompleta',
            'justified' => 'Justificada',
        ],

        'actions' => [
            'view' => 'Ver detalles',
            'modify' => 'Modificar marcas',
        ],

        'overtime' => [
            'statuses' => [
                'not_opened' => 'Sin abrir',
            ],
            'actions' => [
                'approve' => 'Aprobar horas extra',
                'object' => 'Objetar horas extra',
            ],
            'bulk' => [
                'trigger_approve' => 'Aprobar horas extra',
                'trigger_object' => 'Objetar horas extra',
                'approve_title' => 'Aprobar horas extra en bloque',
                'object_title' => 'Objetar horas extra en bloque',
                'approve_description' => 'Se aprobarán íntegramente las :count jornadas seleccionadas con horas extra pendientes. Una jornada con anomalías sin revisar, o que exceda un tope legal sin motivo, quedará pendiente.',
                'object_description' => 'Se objetarán las :count jornadas seleccionadas con horas extra pendientes.',
                'reason' => 'Motivo',
                'submit' => 'Confirmar',
            ],
        ],

        'modify' => [
            'title' => 'Modificar marcas',
            'description' => 'Ajusta las horas de :employee del :date. Solo se envían a revisión las marcas que cambies; se notifica al empleado.',
            'mark_in' => 'Marca de entrada',
            'mark_out' => 'Marca de salida',
            'no_mark' => 'Sin marca registrada',
            'reason' => 'Motivo',
            'notes' => 'Notas',
            'submit' => 'Enviar solicitud',
        ],

        'detail' => [
            'title' => 'Detalles de la jornada',
            'in_delta' => 'Diferencia de entrada',
            'out_delta' => 'Diferencia de salida',
            'pending' => 'Modificaciones pendientes',
        ],

        'show' => [
            'back' => 'Volver a jornadas',
            'eyebrow' => 'Jornada',
            'scheduled' => 'Programado',
            'edit_locked' => 'Edición bloqueada',
            'attendance_title' => 'Asistencia del día',
            'shift_range' => 'Turno :range',
            'extra_sub' => 'sobre el turno',
            'missing_sub' => 'del turno',
            'requested_by' => 'Solicitada por',
            'reviewed_inline' => 'revisada por',
            'requests_count' => ':count solicitud(es)',
            'strip' => [
                'entry' => 'Entrada turno',
                'exit' => 'Salida turno',
                'legend_shift' => 'Turno programado',
                'legend_late' => 'Entrada tardía',
                'legend_extra' => 'Salida con tiempo extra',
            ],
            'delta' => [
                'late' => 'tarde',
                'early' => 'antes',
                'extra' => 'extra',
                'on_time' => 'En hora',
            ],
            'employee' => 'Empleado',
            'no_shift' => 'Sin turno asignado',
            'no_premise' => 'Sin sucursal',
            'no_leave' => 'Sin permiso',
            'leave_range' => ':type (:start - :end)',
            'mark_in' => 'Marca de entrada',
            'mark_out' => 'Marca de salida',
            'no_mark' => 'Sin marca',
            'pending_badge' => 'Modificación pendiente',
            'modified_badge' => 'Modificada',
            'view_mark' => 'Ver marca',
            'modify_mark' => 'Modificar marca',
            'summary_title' => 'Resumen',
            'worked' => 'Trabajado',
            'extra' => 'Tiempo extra',
            'missing' => 'Tiempo faltante',
            'mark_details' => [
                'title' => 'Detalles de la marca',
                'date' => 'Fecha registro',
                'time' => 'Hora registro',
                'type' => 'Tipo de marca',
                'shift' => 'Turno',
                'employee_name' => 'Nombre trabajador',
                'employee_rut' => 'RUT trabajador',
                'employer_name' => 'Nombre empleador',
                'employer_rut' => 'RUT empleador',
                'premise_name' => 'Sucursal',
                'premise_address' => 'Dirección sucursal',
                'coordinates' => 'Coordenadas',
            ],
            'history' => [
                'title' => 'Historial de la jornada',
                'empty' => 'No hay eventos registrados en esta jornada',
                'type' => 'Marca',
                'status' => 'Estado',
                'original' => 'Original',
                'modified' => 'Modificada',
                'approve' => 'Aprobar',
                'decline' => 'Rechazar',
                'view_detail' => 'Ver detalle',
                'confirm_approve' => '¿Aprobar esta modificación de marca?',
                'confirm_decline' => '¿Rechazar esta modificación de marca?',
            ],
            'detail' => [
                'title' => 'Detalles de la modificación',
                'reason' => 'Motivo',
                'notes' => 'Comentario',
                'created_by' => 'Creada por',
                'created_at' => 'Creada el',
                'reviewed_by' => 'Revisada por',
                'reviewed_at' => 'Revisada el',
                'not_reviewed' => 'Sin revisar',
            ],
            'flash' => [
                'approved' => 'Modificación aprobada.',
                'declined' => 'Modificación rechazada.',
            ],

            'overtime' => [
                'title' => 'Horas extra',
                'calculated_short' => 'Calculadas:',
                'actions' => [
                    'approve' => 'Aprobar',
                    'object' => 'Objetar',
                ],
                'approve_dialog' => [
                    'title' => 'Aprobar horas extra',
                    'description' => 'Jornada de :employee del :date. Puedes autorizar menos horas de las calculadas.',
                    'authorized_hours' => 'Horas autorizadas',
                    'compensation_type' => 'Compensación',
                    'compensation_type_hint' => ':employee puede compensar horas extra con días de descanso.',
                    'reason' => 'Motivo',
                    'reason_hint' => 'Opcional, salvo que la jornada exceda un tope legal o no cuente con un pacto vigente.',
                    'submit' => 'Aprobar',
                ],
                'object_dialog' => [
                    'title' => 'Objetar horas extra',
                    'description' => 'Jornada de :employee del :date. Las marcas originales no se modifican.',
                    'reason' => 'Motivo',
                    'submit' => 'Objetar',
                ],
                'errors' => [
                    'unresolved_anomalies' => 'No se puede aprobar: la jornada tiene anomalías sin revisar (:reasons). Corrige el dato de origen para desbloquear la aprobación.',
                    'reason_required' => 'Debes indicar un motivo: la jornada excede un tope legal o no cuenta con un pacto vigente.',
                    'not_eligible_for_rest_days' => 'Este empleado no está habilitado para compensar horas extra con días de descanso.',
                ],
                'flash' => [
                    'approved' => 'Horas extra aprobadas correctamente.',
                    'objected' => 'Horas extra objetadas correctamente.',
                    'bulk_decided' => ':decided de :total jornada(s) decidida(s).',
                ],
            ],
        ],

        'bulk' => [
            'trigger' => 'Modificar marcas',
            'title' => 'Modificar marcas',
            'description' => 'Abrir una solicitud de modificación de marca para :count jornada(s) seleccionada(s).',
            'mark_type' => 'Marca',
            'time' => 'Nueva hora',
            'reason' => 'Motivo',
            'notes' => 'Notas',
            'submit' => 'Enviar solicitudes',
        ],

        'flash' => [
            'bulk_modified' => ':count solicitud(es) de modificación de marca creada(s).',
            'modified' => ':count solicitud(es) de modificación de marca creada(s).',
            'modify_blocked' => 'No se crearon solicitudes: las marcas modificadas ya tienen solicitudes pendientes.',
            'no_changes' => 'No se detectaron cambios: no se solicitó ninguna modificación.',
            'too_soon' => 'Una corrección solo puede realizarse a partir del día hábil siguiente al día que se corrige.',
        ],

        'my' => [
            'title' => 'Mis jornadas',
            'description' => 'Revisa tu asistencia y responde a las correcciones solicitadas.',
            'empty' => 'No tienes jornadas registradas en este rango.',
            'back' => 'Volver a mis jornadas',

            'pending' => [
                'title' => 'Correcciones por revisar',
                'subtitle' => 'Un administrador solicitó ajustar estas marcas. Apruébalas o recházalas.',
                'count' => ':count por revisar',
                'requested_by' => 'Solicitada por :name',
                'original' => 'Marca actual',
                'proposed' => 'Marca propuesta',
                'no_mark' => 'Sin marca',
                'reason' => 'Motivo',
                'notes' => 'Comentario',
                'approve' => 'Aprobar',
                'decline' => 'Rechazar',
                'expired' => 'Plazo vencido',
                'expired_hint' => 'El plazo para revisar esta corrección venció.',
            ],

            'list' => [
                'title' => 'Historial de jornadas',
                'pending_flag' => 'Corrección pendiente',
            ],

            'columns' => [
                'date' => 'Fecha',
                'status' => 'Estado',
                'mark_in' => 'Entrada',
                'mark_out' => 'Salida',
                'worked' => 'Trabajado',
                'shift' => 'Turno',
            ],

            'filters' => [
                'from' => 'Desde',
                'to' => 'Hasta',
            ],

            'flash' => [
                'approved' => 'Corrección aprobada. Tu marca fue actualizada.',
                'declined' => 'Corrección rechazada. Tu marca queda sin cambios.',
            ],
        ],
    ],

    'overtime' => [
        'index' => [
            'title' => 'Horas extra',
            'description' => 'Gestión de horas extraordinarias, sus autorizaciones y pactos.',
            'coming_soon' => 'Las pantallas de esta sección se irán habilitando próximamente.',
            'new_request' => 'Solicitar horas extra',
            'my_requests' => 'Mis solicitudes',
        ],

        'calculation_states' => [
            'not_applicable' => 'Sin horas extra',
            'pending_review' => 'Pendiente de revisión',
        ],

        'compensation_types' => [
            'payment' => 'Pago en remuneraciones',
            'rest_days' => 'Días de descanso',
        ],

        'authorization_statuses' => [
            'pending' => 'Pendiente',
            'approved' => 'Autorizada',
            'objected' => 'Objetada',
        ],

        'requests' => [
            'statuses' => [
                'pending' => 'Pendiente',
                'approved' => 'Aprobada',
                'rejected' => 'Rechazada',
            ],

            'validation' => [
                'retroactive_window' => 'Solo puedes solicitar horas extra retroactivas dentro de los últimos :days días.',
                'positive_hours' => 'Las horas solicitadas deben ser mayores a 0.',
            ],

            'flash' => [
                'created' => 'Solicitud de horas extra enviada.',
            ],

            'my' => [
                'title' => 'Mis solicitudes de horas extra',
                'description' => 'Solicita horas extra y revisa el estado de tus solicitudes.',
                'new' => 'Solicitar horas extra',
                'empty' => 'Aún no tienes solicitudes de horas extra.',

                'tabs' => [
                    'all' => 'Todas',
                ],

                'columns' => [
                    'date' => 'Fecha',
                    'requested_hours' => 'Horas solicitadas',
                    'reason' => 'Motivo',
                    'status' => 'Estado',
                    'reviewed_by' => 'Decidido por',
                ],

                'detail' => [
                    'title' => 'Detalle de la solicitud',
                    'date' => 'Fecha',
                    'requested_hours' => 'Horas solicitadas',
                    'status' => 'Estado',
                    'reason' => 'Motivo de la solicitud',
                    'decision_reason' => 'Motivo de la decisión',
                    'reviewed_by' => 'Decidido por',
                    'created_at' => 'Solicitada el',
                    'none' => '—',
                    'no_reason' => 'Sin motivo indicado.',
                ],

                'create' => [
                    'title' => 'Solicitar horas extra',
                    'description' => 'Envía una solicitud de horas extra para su aprobación.',
                    'submit' => 'Enviar solicitud',
                ],

                'form' => [
                    'date' => 'Fecha',
                    'requested_hours' => 'Horas solicitadas',
                    'requested_hours_hint' => 'Usa decimales, por ejemplo 1.5 para una hora y media.',
                    'reason' => 'Motivo',
                    'retroactive_hint' => 'Puedes solicitar fechas pasadas hasta :days días atrás.',
                ],
            ],
        ],

        'anomaly_reasons' => [
            'no_assigned_shift' => 'Sin turno asignado ese día',
            'incomplete_marks' => 'Solo una de las dos marcas del día',
            'contract_not_active' => 'El contrato no está vigente en la fecha marcada',
            'outside_geofence' => 'La marca quedó fuera del geocerco esperado',
            'period_volume_exceeded' => 'El total de horas extra del período supera el umbral configurado',
        ],

        'pact_statuses' => [
            'active' => 'Activo',
            'revoked' => 'Revocado',
        ],

        'pacts' => [
            'title' => 'Pactos de horas extraordinarias',
            'description' => 'Acuerdos escritos de horas extraordinarias, con vigencia transitoria de hasta tres meses (art. 32 del Código del Trabajo).',
            'new' => 'Nuevo pacto',
            'back' => 'Volver a horas extra',
            'search_placeholder' => 'Buscar por empleado...',
            'empty' => 'No se encontraron pactos.',

            'columns' => [
                'employee' => 'Empleado',
                'start_date' => 'Inicio',
                'end_date' => 'Término',
                'status' => 'Estado',
            ],

            'actions' => [
                'edit' => 'Editar',
                'revoke' => 'Revocar',
                'activate' => 'Reactivar',
            ],

            'create_dialog' => [
                'title' => 'Nuevo pacto',
                'submit' => 'Crear pacto',
            ],

            'edit_dialog' => [
                'title' => 'Editar pacto',
                'submit' => 'Guardar cambios',
            ],

            'form' => [
                'employee' => 'Empleado',
                'employee_placeholder' => 'Selecciona un empleado',
                'employee_search' => 'Buscar empleado...',
                'employee_empty' => 'No se encontraron empleados.',
                'start_date' => 'Fecha de inicio',
                'end_date' => 'Fecha de término',
            ],

            'revoke_dialog' => [
                'title' => 'Revocar pacto',
                'description' => 'El pacto de :employee quedará revocado. El registro se conserva como evidencia de lo acordado.',
                'confirm' => 'Revocar',
            ],

            'validation' => [
                'exceeds_three_months' => 'El pacto no puede tener una vigencia superior a tres meses (art. 32 del Código del Trabajo).',
            ],

            'flash' => [
                'created' => 'Pacto creado correctamente.',
                'updated' => 'Pacto actualizado correctamente.',
                'revoked' => 'Pacto revocado correctamente.',
                'activated' => 'Pacto reactivado correctamente.',
            ],
        ],

        'rest_day_balances' => [
            'title' => 'Saldo de descanso compensatorio',
            'description' => 'Horas extra compensadas en días de descanso adicionales, con su fecha de origen y vencimiento (art. 32 del Código del Trabajo).',
            'back' => 'Volver a horas extra',
            'search_placeholder' => 'Buscar por empleado...',
            'empty' => 'No hay saldos de descanso compensatorio.',
            'register_consumption' => 'Registrar consumo',

            'columns' => [
                'employee' => 'Empleado',
                'accrued_hours' => 'Horas extra origen',
                'rest_hours' => 'Horas de descanso',
                'consumed_hours' => 'Consumidas',
                'remaining_hours' => 'Disponibles',
                'accrual_date' => 'Origen',
                'expiry_date' => 'Vence',
                'status' => 'Estado',
            ],

            'statuses' => [
                'active' => 'Vigente',
                'expired' => 'Expirado — a pagar',
            ],

            'expired_hint' => 'El saldo no consumido dentro de los seis meses siguientes debe pagarse en la remuneración del período (art. 32 del Código del Trabajo), no se pierde.',

            'consume_dialog' => [
                'title' => 'Registrar consumo de descanso',
                'employee' => 'Empleado',
                'employee_placeholder' => 'Selecciona un empleado',
                'employee_search' => 'Buscar empleado...',
                'employee_empty' => 'No se encontraron empleados.',
                'hours' => 'Horas a consumir',
                'consumed_on' => 'Fecha de uso',
                'note' => 'Nota',
                'note_placeholder' => 'Opcional',
                'submit' => 'Registrar',
            ],

            'validation' => [
                'positive_hours' => 'Las horas a consumir deben ser mayores a 0.',
                'insufficient_balance' => 'El empleado no tiene saldo suficiente de descanso compensatorio disponible.',
            ],

            'flash' => [
                'consumed' => 'Consumo registrado correctamente.',
            ],

            'my' => [
                'title' => 'Mi saldo de descanso compensatorio',
                'description' => 'Horas extra que compensaste en días de descanso, con su origen y vencimiento.',
                'available' => 'Saldo disponible',
                'empty' => 'Aún no tienes horas acumuladas por descanso compensatorio.',

                'columns' => [
                    'accrued_hours' => 'Horas extra origen',
                    'rest_hours' => 'Horas de descanso',
                    'consumed_hours' => 'Consumidas',
                    'remaining_hours' => 'Disponibles',
                    'accrual_date' => 'Origen',
                    'expiry_date' => 'Vence',
                    'status' => 'Estado',
                ],
            ],
        ],

        'queue' => [
            'title' => 'Horas extra pendientes',
            'description' => 'Aprueba u objeta las jornadas con horas extraordinarias, individualmente o en bloque.',
            'back' => 'Volver a horas extra',
            'empty' => 'No hay jornadas de horas extra para este filtro.',
            'select_all' => 'Seleccionar todas las filas',
            'select_row' => 'Seleccionar fila',
            'selected' => ':count seleccionadas',

            'tabs' => [
                'all' => 'Todas',
                'excess' => 'Jornadas',
                'requests' => 'Solicitudes',
            ],

            'requests' => [
                'empty' => 'No hay solicitudes de horas extra pendientes.',

                'columns' => [
                    'employee' => 'Empleado',
                    'date' => 'Fecha',
                    'requested_hours' => 'Horas solicitadas',
                    'reason' => 'Motivo',
                    'status' => 'Estado',
                    'reviewed_by' => 'Decidido por',
                ],

                'actions' => [
                    'approve' => 'Aprobar',
                    'reject' => 'Rechazar',
                ],

                'approve_dialog' => [
                    'title' => 'Aprobar solicitud',
                    'description' => 'Solicitud de :employee para el :date. Aprobarla no genera horas pagables por sí sola; las horas trabajadas seguirán pasando por esta misma cola una vez calculadas.',
                    'submit' => 'Aprobar',
                ],

                'reject_dialog' => [
                    'title' => 'Rechazar solicitud',
                    'description' => 'Solicitud de :employee para el :date. Rechazarla no le impide trabajar ese día.',
                    'reason' => 'Motivo',
                    'submit' => 'Rechazar',
                ],

                'flash' => [
                    'approved' => 'Solicitud aprobada correctamente.',
                    'rejected' => 'Solicitud rechazada correctamente.',
                ],
            ],

            'columns' => [
                'employee' => 'Empleado',
                'date' => 'Fecha',
                'calculated_hours' => 'Calculadas (OHC)',
                'authorized_hours' => 'Autorizadas (OHA)',
                'final_hours' => 'Pagables',
                'status' => 'Estado',
                'reviewed_by' => 'Decidido por',
                'reason' => 'Motivo',
            ],

            'flags' => [
                'label' => 'Con anomalías',
                'tooltip' => 'Anomalías sin revisar, bloquean la aprobación: :reasons',
            ],

            'filters' => [
                'employee' => 'Empleado',
                'from' => 'Desde',
                'to' => 'Hasta',
            ],

            'actions' => [
                'approve' => 'Aprobar',
                'object' => 'Objetar',
            ],

            'approve_dialog' => [
                'title' => 'Aprobar horas extra',
                'description' => 'Jornada de :employee del :date. Puedes autorizar menos horas de las calculadas.',
                'authorized_hours' => 'Horas autorizadas',
                'compensation_type' => 'Compensación',
                'compensation_type_hint' => ':employee puede compensar horas extra con días de descanso.',
                'reason' => 'Motivo',
                'reason_hint' => 'Opcional, salvo que la jornada exceda un tope legal o no cuente con un pacto vigente.',
                'submit' => 'Aprobar',
            ],

            'object_dialog' => [
                'title' => 'Objetar horas extra',
                'description' => 'Jornada de :employee del :date. Las marcas originales no se modifican.',
                'reason' => 'Motivo',
                'submit' => 'Objetar',
            ],

            'bulk' => [
                'trigger_approve' => 'Aprobar seleccionadas',
                'trigger_object' => 'Objetar seleccionadas',
                'approve_title' => 'Aprobar horas extra en bloque',
                'object_title' => 'Objetar horas extra en bloque',
                'approve_description' => 'Se aprobarán íntegramente las :count jornadas seleccionadas. Una jornada con anomalías sin revisar, o que exceda un tope legal sin motivo, quedará pendiente.',
                'object_description' => 'Se objetarán las :count jornadas seleccionadas.',
                'reason' => 'Motivo',
                'submit' => 'Confirmar',
            ],

            'errors' => [
                'unresolved_anomalies' => 'No se puede aprobar: la jornada tiene anomalías sin revisar (:reasons). Corrige el dato de origen para desbloquear la aprobación.',
                'reason_required' => 'Debes indicar un motivo: la jornada excede un tope legal o no cuenta con un pacto vigente.',
                'not_eligible_for_rest_days' => 'Este empleado no está habilitado para compensar horas extra con días de descanso.',
            ],

            'flash' => [
                'approved' => 'Horas extra aprobadas correctamente.',
                'objected' => 'Horas extra objetadas correctamente.',
                'bulk_decided' => ':decided de :total jornadas decididas. Las demás quedaron pendientes por anomalías o topes sin justificar.',
            ],
        ],
    ],

    'mark_modifications' => [
        'statuses' => [
            'pending' => 'Pendiente',
            'approved' => 'Aprobada',
            'declined' => 'Rechazada',
        ],

        'reasons' => [
            'mark_forgotten' => 'Marca olvidada',
            'mark_incorrect' => 'Marca incorrecta',
            'system_error' => 'Error del sistema',
            'shift_change' => 'Cambio de turno',
            'justified_missing_time' => 'Tiempo faltante justificado',
            'inside_tolerance_time' => 'Dentro de la tolerancia',
            'other' => 'Otro',
        ],

        'review' => [
            'title' => 'Revisar corrección de marca',
            'description' => 'Aprueba o rechaza el cambio solicitado a tu marca de asistencia.',
            'employee' => 'Empleado',
            'mark_type' => 'Marca',
            'original' => 'Hora original',
            'proposed' => 'Hora propuesta',
            'no_mark' => 'Sin marca registrada',
            'reason' => 'Motivo',
            'notes' => 'Notas',
            'approve' => 'Aprobar',
            'decline' => 'Rechazar',
            'approved_title' => 'Corrección aprobada',
            'approved_body' => 'Tu marca de asistencia fue actualizada. Puedes cerrar esta página.',
            'declined_title' => 'Corrección rechazada',
            'declined_body' => 'La solicitud fue rechazada y tu marca queda sin cambios. Puedes cerrar esta página.',
            'expired_title' => 'Plazo de objeción vencido',
            'expired_body' => 'Pasaron 48 horas sin objeción, por lo que la corrección se aplicó automáticamente. Ya no es posible aprobarla ni rechazarla.',
        ],
    ],

    'dt' => [
        'nav' => [
            'dashboard' => 'Inicio',
            'validate_mark' => 'Validar marca',
            'documents' => 'Documentos',
            'reports' => 'Reportes',
            'select_organization' => 'Cambiar empleador',
        ],
        'organization' => [
            'select' => [
                'title' => 'Seleccione un empleador a fiscalizar',
                'description' => 'Elija el empleador cuyos registros desea revisar. Todas las vistas de esta sesión se limitarán a su selección.',
                'search_placeholder' => 'Buscar por nombre o RUT',
                'columns' => [
                    'name' => 'Empleador',
                    'rut' => 'RUT',
                ],
                'current' => 'Fiscalizando actualmente',
                'submit' => 'Fiscalizar este empleador',
                'empty' => 'No hay empleadores disponibles para fiscalizar.',
                'no_results' => 'Ningún empleador coincide con su búsqueda.',
            ],
        ],
        'marks' => [
            'validate' => [
                'title' => 'Validar marca',
                'description' => 'Pega el checksum SHA-256 impreso en un comprobante de asistencia para verificar su integridad contra la base de datos.',
                'checksum' => 'Checksum o hash',
                'checksum_placeholder' => 'Pega aquí el checksum de la marca',
                'submit' => 'Validar',
                'not_found' => 'No se encontró una marca con ese checksum.',
                'result_title' => 'Información de la marca',
                'result_description' => 'El checksum coincide con la siguiente información de marca.',
                'employee_name' => 'Nombre trabajador',
                'employee_rut' => 'RUT trabajador',
                'employer_name' => 'Nombre empleador',
                'employer_rut' => 'RUT empleador',
                'date_time' => 'Fecha y hora de registro',
                'type' => 'Tipo de marca',
                'premise_name' => 'Sucursal',
                'premise_address' => 'Dirección sucursal',
                'coordinates' => 'Coordenadas',
                'checksum_value' => 'Checksum',
                'not_available' => 'No disponible',
            ],
        ],
        'documents' => [
            'title' => 'Documentos',
            'description' => 'Documentos laborales registrados para el empleador fiscalizado.',
            'columns' => [
                'employee' => 'Empleado',
                'type' => 'Tipo',
                'status' => 'Estado',
                'published_at' => 'Publicado',
                'signed_at' => 'Firmado',
            ],
            'empty' => 'No se registraron documentos para este empleador.',
            'show' => [
                'back' => 'Volver a documentos',
                'details' => 'Detalles',
                'body' => 'Contenido',
                'body_empty' => 'Este documento aún no tiene contenido.',
                'download' => 'Descargar PDF',
            ],
        ],
        'reports' => [
            'title' => 'Reportes',
            'description' => 'Genera los reportes de asistencia y jornada laboral exigidos por la Resolución 38.',
            'coming_soon' => 'Este reporte estará disponible próximamente.',
            'types' => [
                'attendance' => 'Reporte de asistencia',
                'daily' => 'Reporte de jornada diaria',
                'shift-changes' => 'Reporte de modificaciones de turnos',
                'sundays' => 'Reporte de domingos y festivos',
                'incidents' => 'Reporte de incidentes técnicos',
            ],
            'filters' => [
                'type' => 'Tipo de reporte',
                'type_placeholder' => 'Selecciona un tipo de reporte',
                'start' => 'Fecha desde',
                'end' => 'Fecha hasta',
                'employees' => 'Trabajadores',
                'employees_all' => 'Todos los trabajadores',
                'employees_search' => 'Buscar por nombre o RUT',
                'positions' => 'Cargos',
                'positions_all' => 'Todos los cargos',
                'positions_search' => 'Buscar cargo',
                'premises' => 'Sucursales',
                'premises_all' => 'Todas las sucursales',
                'premises_search' => 'Buscar sucursal',
                'journals' => 'Jornadas',
                'journals_all' => 'Todas las jornadas',
                'journals_search' => 'Buscar tipo de jornada',
                'shifts' => 'Turnos',
                'shifts_all' => 'Todos los turnos',
                'shifts_search' => 'Buscar turno',
                'checksum' => 'Hash o checksum',
                'checksum_placeholder' => 'Buscar marcación por su código Hash',
                'periods' => [
                    'label' => 'Periodo predeterminado',
                    'week' => 'Última semana',
                    'fortnight' => 'Quincena',
                    'last_month' => 'Mes anterior',
                    'last_12_months' => 'Últimos 12 meses',
                ],
                'selected' => ':count seleccionado(s)',
                'no_results' => 'Sin resultados.',
                'generate' => 'Generar reporte',
            ],
            'attendance' => [
                'title' => 'Reporte de asistencia',
                'description' => 'Asistencia diaria de cada trabajador en el período seleccionado (Resolución 38, Art. 27 a).',
                'no_workers' => 'No hay trabajadores que coincidan con la selección',
                'yes' => 'Sí',
                'no' => 'No',
                'justified' => 'Justificada',
                'unjustified' => 'Injustificada',
                'header' => [
                    'employer' => 'Empleador',
                    'employee' => 'Trabajador',
                    'premise' => 'Lugar de prestación',
                ],
                'columns' => [
                    'date' => 'Fecha',
                    'attendance' => 'Asistencia',
                    'absence' => 'Ausencia',
                    'observations' => 'Observaciones',
                ],
                'observations' => [
                    'free' => 'Libre',
                    'holiday' => 'Feriado',
                ],
                'export' => [
                    'excel' => 'Excel',
                    'pdf' => 'PDF',
                    'word' => 'Word',
                ],
            ],
            'daily' => [
                'title' => 'Reporte de jornada diaria',
                'description' => 'Horas diarias y semanales laboradas de cada trabajador en el período seleccionado (Resolución 38, Art. 27 b).',
                'no_workers' => 'No hay trabajadores que coincidan con la selección',
                'not_applicable' => 'No aplica',
                'yes' => 'Sí',
                'no' => 'No',
                'week_total' => 'Total semana',
                'compensation' => 'Compensación',
                'header' => [
                    'flexible_band' => 'Sujeto a banda horaria',
                ],
                'columns' => [
                    'date' => 'Fecha',
                    'journey' => 'Jornada ordinaria pactada',
                    'journey_marks' => 'Marcaciones jornada',
                    'lunch' => 'Colación',
                    'lunch_marks' => 'Marcaciones colación',
                    'undertime' => 'Tiempo faltante',
                    'overtime' => 'Tiempo extra',
                    'other_marks' => 'Otras marcaciones',
                    'observations' => 'Observaciones',
                    'exceptional_cycle' => 'Ciclos (distribución excepcional)',
                ],
                'export' => [
                    'excel' => 'Excel',
                    'pdf' => 'PDF',
                    'word' => 'Word',
                ],
            ],
            'shift-changes' => [
                'title' => 'Reporte de modificaciones de turnos',
                'description' => 'Modificaciones y alteraciones de turnos de cada trabajador en el período seleccionado (Resolución 38, Art. 27 d).',
                'no_workers' => 'No hay trabajadores que coincidan con la selección',
                'no_changes' => 'Sin cambios o modificaciones en el periodo consultado',
                'fixed_journey' => 'La jornada de este trabajador es fija y permanente, por lo que no registra modificaciones de turno.',
                'header' => [
                    'employer' => 'Empleador',
                    'employee' => 'Trabajador',
                    'premise' => 'Lugar de prestación',
                ],
                'columns' => [
                    'old_start_date' => 'Fecha asignación turno',
                    'old_shift' => 'Turno asignado',
                    'old_extension' => 'Extensión del turno',
                    'notification_date' => 'Fecha asignación nuevo turno',
                    'new_start_date' => 'Inicio de turno',
                    'new_shift' => 'Nuevo turno asignado',
                    'new_extension' => 'Extensión del nuevo turno',
                    'requested_by' => 'Solicitado por',
                    'observations' => 'Observaciones',
                ],
                'requested_by' => [
                    'employee' => 'Trabajador',
                    'employer' => 'Empleador',
                ],
                'export' => [
                    'excel' => 'Excel',
                    'pdf' => 'PDF',
                    'word' => 'Word',
                ],
            ],
            'sundays' => [
                'title' => 'Reporte de días domingo y/o días festivos',
                'description' => 'Domingos y festivos laborados por cada trabajador en el período seleccionado (Resolución 38, Art. 27 c).',
                'no_workers' => 'No hay trabajadores que coincidan con la selección',
                'no_sundays' => 'La jornada de este trabajador no incluye domingos o festivos',
                'additional_flag' => 'Trabajador del comercio con descansos dominicales adicionales',
                'yes' => 'Sí',
                'no' => 'No',
                'justified' => 'Justificada',
                'unjustified' => 'Injustificada',
                'month_total' => 'Total :month',
                'period_total' => 'Total período',
                'header' => [
                    'position' => 'Cargo o función',
                ],
                'columns' => [
                    'additional' => 'Descanso dominical adicional',
                    'date' => 'Fecha',
                    'attendance' => 'Asistencia',
                    'absence' => 'Ausencia',
                    'observations' => 'Observaciones',
                ],
                'export' => [
                    'excel' => 'Excel',
                    'pdf' => 'PDF',
                    'word' => 'Word',
                ],
            ],
            'incidents' => [
                'title' => 'Reporte de incidentes técnicos',
                'description' => 'Incidentes que provocaron el cese operacional total o parcial del sistema electrónico de asistencia, en el período seleccionado (Resolución 38, Art. 27 f).',
                'ongoing' => 'En curso',
                'empty' => 'No se registraron incidentes técnicos en el período seleccionado.',
                'columns' => [
                    'start_time' => 'Fecha/hora inicio',
                    'end_time' => 'Fecha/hora término',
                    'duration' => 'Duración',
                    'description' => 'Descripción del incidente',
                ],
                'export' => [
                    'excel' => 'Excel',
                    'pdf' => 'PDF',
                    'word' => 'Word',
                ],
            ],
        ],
    ],

    'documents' => [
        'title' => 'Documentos',
        'description' => 'Redacta, publica y da seguimiento a los documentos de los empleados',
        'new' => 'Nuevo documento',
        'search_placeholder' => 'Buscar por título...',
        'empty' => 'No se encontraron documentos.',

        'columns' => [
            'title' => 'Título',
            'type' => 'Tipo',
            'employee' => 'Empleado',
            'status' => 'Estado',
            'published_at' => 'Publicado',
            'signed_at' => 'Firmado',
        ],

        'filters' => [
            'status_all' => 'Estado: todos',
            'type_all' => 'Tipo: todos',
            'employee' => 'Empleado',
            'from' => 'Publicado desde',
            'to' => 'Publicado hasta',
            'clear' => 'Limpiar filtros',
        ],

        'actions' => [
            'edit' => 'Editar',
            'delete' => 'Eliminar',
            'publish' => 'Publicar',
            'download' => 'Descargar PDF',
            'void' => 'Anular documento',
            'duplicate' => 'Duplicar como borrador',
        ],

        'statuses' => [
            'draft' => 'Borrador',
            'published' => 'Publicado',
            'pending_signature' => 'Pendiente de firma',
            'signed' => 'Firmado',
            'rejected' => 'Rechazado',
            'voided' => 'Anulado',
            'archived' => 'Archivado',
        ],

        'types' => [
            'annexes' => 'Anexo',
            'contracts' => 'Contrato',
            'certificates' => 'Certificado',
            'regulations' => 'Reglamento',
            'pacts' => 'Pacto',
            'notifications' => 'Notificación',
            'requests' => 'Solicitud',
            'others' => 'Otro',
        ],

        'create' => [
            'title' => 'Nuevo documento',
            'description' => 'Redacta un documento para un empleado',
            'submit' => 'Crear documento',
        ],

        'edit' => [
            'title' => 'Editar documento',
            'description' => 'Actualiza los datos del documento',
            'submit' => 'Guardar cambios',
        ],

        'form' => [
            'title' => 'Título',
            'type' => 'Tipo de documento',
            'type_placeholder' => 'Selecciona un tipo',
            'employee' => 'Empleado',
            'employee_placeholder' => 'Selecciona un empleado',
            'body' => 'Cuerpo',
            'body_hint' => 'Usa "Insertar variable" para agregar marcadores que se resuelven al publicar.',
            'body_placeholder' => 'Redacta el documento…',
            'signature_config' => 'Configuración de firma',
            'legal_rep_signatories' => 'Firmantes representantes legales',
            'legal_rep_signatories_hint' => 'Cuántos representantes legales deben firmar.',
            'ordered_signing' => 'Firma ordenada',
            'ordered_signing_hint' => 'Exigir que los representantes legales firmen en orden.',
            'load_template' => 'Cargar plantilla',
            'load_template_hint' => 'Rellena el cuerpo desde una plantilla guardada.',
            'template_search' => 'Buscar plantillas...',
            'template_empty' => 'No se encontraron plantillas.',
        ],

        'editor' => [
            'bold' => 'Negrita',
            'italic' => 'Cursiva',
            'heading' => 'Encabezado',
            'bullet_list' => 'Lista con viñetas',
            'ordered_list' => 'Lista numerada',
            'quote' => 'Cita',
            'undo' => 'Deshacer',
            'redo' => 'Rehacer',
            'insert_variable' => 'Insertar variable',
            'variable_search' => 'Buscar variables...',
            'variable_empty' => 'No se encontraron variables.',
        ],

        'show' => [
            'back' => 'Volver a documentos',
            'eyebrow' => 'Documento',
            'body' => 'Cuerpo del documento',
            'body_hint' => 'Vista previa con las variables resueltas para este empleado.',
            'body_empty' => 'Este documento aún no tiene cuerpo.',
            'details' => 'Detalles',
            'employee' => 'Empleado',
            'legal_rep_signatories' => 'Firmantes representantes legales',
            'ordered_signing' => 'Firma ordenada',
            'signatures' => 'Firmas',
            'activity' => 'Actividad',
        ],

        'activity' => [
            'empty' => 'No hay actividad registrada',
            'status_change' => ':from → :to',
            'events' => [
                'published' => [
                    'title' => 'Documento publicado',
                    'description' => 'El documento ha sido publicado con éxito.',
                ],
                'signature_requested' => [
                    'title' => 'Firma solicitada',
                    'description' => 'Se solicitó la firma a :name.',
                ],
                'signature_signed' => [
                    'title' => 'Firma registrada',
                    'description' => ':name firmó el documento.',
                ],
                'signed' => [
                    'title' => 'Documento firmado',
                    'description' => 'El documento ha sido firmado por todas las partes.',
                ],
                'signature_rejected' => [
                    'title' => 'Firma rechazada',
                    'description' => ':name rechazó la firma del documento.',
                ],
                'voided' => [
                    'title' => 'Documento anulado',
                    'description' => 'El documento fue anulado y ya no puede firmarse.',
                ],
            ],
        ],

        'pdf' => [
            'signatures_heading' => 'Firmas electrónicas simples',
            'rut' => 'RUT',
            'email' => 'Correo',
            'signed_at' => 'Fecha y hora',
            'hash' => 'Código de verificación',
        ],

        'signatures' => [
            'statuses' => [
                'pending' => 'Pendiente',
                'signed' => 'Firmado',
                'rejected' => 'Rechazado',
                'cancelled' => 'Cancelado',
            ],
            'types' => [
                'employee' => 'Empleado',
                'legal_rep' => 'Representante legal',
                'supervisor' => 'Supervisor',
            ],
            'empty' => 'Este documento aún no tiene firmas. Se generan al publicarlo.',
            'progress' => 'firmadas',
            'signed_at' => 'Firmado el :date',
            'resend' => [
                'action' => 'Reenviar',
                'sent' => 'Solicitud de firma reenviada.',
                'not_pending' => 'Solo se pueden reenviar firmas pendientes.',
            ],
            'sign' => [
                'code_sent' => 'Te enviamos un código de verificación a tu correo personal.',
                'not_your_turn' => 'Aún no es tu turno de firmar este documento.',
                'invalid_code' => 'El código es inválido o ha expirado.',
                'signed' => 'Documento firmado con éxito.',
                'rejected' => 'Has rechazado el documento.',
            ],
        ],

        'my' => [
            'title' => 'Mis documentos',
            'description' => 'Documentos publicados para ti y su estado de firma.',
            'empty' => 'No tienes documentos publicados.',
            'awaiting_you' => 'Requiere tu firma',
            'view' => 'Ver',
            'columns' => [
                'title' => 'Documento',
                'type' => 'Tipo',
                'status' => 'Estado',
                'my_signature' => 'Mi firma',
                'published_at' => 'Publicado',
            ],
            'show' => [
                'back' => 'Volver a mis documentos',
                'eyebrow' => 'Documento',
                'body' => 'Contenido del documento',
                'download_signed' => 'Descargar copia firmada',
                'sign_panel' => 'Firma electrónica',
                'request_code' => 'Solicitar código',
                'resend_code' => 'Reenviar código',
                'code_label' => 'Código de verificación',
                'code_hint' => 'Ingresa el código de 6 dígitos que enviamos a tu correo personal.',
                'sign' => 'Firmar documento',
                'reject' => 'Rechazar',
                'reject_reason' => 'Motivo del rechazo (opcional)',
                'reject_confirm_title' => 'Rechazar documento',
                'reject_confirm_description' => 'Al rechazar, el documento no podrá ser firmado por ninguna de las partes. ¿Continuar?',
                'already_signed' => 'Ya firmaste este documento.',
                'already_rejected' => 'Rechazaste este documento.',
                'waiting_others' => 'Esperando la firma de las demás partes.',
                'not_your_turn' => 'Podrás firmar cuando sea tu turno.',
            ],
        ],

        'flash' => [
            'created' => 'Documento creado.',
            'updated' => 'Documento actualizado.',
            'deleted' => 'Documento eliminado.',
            'published' => 'Documento publicado.',
            'voided' => 'Documento anulado.',
            'duplicated' => 'Copia en borrador creada. Realiza las correcciones y publícala.',
        ],

        'duplicate' => [
            'title_suffix' => ':title (copia)',
        ],

        'delete_dialog' => [
            'title' => 'Eliminar documento',
            'description' => '¿Seguro que deseas eliminar ":title"? Esta acción no se puede deshacer.',
            'confirm' => 'Eliminar',
        ],

        'publish_dialog' => [
            'title' => 'Publicar documento',
            'description' => 'Al publicar se resuelven las variables del documento y se registra la fecha de publicación. ¿Continuar?',
            'confirm' => 'Publicar',
        ],

        'void_dialog' => [
            'title' => 'Anular documento',
            'description' => 'Al anular se retira el documento y se cancelan las firmas pendientes — ya no podrá firmarse. El documento permanece en el registro para auditoría. Para corregirlo, duplícalo como borrador. ¿Continuar?',
            'confirm' => 'Anular documento',
        ],
    ],

    'document_templates' => [
        'title' => 'Plantillas de documentos',
        'description' => 'Cuerpos de documento reutilizables que puedes cargar en nuevos documentos',
        'new' => 'Nueva plantilla',
        'search_placeholder' => 'Buscar por título...',
        'empty' => 'No se encontraron plantillas.',

        'columns' => [
            'title' => 'Título',
            'type' => 'Tipo',
            'variables' => 'Variables',
            'updated_at' => 'Actualizada',
            'state' => 'Estado',
        ],

        'state' => [
            'active' => 'Activa',
            'deleted' => 'Eliminada',
        ],

        'actions' => [
            'edit' => 'Editar',
            'delete' => 'Eliminar',
            'restore' => 'Restaurar',
        ],

        'create' => [
            'title' => 'Nueva plantilla',
            'description' => 'Redacta una plantilla de documento reutilizable',
            'submit' => 'Crear plantilla',
        ],

        'edit' => [
            'title' => 'Editar plantilla',
            'description' => 'Actualiza los datos de la plantilla',
            'submit' => 'Guardar cambios',
        ],

        'form' => [
            'title' => 'Título',
            'type' => 'Tipo de documento',
            'type_placeholder' => 'Selecciona un tipo',
            'body' => 'Cuerpo',
            'body_hint' => 'Haz clic en una variable para insertar su marcador en el cursor.',
            'body_placeholder' => 'Redacta la plantilla…',
        ],

        'flash' => [
            'created' => 'Plantilla creada.',
            'updated' => 'Plantilla actualizada.',
            'deleted' => 'Plantilla eliminada.',
            'restored' => 'Plantilla restaurada.',
        ],

        'delete_dialog' => [
            'title' => 'Eliminar plantilla',
            'description' => '¿Seguro que deseas eliminar ":title"? Podrás restaurarla más tarde.',
            'confirm' => 'Eliminar',
        ],
    ],

];
