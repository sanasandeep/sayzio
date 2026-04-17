<?php

namespace App\Modules\User\Services\Calendar;

class CalendarProviderRegistry
{
    /** @var array<string, callable():CalendarProvider> */
    protected array $factories = [];

    public function register(string $key, callable $factory): void
    {
        $this->factories[$key] = $factory;
    }

    public function get(string $key): CalendarProvider
    {
        if (!isset($this->factories[$key])) {
            throw new CalendarSyncException("Unknown calendar provider [{$key}].");
        }
        return ($this->factories[$key])();
    }

    /** @return string[] */
    public function keys(): array
    {
        return array_keys($this->factories);
    }
}
