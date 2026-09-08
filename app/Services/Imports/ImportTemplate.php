<?php

namespace App\Services\Imports;

use Symfony\Component\HttpFoundation\Response;

/**
 * The downloadable template generator for a given importable resource
 * (KOL-94.8, KOL-107): mirrors {@see ImportSchema} — one implementation per
 * resource, resolved by {@see ImportResourceRegistry} — so
 * ImportWizardController's `template()` action never needs to know a
 * concrete resource's template class.
 */
interface ImportTemplate
{
    /**
     * @return list<string>
     */
    public function formats(): array;

    public function download(string $format): Response;
}
