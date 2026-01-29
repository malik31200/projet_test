<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129140940 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(20, 0) NOT NULL, stripe_payment_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, session_book_id INT DEFAULT NULL, registration_id INT DEFAULT NULL, INDEX IDX_6D28840DA76ED395 (user_id), INDEX IDX_6D28840D47A31DB (session_book_id), INDEX IDX_6D28840D833D8F43 (registration_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE registration (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, registered_at DATETIME NOT NULL, cancelled_at DATETIME DEFAULT NULL, user_id INT NOT NULL, session_id INT NOT NULL, session_book_id INT DEFAULT NULL, INDEX IDX_62A8A7A7A76ED395 (user_id), INDEX IDX_62A8A7A7613FECDF (session_id), INDEX IDX_62A8A7A747A31DB (session_book_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE session_book (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, total_sessions INT NOT NULL, remaining_sessions INT NOT NULL, price NUMERIC(10, 2) NOT NULL, expires_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_D94A31BA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D47A31DB FOREIGN KEY (session_book_id) REFERENCES session_book (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D833D8F43 FOREIGN KEY (registration_id) REFERENCES registration (id)');
        $this->addSql('ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A7A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A7613FECDF FOREIGN KEY (session_id) REFERENCES session (id)');
        $this->addSql('ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A747A31DB FOREIGN KEY (session_book_id) REFERENCES session_book (id)');
        $this->addSql('ALTER TABLE session_book ADD CONSTRAINT FK_D94A31BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DA76ED395');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D47A31DB');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D833D8F43');
        $this->addSql('ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A7A76ED395');
        $this->addSql('ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A7613FECDF');
        $this->addSql('ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A747A31DB');
        $this->addSql('ALTER TABLE session_book DROP FOREIGN KEY FK_D94A31BA76ED395');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE registration');
        $this->addSql('DROP TABLE session_book');
    }
}
