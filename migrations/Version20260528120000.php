<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure the default admin account exists';
    }

    public function up(Schema $schema): void
    {
        $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
        $roles = json_encode(['ROLE_ADMIN'], JSON_THROW_ON_ERROR);

        $existingAdminId = $this->connection->fetchOne(
            'SELECT id FROM user WHERE username = ?',
            ['admin']
        );

        if ($existingAdminId !== false) {
            $this->connection->executeStatement(
                'UPDATE user SET email = ?, roles = ?, password = ?, is_verified = ?, verification_token = ?, is_active = ? WHERE username = ?',
                ['admin@example.com', $roles, $adminPassword, 1, null, 1, 'admin']
            );

            return;
        }

        $this->connection->executeStatement(
            'INSERT INTO user (username, roles, password, email, is_verified, verification_token, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)',
            ['admin', $roles, $adminPassword, 'admin@example.com', 1, null, 1]
        );
    }

    public function down(Schema $schema): void
    {
        $this->connection->executeStatement(
            'DELETE FROM user WHERE username = ?',
            ['admin']
        );
    }
}
