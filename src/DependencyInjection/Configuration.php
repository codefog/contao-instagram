<?php

namespace Codefog\InstagramBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('codefog_instagram');

        $treeBuilder
            ->getRootNode()
            ->children()
                ->integerNode('access_token_ttl')->defaultValue(86400)->end()
                ->integerNode('cache_ttl')->defaultValue(3600)->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
