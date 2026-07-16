<?php
try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=postgres', 'postgres', 'mynewdb');
    $pdo->exec('CREATE DATABASE yoop');
    echo "Database yoop criado com sucesso!\n";
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'already exists')) {
        echo "Database yoop já existe.\n";
    } else {
        echo "Erro: " . $e->getMessage() . "\n";
    }
}
