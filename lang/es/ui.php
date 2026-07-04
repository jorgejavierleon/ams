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
        'documents' => 'Documentos',
        'settings' => 'Configuración',
        'dashboard' => 'Panel',
        'roles' => 'Roles',
        'positions' => 'Cargos',
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

];
