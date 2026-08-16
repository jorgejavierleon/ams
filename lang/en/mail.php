<?php

return [
    'auth_profile_updated' => [
        'subject' => 'Profile details updated',
        'heading' => 'Profile updated',
        'body' => 'Your profile information (email, personal email or password) has been updated successfully.',
        'warning' => 'If you did not perform this action or believe this is a mistake, please contact your administrator.',
    ],

    'password_reset' => [
        'subject' => 'Reset your password',
        'heading' => 'Reset your password',
        'body' => 'You are receiving this email because a password reset was requested for your account.',
        'action' => 'Create a new password',
        'expiry' => 'The link expires in :minutes minutes.',
        'warning' => 'If you did not request this, no action is needed: your current password keeps working.',
    ],

    'mark_created' => [
        'subject' => 'Attendance mark receipt',
        'heading' => 'Mark registered',
        'body' => 'Your attendance mark has been registered with the following details:',
        'folio' => 'Receipt no.',
        'type' => 'Type',
        'date_time' => 'Date and time',
        'checksum' => 'Verification code',
    ],

    'leave' => [
        'type' => 'Type',
        'dates' => 'Dates',
        'days' => 'Business days',
        'action_my_leaves' => 'View my leaves',
    ],

    'leave_submitted' => [
        'subject' => 'New leave request awaiting your review',
        'heading' => 'Leave request submitted',
        'body' => ':employee has submitted a leave request that needs your review.',
        'action' => 'Review request',
    ],

    'overtime_pact' => [
        'employee' => 'Employee',
        'end_date' => 'End date',
    ],

    'overtime_request' => [
        'date' => 'Date',
        'hours' => 'Requested hours',
        'action_my_requests' => 'View my requests',
    ],

    'overtime_request_submitted' => [
        'subject' => 'New overtime request pending review',
        'heading' => 'Overtime request submitted',
        'body' => ':employee has submitted an overtime request that needs your review.',
        'action' => 'Review request',
    ],

    'overtime_request_approved' => [
        'subject' => 'Your overtime request was approved',
        'heading' => 'Overtime request approved',
        'body' => 'Good news: your overtime request has been approved.',
    ],

    'overtime_request_rejected' => [
        'subject' => 'Your overtime request was rejected',
        'heading' => 'Overtime request rejected',
        'body' => 'Your overtime request has been rejected. This does not stop you from working that day, but those hours will not arrive at the review queue with a prior request behind them.',
    ],

    'overtime_pact_nearing_expiry' => [
        'subject' => 'An overtime pacto is about to expire',
        'heading' => 'Pacto nearing expiry',
        'body' => ":employee's overtime pacto expires on :date. If it should be renewed, create the new agreement ahead of time so the period is not left uncovered.",
        'action' => 'View pactos',
    ],

    'leave_approved' => [
        'subject' => 'Your leave request was approved',
        'heading' => 'Leave request approved',
        'body' => 'Good news — your leave request has been approved.',
    ],

    'leave_rejected' => [
        'subject' => 'Your leave request was rejected',
        'heading' => 'Leave request rejected',
        'body' => 'Your leave request has been rejected. Please contact your supervisor if you have questions.',
    ],

    'document_signature_requested' => [
        'subject' => 'A document is awaiting your signature',
        'heading' => 'Signature requested',
        'body' => 'A document has been published and requires your signature. Please review and sign it.',
        'document' => 'Document',
        'type' => 'Type',
    ],

    'document_signature_verification_code' => [
        'subject' => 'Your electronic signature code',
        'heading' => 'Verification code',
        'body' => 'Use the following code to electronically sign the document. Do not share it with anyone.',
        'document' => 'Document',
        'expiry' => 'The code expires in 15 minutes.',
    ],

    'document_fully_signed' => [
        'subject' => 'Document signed by all parties',
        'heading' => 'Document signed',
        'body' => 'All parties have signed the document. You can now download the signed copy from your documents.',
        'document' => 'Document',
    ],

    'mark_modification_requested' => [
        'subject' => 'Attendance mark correction awaiting your review',
        'heading' => 'Mark correction requested',
        'body' => 'A correction to one of your attendance marks has been requested with the following details:',
        'mark_type' => 'Mark',
        'original' => 'Original mark',
        'no_mark' => 'No mark',
        'new' => 'New time',
        'reason' => 'Reason',
        'notes' => 'Notes',
        'auto_approve' => 'If you do not approve or decline it, this request will be approved automatically in 48 hours.',
        'action' => 'Review request',
    ],
];
