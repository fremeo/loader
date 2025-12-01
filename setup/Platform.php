<?php
/**
 * Klassen-Datei: Plattform.php
 * Vereinheitlichte Schnittstelle für GitHub/GitLab:
 * - getProjectList(platform, topic, visibility, search)
 * - getProject(platform, id)
 * - getWikiList(platform, id)
 * - getWikiPage(platform, id, pageId)
 * - getTags(platform, id)
 *
 * Intern getrennte Implementationen/Helper sind private.
 * Hinweis: Für produktiven Einsatz ggf. Auth-Header/Tokens hinzufügen, Rate Limits beachten.
 */

declare(strict_types=1);

class Platform
{
	
    private string $githubApi = 'https://api.github.com';
    private string $gitlabApi = 'https://gitlab.com/api/v4';
    // Optional: Tokens
    private ?string $githubToken = null; // getenv('GITHUB_TOKEN') ?: null;
    private ?string $gitlabToken = null; // getenv('GITLAB_TOKEN') ?: null;

	public function ensureDirectoriesAndComposer(): void
    {
        $setupDir   = __DIR__ . '/../setup';
        $systemDir  = __DIR__ . '/../system';
        $composerPhar = $setupDir . '/composer.phar';

        if (!is_dir($setupDir)) {
            mkdir($setupDir, 0775, true);
        }
        if (!is_dir($systemDir)) {
            mkdir($systemDir, 0775, true);
        }

        if (!file_exists($composerPhar)) {
            $url = 'https://getcomposer.org/download/latest-stable/composer.phar';
            $phar = @file_get_contents($url);
            if ($phar === false) {
                $url2 = 'https://getcomposer.org/composer-stable.phar';
                $phar = @file_get_contents($url2);
            }
            if ($phar !== false) {
                file_put_contents($composerPhar, $phar);
            }
        }
    }
	
    /**
     * Öffentliche Projekte holen (Topic=phpapp2 optional, zusätzlich Suchfilter)
     */
    public function getProjectList(string $platform = 'all', ?string $topic = 'phpapp2', string $visibility = 'public', ?string $search = ''): array
    {
        $list = [];
        if ($platform === 'github' || $platform === 'all') {
            $list = array_merge($list, $this->fetchGithubProjects($topic, $search));
        }
        if ($platform === 'gitlab' || $platform === 'all') {
            $list = array_merge($list, $this->fetchGitlabProjects($topic, $search));
        }
        // Normalisiere Felder
        return array_map(function($p){
            return [
                'id' => $p['id'],
                'title' => $p['title'],
                'description' => $p['description'] ?? '',
                'author' => $p['author'] ?? '',
                'platform' => $p['platform'],
                'avatar_url' => $p['avatar_url'] ?? '',
                'web_url' => $p['web_url'],
                'has_wiki' => $p['has_wiki'] ?? false,
                'package_name' => $p['package_name'] ?? null, // für Composer
                'repo_url' => $p['repo_url'] ?? null,         // für Composer vcs
            ];
        }, $list);
    }

    public function getProject(string $platform, string $id): array
    {
        if ($platform === 'github') {
            return $this->fetchGithubProject($id);
        } elseif ($platform === 'gitlab') {
            return $this->fetchGitlabProject($id);
        }
        throw new \InvalidArgumentException('Unknown platform');
    }

    public function getWikiList(string $platform, string $id): array
    {
        if ($platform === 'github') {
            // GitHub: WIKI ist eigenes Repo, hier Minimal-Stub
            // In der Praxis: /repos/{owner}/{repo}/wiki (nicht offizielle API, oft via pages)
            return []; // optional implementieren
        } else {
            return $this->fetchGitlabWikiList($id);
        }
    }

    public function getWikiPage(string $platform, string $id, string $pageId): string
    {
        if ($platform === 'gitlab') {
            return $this->fetchGitlabWikiPage($id, $pageId) ?? '';
        }
        return 'Nicht verfügbar';
    }

