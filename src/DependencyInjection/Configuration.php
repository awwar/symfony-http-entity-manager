<?php

declare(strict_types=1);

namespace Awwar\SymfonyHttpEntityManager\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const NAME = 'symfony_http_entity_manager';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $root = new TreeBuilder(self::NAME);

        $root
            ->getRootNode()
                ->children()
                    ->arrayNode('entity_mapping')
                        ->useAttributeAsKey('name')
                        ->normalizeKeys(false)
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('directory')
                                    ->info('Directory containing the entity')
                                    ->cannotBeEmpty()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end();
        ;

        return $root;
    }
}
