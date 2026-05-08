<?php

namespace IconPicker\Icons;

final readonly class Icon
{
    public function __construct(
        public string $id,
        public string $label,
        public string $svg,
    ) {}

    /** @return array{id: string, label: string, svg: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'svg' => $this->svg,
        ];
    }
}
