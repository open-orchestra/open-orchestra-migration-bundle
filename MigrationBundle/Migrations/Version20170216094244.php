<?php

namespace OpenOrchestra\MigrationBundle\Migrations;

use Doctrine\MongoDB\Database;
use Doctrine\ODM\MongoDB\DocumentManager;
use OpenOrchestra\ModelBundle\Document\Block;
use OpenOrchestra\ModelBundle\Document\Node;
use OpenOrchestra\ModelInterface\Model\NodeInterface;
use OpenOrchestra\ModelInterface\Model\SiteInterface;
use OpenOrchestra\ModelInterface\Model\StatusInterface;
use OpenOrchestra\ModelInterface\Repository\NodeRepositoryInterface;

/**
 * Class Version20170216094244
 */
class Version20170216094244 extends AbstractMigrationContentNode
{
    /**
     * @return string
     */
    public function getDescription()
    {
        return "Update node from 1.2 to 2.0";
    }

    /**
     * @inheritdoc
     */
    public function up(Database $db)
    {
        $this->checkRequirements();

        $configNodeMigration = $this->container->getParameter('open_orchestra_migration.node_configuration');
        $templateSetConfig = $this->container->get('open_orchestra_backoffice.manager.template')->getTemplateSetParameters();
        $dm = $this->container->get('doctrine.odm.mongodb.document_manager');
        $nodeRepository = $this->container->get('open_orchestra_model.repository.node');
        $referenceManager = $this->container->get('open_orchestra_backoffice.reference.manager');

        $this->write(' + Adding the template');
        $configTemplate = $configNodeMigration['template_configuration'];
        $this->checkExecute($this->upTemplate($db, $configTemplate));

        $this->write(' + Adding the version name and convert version to string');
        $this->checkExecute($this->upVersionName($db, 'node'));

        $this->write(' + Change status of published node not currentlyPublished in offline status');
        $this->checkExecute($this->upPublishedEntity($db, 'node'));

        $this->write(' + Update storage blocks and areas');
        $this->checkExecute($this->upAreasNode($db, $templateSetConfig));

        $this->write(' + Removing unused properties (boLabel, templateId, currentlyPublished, status.fromRoles, status.toRoles, metaKewyords, blocks, rootArea)');
        $this->checkExecute($this->upRemoveUnusedProperties($db));

        $this->write(' + Removing transverse node');
        $this->checkExecute($this->upRemoveTransverseNode($db));

        $this->write(' + Update path and position of error nodes');
        $this->upPathErrorNode($db);

        $sites = $this->container->get('open_orchestra_model.repository.site')->findByDeleted(false);

        /** @var SiteInterface $site */
        foreach ($sites as $site) {
            $siteId = $site->getSiteId();
            $nodeIds = $this->getNodeIdsInSite($siteId, $dm);
            $languages = $site->getLanguages();

            $this->write(' + Create missing node language of site '. $siteId);
            foreach ($nodeIds as $nodeId) {
                $missingLanguages = $this->searchMissingNodeLanguage($nodeId, $languages, $siteId, $nodeRepository);
                if (count($missingLanguages) > 0) {
                    $this->createMissingNodeLanguage($missingLanguages, $nodeId, $siteId, $nodeRepository, $dm);
                }
            }

            $this->write(' + Create missing error language of site '. $siteId);
            $errorNodeIds = $configNodeMigration['error_node_ids'];
            $status = $this->container->get('open_orchestra_model.repository.status')->findOneByTranslationState();
            foreach ($errorNodeIds as $errorNodeId) {
                if (0 === $this->countErrorNode($errorNodeId, $siteId, $dm)) {
                    $this->createErrorNode($errorNodeId, $siteId, $languages, $site->getTemplateNodeRoot(), $status, $dm);
                }
            }
        }

        $this->write(' + Update use references of nodes');
        $this->updateUseReferenceEntity(Node::class, $dm, $referenceManager);

        $this->write(' + Update use references of blocks');
        $this->updateUseReferenceEntity(Block::class, $dm, $referenceManager);
    }

    /**
     * @param string          $errorNodeId
     * @param string          $siteId
     * @param array           $languages
     * @param string          $template
     * @param StatusInterface $status
     * @param DocumentManager $dm
     */
    protected function createErrorNode(
        $errorNodeId,
        $siteId,
        array $languages,
        $template,
        StatusInterface $status,
        DocumentManager $dm
    ) {
        $nodeManager = $this->container->get('open_orchestra_backoffice.manager.node');
        foreach ($languages as $language) {
            $errorNode = $nodeManager->createNewErrorNode($errorNodeId, $errorNodeId, NodeInterface::ROOT_PARENT_ID, $siteId, $language, $template);
            $errorNode->setStatus($status);
            $dm->persist($errorNode);
        }

        $dm->flush();
    }

