<?php
// classes/Packagist.php

declare(strict_types=1);

class Packagist
{
    private function fetchJson(string $url): array
    {
        $ctx = stream_context_create(['http' => ['timeout' => 20, 'header' => "User-Agent: PHP-Setup\r\n"]]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) {
            return [];
        }
        $json = json_decode($data, true);
        return is_array($json) ? $json : [];
    }

    public function getProjectList(string $tags, string $search): array
    {
        $query = [];
        if ($search !== '') $query[] = 'q=' . urlencode($search);
        if ($tags !== '') $query[] = 'tags=' . urlencode($tags);
        $url = 'https://packagist.org/search.json' . (empty($query) ? '' : ('?' . implode('&', $query)));
        $res = $this->fetchJson($url);

        $items = [];
        foreach (($res['results'] ?? []) as $r) {
            $items[] = [
                'name' => $r['name'] ?? '',
                'description' => $r['description'] ?? '',
                'url' => $r['url'] ?? '',
                'repository' => $r['repository'] ?? '',
                'downloads' => $r['downloads'] ?? 0,
                'favers' => $r['favers'] ?? 0,
                'author' => $r['vendor'] ?? '', // vendor maps roughly to author/org
				'type' => $this->getPackageType($r['name']),
                'installed' => false,
                'installed_version' => '',
                'update_available' => false,
            ];
        }
        return $items;
    }

    public function getProject(string $package): array
    {
        // Primary package info: includes versions via /packages/<vendor>/<name>.json
        $pkgUrl = 'https://repo.packagist.org/p2/' . $package . '.json';
        $data = $this->fetchJson($pkgUrl);

        $versions = [];
        if (isset($data['packages'][$package]) && is_array($data['packages'][$package])) {
            foreach ($data['packages'][$package] as $ver) {
                $versions[] = [
                    'version' => $ver['version'] ?? '',
                    'require' => $ver['require'] ?? [],
                    'time' => $ver['time'] ?? null
                ];
            }
        }

        // Attempt to infer metadata for UI continuity
        $metaUrl = 'https://packagist.org/packages/' . $package . '.json';
        $meta = $this->fetchJson($metaUrl);
        $desc = $meta['package']['description'] ?? '';
        $url = $meta['package']['repository'] ?? ('https://packagist.org/packages/' . $package);
        $authors = $meta['package']['authors'][0]['name'] ?? '';
		$type = $meta['package']['type'] ?? '';
        $wiki = null; // Not standardized; could be from extra, if present
        if (isset($meta['package']['extra']['wiki'])) {
            $wiki = $meta['package']['extra']['wiki'];
        }

        // Security advisories endpoint example (for existence)
        // https://packagist.org/api/security-advisories/?packages[]=vendor/name
        // Not parsed here, but could be used to flag advisories.

        return [
            'versions' => $versions,
            'description' => $desc,
            'url' => $url,
            'author' => $authors,
            'wiki' => $wiki,
			'type' => $meta['package']['type'] ?? ''
        ];
    }
	
	private function getPackageType(string $package): string
{
    // Detail-Endpoint liefert den Typ
    $metaUrl = 'https://packagist.org/packages/' . $package . '.json';
    $meta = $this->fetchJson($metaUrl);

    if (isset($meta['package']['type'])) {
        return $meta['package']['type'];
    }
    return '';
}
}
