<?php

namespace App\Exceptions;

use App\Models\OvertimeRequest;
use RuntimeException;

/**
 * An overtime request was asked to leave `pending` in a way that would leave
 * the decision unaccountable.
 *
 * @see OvertimeRequest
 */
class OvertimeRequestDecisionRefused extends RuntimeException
{
    /**
     * Mirrors {@see OvertimeDecisionRefused::withoutAReviewer()}: a request can
     * only carry `approved` or `rejected` with the person who decided it and
     * when, so an unanswered request simply stays pending rather than aging
     * into either outcome.
     */
    public static function withoutAReviewer(): self
    {
        return new self(
            'An overtime request cannot be approved or rejected without the user who decided it and when.'
        );
    }

    /**
     * A rejection without a reason is unanswerable to the employee.
     */
    public static function withoutAReason(): self
    {
        return new self(
            'An overtime request cannot be rejected without a reason.'
        );
    }

    /**
     * Tenant isolation at the write, mirroring
     * {@see OvertimeDecisionRefused::byAnotherTenant()}.
     */
    public static function byAnotherTenant(): self
    {
        return new self(
            'An overtime request can only be decided by a user of the same organization.'
        );
    }
}
