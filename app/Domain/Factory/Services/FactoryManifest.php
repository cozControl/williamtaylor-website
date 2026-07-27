<?php

namespace App\Domain\Factory\Services;

use InvalidArgumentException;

final class FactoryManifest
{
    public const VERSION = 'william-taylor-factory-v1';

    public const VERSION_2 = 'william-taylor-factory-v2';

    public function version(): string
    {
        $version = (string) config('factory.runtime_version', self::VERSION);

        return in_array($version, [self::VERSION, self::VERSION_2], true) ? $version : throw new InvalidArgumentException('Unknown factory version.');
    }

    public function directory(): string
    {
        return database_path('factory/'.($this->version() === self::VERSION ? 'william-taylor-v1' : self::VERSION_2));
    }

    /** @return array<string, mixed> */
    public function load(string $file): array
    {
        $path = $this->directory().DIRECTORY_SEPARATOR.$file.'.php';
        if (! is_file($path) && $this->version() === self::VERSION_2) {
            $path = database_path('factory/william-taylor-v1').DIRECTORY_SEPARATOR.$file.'.php';
        }
        if (! is_file($path)) {
            throw new InvalidArgumentException("Factory manifest [{$file}] is missing.");
        }
        $value = require $path;
        if (! is_array($value) || ! in_array(($value['factory_version'] ?? null), [self::VERSION, $this->version()], true)) {
            throw new InvalidArgumentException("Factory manifest [{$file}] has an invalid version.");
        }

        return $value;
    }

    /** @return list<array<string, mixed>> */
    public function content(): array
    {
        $announcements = array_values($this->load('announcements')['resources']);

        return [$this->load('primary-navigation'), $this->load('footer-navigation'), ...$announcements, $this->load('site-profile')];
    }

    /** @return list<array<string, mixed>> */
    public function media(): array
    {
        return $this->load('media')['entries'];
    }

    /** @return array<string, mixed>|null */
    public function about(): ?array
    {
        $path = $this->directory().DIRECTORY_SEPARATOR.'about-page.php';

        return is_file($path) ? require $path : null;
    }

    public function checksum(): string
    {
        return hash('sha256', $this->canonicalJson(['manifest' => $this->load('manifest'), 'roles' => $this->load('roles'), 'users' => $this->load('users'), 'content' => $this->content(), 'about' => $this->about()]));
    }

    public function mediaChecksum(): string
    {
        return hash('sha256', $this->canonicalJson($this->media()));
    }

    private function canonicalJson(mixed $value): string
    {
        $normalize = function (mixed $item) use (&$normalize): mixed {
            if (! is_array($item)) {
                return $item;
            } if (! array_is_list($item)) {
                ksort($item);
            }

            return array_map($normalize, $item);
        };

        return json_encode($normalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
