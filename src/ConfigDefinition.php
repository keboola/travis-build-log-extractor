<?php

declare(strict_types=1);

namespace TravisLogExtractor;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class ConfigDefinition extends BaseConfigDefinition
{
    protected function getParametersDefinition(): ArrayNodeDefinition
    {
        $parametersNode = parent::getParametersDefinition();
        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $parametersNode
            ->children()
                ->scalarNode('#token')->end()
                ->scalarNode('repo')->end()
                ->scalarNode('branch')->end()
                ->scalarNode('state')->end()
            ->end()
        ;
        // @formatter:on
        return $parametersNode;
    }
}
