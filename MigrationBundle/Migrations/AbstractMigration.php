<?php

namespace OpenOrchestra\MigrationBundle\Migrations;

use AntiMattr\MongoDB\Migrations\AbstractMigration as BaseAbstractMigration;
use Doctrine\MongoDB\Database;

/**
 * Class AbstractMigration
 */
abstract class AbstractMigration extends BaseAbstractMigration
{
    /**
     * @param Database $db
     */
    public function preUp(Database $db)
    {
        $this->write("");
        $this->write("<error> Warning this migration not work with sharded collections</error>");
    }
}
