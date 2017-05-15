<?php

namespace OpenOrchestra\MigrationBundle\Migrations;

use Doctrine\MongoDB\Database;
use OpenOrchestra\MediaAdmin\FolderEvents;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170307181737 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

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
        $this->updateContents($db);
        $this->updateMedias($db);
    }

    /**
     * Add alt+legend to tinyMce attributes in blocks
     * alt is taken from the media document
     *
     * @param Database $db
     */
    protected function updateBlocks(Database $db)
    {
        $this->write(' + Updating block documents');

        $this->write(' + updating medias in tinyMce attributes');

        $this->checkExecute($db->execute(
            $this->getJSFunctions() . '

            db.block.find({}).snapshot().forEach(function(block) {
                var updated = false;

                for (var attributeName in block.attributes) {
                    if (block.attributes.hasOwnProperty(attributeName) && (typeof block.attributes[attributeName] == "string")) {
                        var pattern = /\[media=([^\]\{]+)\]([^\]]+)\[\/media\]|\[media=\{"format":"([^"]+)"\}\]([^\]]+)\[\/media\]/g;
                        var matches = pattern.execAll(block.attributes[attributeName]);

                        for (var i = 0; i < matches.length; i++) {
                            var mediaId = getMediaIdFromMatch(matches[i]);
                            var format = getMediaFormatFromMatch(matches[i]);
                            block.attributes[attributeName] = updateAttribute(block.attributes[attributeName], mediaId, format, getMediaAlt(mediaId, block.language));
                            updated = true;
                        }
                    }
                }

                if (updated) {
                    db.block.update({_id: block._id}, block);
                }
            });
        '));

        $this->write(' + updating medias in block attributes');

        $configMediaFieldType = $this->container->getParameter('open_orchestra_migration.media_configuration');

        $this->checkExecute($db->execute(
            $this->getJSFunctions() . '

            var blockMediaFieldAttribute = '.json_encode($configMediaFieldType['block_media_field_attribute']).';
            db.block.find({}).snapshot().forEach(function(block) {
                var updated = false;
                for (var attributeName in block.attributes) {
                    if (
                        block.attributes.hasOwnProperty(attributeName) &&
                        blockMediaFieldAttribute.indexOf(attributeName) > -1
                    ) {
                        var alt = "";
                        if (
                            null !== block.attributes[attributeName].id &
                            "" !== block.attributes[attributeName].id
                        ) {
                           alt = getMediaAlt(block.attributes[attributeName].id, block.language);
                        }
                        block.attributes[attributeName].alt = alt;
                        block.attributes[attributeName].legend = "";
                        updated = true;
                    }
                }

                if (updated) {
                    db.block.update({_id: block._id}, block);
                }
            });
        '));
    }

    /**
     * Update Content documents
     *
     * @param Database $db
     */
    protected function updateContents(Database $db)
    {
        $this->write(' + Updating content documents');

        $this->updateTinyMCEInContent($db);
        $this->updateMediaInContent($db);
    }

    /**
     * Add alt+legend to tinyMce attributes in contents
     * alt is taken from the media document
     *
     * @param Database $db
     */
    protected function updateTinyMCEInContent(Database $db)
    {
        $this->write('  + Updating medias in tinyMce attributes');

        $this->checkExecute($db->execute(
            $this->getJSFunctions() . '

            db.content.find({}).snapshot().forEach(function(content) {
                var updated = false;

                for (var attributeName in content.attributes) {
                    if (content.attributes.hasOwnProperty(attributeName) && "wysiwyg" == content.attributes[attributeName].type) {
                        var pattern = /\[media=([^\]\{]+)\]([^\]]+)\[\/media\]|\[media=\{"format":"([^"]+)"\}\]([^\]]+)\[\/media\]/g;
                        var matches = pattern.execAll(content.attributes[attributeName].value);

                        for (var i = 0; i < matches.length; i++) {
                            var mediaId = getMediaIdFromMatch(matches[i]);
                            var format = getMediaFormatFromMatch(matches[i]);
                            content.attributes[attributeName].value = updateAttribute(content.attributes[attributeName].value, mediaId, format, getMediaAlt(mediaId, content.language));
                            updated = true;
                        }
                    }
                }

                if (updated) {
                    db.content.update({_id: content._id}, content);
                }
            });
        '));
    }

    /**
     * Update Medias in contents by adding alt and legend
     * alt is taken from the media document
     *
     * @param Database $db
     */
    protected function updateMediaInContent(Database $db)
    {
        $this->write('  + Updating orchestra_media attributes');
        $configMediaFieldType = $this->container->getParameter('open_orchestra_migration.media_configuration');
        $this->checkExecute($db->execute(
            $this->getJSFunctions() . '
            var contentMediaFieldType = '.json_encode($configMediaFieldType['content_media_field_type']).';
            db.content.find({}).snapshot().forEach(function(content) {
                var updated = false;

                for (var attributeName in content.attributes) {
                    if (
                        content.attributes.hasOwnProperty(attributeName) &&
                        contentMediaFieldType.indexOf(content.attributes[attributeName].type) > -1 &&
                        content.attributes[attributeName].hasOwnProperty("value")
                    ) {
                        var alt = "";
                        if (
                            null !== content.attributes[attributeName].value.id &
                            "" !== content.attributes[attributeName].value.id
                        ) {
                            alt = getMediaAlt(content.attributes[attributeName].value.id, content.language);
                        }
                        content.attributes[attributeName].value.alt = alt;
                        content.attributes[attributeName].value.legend = "";
                        updated = true;
                    }
                }

                if (updated) {
                    db.content.update({_id: content._id}, content);
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
        $this->updateFolderNames($db);
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
            db.folder.find({}).snapshot().forEach(function(folder) {
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
            $oldPath = $folder->getPath();
            $folder->setPath('/');
            $event = $this->container->get('open_orchestra_media_admin.event.folder_event.factory')->createFolderEvent();
            $event->setFolder($folder);
            $event->setPreviousPath($oldPath);
            $this->container->get('event_dispatcher')->dispatch(FolderEvents::PATH_UPDATED, $event);
        }

        $this->container->get('object_manager')->flush();
    }

    /**
     * Internationalize Folder name
     *
     * @param Database $db
     */
    protected function updateFolderNames(Database $db)
    {
        $this->write('  + Updating folderNames');

        $this->checkExecute($db->execute('
            var backLanguages = ["'
                . implode('", "', $this->container->getParameter('open_orchestra_base.administration_languages'))
            . '"];

            db.folder.find({}).snapshot().forEach(function(folder) {
                var name = folder.name;

                folder.names = {};
                for (var language in backLanguages) {
                    folder.names[backLanguages[language]] = name;
                }

                delete folder.name;
                db.folder.update({_id: folder._id}, folder);
            });
        '));
    }

    /**
     * Get the JS function required for the migration
     *
     * @return string
     */
    protected function getJSFunctions()
    {
        return '
            /**
             * Return all matching patterns with detailed info, not only the first match
             */
            RegExp.prototype.execAll = function(string) {
                var results = [], match;
                this.lastIndex = 0;
                while (match = this.exec(string)) {
                    results.push(match);
                }

                return results;
            };

            /**
             * Get the alt from mediaId in provided language
             */
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

            /**
             * Update media tags in attribute inserting alt
             */
            function updateAttribute(html, mediaId, format, alt) {
                var oldTag1 = "[media=" + format + "]" + mediaId + "[/media]";
                var oldTag2 = "[media={\"format\":\"" + format + "\"}]" + mediaId + "[/media]";
                var newTag = "[media={\"format\":\"" + format + "\",\"alt\":\"" + alt + "\",\"legend\":\"\"}]" + mediaId + "[/media]";

                html = html.replace(oldTag2, newTag);
                html = html.replace(oldTag1, newTag);

                return html;
            }

            /**
             * Extract media id from match
             */
            function getMediaIdFromMatch(match) {
                var mediaId = match[2];
                if (null == mediaId) {
                    mediaId = match[4];
                }

                return mediaId;
            }

            /**
             * Extract media format from match
             */
            function getMediaFormatFromMatch(match) {
                var format = match[1];
                if (null == format) {
                    format = match[3];
                }

                return format;
            }
        ';
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
