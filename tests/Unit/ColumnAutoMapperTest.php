<?php

use App\Actions\Imports\ColumnAutoMapper;
use App\Enums\ImportFieldType;
use App\Support\Imports\ImportField;

test('the higher-scoring header wins a contested field, the lower scorer stays Unmapped', function () {
    // Both headers score >= the 0.6 threshold against 'second_last_name'
    // ('Segundo apellido paterno' token-overlaps at 2/3 ≈ 0.667, 'Segundo
    // apellido' is an exact match at 1.0) — deliberately listed with the
    // lower scorer first so a first-wins bug wouldn't pass this test.
    $fields = [
        new ImportField(name: 'second_last_name', label: 'Segundo apellido', type: ImportFieldType::String),
    ];

    $result = (new ColumnAutoMapper)->map(['Segundo apellido paterno', 'Segundo apellido'], $fields);

    expect($result[0])->toMatchArray(['targetField' => null, 'status' => 'unmapped'])
        ->and($result[1])->toMatchArray(['targetField' => 'second_last_name', 'status' => 'mapped']);
});

test('a blank or null header never gets an auto-mapping guess', function () {
    $fields = [
        new ImportField(name: 'first_name', label: 'Nombre', type: ImportFieldType::String),
    ];

    $result = (new ColumnAutoMapper)->map([null, '   ', 'Nombre'], $fields);

    expect($result[0])->toMatchArray(['sourceHeaderLabel' => null, 'targetField' => null, 'status' => 'unmapped'])
        ->and($result[1])->toMatchArray(['sourceHeaderLabel' => '   ', 'targetField' => null, 'status' => 'unmapped'])
        ->and($result[2])->toMatchArray(['sourceHeaderLabel' => 'Nombre', 'targetField' => 'first_name', 'status' => 'mapped']);
});
