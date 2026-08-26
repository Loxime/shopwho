<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260826183642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE customer_order DROP CONSTRAINT fk_customer_order_user');
        $this->addSql('ALTER TABLE customer_order ADD CONSTRAINT FK_3B1CE6A3A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE product_review DROP CONSTRAINT fk_product_review_user');
        $this->addSql('ALTER TABLE product_review ADD CONSTRAINT FK_1B3FC062A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE customer_order DROP CONSTRAINT FK_3B1CE6A3A76ED395');
        $this->addSql('ALTER TABLE customer_order ADD CONSTRAINT fk_customer_order_user FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_review DROP CONSTRAINT FK_1B3FC062A76ED395');
        $this->addSql('ALTER TABLE product_review ADD CONSTRAINT fk_product_review_user FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
