<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260527152303 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE products DROP created_at');
        $this->addSql('ALTER TABLE stock_adjustment CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE stock_adjustment RENAME INDEX idx_3b6a4a7e6daf1a8 TO IDX_27B08FBADCD6110');
        $this->addSql('ALTER TABLE stock_adjustment RENAME INDEX idx_3b6a4a7e72ca4172 TO IDX_27B08FBA55B127A4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE products ADD created_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_adjustment CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE stock_adjustment RENAME INDEX idx_27b08fbadcd6110 TO IDX_3B6A4A7E6DAF1A8');
        $this->addSql('ALTER TABLE stock_adjustment RENAME INDEX idx_27b08fba55b127a4 TO IDX_3B6A4A7E72CA4172');
    }
}
