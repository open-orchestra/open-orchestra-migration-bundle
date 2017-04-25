<?php

namespace OpenOrchestra\MigrationBundle\Migrations;

use Doctrine\MongoDB\Database;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170216094224 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @return string
     */
    public function getDescription()
    {
        return "Update sites from 1.2 to 2.0";
    }

    /**
     * @param Database $db
     */
    public function up(Database $db)
    {
        $configSiteMigration = $this->container->getParameter('open_orchestra_migration.site_configuration');
        $configTemplate = $configSiteMigration['template_configuration'];

        $this->write(' + Move metaIndex and metaFollow properties in alias');
        $db->execute('
            db.site.find().snapshot().forEach(function(item) {
                var aliases = item.aliases;
                for (var i in aliases) {
                    var alias = aliases[i];
                    alias.metaIndex = item.metaIndex;
                    alias.metaFollow = item.metaFollow;
                }

                db.site.update({ _id: item._id }, item);
            });
        ');

        $this->write(' + Adding properties templateSet and templateNodeRoot');

        $db->execute('
            var configTemplate = '.json_encode($configTemplate).';
            db.site.find().snapshot().forEach(function(item) {
                var templateSet = configTemplate["defaultTemplateSet"];
                var templateNodeRoot = configTemplate["defaultTemplateNodeRoot"];
                var siteId = item.siteId;
                if (typeof configTemplate["specificTemplate"][siteId] !== "undefined") {
                    templateSet = configTemplate["specificTemplate"][siteId]["templateSet"];
                    templateNodeRoot = configTemplate["specificTemplate"][siteId]["templateNodeRoot"];
                }

                item.templateSet = templateSet;
                item.templateNodeRoot = templateNodeRoot;

                db.site.update({ _id: item._id }, item);
            });
        ');

        $this->write(' + Remove properties metaKeywords, theme');
        $db->execute('
            db.site.find().snapshot().forEach(function(item) {
                 if (item.metaKeywords) {
                    delete item.metaKeywords;
                 }
                 if (item.theme) {
                    delete item.theme;
                 }

                 db.site.update({ _id: item._id }, item);
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
