<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240609094903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add new Sample entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sample (id INT AUTO_INCREMENT NOT NULL, vinyl_id INT NOT NULL, rate_face_a INT NOT NULL, rate_face_b INT NOT NULL, has_cover TINYINT(1) NOT NULL, has_generic_cover TINYINT(1) NOT NULL, rate_cover INT DEFAULT NULL, price DOUBLE PRECISION DEFAULT NULL, details LONGTEXT DEFAULT NULL, INDEX IDX_F10B76C33FFFF645 (vinyl_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE sample ADD CONSTRAINT FK_F10B76C33FFFF645 FOREIGN KEY (vinyl_id) REFERENCES vinyl (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sample');
    }
}
