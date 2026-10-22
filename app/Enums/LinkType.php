<?php

namespace App\Enums;

enum LinkType: string
{
    case Figma = 'figma';
    case Repo = 'repo';
    case Staging = 'staging';
    case Other = 'other';

    public function label(): string
    {
        return config("orbit.link_types.{$this->value}.label", $this->value);
    }

    /** تشخیص خودکار نوع لینک از روی دامنه (hosts در config/orbit.php) */
    public static function detect(string $url): self
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);

        foreach (config('orbit.link_types', []) as $type => $meta) {
            foreach ($meta['hosts'] ?? [] as $known) {
                if ($host === $known || str_ends_with($host, '.'.$known)) {
                    return self::from($type);
                }
            }
        }

        return self::Other;
    }
}
