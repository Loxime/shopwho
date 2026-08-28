<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260827203109 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product_review DROP CONSTRAINT fk_product_review_product');
        $this->addSql('ALTER TABLE product_review ADD CONSTRAINT FK_1B3FC0624584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product_review DROP CONSTRAINT FK_1B3FC0624584665A');
        $this->addSql('ALTER TABLE product_review ADD CONSTRAINT fk_product_review_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
