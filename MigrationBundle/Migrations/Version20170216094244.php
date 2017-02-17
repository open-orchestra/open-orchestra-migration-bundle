<?php

namespace OpenOrchestra\MigrationBundle\Migrations;

use AntiMattr\MongoDB\Migrations\AbstractMigration;
use Doctrine\MongoDB\Database;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Class Version20170216094244
 */
class Version20170216094244 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @return string
     */
    public function getDescription()
    {
        return "Migration node 1.2 to 2.0";
    }

    /**
     * @inheritdoc
     */
    public function up(Database $db)
    {
        $configNodeMigration = $this->container->getParameter('open_orchestra_migration.node_configuration');
        $templateSetConfig = $this->container->get('open_orchestra_backoffice.manager.template')->getTemplateSetParameters();

        $this->write(' + Adding the template');
        $configTemplate = $configNodeMigration['template_configuration'];
        $this->upTemplate($db, $configTemplate);

        $this->write(' + Adding the version name');
        $this->upVersionName($db);

        $this->write(' + Change status of published node not currentlyPublished in offline status');
        $this->upPublishedNode($db);

        $this->write(' + Update storage blocks and areas');
        $this->upAreasNode($db, $templateSetConfig);

        $this->write(' + Removing unused properties (boLabel, templateId, currentlyPublished, status.fromRoles, status.toRoles, metaKewyords, blocks, rootArea)');
        $this->upRemoveUnusedProperties($db);

        $this->write(' + Removing transverse node');
        $this->upRemoveTransverseNode($db);

        $this->write(' + Update path and position of error nodes');
        $this->upPathErrorNode($db);
    }


    /**
     * @param Database $db
     * @param array    $templateSetConfig
     */
    protected function upAreasNode(Database $db, array $templateSetConfig)
    {
        $db->execute(
            $this->getFindAreaFunction().'

            '.$this->getBlockRefIdFunction().'

            '.$this->getBlockRefFunction().'

            '.$this->getNewAreaStorageFunction().'

            var templateSetConfig = '.json_encode($templateSetConfig).';
            var sharedBlocks = {};

            db.node.find({"nodeType": { $ne: "general"} }).forEach(function(item) {
                var site = db.site.findOne({"siteId": item.siteId});
                var templateSet = site.templateSet;

                // get editable areas defined in template set
                if (typeof templateSetConfig[templateSet] !== "undefined" &&
                    typeof templateSetConfig[templateSet]["templates"][item.template] !== "undefined"
                ) {
                    var areas = {};
                    var editableAreas = templateSetConfig[templateSet]["templates"][item.template]["areas"];
                    for(i in editableAreas) {
                        var areaId = editableAreas[i];
                        var area = getNewAreaStorage(areaId, item);
                        areas[areaId] = area;
                    }
                    item.areas = areas;
                    db.node.update({ _id: item._id }, item);
                }
            });
        ');
    }

    /**
     * Function to find area in all areas of node
     *
     * @return string
     */
    protected function getFindAreaFunction()
    {
        return '
            var findArea = function(area, areaId) {
                if (area.areaId === areaId) {
                    return area;
                }
                if (typeof area.subAreas != \'undefined\') {
                    var areas = area.subAreas;
                    for (var i in areas) {
                        var subAreas = areas[i];
                        var res = findArea(subAreas, areaId);
                        if (res !== null) {
                            return res;
                        }
                    }
                }

                return null;
            }
        ';
    }

    /**
     * Create block in collection block and return dbRef
     * @return string
     */
    protected function getBlockRefFunction()
    {
        return '
            var getBlockRef = function(blockPosition, node) {
                var dbRefBlock = null;
                if ("transverse" == blockPosition.nodeId) {
                    var nodeTransverse = db.node.findOne({"nodeId": "transverse", "language": node.language, "siteId": node.siteId});
                    if (typeof nodeTransverse !== \'undefined\') {
                        dbRefBlock = getBlockRefId(blockPosition.blockId, nodeTransverse, true);
                    }
                }
                else if (0 == blockPosition.nodeId) {
                    dbRefBlock = getBlockRefId(blockPosition.blockId, node, false);
                }

                return dbRefBlock;
            }
        ';
    }

    /**
     * Find all old blocks and create an entity block for each
     * Storage DBRef of new block in area
     *
     * @return string
     */
    protected function getNewAreaStorageFunction()
    {
        return '
            var getNewAreaStorage = function (areaId, node) {
                var rootArea = node.rootArea;
                var area = { "blocks": [] };
                if (typeof rootArea !== "undefined") {
                    var oldArea = findArea(rootArea, areaId);
                    if (typeof oldArea.blocks != "undefined") {
                        var blocks = oldArea.blocks;
                        for (var i in blocks) {
                            var blockPosition = blocks[i];
                            var dbRefBlock = getBlockRef(blockPosition, node);

                            if (null !== dbRefBlock) {
                                area.blocks.push(dbRefBlock);
                            }
                        }
                    }
                }

                return area;
            }
        ';
    }

    /**
     * Create entity block with old information storage in current node or in
     * transverse node.
     * If is a block transverse , the block is create only once
     *
     * @return string
     */
    protected function getBlockRefIdFunction()
    {
        return '
            // node is current node or nodeTransverse
            var getBlockRefId = function(blockPosition, node, isTransverse) {
                var blockPosition = (blockPosition + 0); // convert NumberLong to Int
                var blockProperties = node.blocks[blockPosition]; // get old properties of block in node (current or transverse)

                // check if properties of block exist
                if (typeof blockProperties !== "undefined") {
                    var tempStorageSharedBlockId = node._id.valueOf()+\'_\'+blockPosition;
                    var dbRefBlockId = null;

                    // check if block is transverse and never created or is not a block transverse
                    if (
                        false === isTransverse ||
                        (true === isTransverse && typeof sharedBlocks[tempStorageSharedBlockId] === "undefined")
                    ) {
                        var block = {
                            "_id": ObjectId(),
                            "component": blockProperties.component,
                            "label": blockProperties.label,
                            "language": node.language,
                            "transverse": isTransverse,
                            "siteId": node.siteId,
                            "attributes": blockProperties.attributes,
                            "createdAt": new Date(),
                            "updatedAt": new Date()
                        };
                        var writeResult = db.block.insert(block);
                        if (1 == writeResult.nInserted) {
                            dbRefBlockId = block._id;
                            if (true === isTransverse) {
                                // storage transverse block is temporary array
                                sharedBlocks[tempStorageSharedBlockId] = block._id;
                            }
                        }
                    } else {
                        // block transverse is already created
                        // find dbRef of transverse block in temporary storage
                        dbRefBlockId = sharedBlocks[tempStorageSharedBlockId];
                    }

                    if (null !== dbRefBlockId) {
                        return new DBRef(\'block\', dbRefBlockId);
                    }
                }

                return null;
            }
        ';
    }

    /**
     * @param Database $db
     */
    protected function upPublishedNode(Database $db)
    {
        $db->execute('
            var offlineStatus = db.status.findOne({"autoUnpublishToState": true});
            if (typeof offlineStatus !== "undefined") {
                db.node.find({"currentlyPublished": false, "status.published": true}).forEach(function(item) {
                    item.status = offlineStatus;
                    db.node.update({ _id: item._id }, item);
                });
            }
        ');
    }

    /**
     * @param Database $db
     */
    protected function upVersionName(Database $db)
    {
        $db->execute('
            db.node.find().forEach(function(item) {
                var date = item.createdAt.getUTCFullYear()+"-"+item.createdAt.getUTCMonth()+"-"+item.createdAt.getUTCDate();
                var time = item.createdAt.getHours()+":"+item.createdAt.getMinutes()+":"+item.createdAt.getSeconds();
                item.versionName = item.name + "_" + date + "_" + time;

                db.node.update({ _id: item._id }, item);
            });
        ');
    }

    /**
     * @param Database $db
     * @param array    $configTemplate
     */
    protected function upTemplate(Database $db, array $configTemplate)
    {
        $db->execute('
            var configTemplate = '.json_encode($configTemplate).';
            db.node.find().forEach(function(item) {
                var template = configTemplate.defaultTemplate;
                for (var i in configTemplate.specificTemplate) {
                    var nodesId = configTemplate.specificTemplate[i];
                    if (typeof nodesId[item.nodeId] !== "undefined") {
                        template = i;
                        break;
                    }
                }
                item.template = template;

                db.node.update({ _id: item._id }, item);
            });
        ');
    }

    /**
     * @param Database $db
     */
    protected function upPathErrorNode(Database $db)
    {
        $db->execute('
            db.node.find({"nodeType": "error"}).forEach(function(item) {
                item.parentId = "-";
                item.path = item.nodeId;
                item.order = -1;

                db.node.update({ _id: item._id }, item);
            });
        ');
    }

   /**
     * @param Database $db
     */
    protected function upRemoveUnusedProperties(Database $db)
    {
        $db->execute('
            db.node.find().forEach(function(item) {
                 if (item.boLabel) {
                    delete item.boLabel;
                 }
                 if (item.templateId) {
                    delete item.templateId;
                 }
                 if (item.currentlyPublished) {
                    delete item.currentlyPublished;
                 }
                 if (item.status.fromRoles) {
                    delete item.status.fromRoles;
                 }
                 if (item.status.toRoles) {
                    delete item.status.toRoles;
                 }
                 if (item.status.metaKeywords) {
                    delete item.status.metaKeywords;
                 }
                 if (item.rootArea) {
                    delete item.rootArea;
                 }
                 if (item.blocks) {
                    delete item.blocks;
                 }

                 db.node.update({ _id: item._id }, item);
            });
        ');
    }

   /**
     * @param Database $db
     */
    protected function upRemoveTransverseNode(Database $db)
    {
        $db->execute('
            db.node.remove({"nodeType": "general"});
        ');
    }

    /**
     * @param Database $db
     */
    public function down(Database $db){
        $db->execute('
            db.node.find().forEach(function(item) {
                 db.node.update({ _id: item._id }, item);
            });
        ');
    }
}
