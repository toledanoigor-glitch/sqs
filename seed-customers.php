<?php
require_once __DIR__ . '/config.php';

$db = (new Lab\Database())->connect();
$faker = Faker\Factory::create('en_US');
$faker->seed(2026);

$seeder = new Lab\CustomerSeeder($db, $faker);
$seeder->seed();

echo "Seeded 10,000 customers successfully!\n";