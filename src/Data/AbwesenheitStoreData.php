<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Data;

use Illuminate\Support\Carbon;

readonly class AbwesenheitStoreData
{
    public function __construct(
        public string $email_vertreter,
        public bool $email_delegate,
        public bool $call_forwarding,
        public bool $d3_forwarding,
        public ?string $call_destination = null,
        public ?Carbon $start = null,
        public ?Carbon $end = null,
        public ?string $notice = null,
        public ?string $phone_vertreter = null,
        public ?string $d3_vertreter = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $start = isset($data['start']) && $data['start'] !== null
            ? ($data['start'] instanceof Carbon ? $data['start'] : Carbon::parse($data['start']))
            : null;
        $end = isset($data['end']) && $data['end'] !== null
            ? ($data['end'] instanceof Carbon ? $data['end'] : Carbon::parse($data['end']))
            : null;

        return new self(
            email_vertreter: (string) $data['email_vertreter'],
            email_delegate: (bool) ($data['email_delegate'] ?? false),
            call_forwarding: (bool) ($data['call_forwarding'] ?? false),
            d3_forwarding: (bool) ($data['d3_forwarding'] ?? false),
            call_destination: $data['call_destination'] ?? null,
            start: $start,
            end: $end,
            notice: $data['notice'] ?? null,
            phone_vertreter: $data['phone_vertreter'] ?? null,
            d3_vertreter: $data['d3_vertreter'] ?? null,
        );
    }

    public function phoneVertreterUsername(): string
    {
        return $this->phone_vertreter ?? $this->email_vertreter;
    }

    public function d3VertreterUsername(): string
    {
        return $this->d3_vertreter ?? $this->email_vertreter;
    }

    public function toArray(): array
    {
        return [
            'email_vertreter' => $this->email_vertreter,
            'email_delegate' => $this->email_delegate,
            'call_forwarding' => $this->call_forwarding,
            'd3_forwarding' => $this->d3_forwarding,
            'call_destination' => $this->call_destination,
            'start' => $this->start?->toIso8601String(),
            'end' => $this->end?->toIso8601String(),
            'notice' => $this->notice,
            'phone_vertreter' => $this->phone_vertreter,
            'd3_vertreter' => $this->d3_vertreter,
        ];
    }
}
