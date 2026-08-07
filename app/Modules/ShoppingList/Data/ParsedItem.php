<?php

namespace App\Modules\ShoppingList\Data;

/**
 * Das Ergebnis von ItemTextParser: aus "2 l Vollmilch, kalt stellen" wird
 * Name, Menge, Einheit und Notiz.
 */
class ParsedItem
{
    public function __construct(
        public readonly string $name,
        public readonly ?float $amount = null,
        public readonly ?string $amountRaw = null,
        public readonly ?string $unit = null,
        public readonly ?string $note = null,
    ) {}

    /**
     * Dieselbe Eingabe mit einer anderen Notiz -- fuer Formulare, die die
     * Notiz in einem eigenen Feld fuehren statt hinter einem Komma.
     */
    public function mitNotiz(?string $note): self
    {
        return new self(
            name: $this->name,
            amount: $this->amount,
            amountRaw: $this->amountRaw,
            unit: $this->unit,
            note: $note,
        );
    }

    /**
     * Als Attributsatz fuer einen Listeneintrag.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'raw_name' => $this->name,
            'amount' => $this->amount,
            'amount_raw' => $this->amountRaw,
            'unit' => $this->unit,
            'note' => $this->note,
        ];
    }
}
