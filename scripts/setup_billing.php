<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

echo "Setting up Billing tables...\n";

$queries = [
    "CREATE TABLE IF NOT EXISTS billing_accounts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        client_user_id BIGINT UNSIGNED NOT NULL,
        balance DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
        currency VARCHAR(10) NOT NULL DEFAULT 'BRL',
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY idx_billing_accounts_client (client_user_id),
        CONSTRAINT fk_billing_accounts_client FOREIGN KEY (client_user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS billing_items (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        client_user_id BIGINT UNSIGNED NOT NULL,
        description VARCHAR(255) NOT NULL,
        amount DECIMAL(15, 2) NOT NULL,
        type VARCHAR(20) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        billing_date DATE NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_billing_items_client (client_user_id),
        CONSTRAINT fk_billing_items_client FOREIGN KEY (client_user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS billing_history (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        client_user_id BIGINT UNSIGNED NOT NULL,
        action_user_id BIGINT UNSIGNED NOT NULL,
        action VARCHAR(50) NOT NULL,
        details TEXT,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_billing_history_client (client_user_id),
        CONSTRAINT fk_billing_history_client FOREIGN KEY (client_user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_billing_history_action_user FOREIGN KEY (action_user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS billing_invoices (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        client_user_id BIGINT UNSIGNED NOT NULL,
        invoice_number VARCHAR(50) NOT NULL,
        total_amount DECIMAL(15, 2) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'issued',
        due_date DATE NOT NULL,
        pdf_path VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY idx_billing_invoices_number (invoice_number),
        KEY idx_billing_invoices_client (client_user_id),
        CONSTRAINT fk_billing_invoices_client FOREIGN KEY (client_user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "Query executed successfully.\n";
    } catch (PDOException $e) {
        echo "Error executing query: " . $e->getMessage() . "\n";
    }
}

echo "Billing tables setup complete.\n";
