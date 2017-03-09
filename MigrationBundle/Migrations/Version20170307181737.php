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

    /**
     * @param ContainerInterface $container
     */
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

    /**
     * @param Database $db
     */
    public function up(Database $db)
    {
        $this->updateFolders($db);
        $this->updateBlocks($db);

        $this->updateMedias($db);
    }

    /**
     * Add alt+legens to tinyMce attributes in blocks
     * alt is taken from media document
     *
     * @param Database $db
     */
    protected function updateBlocks(Database $db)
    {
        $this->write(' + Updating block documents (medias in tinyMce attributes)');

        $this->checkExecute($db->execute('
            RegExp.prototype.execAll = function(string) {
                var results = [], match;
                this.lastIndex = 0;
                while (match = this.exec(string)) {
                    results.push(match);
                }

                return results;
            };

            function getMediaAlt(mediaId, language) {
                var alt = "";
                var mediaFilters = {"_id": ObjectId(mediaId), "alts": {$exists: true}};

                db.media.find(mediaFilters).forEach(function(media) {
                    if (media.alts[language]) {
                        alt = media.alts[language];
                    }
                });

                return alt;
            }

            db.block.find({}).forEach(function(block) {
                var updated = false;

                for (var attributeName in block.attributes) {
                    if (block.attributes.hasOwnProperty(attributeName)
                        && (typeof block.attributes[attributeName] == "string")
                    ) {
                        var pattern = /\[media=([^\]\{]+)\]([^\]]+)\[\/media\]/g;
                        var matches = pattern.execAll(block.attributes[attributeName]);

                        for (var i = 0; i < matches.length; i++) {
                            var format = matches[i][1];
                            var mediaId = matches[i][2];
                            var alt = getMediaAlt(mediaId, block.language);
                            var oldTag = "[media=" + format + "]" + mediaId + "[/media]";
                            var newTag = "[media={\"format\":\"" + format + "\",\"alt\":\"" + alt + "\",\"legend\":\"\"}]"
                                + mediaId + "[/media]";
                            block.attributes[attributeName] = block.attributes[attributeName].replace(oldTag, newTag);
                            updated = true;
                        }
                    }
                }

                if (updated) {
                    db.block.update({_id: block._id}, block);
                }
            });
        '));
    }

    /**
     * Update folder documents
     *
     * @param Database $db
     */
    protected function updateFolders(Database $db)
    {
        $this->write(' + Updating media folder documents');

        $this->createFolderId($db);
        $this->createFolderPath();
    }

    /**
     * Update media documents
     *
     * @param Database $db
     */
    protected function updateMedias(Database $db)
    {
        $this->write(' + Updating media documents');
        $this->write('  + Removing alts');

        $this->checkExecute($db->execute('
            db.media.update({}, {$unset: {alts: ""}}, {multi: true});
        '));
    }

    /**
     * Add folderId to each folder
     * Note that the folderId generated is not like those generated by the Back Office,
     * but as it is unique, its ok
     *
     * @param Database $db
     */
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

    /**
     * Add Path to each folder
     */
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

    /**
     * @param Database $db
     */
    public function down(Database $db)
    {
        $this->write('There is no down method for this migration');
    }
}
