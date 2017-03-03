<?php

namespace OpenOrchestra\MigrationBundle\Migrations;

use AntiMattr\MongoDB\Migrations\AbstractMigration;
use Doctrine\MongoDB\Database;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170302120147 extends AbstractMigration
{
    /**
     * @return string
     */
    public function getDescription()
    {
        return "Migration user & group 1.2 to 2.0";
    }

    /**
     * @param Database $db
     */
    public function up(Database $db)
    {
        return $db->execute('
            db.user.update({} , {$unset: {locked: "" , expired: "", credentialsExpired: ""}}, {multi: true});
            db.user.update({}, {$set: {languageBySites: [], editAllowed: false}}, {multi: true});
            db.user.find({"roles.0": "ROLE_SUPER_ADMIN"}).forEach(function(user) {
                user.roles = {"0": "ROLE_DEVELOPER"};
                db.user.update({_id: user._id}, user);
            });
            db.user.find({"roles.0": {$ne: "ROLE_DEVELOPER"}}).forEach(function(user) {
                user.roles = [];
                db.user.update({_id: user._id}, user);
            });
            db.users_group.update({} , {$unset: {modelRoles: "" , roles: ""}}, {multi: true});
            db.users_group.update({}, {$set: {workflowProfileCollections: {}, perimeters: {}, deleted: false}}, {multi: true});
            db.users_group.find({"site": {$exists: false}}).forEach(function(users_group) {
                var new_users_groups = [];
                var users_group_name = users_group.name;
                var users_group_id = users_group._id;
                var insert = true;
                db.site.find({"deleted": false}).forEach(function(site) {
                    users_group.site = new DBRef("site", site._id);
                    if (insert) {
                        db.users_group.update({_id: users_group._id}, users_group);
                        insert = false;
                    } else {
                        users_group.name = users_group_name + " (" + site.name + ")";
                        users_group._id = new ObjectId();
                        db.users_group.insert(users_group);
                        new_users_groups.push(new DBRef("users_group", users_group._id));
                    }
                });
                db.user.find({"groups": {$exists: true}}).forEach(function(user) {
                    for (var i in user.groups) {
                        if (user.groups[i].$id == users_group_id.str) {
                            user.groups = user.groups.concat(new_users_groups);
                            db.user.update({_id: user._id}, user);
                            break;
                        }
                    }
                });
            });
        ');
    }

    public function down(Database $db)
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
