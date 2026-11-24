<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Troca os índices da tabela do Messenger pelo índice composto que o Symfony 7.4 espera';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_messenger_queue_name');
        $this->addSql('DROP INDEX idx_messenger_available_at');
        $this->addSql('DROP INDEX idx_messenger_delivered_at');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750');
        $this->addSql('CREATE INDEX idx_messenger_queue_name ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX idx_messenger_available_at ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX idx_messenger_delivered_at ON messenger_messages (delivered_at)');
    }
}
