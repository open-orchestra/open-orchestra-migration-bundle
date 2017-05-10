<?php

namespace OpenOrchestra\MigrationBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class OpenOrchestraMigrationExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('open_orchestra_migration.node_configuration', $config['node_configuration']);
        $container->setParameter('open_orchestra_migration.site_configuration', $config['site_configuration']);
        $container->setParameter('open_orchestra_migration.site_alias', $config['site_alias']);
        $container->setParameter('open_orchestra_migration.media_configuration', $config['media_configuration']);
    }
}