    public function getTags(string $platform, string $id): array
    {
        if ($platform === 'github') {
            return $this->fetchGithubTags($id);
        } else {
            return $this->fetchGitlabTags($id);
        }
    }

    // ------------------ Private Implementierungen ------------------

    private function fetchGithubProjects(?string $topic, ?string $search): array
{
    // GitHub Search API: https://api.github.com/search/repositories?q=topic:phpapp2+<search>
    $qParts = [];
    if ($topic !== null && $topic !== '') {
        // ÄNDERUNG: Topic darf nicht durch escapeQuery laufen, sondern direkt
        $qParts[] = 'topic:' . $topic;
    }
    if ($search !== null && $search !== '') {
        // ÄNDERUNG: Suchbegriff muss mit escapeQuery vorbereitet werden
        $qParts[] = $this->escapeQuery($search);
    }

    // ÄNDERUNG: implode OHNE urlencode, da GitHub '+' als Trenner erwartet
    $q = implode('+', $qParts);
    if ($q === '') $q = 'topic:phpapp2';

    // ÄNDERUNG: Query direkt einsetzen, nicht nochmal urlencode()
    $url = $this->githubApi . '/search/repositories?q=' . $q . '&per_page=50';

    $json = $this->httpGet($url, $this->githubHeaders());
    $data = json_decode($json, true);
    $items = $data['items'] ?? [];
    $out = [];
    foreach ($items as $it) {
        $out[] = [
            'id' => $it['full_name'],
            'title' => $it['name'],
            'description' => $it['description'] ?? '',
            'author' => $it['owner']['login'] ?? '',
            'platform' => 'github',
            'avatar_url' => $it['owner']['avatar_url'] ?? '',
            'web_url' => $it['html_url'],
            'has_wiki' => (bool)($it['has_wiki'] ?? false),
            'package_name' => $it['full_name'],
            'repo_url' => $it['html_url'],
        ];
    }
    return $out;
}

    private function fetchGitlabProjects(?string $topic, ?string $search): array
{
    $params = [
        'visibility' => 'public',
        'per_page' => '50'
    ];
    if ($search) {
        // ÄNDERUNG: Suchbegriff vorbereiten
        $params['search'] = $this->escapeQuery($search);
    }
    $url = $this->gitlabApi . '/projects?' . http_build_query($params);

    $json = $this->httpGet($url, $this->gitlabHeaders());
    $items = json_decode($json, true) ?: [];
    $out = [];
    foreach ($items as $it) {
        $topics = $it['topics'] ?? [];
        // ÄNDERUNG: Nur filtern, wenn $topic nicht leer ist UND Projekt überhaupt Topics hat
        if ($topic && $topic !== '' && is_array($topics) && !in_array($topic, $topics, true)) {
            continue;
        }
        $out[] = [
            'id' => (string)$it['id'],
            'title' => $it['name'] ?? $it['path'],
            'description' => $it['description'] ?? '',
            'author' => $it['namespace']['full_path'] ?? ($it['owner']['username'] ?? ''),
            'platform' => 'gitlab',
            'avatar_url' => $it['avatar_url'] ?? '',
            'web_url' => $it['web_url'],
            'has_wiki' => (bool)($it['wiki_enabled'] ?? false),
            'package_name' => $it['path_with_namespace'] ?? null,
            'repo_url' => $it['web_url'] ?? null,
        ];
    }
    return $out;
}

    private function fetchGithubProject(string $fullName): array
    {
        $url = $this->githubApi . '/repos/' . $fullName;
        $json = $this->httpGet($url, $this->githubHeaders());
        $it = json_decode($json, true) ?: [];
        return [
            'id' => $it['full_name'] ?? $fullName,
            'title' => $it['name'] ?? $fullName,
            'description' => $it['description'] ?? '',
            'author' => $it['owner']['login'] ?? '',
            'platform' => 'github',
            'avatar_url' => $it['owner']['avatar_url'] ?? '',
            'web_url' => $it['html_url'] ?? ('https://github.com/' . $fullName),
            'has_wiki' => (bool)($it['has_wiki'] ?? false),
            'package_name' => $it['full_name'] ?? $fullName,
            'repo_url' => $it['html_url'] ?? ('https://github.com/' . $fullName),
        ];
    }

