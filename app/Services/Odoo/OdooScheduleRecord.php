<?php

namespace App\Services\Odoo;

class OdooScheduleRecord
{
    public function __construct(private array $attributes) {}
    public function __get(string $key): mixed { return $this->attributes[$key] ?? null; }
    public function __isset(string $key): bool { return isset($this->attributes[$key]); }
    public function toArray(): array { return $this->attributes; }
}
