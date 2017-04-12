<?php

namespace OpenOrchestra\MigrationBundle\Migrations;

use AntiMattr\MongoDB\Migrations\AbstractMigration;
use Doctrine\MongoDB\Database;
use Doctrine\ODM\MongoDB\DocumentManager;
use OpenOrchestra\Backoffice\Reference\ReferenceManager;
use OpenOrchestra\ModelBundle\Document\Block;
use OpenOrchestra\ModelBundle\Document\Content;
use OpenOrchestra\ModelBundle\Document\Node;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Class Version20170412093725
 */
class Version20170412093725 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @return string
     */
    public function getDescription()
    {
        return "Update use reference";
    }

    /**
     * @param Database $db
     */
    public function up(Database $db)
    {
        $dm = $this->container->get('doctrine.odm.mongodb.document_manager');
        $referenceManager = $this->container->get('open_orchestra_backoffice.reference.manager');

        $this->write(' + Update use references of nodes');
        $this->updateUseReferenceEntity(Node::class, $dm, $referenceManager);

        $this->write(' + Update use references of blocks');
        $this->updateUseReferenceEntity(Block::class, $dm, $referenceManager);

        $this->write(' + Update use references of contents');
        $this->updateUseReferenceEntity(Content::class, $dm, $referenceManager);
        die();
    }

    /**
     * @param Database $db
     */
    public function down(Database $db)
    {
        $this->write('There is no down method for this migration');
    }

    /**
     * @param String           $entityClass
     * @param DocumentManager  $dm
     * @param ReferenceManager $referenceManager
     */
    protected function updateUseReferenceEntity($entityClass, DocumentManager $dm, ReferenceManager $referenceManager)
    {
        $timestamp_debut = microtime(true);
        $limit = 20;
        $countEntities = $dm->createQueryBuilder($entityClass)->getQuery()->count();
        for ($skip = 0; $skip < $countEntities; $skip += $limit) {
            $this->write('  - Update references from '.$skip.' to '.($skip+$limit));
            $entities = $dm->createQueryBuilder($entityClass)
                ->skip($skip)
                ->limit($limit)
                ->getQuery()->execute();
            foreach ($entities as $entity) {
                $referenceManager->updateReferencesToEntity($entity);
            }
            $dm->clear();
        }
        $timestamp_fin = microtime(true);
        $difference_ms = $timestamp_fin - $timestamp_debut;
        $this->write($difference_ms);
    }
}
