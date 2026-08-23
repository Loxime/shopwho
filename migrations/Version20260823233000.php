<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260823233000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add external identity for imported products and historical product reference snapshot'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD external_ref VARCHAR(180) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D34A04ADB445906B ON product (external_ref)');
        $this->addSql('ALTER TABLE order_item ADD product_external_ref_snapshot VARCHAR(180) DEFAULT NULL');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_D34A04ADB445906B');
        $this->addSql('ALTER TABLE product DROP external_ref');
        $this->addSql('ALTER TABLE order_item DROP product_external_ref_snapshot');
    }
}
