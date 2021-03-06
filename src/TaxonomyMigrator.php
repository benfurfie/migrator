<?php

namespace Statamic\Migrator;

class TaxonomyMigrator extends Migrator
{
    use Concerns\MigratesFile,
        Concerns\MigratesRoute;

    /**
     * Perform migration.
     *
     * @param string $handle
     */
    public function migrate()
    {
        $this
            ->setNewPath(base_path($relativePath = "content/taxonomies/{$this->handle}"))
            ->validateUnique()
            ->copyDirectoryFromSiteToNewPath($relativePath)
            ->migrateTerms()
            ->migrateYamlConfig();
    }

    /**
     * Specify unique paths that shouldn't be overwritten.
     *
     * @return array
     */
    protected function uniquePaths()
    {
        return [
            $this->newPath(),
            $this->newPath("../{$this->handle}.yaml"),
        ];
    }

    /**
     * Migrate terms.
     *
     * @return $this
     */
    protected function migrateTerms()
    {
        $files = $this->files->exists($this->newPath())
            ? $this->files->files($this->newPath())
            : [];

        collect($files)->each(function ($term) {
            $this->updateYaml($this->newPath($term->getFilename()));
        });

        return $this;
    }

    /**
     * Migrate yaml config.
     *
     * @return $this
     */
    protected function migrateYamlConfig()
    {
        $config = $this->getSourceYaml("content/taxonomies/{$this->handle}.yaml", true);

        if ($fieldset = $config->get('fieldset')) {
            $config->put('blueprints', [$fieldset]);
            $config->forget('fieldset');
        }

        if ($route = $this->migrateRoute("taxonomies.{$this->handle}")) {
            $config->put('route', $route);
        }

        $this->saveMigratedYaml($config, $this->newPath("../{$this->handle}.yaml"));

        return $this;
    }
}
