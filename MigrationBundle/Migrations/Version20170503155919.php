<?php

namespace OpenOrchestra\MigrationBundle\Migrations;

use Doctrine\MongoDB\Database;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170503155919 extends AbstractMigration
{
    /**
     * @return string
     */
    public function getDescription()
    {
        return "Update trash item";
    }

    /**
     * @param Database $db
     */
    public function up(Database $db)
    {
        $db->execute('
            var distinctItemIds = [];
            db.trash_item.find({}).snapshot().forEach(function(media) {
                var entity = db[item.entity.getCollection()].findOne({_id: item.entity.getId()});
                if (null !== entity) {
                    item.siteId = entity.siteId;
                    if ("node" === item.type) {
                        item.entityId = entity.nodeId;
                    } else if ("content" === item.type) {
                        item.entityId = entity.contentId;
                    }
                    delete item.entity;
                    if (distinctItemIds.indexOf(item.entityId + "#" + item.siteId) === -1 ) {
                        distinctItemIds.push(item.entityId + "#" + item.siteId);
                        db.trash_item.update({ _id: item._id }, item);
                    } else {
                        db.trash_item.remove({ _id: item._id }, item);
                    }
                } else {
                    db.trash_item.remove({ _id: item._id }, item);
                }
            });
        ');
    }

    /**
     * @param Database $db
     */
    public function down(Database $db)
    {
        $this->write('There is no down method for this migration');
    }
}
