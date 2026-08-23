<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extend customer accounts and associate tracking events with authenticated users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD first_name VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD last_name VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD external_ref VARCHAR(180) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9B445906B ON app_user (external_ref)');
        $this->addSql('ALTER TABLE tracking_event ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tracking_event ADD CONSTRAINT FK_46C4B4D0A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_tracking_user ON tracking_event (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tracking_event DROP CONSTRAINT FK_46C4B4D0A76ED395');
        $this->addSql('DROP INDEX idx_tracking_user');
        $this->addSql('ALTER TABLE tracking_event DROP user_id');
        $this->addSql('DROP INDEX UNIQ_88BDF3E9B445906B');
        $this->addSql('ALTER TABLE app_user DROP first_name');
        $this->addSql('ALTER TABLE app_user DROP last_name');
        $this->addSql('ALTER TABLE app_user DROP external_ref');
    }
}
