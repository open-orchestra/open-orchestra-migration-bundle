<?php

namespace OpenOrchestra\MigrationBundle\Migrations;

use AntiMattr\MongoDB\Migrations\AbstractMigration;
use Doctrine\MongoDB\Database;

/**
 * Class Version20170324153458
 */
class Version20170324153458 extends AbstractMigration
{
    /**
     * @return string
     */
    public function getDescription()
    {
        return "Add siteId to medias";
    }

    /**
     * @param Database $db
     */
    public function up(Database $db)
    {
        $db->execute('
            db.media.find({}).forEach(function(media) {
                var folderId = media.mediaFolder.getId();
                var folder = db.folder.findOne({_id: folderId});
                if (folder !== null) {
                    media.siteId = folder.siteId;
                }
                db.media.update({ _id: media._id }, media);
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
