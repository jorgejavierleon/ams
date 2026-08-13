<?php

return [
    'auth_profile_updated' => [
        'subject' => 'Datos de perfil actualizados',
        'heading' => 'Perfil actualizado',
        'body' => 'Tu información de perfil (email, email personal o clave) ha sido actualizada con éxito.',
        'warning' => 'Si no realizaste esta acción o crees que es un error, por favor contacta a tu administrador.',
    ],

    'password_reset' => [
        'subject' => 'Restablece tu contraseña',
        'heading' => 'Restablece tu contraseña',
        'body' => 'Recibes este correo porque se solicitó restablecer la contraseña de tu cuenta.',
        'action' => 'Crear una contraseña nueva',
        'expiry' => 'El enlace vence en :minutes minutos.',
        'warning' => 'Si no solicitaste esto, no necesitas hacer nada: tu contraseña actual sigue funcionando.',
    ],

    'mark_created' => [
        'subject' => 'Comprobante de marca de asistencia',
        'heading' => 'Marca registrada',
        'body' => 'Se ha registrado tu marca de asistencia con los siguientes datos:',
        'folio' => 'N° comprobante',
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

    'overtime_pact' => [
        'employee' => 'Empleado',
        'end_date' => 'Fecha de término',
    ],

    'overtime_pact_nearing_expiry' => [
        'subject' => 'Un pacto de horas extraordinarias está por vencer',
        'heading' => 'Pacto próximo a vencer',
        'body' => 'El pacto de horas extraordinarias de :employee vence el :date. Si corresponde renovarlo, créalo con anticipación para no dejar el período sin cobertura.',
        'action' => 'Ver pactos',
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

    'document_signature_verification_code' => [
        'subject' => 'Tu código de firma electrónica',
        'heading' => 'Código de verificación',
        'body' => 'Usa el siguiente código para firmar electrónicamente el documento. No lo compartas con nadie.',
        'document' => 'Documento',
        'expiry' => 'El código vence en 15 minutos.',
    ],

    'document_fully_signed' => [
        'subject' => 'Documento firmado por todas las partes',
        'heading' => 'Documento firmado',
        'body' => 'Todas las partes han firmado el documento. Ya puedes descargar la copia firmada desde tus documentos.',
        'document' => 'Documento',
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
