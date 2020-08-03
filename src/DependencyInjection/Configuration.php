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

        if (\method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            // Backwards compatibility
            $rootNode = $treeBuilder->root('codefog_instagram');
        }

        $rootNode
            ->children()
                ->integerNode('access_token_ttl')->defaultValue(86400)->end()
                ->integerNode('cache_ttl')->defaultValue(3600)->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