    /**
     * @param string          $errorNodeId
     * @param string          $siteId
     * @param DocumentManager $dm
     *
     * @return int
     */
    protected function countErrorNode($errorNodeId, $siteId, DocumentManager $dm)
    {
        $qb = $dm->createQueryBuilder(Node::class);
        $qb->field('deleted')->equals(false)
            ->field('siteId')->equals($siteId)
            ->field('nodeId')->equals($errorNodeId)
            ->field('nodeType')->equals(NodeInterface::TYPE_ERROR);

        return $qb->getQuery()->count();
    }

    /**
     * @param array                   $missingLanguages
     * @param string                  $nodeId
     * @param string                  $siteId
     * @param NodeRepositoryInterface $nodeRepository
     * @param DocumentManager         $dm
     */
    protected function createMissingNodeLanguage(
        array $missingLanguages,
        $nodeId,
        $siteId,
        NodeRepositoryInterface $nodeRepository,
        DocumentManager $dm
    ) {
        $originalNode = $nodeRepository->findOneByNodeAndSite($nodeId, $siteId);
        foreach ($missingLanguages as $missingLanguage) {
            $newNode = $this->container->get('open_orchestra_backoffice.manager.node')->createNewLanguageNode($originalNode, $missingLanguage);
            $dm->persist($newNode);
        }
        $dm->flush();
    }

    /**
     * @param string $nodeId
     * @param array  $languages
     * @param string $siteId
     * @param NodeRepositoryInterface $nodeRepository
     *
     * @return array
     */
    protected function searchMissingNodeLanguage($nodeId, array $languages, $siteId, NodeRepositoryInterface $nodeRepository)
    {
        $missingLanguages = [];
        foreach ($languages as $language) {
            if ($nodeRepository->countNotDeletedVersions($nodeId, $language, $siteId) === 0) {
                $missingLanguages[] = $language;
            }
        }

        return $missingLanguages;
    }

    /**
     * @param string          $siteId
     * @param DocumentManager $dm
     *
     * @return array
     * @throws \Doctrine\ODM\MongoDB\MongoDBException
     */
    protected function getNodeIdsInSite($siteId, DocumentManager $dm)
    {
        $qb = $dm->createQueryBuilder(Node::class)->hydrate(false);
        $qb->field('deleted')->equals(false)
            ->field('siteId')->equals($siteId)
            ->distinct('nodeId');

        return $qb->getQuery()->execute()->toArray();
    }

    /**
     * @param Database $db
     * @param array    $templateSetConfig
     *
     * @return array
     */
    protected function upAreasNode(Database $db, array $templateSetConfig)
    {
        return $db->execute(
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
                    if (oldArea !== null && typeof oldArea.blocks != "undefined") {
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
     * @param array    $configTemplate
     *
     * @return array
     */
    protected function upTemplate(Database $db, array $configTemplate)
    {
        return $db->execute('
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
     *
     * @return array
     */
    protected function upPathErrorNode(Database $db)
    {
        return $db->execute('
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
     *
     * @return array
     */
    protected function upRemoveUnusedProperties(Database $db)
    {
        return $db->execute('
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
     *
     * @return array
     */
    protected function upRemoveTransverseNode(Database $db)
    {
        return $db->execute('
            db.node.remove({"nodeType": "general"});
        ');
    }

    /**
     * @param Database $db
     */
    public function down(Database $db)
    {
        $this->write('There is no down method for this migration');
    }

    /**
     * Check requirements for the migration
     */
    protected function checkRequirements()
    {
        $statusRepository = $this->container->get('open_orchestra_model.repository.status');
        $this->abortIf((null === $statusRepository->findOnebyAutoUnpublishTo()), "Require offline status");
        $this->abortIf((null === $statusRepository->findOneByTranslationState()), "Require to translate status");

        $siteRepository = $this->container->get('open_orchestra_model.repository.site');
        $sites = $siteRepository->findByDeleted(false);

        /** @var SiteInterface $site */
        foreach ($sites as $site) {
            $this->abortIf((null === $site->getTemplateSet()), "Site " . $site->getSiteId() . " require a template set");
            $this->abortIf((null === $site->getTemplateNodeRoot()), "TemplateNodeRoot of site " . $site->getSiteId() . " must be set");
        }
    }
}
