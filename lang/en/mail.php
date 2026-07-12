<?php

return [
    'auth_profile_updated' => [
        'subject' => 'Profile details updated',
        'heading' => 'Profile updated',
        'body' => 'Your profile information (email, personal email or password) has been updated successfully.',
        'warning' => 'If you did not perform this action or believe this is a mistake, please contact your administrator.',
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
];
