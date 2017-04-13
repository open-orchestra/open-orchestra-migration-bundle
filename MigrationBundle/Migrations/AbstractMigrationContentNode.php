<?php

namespace OpenOrchestra\MigrationBundle\Migrations;

use AntiMattr\MongoDB\Migrations\AbstractMigration;
use Doctrine\MongoDB\Database;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Class AbstractMigrationContentNode
 */
abstract class AbstractMigrationContentNode extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

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
