<?php

declare(strict_types=1);

namespace TravisLogExtractor;

use Keboola\Component\Config\BaseConfig;

class Config extends BaseConfig
{

    private function getOptionalValue(array $keys)
    {
        $value = $this->getValue($keys, '');
        if ($value === '') {
            return null;
        }
        return $value;

    }
    public function getRepo(): string
    {
        return $this->getValue(['parameters', 'repo']);
    }

    public function getToken()
    {
        return $this->getValue(['parameters', '#token']);
    }

    public function getBranch()
    {
        return $this->getOptionalValue(['parameters', 'branch'], false);
    }

    public function getState()
    {
        return $this->getOptionalValue(['parameters', 'state'], false);
    }
}
