<?php

namespace OpenOrchestra\MigrationBundle\Migrations;

use AntiMattr\MongoDB\Migrations\AbstractMigration;
use Doctrine\MongoDB\Database;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170307181737 extends AbstractMigration
{
    /**
     * @return string
     */
    public function getDescription()
    {
        return "Update media from 1.2 to 2.0";
    }

    public function up(Database $db)
    {
        // this up() migration is auto-generated, please modify it to your needs

    }

    public function down(Database $db)
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
