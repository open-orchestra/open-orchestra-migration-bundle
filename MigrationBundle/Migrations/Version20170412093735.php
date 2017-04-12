<?php

namespace OpenOrchestra\ModelBundle\Migrations\MongoDB;

use AntiMattr\MongoDB\Migrations\AbstractMigration;
use Doctrine\MongoDB\Database;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160831150650 extends AbstractMigration
{
    /**
     * @return string
     */
    public function getDescription()
    {
        return "Update storage translation";
    }

    /**
     * @param Database $db
     */
    public function up(Database $db)
    {
        $db->execute('
            db.workflow_function.find({"names":{$exists:1}}).forEach(function(item) {
                 var property = item.names;
                 var newProperty = {};
                 for (var i in property) {
                    var element = property[i];
                    var language = element.language;
                    var value = element.value;
                    newProperty[language] = value;
                 }
                 item.names = newProperty;

                 db.workflow_function.update({_id: item._id}, item);
            });
        ');
    }

    /**
     * @param Database $db
     */
    public function down(Database $db)
    {
    }
}