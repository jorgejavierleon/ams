<?php

use App\Support\RolePresenter;

it('localizes known role names', function () {
    expect(RolePresenter::roleLabel('employee'))->toBe('Empleado')
        ->and(RolePresenter::roleLabel('supervisor'))->toBe('Supervisor')
        ->and(RolePresenter::roleLabel('admin'))->toBe('Administrador');
});

it('localizes known permission names', function () {
    expect(RolePresenter::permissionLabel('ViewOwn:Mark'))->toBe('Ver marcas propias')
        ->and(RolePresenter::permissionLabel('ApproveTeam:Leave'))->toBe('Aprobar permisos del equipo');
});

it('derives a stable group key from the permission resource', function () {
    expect(RolePresenter::groupKey('ViewOwn:Mark'))->toBe('Mark')
        ->and(RolePresenter::groupKey('ReviewOwn:MarkModification'))->toBe('MarkModification')
        ->and(RolePresenter::groupKey('view_employee'))->toBe('employee')
        ->and(RolePresenter::groupKey('editor'))->toBe('editor');
});

it('localizes known group keys', function () {
    expect(RolePresenter::groupLabel('Mark'))->toBe('Asistencia')
        ->and(RolePresenter::groupLabel('Leave'))->toBe('Permisos y ausencias');
});

it('falls back to a title-cased name for unknown identifiers', function () {
    expect(RolePresenter::roleLabel('regional_manager'))->toBe('Regional Manager')
        ->and(RolePresenter::permissionLabel('view_employee'))->toBe('View Employee')
        ->and(RolePresenter::groupLabel('employee'))->toBe('Employee');
});
