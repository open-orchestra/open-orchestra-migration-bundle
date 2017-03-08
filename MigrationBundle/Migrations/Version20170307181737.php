<?php

namespace OpenOrchestra\MigrationBundle\Migrations;

use AntiMattr\MongoDB\Migrations\AbstractMigration;
use Doctrine\MongoDB\Database;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use OpenOrchestra\MediaAdmin\FolderEvents;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170307181737 extends AbstractMigration implements ContainerAwareInterface
{
    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return "Update media from 1.2 to 2.0";
    }

    public function up(Database $db)
    {
        $this->updateFolder($db);

        $this->updateMedia($db);
    }

    protected function updateMedia(Database $db)
    {
        $this->write(' + Updating media documents');
        $this->write('  + Removing alts');

        $this->checkExecute($db->execute('
            db.media.update({} , {$unset: {alts: ""}}, {multi: true});
        '));
    }

    protected function updateFolder(Database $db)
    {
        $this->write(' + Updating media folder documents');

        $this->createFolderId($db);
        $this->createFolderPath();
    }

    protected function createFolderId(Database $db)
    {
        $this->write('  + Adding folderId');

        $this->checkExecute($db->execute('
            db.folder.find({}).forEach(function(folder) {
                folder.folderId = folder._id.str;
                db.folder.update({_id: folder._id}, folder);
            });
        '));
    }

    protected function createFolderPath()
    {
        $this->write('  + Adding folderPath');

        $rootFolders = $this->container->get('open_orchestra_media.repository.media_folder')->findBy(array('parent' => null));
        foreach ($rootFolders as $folder) {
            $event = $this->container->get('open_orchestra_media_admin.event.folder_event.factory')->createFolderEvent();
            $event->setFolder($folder);
            $this->container->get('event_dispatcher')->dispatch(FolderEvents::PARENT_UPDATED, $event);
        }
        $this->container->get('object_manager')->flush();
    }

    /**
     * @param array $res
     */
    protected function checkExecute(array $res)
    {
        $message = isset($res["errmsg"]) ? $res["errmsg"] : '';
        $this->abortIf((isset($res['ok']) && $res['ok'] == 0), $message);
    }

    public function down(Database $db)
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
