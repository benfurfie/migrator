<?php

namespace Tests;

use Tests\TestCase;
use Statamic\Migrator\YAML;
use Statamic\Migrator\FieldsetMigrator;

class MigrateUserTest extends TestCase
{
    protected function paths($key = null)
    {
        $paths = [
            'old_username' => base_path('site/users/irmagobb.yaml'),
            'old_email' => base_path('site/users/irmagobb@example.com.yaml'),
            'new' => base_path('users/irmagobb@example.com.yaml'),
        ];

        return $key ? $paths[$key] : $paths;
    }

    private function migrateUser($userConfig)
    {
        $this->files->put($this->paths('old_username'), YAML::dump($userConfig));

        $this->assertFileNotExists($this->paths('new'));

        $this->artisan('statamic:migrate:user', ['handle' => 'irmagobb']);

        $this->assertFileExists($this->paths('new'));

        return YAML::parse($this->files->get($this->paths('new')));
    }

    /** @test */
    function it_can_migrate_a_user()
    {
        $user = $this->migrateUser([
            'first_name' => 'Irma',
            'last_name' => 'Gobb',
            'email' => 'irmagobb@example.com',
            'password' => 'mrbeanisdreamy',
            'super' => true,
        ]);

        $this->assertEquals($user, [
            'name' => 'Irma Gobb',
            'password' => 'mrbeanisdreamy',
            'super' => true,
        ]);
    }

    /** @test */
    function it_requires_an_email_for_handle()
    {
        $this->files->put($this->paths('old_username'), YAML::dump([
            'first_name' => 'Irma',
        ]));

        $this->artisan('statamic:migrate:user', ['handle' => 'irmagobb']);

        $this->assertFileExists($this->paths('old_username'));
        $this->assertFileNotExists($this->paths('new'));
    }

    /** @test */
    function it_migrates_email_from_filename()
    {
        $this->files->put($this->sitePath('settings/users.yaml'), 'login_type: email');

        $this->files->put($this->paths('old_email'), YAML::dump([
            'first_name' => 'Irma',
        ]));

        $this->assertFileNotExists($this->paths('new'));

        $this->artisan('statamic:migrate:user', ['handle' => 'irmagobb@example.com']);

        $this->assertFileExists($this->paths('new'));
    }

    /** @test */
    function it_migrates_with_only_first_name()
    {
        $user = $this->migrateUser([
            'first_name' => 'Irma',
            'email' => 'irmagobb@example.com',
        ]);

        $this->assertEquals($user, [
            'name' => 'Irma',
        ]);
    }

    /** @test */
    function it_migrates_with_only_last_name()
    {
        $user = $this->migrateUser([
            'last_name' => 'Gobb',
            'email' => 'irmagobb@example.com',
        ]);

        $this->assertEquals($user, [
            'name' => 'Gobb',
        ]);
    }

    /** @test */
    function it_migrates_singular_name_field()
    {
        $user = $this->migrateUser([
            'name' => 'Irma Gobb',
            'email' => 'irmagobb@example.com',
        ]);

        $this->assertEquals($user, [
            'name' => 'Irma Gobb',
        ]);
    }

    /** @test */
    function it_migrates_roles()
    {
        $user = $this->migrateUser([
            'name' => 'Irma Gobb',
            'email' => 'irmagobb@example.com',
            'roles' => [
                'd32e14fb-08c9-44c2-aaf8-21200852bafd'
            ]
        ]);

        $this->assertEquals($user, [
            'name' => 'Irma Gobb',
            'roles' => [
                'super_admin'
            ]
        ]);
    }
}
