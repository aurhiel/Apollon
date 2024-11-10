<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240720094252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add link between samples and "in sale" entity in order to manage sample when added to an advert';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE in_sale ADD sample_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE in_sale ADD CONSTRAINT FK_CFF0FF611B1FEA20 FOREIGN KEY (sample_id) REFERENCES sample (id)');
        $this->addSql('CREATE INDEX IDX_CFF0FF611B1FEA20 ON in_sale (sample_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE in_sale DROP FOREIGN KEY FK_CFF0FF611B1FEA20');
        $this->addSql('DROP INDEX IDX_CFF0FF611B1FEA20 ON in_sale');
        $this->addSql('ALTER TABLE in_sale DROP sample_id');
    }
}
