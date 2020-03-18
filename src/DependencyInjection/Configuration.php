<?php

namespace Codefog\InstagramBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('codefog_instagram');
        $treeBuilder->getRootNode()
            ->children()
                ->integerNode('cache_ttl')->defaultValue(3600)->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
