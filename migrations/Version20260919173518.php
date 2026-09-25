<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260919173518 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the uploads table and hangs a profile picture off the user';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE upload (id UUID NOT NULL, filename VARCHAR(255) DEFAULT NULL, original_filename VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(255) DEFAULT NULL, dimensions VARCHAR(255) DEFAULT NULL, size INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE "user" ADD picture_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D649EE45BDBF FOREIGN KEY (picture_id) REFERENCES upload (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649EE45BDBF ON "user" (picture_id)');
    }

    public function down(Schema $schema): void
    {
        // Reordered: the generated version drops the table before the foreign key
        // pointing at it, which Postgres refuses.
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D649EE45BDBF');
        $this->addSql('DROP INDEX UNIQ_8D93D649EE45BDBF');
        $this->addSql('ALTER TABLE "user" DROP picture_id');
        $this->addSql('DROP TABLE upload');
    }
}
