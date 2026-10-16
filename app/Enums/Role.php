<?php

namespace App\Enums;

// SOW §3.13 / MSA Exhibit B.5 — field, office, administrator.
enum Role: string
{
    case Field = 'field';
    case Office = 'office';
    case Administrator = 'administrator';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
