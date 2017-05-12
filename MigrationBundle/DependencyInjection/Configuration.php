<?php

namespace OpenOrchestra\MigrationBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('open_orchestra_migration');

        $rootNode->children()
            ->arrayNode('node_configuration')->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('template_configuration')->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('defaultTemplate')->defaultValue('default')->end()
                            ->arrayNode('specificTemplate')
                                ->useAttributeAsKey('template')
                                ->info('Specific template for a nodeId')
                                ->defaultValue(array(
                                    'default' => array('root'),
                                ))
                                ->prototype('array')
                                    ->useAttributeAsKey('siteId')
                                    ->prototype('array')
                                        ->prototype('scalar')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('error_node_ids')
                        ->defaultValue(array('errorPage404', 'errorPage503'))
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('media_configuration')->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('block_media_field_attribute')
                        ->defaultValue(array("picture"))
                        ->prototype('scalar')->end()
                    ->end()
                    ->arrayNode('content_media_field_type')
                        ->defaultValue(array("orchestra_media"))
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('site_configuration')->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('template_configuration')->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('defaultTemplateSet')->defaultValue('default')->end()
                            ->scalarNode('defaultTemplateNodeRoot')->defaultValue('home')->end()
                            ->arrayNode('specificTemplate')
                            ->useAttributeAsKey('siteId')
                            ->info('Specific templateSet and templateNodeRoot for a siteId')
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('templateSet')->end()
                                    ->scalarNode('templateNodeRoot')->end()
                                ->end()
                            ->end()
                            ->defaultValue(array(
                                '2' => array(
                                    'templateSet'=> 'default',
                                    'templateNodeRoot'=> 'column_left',
                                ),
                            ))
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('site_alias')->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('collections')
                    ->prototype('scalar')->end()
                    ->defaultValue(array(
                        'block',
                        'content',
                        'folder',
                        'media',
                        'node',
                        'redirection',
                        'route_document'))->end()
                ->end()
            ->end();
        return $treeBuilder;
    }
}
