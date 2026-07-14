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
        'settings' => 'Configuración',
        'dashboard' => 'Panel',
        'roles' => 'Roles',
        'positions' => 'Cargos',
        'companies' => 'Empresas',
        'premises' => 'Sucursales',
        'shifts' => 'Turnos',
        'workdays_list' => 'Jornadas',
        'employees' => 'Empleados',
        'holidays' => 'Feriados',
        'leaves' => 'Permisos',
        'my_leaves' => 'Mis permisos',
        'team_leaves' => 'Permisos del equipo',
    ],

    'user_menu' => [
        'settings' => 'Configuración',
        'logout' => 'Cerrar sesión',
    ],

    'common' => [
        'save' => 'Guardar',
        'cancel' => 'Cancelar',

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
        'title' => 'Empresas',
        'description' => 'Entidades legales dentro de tu organización',
        'new' => 'Nueva empresa',
        'search_placeholder' => 'Buscar por nombre o RUT...',
        'empty' => 'No se encontraron empresas.',

        'columns' => [
            'name' => 'Empresa',
            'rut' => 'RUT',
            'region' => 'Región',
            'commune' => 'Comuna',
            'employees' => 'Empleados',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ],

        'actions' => [
            'edit' => 'Editar',
            'delete' => 'Eliminar',
        ],

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

        'create' => [
            'title' => 'Nueva empresa',
            'description' => 'Registra una nueva empresa',
            'submit' => 'Crear empresa',
        ],

        'edit' => [
            'title' => 'Editar empresa',
            'description' => 'Actualiza los datos y representantes de la empresa',
            'submit' => 'Guardar cambios',
        ],

        'delete_dialog' => [
            'title' => 'Eliminar empresa',
            'description' => '¿Seguro que quieres eliminar :name? Podrá restaurarse más adelante.',
            'confirm' => 'Eliminar',
        ],

        'flash' => [
            'created' => 'Empresa creada.',
            'updated' => 'Empresa actualizada.',
            'deleted' => 'Empresa eliminada.',
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
            'exceeds_weekly' => 'El total semanal (:total h) supera el máximo legal de :max horas.',
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

        'columns' => [
            'employee' => 'Empleado',
            'email' => 'Email',
            'rut' => 'RUT',
            'position' => 'Cargo',
            'premise' => 'Sucursal',
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
            'company' => 'Empresa',
            'premise' => 'Sucursal',
            'position' => 'Cargo',
            'supervisor' => 'Supervisor',
            'contract_start_date' => 'Inicio de contrato',
            'contract_end_date' => 'Término de contrato',
            'is_admin' => 'Administrador',
            'vacation_days' => 'Días de vacaciones',
            'additional_vacation_days' => 'Días de vacaciones adicionales',
            'administrative_days' => 'Días administrativos',
            'has_additional_sundays' => 'Tiene domingos adicionales',
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
        ],

        'detail' => [
            'title' => 'Detalles de la jornada',
            'in_delta' => 'Diferencia de entrada',
            'out_delta' => 'Diferencia de salida',
            'pending' => 'Modificaciones pendientes',
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
    ],

];
