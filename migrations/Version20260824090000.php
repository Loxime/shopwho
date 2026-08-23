<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track explicit native/imported provenance for users and products';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE app_user ADD data_origin VARCHAR(20) DEFAULT NULL");
        $this->addSql("ALTER TABLE product ADD data_origin VARCHAR(20) DEFAULT NULL");
        $this->addSql("UPDATE app_user SET data_origin = CASE WHEN external_ref IS NOT NULL THEN 'imported' ELSE 'native' END");
        $this->addSql("UPDATE product SET data_origin = CASE WHEN external_ref IS NOT NULL THEN 'imported' ELSE 'native' END");
        $this->addSql('ALTER TABLE app_user ALTER data_origin SET NOT NULL');
        $this->addSql('ALTER TABLE product ALTER data_origin SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP data_origin');
        $this->addSql('ALTER TABLE product DROP data_origin');
    }
}
