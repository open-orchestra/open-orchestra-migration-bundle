<?php

namespace OpenOrchestra\MigrationBundle\Migrations;

use Doctrine\MongoDB\Database;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170321150342 extends AbstractMigration
{
    /**
     * @return string
     */
    public function getDescription()
    {
        return "Update workflow from 1.2 to 2.0";
    }

    /**
     * @param Database $db
     */
    public function up(Database $db)
    {
        $this->write(' + Rename workflow_function collection to workflow_profile');
        $db->execute('db.workflow_function.renameCollection("workflow_profile");');

        $this->write(' + Rename property names to labels');
        $db->execute('db.workflow_profile.update( {}, { $rename: { "names": "labels" } }, { multi: true } )');

        $this->write(' + Adding property descriptions');
        $db->execute('
            db.workflow_profile.find().snapshot().forEach(function(item) {
                var labels = item.labels;
                var descriptions = {};
                for (var i in labels) {
                    var label = labels[i];
                    descriptions[i] = label;
                }
                item.descriptions = descriptions;

                db.workflow_profile.update({ _id: item._id }, item);
            });'
        );

        $this->write(' + Adding property transitions');
        $db->execute('db.workflow_profile.update( {}, { $set: { "transitions": [] } }, { multi: true } )');

        $this->write(' + Remove properties createdAt, updatedAt, roles');
        $db->execute('
            db.workflow_profile.find().snapshot().forEach(function(item) {
                 if (item.createdAt) {
                    delete item.createdAt;
                 }
                 if (item.updatedAt) {
                    delete item.updatedAt;
                 }
                 if (item.roles) {
                    delete item.roles;
                 }

                 db.workflow_profile.update({ _id: item._id }, item);
            });
        ');

        $this->write(' + Removing role collection');
        $db->execute('db.role.drop();');

        $this->write(' + Removing workflow_profile collection');
        $db->execute('db.workflow_right.drop();');
    }

    /**
     * @param Database $db
     */
    public function down(Database $db)
    {
        $this->write('There is no down method for this migration');
    }
}
