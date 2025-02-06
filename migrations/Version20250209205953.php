<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250209205953 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C545317D1');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicle (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX UNIQ_1B80E486BE73422E ON vehicle');
        $this->addSql('ALTER TABLE vehicle CHANGE marque marque VARCHAR(255) NOT NULL, CHANGE immatriculation immatriculation VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C545317D1');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicle (id)');
        $this->addSql('ALTER TABLE vehicle CHANGE marque marque VARCHAR(100) NOT NULL, CHANGE immatriculation immatriculation VARCHAR(50) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1B80E486BE73422E ON vehicle (immatriculation)');
    }
}
