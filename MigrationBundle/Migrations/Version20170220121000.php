<?php

namespace OpenOrchestra\MigrationBundle\Migrations;

use Doctrine\MongoDB\Database;

/**
 * Class Version20170220121000
 */
class Version20170220121000 extends AbstractMigrationContentNode
{
    /**
     * @return string
     */
    public function getDescription()
    {
        return "Update content from 1.2 to 2.0";
    }

    /**
     * @param Database $db
     */
    public function up(Database $db)
    {
        $this->write(' + Adding the version name');
        $this->checkExecute($this->upVersionName($db, 'content'));

        $this->write(' + Change status of published content not currentlyPublished in offline status');
        $this->checkExecute($this->upPublishedEntity($db));

        $this->write(' + Removing unused properties (currentlyPublished, status.fromRoles, status.toRoles, contentTypeVersion)');
        $this->checkExecute($this->upRemoveUnusedProperties($db));
    }

    /**
     * @param Database $db
     */
    public function down(Database $db)
    {
        $this->write('There is no down method for this migration');
    }

    /**
     * @param Database $db
     *
     * @return array
     */
    protected function upRemoveUnusedProperties(Database $db)
    {
        return $db->execute('
            db.content.find().snapshot().forEach(function(item) {
                 if (item.currentlyPublished) {
                    delete item.currentlyPublished;
                 }
                 if (item.contentTypeVersion) {
                    delete item.contentTypeVersion;
                 }
                 if (item.status.fromRoles) {
                    delete item.status.fromRoles;
                 }
                 if (item.status.toRoles) {
                    delete item.status.toRoles;
                 }

                 db.content.update({ _id: item._id }, item);
            });
        ');
    }

    /**
     * @param Database $db
     *
     * @return array
     */
    protected function upPublishedEntity(Database $db)
    {
        return $db->execute('
            var offlineStatus = db.status.findOne({"autoUnpublishToState": true});
            if (typeof offlineStatus !== "undefined") {
                db.content.find({"status.publishedState": true}).snapshot().forEach(function(item) {
                    var lastPublished = db.content.find({"status.publishedState": true, "language": item.language, "contentId": item.contentId}).sort({"version": -1}).limit(1).toArray()[0];
                    if (lastPublished._id.str != item._id.str) {
                        item.status = offlineStatus;
                        db.content.update({ _id: item._id }, item);
                    }
                });
            }
        ');
    }

    /**
     * Check requirements for the migration
     */
    protected function checkRequirements()
    {
        $statusRepository = $this->container->get('open_orchestra_model.repository.status');
        $this->abortIf((null === $statusRepository->findOnebyAutoUnpublishTo()), "Require offline status");
    }
}
