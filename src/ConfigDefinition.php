<?php

declare(strict_types=1);

namespace TravisLogExtractor;

use DateTimeImmutable;
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
                ->scalarNode('since')
                    ->validate()
                    ->ifTrue(function ($s) {
                        $datetime = DateTimeImmutable::createFromFormat('Y-m-d', $s);
                        if ($datetime === false) {
                            return true;
                        }
                        $errors = $datetime->getLastErrors();
                        if (!$errors) {
                            return false;
                        }
                        return $errors['warning_count'] > 0 || $errors['error_count'] > 0;
                    })
                    ->thenInvalid('Invalid date %s')
                    ->end()
                ->end()
            ->end()
        ;
        // @formatter:on
        return $parametersNode;
    }
}
