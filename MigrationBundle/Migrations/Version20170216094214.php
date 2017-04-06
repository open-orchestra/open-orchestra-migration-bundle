<?php

namespace OpenOrchestra\MigrationBundle\Migrations;

use AntiMattr\MongoDB\Migrations\AbstractMigration;
use Doctrine\MongoDB\Database;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170216094214 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @return string
     */
    public function getDescription()
    {
        return "Update status from 1.2 to 2.0";
    }

    /**
     * @param Database $db
     */
    public function up(Database $db)
    {
        $this->write(' + Rename published to publishedState');
        $db->execute('db.status.update( {}, { $rename: { "published": "publishedState" } }, { multi: true } )');

        $this->write(' + Rename initial to initialState');
        $db->execute('db.status.update( {}, { $rename: { "initial": "initialState" } }, { multi: true } )');

        $this->write(' + Rename autoPublishFrom to autoPublishFromState');
        $db->execute('db.status.update( {}, { $rename: { "autoPublishFrom": "autoPublishFromState" } }, { multi: true } )');

        $this->write(' + Rename autoUnpublishTo to autoUnpublishToState');
        $db->execute('db.status.update( {}, { $rename: { "autoUnpublishTo": "autoUnpublishToState" } }, { multi: true } )');

        $this->write(' + Adding property translationState');
        $db->execute('db.status.update( {}, { $set: { "translationState": false } }, { multi: true } )');

        $statusRepository = $this->container->get('open_orchestra_model.repository.status');

        $offlineState = $statusRepository->findOnebyAutoUnpublishTo();
        if (null === $offlineState) {
            $this->write(' + Adding offline status');
            $db->execute('db.status.insert([{
                name: "offline",
                labels: {
                    "en": "Offline",
                    "fr": "Hors ligne"
                },
                initialState: false,
                publishedState: false,
                autoPublishFromState: false,
                autoUnpublishToState: true,
                blockedEdition: false,
                outOfWorkflow: false,
                translationState: false,
                displayColor: "dark-grey",
            }] )');

        }

        $translationState = $statusRepository->findOneByTranslationState();

        if (null === $translationState) {
            $this->write(' + Adding to translate status');
            $db->execute('db.status.insert([{
                name: "toTranslate",
                labels: {
                    "en": "To translate",
                    "fr": "A traduire"
                },
                initialState: false,
                publishedState: false,
                autoPublishFromState: false,
                autoUnpublishToState: false,
                blockedEdition: false,
                outOfWorkflow: false,
                translationState: true,
                displayColor: "blue"
            }] )');
        }

        $this->write(' + Update embedded status in node and content');
        $db->execute('
            db.status.find({}).forEach(function(item) {
                db.node.update( {"status._id": item._id}, { $set: { "status": item } }, { multi: true } );
                db.content.update( {"status._id": item._id}, { $set: { "status": item } }, { multi: true } );
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
