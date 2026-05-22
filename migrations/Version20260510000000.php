<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create stock adjustment history table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE stock_adjustment (id INT AUTO_INCREMENT NOT NULL, stock_id INT NOT NULL, added_by_id INT DEFAULT NULL, quantity_added INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_3B6A4A7E6DAF1A8 (stock_id), INDEX IDX_3B6A4A7E72CA4172 (added_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock_adjustment ADD CONSTRAINT FK_3B6A4A7E6DAF1A8 FOREIGN KEY (stock_id) REFERENCES stock (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stock_adjustment ADD CONSTRAINT FK_3B6A4A7E72CA4172 FOREIGN KEY (added_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE stock_adjustment');
    }
}