    private function fetchGitlabProject(string $id): array
    {
        $url = $this->gitlabApi . '/projects/' . urlencode($id);
        $json = $this->httpGet($url, $this->gitlabHeaders());
        $it = json_decode($json, true) ?: [];
        return [
            'id' => (string)($it['id'] ?? $id),
            'title' => $it['name'] ?? $it['path'] ?? $id,
            'description' => $it['description'] ?? '',
            'author' => $it['namespace']['full_path'] ?? '',
            'platform' => 'gitlab',
            'avatar_url' => $it['avatar_url'] ?? '',
            'web_url' => $it['web_url'] ?? '',
            'has_wiki' => (bool)($it['wiki_enabled'] ?? false),
            'package_name' => $it['path_with_namespace'] ?? null,
            'repo_url' => $it['web_url'] ?? null,
        ];
    }

    private function fetchGithubTags(string $fullName): array
    {
        $url = $this->githubApi . '/repos/' . $fullName . '/tags?per_page=100';
        $json = $this->httpGet($url, $this->githubHeaders());
        $items = json_decode($json, true) ?: [];
        return array_values(array_filter(array_map(function($t){ return $t['name'] ?? null; }, $items)));
    }

    private function fetchGitlabTags(string $id): array
    {
        $url = $this->gitlabApi . '/projects/' . urlencode($id) . '/repository/tags?per_page=100';
        $json = $this->httpGet($url, $this->gitlabHeaders());
        $items = json_decode($json, true) ?: [];
        return array_values(array_filter(array_map(function($t){ return $t['name'] ?? null; }, $items)));
    }

    private function fetchGitlabWikiList(string $id): array
    {
        $url = $this->gitlabApi . '/projects/' . urlencode($id) . '/wikis';
        $json = $this->httpGet($url, $this->gitlabHeaders());
        $items = json_decode($json, true) ?: [];
        return array_map(function($p){
            return [
                'id' => $p['slug'] ?? $p['title'],
                'title' => $p['title'] ?? ($p['slug'] ?? 'Seite')
            ];
        }, $items);
    }

    private function fetchGitlabWikiPage(string $id, string $pageId): ?string
    {
        $url = $this->gitlabApi . '/projects/' . urlencode($id) . '/wikis/' . urlencode($pageId);
        $json = $this->httpGet($url, $this->gitlabHeaders());
        $item = json_decode($json, true) ?: null;
        return $item['content'] ?? null;
    }

    private function httpGet(string $url, array $headers = []): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'phpapp2-setup', // WICHTIG für GitHub
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $out = curl_exec($ch);
    if ($out === false) {
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        throw new \RuntimeException('HTTP GET failed (' . $code . '): ' . $err);
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Zusätzliche Fehlerbehandlung bei 4xx/5xx
    if ($code >= 400) {
        throw new \RuntimeException('HTTP GET returned status ' . $code . ' for ' . $url);
    }
    return $out;
}

    private function githubHeaders(): array
{
    $h = [
        'Accept: application/vnd.github+json',
    ];
    if ($this->githubToken) {
        $h[] = 'Authorization: Bearer ' . $this->githubToken;
    }
    return $h;
}

    private function gitlabHeaders(): array
{
    $h = [
        'Accept: application/json',
    ];
    if ($this->gitlabToken) {
        $h[] = 'PRIVATE-TOKEN: ' . $this->gitlabToken;
    }
    return $h;
}

    private function escapeQuery(string $q): string
    {
        return preg_replace('/\s+/', '+', trim($q));
    }
}
