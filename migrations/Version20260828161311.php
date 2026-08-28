<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828161311 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track explicit native/imported provenance for categories';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE category ADD external_ref VARCHAR(180) DEFAULT NULL'
        );

        $this->addSql(
            'ALTER TABLE category ADD data_origin VARCHAR(20) DEFAULT NULL'
        );

        $this->addSql(
            "UPDATE category SET data_origin = 'native'"
        );

        $this->addSql(
            'ALTER TABLE category ALTER data_origin SET NOT NULL'
        );

        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_64C19C1B445906B ON category (external_ref)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP INDEX UNIQ_64C19C1B445906B'
        );

        $this->addSql(
            'ALTER TABLE category DROP external_ref'
        );

        $this->addSql(
            'ALTER TABLE category DROP data_origin'
        );
    }
}
