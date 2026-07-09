<?php

namespace App\Rules;

use App\Models\ShiftAssignment;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a shift assignment whose date range overlaps an existing assignment
 * for the same employee. An assignment with a null `end_date` is treated as
 * open-ended (runs forever), so any later start collides with it.
 *
 * Attach to the `start_date` field; the rule reads `end_date` from the payload
 * to evaluate the full interval.
 */
class NoOverlappingShift implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    public function __construct(
        protected int $userId,
        protected ?int $ignoreId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $start = $value;
        $end = $this->data['end_date'] ?? null;

        if (! $start) {
            return;
        }

        $overlaps = ShiftAssignment::query()
            ->where('user_id', $this->userId)
            ->when($this->ignoreId, fn ($query) => $query->whereKeyNot($this->ignoreId))
            // Existing assignment starts on or before the new one ends...
            ->when($end, fn ($query) => $query->whereDate('start_date', '<=', $end))
            // ...and ends on or after the new one starts (null end = open-ended).
            ->where(fn ($query) => $query
                ->whereNull('end_date')
                ->orWhereDate('end_date', '>=', $start))
            ->exists();

        if ($overlaps) {
            $fail('ui.shifts.shift_assignments.validation.overlap')->translate();
        }
    }
}
