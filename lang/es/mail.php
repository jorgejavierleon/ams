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

    'overtime_request' => [
        'date' => 'Fecha',
        'hours' => 'Horas solicitadas',
        'action_my_requests' => 'Ver mis solicitudes',
    ],

    'overtime_request_submitted' => [
        'subject' => 'Nueva solicitud de horas extra pendiente de revisión',
        'heading' => 'Solicitud de horas extra enviada',
        'body' => ':employee ha enviado una solicitud de horas extra que requiere tu revisión.',
        'action' => 'Revisar solicitud',
    ],

    'overtime_request_approved' => [
        'subject' => 'Tu solicitud de horas extra fue aprobada',
        'heading' => 'Solicitud de horas extra aprobada',
        'body' => 'Buenas noticias: tu solicitud de horas extra ha sido aprobada.',
    ],

    'overtime_request_rejected' => [
        'subject' => 'Tu solicitud de horas extra fue rechazada',
        'heading' => 'Solicitud de horas extra rechazada',
        'body' => 'Tu solicitud de horas extra ha sido rechazada. Esto no te impide trabajar el día solicitado, pero esas horas no llegarán con una solicitud previa a la cola de revisión.',
    ],

    'overtime_pact_nearing_expiry' => [
        'subject' => 'Un pacto de horas extraordinarias está por vencer',
        'heading' => 'Pacto próximo a vencer',
        'body' => 'El pacto de horas extraordinarias de :employee vence el :date. Si corresponde renovarlo, créalo con anticipación para no dejar el período sin cobertura.',
        'action' => 'Ver pactos',
    ],

    'rest_day_balance_accrued' => [
        'subject' => 'Tus horas de descanso compensatorio y su vencimiento',
        'heading' => 'Saldo de descanso compensatorio',
        'body' => 'Tienes horas extraordinarias compensadas en días de descanso pendientes de usar. Revisa el detalle y sus fechas de vencimiento a continuación.',
        'hours' => 'Horas',
        'expiry_date' => 'Fecha de vencimiento',
        'action' => 'Ver mi saldo',
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

    'report_export_ready' => [
        'subject' => 'Tu reporte está listo para descargar',
        'heading' => 'Reporte listo',
        'body' => 'El reporte que generaste ya está listo.',
        'expiry' => 'El enlace de descarga vence en :minutes minutos.',
        'action' => 'Descargar reporte',
        'note' => 'Debes haber iniciado sesión y tener seleccionado el mismo empleador auditado para poder descargar el archivo.',
    ],

    'report_export_failed' => [
        'subject' => 'No pudimos generar tu reporte',
        'heading' => 'Reporte no generado',
        'body' => 'Ocurrió un error al generar el reporte que solicitaste. Intenta generarlo nuevamente; si el problema persiste, contacta a soporte.',
    ],

    'import_run_completed' => [
        'subject' => 'Tu importación de empleados terminó',
        'heading' => 'Importación completada',
        'body' => 'La importación de empleados que ejecutaste ya terminó de procesarse.',
        'created' => 'Creados: :count',
        'updated' => 'Actualizados: :count',
        'skipped' => 'Omitidos: :count',
        'errored' => 'Con error: :count',
        'action' => 'Ver resultado',
    ],

    'import_run_failed' => [
        'subject' => 'No pudimos completar tu importación de empleados',
        'heading' => 'Importación no completada',
        'body' => 'Ocurrió un error al procesar la importación de empleados que ejecutaste. Puedes revisar el detalle e intentar una nueva importación.',
        'action' => 'Ver detalle',
    ],
];
