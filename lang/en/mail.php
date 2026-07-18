<?php

return [
    'auth_profile_updated' => [
        'subject' => 'Profile details updated',
        'heading' => 'Profile updated',
        'body' => 'Your profile information (email, personal email or password) has been updated successfully.',
        'warning' => 'If you did not perform this action or believe this is a mistake, please contact your administrator.',
    ],

    'mark_created' => [
        'subject' => 'Attendance mark receipt',
        'heading' => 'Mark registered',
        'body' => 'Your attendance mark has been registered with the following details:',
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
