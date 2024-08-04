<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240714184631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add new `is_sold` field to samples';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sample ADD is_sold TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE vinyl CHANGE quantity_with_cover quantity_with_cover SMALLINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sample DROP is_sold');
        $this->addSql('ALTER TABLE vinyl CHANGE quantity_with_cover quantity_with_cover SMALLINT DEFAULT NULL');
    }
}
