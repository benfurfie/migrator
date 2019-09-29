<?php

namespace Statamic\Migrator;

use Statamic\Support\Str;
use Statamic\Migrator\YAML;
use Statamic\Migrator\Exceptions\AlreadyExistsException;

class PagesMigrator extends Migrator
{
    use Concerns\MigratesFolder;

    protected $entries = [];
    protected $structure = [];
    protected $blueprints = [];

    /**
     * Migrate file.
     *
     * @param string $handle
     */
    public function migrate($handle)
    {
        $this->newPath = base_path("content/collections/pages");

        $this
            ->validateUnique()
            ->copySourceFiles()
            ->parseTree()
            ->createStructure()
            ->createYamlConfig()
            ->migratePagesToEntries();
    }

    /**
     * Validate unique.
     *
     * @throws AlreadyExistsException
     * @return $this
     */
    protected function validateUnique()
    {
        if ($this->overwrite) {
            return $this;
        }

        $newPaths = [
            $this->newPath('../pages.yaml'),
            $this->newPath('../../structures/pages.yaml'),
        ];

        collect($newPaths)
            ->filter(function ($path) {
                return $this->files->exists($path);
            })
            ->each(function ($path) {
                throw new AlreadyExistsException;
            });

        return $this;
    }

    /**
     * Parse tree.
     *
     * @return this
     */
    protected function parseTree()
    {
        $this->parsePageFolder($this->newPath());

        return $this;
    }

    /**
     * Parse page folder.
     *
     * @param string $folder
     * @param string $key
     * @return array
     */
    protected function parsePageFolder($folder, $key = 'root')
    {
        $page = YAML::parse($this->files->get("{$folder}/index.md"));

        $page['slug'] = $key === 'root'
            ? Str::slug($page['title'])
            : preg_replace('/.*\/[0-9]*\.([^\/]*)$/', '$1', $folder);

        $this->entries[] = $page;
        $this->blueprints[] = $page['fieldset'] ?? null;

        $entry = $page['id'];

        $children = collect($this->files->directories("$folder"))
            ->map(function ($folder) use ($key, $entry) {
                return $this->parsePageFolder($folder, "{$key}.{$entry}");
            })
            ->all();

        $data = collect(compact('entry', 'children'))->filter()->all();

        data_set($this->structure, $key, $data);

        return $data;
    }

    /**
     * Create structure.
     *
     * @return $this
     */
    protected function createStructure()
    {
        $config = [
            'title' => 'Pages',
            'expects_root' => true,
            'root' => $this->structure['root']['entry'],
            'tree' => $this->structure['root']['children'],
        ];

        $this->files->put($this->newPath('../../structures/pages.yaml'), YAML::dump($config));

        return $this;
    }

    /**
     * Create yaml config.
     *
     * @return $this
     */
    protected function createYamlConfig()
    {
        $config = [
            'title' => 'Pages',
            'route' => '{{ parent_uri }}/{{ slug }}',
            'blueprints' => collect($this->blueprints)->filter()->unique()->values()->all(),
            'structure' => 'pages',
        ];

        $this->files->put($this->newPath('../pages.yaml'), YAML::dump($config));

        return $this;
    }

    /**
     * Migrate pages to entries.
     *
     * @return $this
     */
    protected function migratePagesToEntries()
    {
        $this->files->cleanDirectory($this->newPath());

        collect($this->entries)
            ->map(function ($entry) {
                return $this->migrateFieldsetToBlueprint($entry);
            })
            ->map(function ($entry) {
                $this->files->put($this->generateEntryPath($entry), $this->dumpEntryToMarkdown($entry));
            });

        return $this;
    }

    /**
     * Generate entry path.
     *
     * @param array $entry
     * @param int $number
     * @return string
     */
    protected function generateEntryPath($entry, $number = 1)
    {
        $appended = $number > 1
            ? "-{$number}"
            : null;

        $path = $this->newPath("{$entry['slug']}{$appended}.md");

        if ($this->files->exists($path)) {
            return $this->generateEntryPath($entry, ++$number);
        }

        return $path;
    }

    /**
     * Migrate fieldset to blueprint.
     *
     * @param array $entry
     * @return array
     */
    protected function migrateFieldsetToBlueprint($entry)
    {
        if (isset($entry['fieldset'])) {
            $entry['blueprint'] = $entry['fieldset'];
        }

        unset($entry['fieldset']);

        return $entry;
    }

    /**
     * Dump entry to markdown.
     *
     * @param array $entry
     * @return string
     */
    protected function dumpEntryToMarkdown($entry)
    {
        return isset($entry['content'])
            ? YAML::dumpFrontMatter(collect($entry)->except('content')->all()) . $entry['content']
            : YAML::dump($entry);
    }
}
