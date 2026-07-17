<?php

return [
    'auth_profile_updated' => [
        'subject' => 'Datos de perfil actualizados',
        'heading' => 'Perfil actualizado',
        'body' => 'Tu información de perfil (email, email personal o clave) ha sido actualizada con éxito.',
        'warning' => 'Si no realizaste esta acción o crees que es un error, por favor contacta a tu administrador.',
    ],

    'mark_created' => [
        'subject' => 'Comprobante de marca de asistencia',
        'heading' => 'Marca registrada',
        'body' => 'Se ha registrado tu marca de asistencia con los siguientes datos:',
        'type' => 'Tipo',
        'date_time' => 'Fecha y hora',
        'checksum' => 'Código de verificación',
    ],

    'leave' => [
        'type' => 'Tipo',
        'dates' => 'Fechas',
        'days' => 'Días hábiles',
        'action_my_leaves' => 'Ver mis permisos',
    ],

    'leave_submitted' => [
        'subject' => 'Nueva solicitud de permiso pendiente de revisión',
        'heading' => 'Solicitud de permiso enviada',
        'body' => ':employee ha enviado una solicitud de permiso que requiere tu revisión.',
        'action' => 'Revisar solicitud',
    ],

    'leave_approved' => [
        'subject' => 'Tu solicitud de permiso fue aprobada',
        'heading' => 'Solicitud de permiso aprobada',
        'body' => 'Buenas noticias: tu solicitud de permiso ha sido aprobada.',
    ],

    'leave_rejected' => [
        'subject' => 'Tu solicitud de permiso fue rechazada',
        'heading' => 'Solicitud de permiso rechazada',
        'body' => 'Tu solicitud de permiso ha sido rechazada. Contacta a tu supervisor si tienes dudas.',
    ],

    'document_signature_requested' => [
        'subject' => 'Un documento espera tu firma',
        'heading' => 'Firma solicitada',
        'body' => 'Se ha publicado un documento que requiere tu firma. Por favor revísalo y fírmalo.',
        'document' => 'Documento',
        'type' => 'Tipo',
    ],

    'mark_modification_requested' => [
        'subject' => 'Corrección de marca pendiente de tu revisión',
        'heading' => 'Corrección de marca solicitada',
        'body' => 'Se ha solicitado una corrección a una de tus marcas de asistencia con la siguiente información:',
        'mark_type' => 'Marca',
        'original' => 'Marca original',
        'no_mark' => 'Sin marca',
        'new' => 'Nueva hora',
        'reason' => 'Motivo',
        'notes' => 'Notas',
        'auto_approve' => 'De no aprobarla o rechazarla, esta solicitud será aprobada automáticamente en 48 horas.',
        'action' => 'Revisar solicitud',
    ],
];
