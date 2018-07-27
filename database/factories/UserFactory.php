<?php

use Faker\Generator as Faker;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(App\User::class, function (Faker $faker) {
    $departments = [
        'Sales and Marketing',
        'Information Technology',
        'Construction',
    ];

    return [
        'name' => $faker->name,
        'email' => $faker->unique()->username.'@cytonn.com',
        'role' => $faker->randomElement(['Supervisor', 'Junior']),
        'job_title' => $faker->jobTitle,
        'address' => $faker->address,
        'phone_number' => $faker->e164PhoneNumber,
        'avatar_url' => $faker->imageUrl(200, 200),
        'department' => $faker->randomElement($departments),
        'password' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret
        'remember_token' => str_random(10),
    ];
});
