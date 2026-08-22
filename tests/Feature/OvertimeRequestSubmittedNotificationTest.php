<?php

use App\Models\Organization;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Notifications\OvertimeRequestSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the submitted-request notification links reviewers to the standalone Solicitudes screen', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id]);
    $overtimeRequest = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $mail = (new OvertimeRequestSubmitted($overtimeRequest))->toMail($employee);

    expect($mail->viewData['url'])->toBe(route('overtime.requests.index'));
});
