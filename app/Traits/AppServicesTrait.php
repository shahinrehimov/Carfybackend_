<?php

namespace App\Traits;

trait AppServicesTrait
{
    private ?int $limit = null;

    public function setLimit(?int $limit = null): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function getLimit(): int
    {
        return $this->limit ?? 10;
    }
}
