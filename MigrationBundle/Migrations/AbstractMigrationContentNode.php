<?php

namespace OpenOrchestra\MigrationBundle\Migrations;

use AntiMattr\MongoDB\Migrations\AbstractMigration;
use Doctrine\MongoDB\Database;
use Doctrine\ODM\MongoDB\DocumentManager;
use OpenOrchestra\Backoffice\Reference\ReferenceManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Class AbstractMigrationContentNode
 */
abstract class AbstractMigrationContentNode extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @param String           $entityClass
     * @param DocumentManager  $dm
     * @param ReferenceManager $referenceManager
     */
    protected function updateUseReferenceEntity($entityClass, DocumentManager $dm, ReferenceManager $referenceManager)
    {
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
        }
    }

    /**
     * @param Database $db
     * @param string   $collection
     *
     * @return array
     */
    protected function upPublishedEntity(Database $db, $collection)
    {
        return $db->execute('
            var offlineStatus = db.status.findOne({"autoUnpublishToState": true});
            if (typeof offlineStatus !== "undefined") {
                db.'.$collection.'.find({"currentlyPublished": false, "status.published": true}).forEach(function(item) {
                    item.status = offlineStatus;
                    db.'.$collection.'.update({ _id: item._id }, item);
                });
            }
        ');
    }

    /**
     * @param Database $db
     * @param string   $collection
     *
     * @return array
     */
    protected function upVersionName(Database $db, $collection)
    {
        return $db->execute('
            db.'.$collection.'.find().forEach(function(item) {
                var date = item.createdAt.getUTCFullYear()+"-"+item.createdAt.getUTCMonth()+"-"+item.createdAt.getUTCDate();
                var time = item.createdAt.getHours()+":"+item.createdAt.getMinutes()+":"+item.createdAt.getSeconds();
                item.versionName = item.name + "_" + date + "_" + time;

                item.version = item.version + "";

                db.'.$collection.'.update({ _id: item._id }, item);
            });
        ');
    }

    /**
     * @param array $res
     */
    protected function checkExecute(array $res)
    {
        $message = isset($res["errmsg"]) ? $res["errmsg"] : '';
        $this->abortIf((isset($res['ok']) && $res['ok'] == 0), $message);
    }
}
