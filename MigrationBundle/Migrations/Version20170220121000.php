<?php

namespace OpenOrchestra\MigrationBundle\Migrations;

use Doctrine\MongoDB\Database;
use OpenOrchestra\ModelBundle\Document\Content;

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
        $dm = $this->container->get('doctrine.odm.mongodb.document_manager');
        $referenceManager = $this->container->get('open_orchestra_backoffice.reference.manager');

        $this->write(' + Adding the version name');
        $this->checkExecute($this->upVersionName($db, 'content'));

        $this->write(' + Change status of published content not currentlyPublished in offline status');
        $this->checkExecute($this->upPublishedEntity($db, 'content'));

        $this->write(' + Update use references of contents');
        $this->updateUseReferenceEntity(Content::class, $dm, $referenceManager);

        $this->write(' + Removing unused properties (currentlyPublished, status.fromRoles, status.toRoles, contentTypeVersion)');
        $this->checkExecute($this->upRemoveUnusedProperties($db));
    }

    /**
     * @param Database $db
     */
    public function down(Database $db){}

    /**
     * @param Database $db
     *
     * @return array
     */
    protected function upRemoveUnusedProperties(Database $db)
    {
        return $db->execute('
            db.content.find().forEach(function(item) {
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
                 if (item.keywords) {
                    delete item.keywords;
                 }

                 db.content.update({ _id: item._id }, item);
            });
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
