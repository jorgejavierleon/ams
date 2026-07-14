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
