<?php

declare(strict_types=1);

namespace TravisLogExtractor;

use Keboola\Component\Config\BaseConfig;

class Config extends BaseConfig
{

    public function getRepo(): string
    {
        return $this->getValue(['parameters', 'repo']);
    }

    public function getToken(): string
    {
        return $this->getValue(['parameters', '#token']);
    }

    public function getBranch(): ?string
    {
        return $this->getOptionalValue(['parameters', 'branch']);
    }

    /**
     * @param string[] $keys
     */
    private function getOptionalValue(array $keys): ?string
    {
        $value = $this->getValue($keys, '');
        if ($value === '') {
            return null;
        }
        return $value;
    }

    public function getState(): ?string
    {
        return $this->getOptionalValue(['parameters', 'state']);
    }
}
