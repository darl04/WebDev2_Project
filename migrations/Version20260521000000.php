<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add collection type to products';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products ADD collection_type VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products DROP collection_type');
    }
}