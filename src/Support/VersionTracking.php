<?php

namespace Infinity\Evolver\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * VersionTracking type.
 *
 * @method bool isEqual(string $version)
 * @method bool isNotEqual(string $version)
 * @method bool isLessThan(string $version)
 * @method bool isLessThanOrEqual(string $version)
 * @method bool isGreaterThan(string $version)
 * @method bool isGreaterThanOrEqual(string $version)
 */
final readonly class VersionTracking
{
    /**
     * Initializes a Version tracking object.
     */
    private function __construct(private string $version)
    {
    }

    /**
     * Set a specific version to compare with. This helper method is useful for testing.
     */
    public static function fromValue(string $version): VersionTracking
    {
        return new VersionTracking($version);
    }

    /**
     * Retrieve the current version from the app.php configuration file and
     * create a version tracking instance.
     */
    public static function fromConfig(): VersionTracking
    {
        $versionKey = config('evolver.version_key');

        if (is_null($versionKey)) {
            throw new RuntimeException('Missing version key in the `evolver` config file');
        }

        return new VersionTracking(config($versionKey));
    }

    /**
     * Retrieve the current version from a Git Repository and create a version tracking instance.
     */
    public static function fromVersionControl(): VersionTracking
    {
        $basePath = base_path();

        $gitDirectory = Arr::join([$basePath, '.git'], DIRECTORY_SEPARATOR);
        if (! File::exists($gitDirectory)) {
            throw new RuntimeException('The directory is not under version control');
        }

        $result = Process::path($basePath)->run(['git', 'describe', '--tags'])->throw();

        if ($result->failed()) {
            throw new RuntimeException('Cannot detect the version of this repository');
        }

        $version = Str::of($result->output())->trim();

        return new VersionTracking($version);
    }

    /**
     * Magically call methods that utilize version tracking.
     *
     * @return bool
     */
    public function __call(string $name, array $arguments)
    {
        $operator = match ($name) {
            'isEqual' => '==',
            'isNotEqual' => '<>',
            'isLessThan' => '<',
            'isLessThanOrEqual' => '<=',
            'isGreaterThan' => '>',
            'isGreaterThanOrEqual' => '>=',
            default => throw new RuntimeException('Unknown operator')
        };

        if (count($arguments) === 0) {
            throw new RuntimeException('Unknown version supplied');
        }

        [$version] = $arguments;

        return version_compare($this->version, $version, $operator);
    }

    /**
     * Returns the current version of the application.
     */
    public function getVersion(): string
    {
        return $this->version;
    }
}
