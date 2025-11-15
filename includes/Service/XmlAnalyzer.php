<?php
namespace Kashiwazaki\XmlVitalCheck\Service;

if (!defined('ABSPATH')) exit;

class XmlAnalyzer {
    public function analyzeUrl(string $url, bool $withReachability = false): array {
        $url = $this->sanitizeUrlString($url);
        $response = wp_remote_get($url, [
            'timeout' => 60, // XMLファイル取得のタイムアウトを60秒に延長
            'sslverify' => false, // SSL証明書の検証を無効化（必要に応じて）
            'redirection' => 5, // リダイレクトを5回まで許可
            'headers' => [
                'Accept' => 'application/xml,text/xml,*/*;q=0.1',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Kashiwazaki-XML-VitalCheck/1.0'
            ],
        ]);
        if (is_wp_error($response)) {
            return [
                'ok' => false,
                'xml_version' => null,
                'format_type' => null,
                'count' => 0,
                'empty' => false,
                'message' => $response->get_error_message(),
            ];
        }
        $body = wp_remote_retrieve_body($response);
        $trimmed = trim((string)$body);
        if ($trimmed === '') {
            return [
                'ok' => false,
                'xml_version' => null,
                'format_type' => null,
                'count' => 0,
                'empty' => true,
                'message' => '空のコンテンツ',
            ];
        }
        $xml = simplexml_load_string($trimmed, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($xml === false) {
            return [
                'ok' => false,
                'xml_version' => $this->extractXmlVersion($trimmed),
                'format_type' => null,
                'count' => 0,
                'empty' => false,
                'message' => 'XML解析に失敗しました',
            ];
        }
        $xmlVersion = $this->extractXmlVersion($trimmed);
        $formatType = $this->detectFormatType($xml);
        $count = $this->estimateItemCount($xml, $formatType);
        $reachability = ['reachable' => null, 'total' => null];
        if ($withReachability) {
            $reachability = $this->checkEmbeddedUrls($xml, $formatType);
        }
        return [
            'ok' => true,
            'xml_version' => $xmlVersion,
            'format_type' => $formatType,
            'count' => $count,
            'reachable' => $reachability['reachable'],
            'reachable_total' => $reachability['total'],
            'empty' => $count === 0,
            'message' => null,
        ];
    }

    private function extractXmlVersion(string $raw): ?string {
        if (preg_match('/<\?xml\s+version=["\']([^"\']+)["\'].*?\?>/i', $raw, $m)) return $m[1];
        return null;
    }

    private function detectFormatType(\SimpleXMLElement $xml): ?string {
        $root = $xml->getName();
        $ns = $xml->getNamespaces(true);
        if ($root === 'sitemapindex') return 'Sitemap Index';
        if ($root === 'urlset') return 'Sitemap URL Set';
        if ($root === 'feed') {
            if (isset($ns['atom']) || (string)$xml['xmlns'] === 'http://www.w3.org/2005/Atom') return 'Atom Feed';
            return 'Feed';
        }
        if ($root === 'rss') return 'RSS';
        if ($root === 'sitemap') return 'Sitemap';
        return $root ?: null;
    }

    private function estimateItemCount(\SimpleXMLElement $xml, ?string $formatType = null): int {
        if ($formatType === 'Sitemap Index') {
            $nodes = $xml->xpath("//*[local-name()='sitemap']");
            $count = is_array($nodes) ? count($nodes) : 0;
            if ($count > 0) return $count;
        }
        if ($formatType === 'Sitemap URL Set') {
            $nodes = $xml->xpath("//*[local-name()='url']");
            $count = is_array($nodes) ? count($nodes) : 0;
            if ($count > 0) return $count;
        }
        if ($formatType === 'RSS') {
            $nodes = $xml->xpath('//channel/item');
            $count = is_array($nodes) ? count($nodes) : 0;
            if ($count > 0) return $count;
        }
        if ($formatType === 'Atom Feed') {
            $nodes = $xml->xpath("//*[local-name()='entry']");
            $count = is_array($nodes) ? count($nodes) : 0;
            if ($count > 0) return $count;
        }
        $rootChildren = $xml->children();
        $freq = [];
        foreach ($rootChildren as $child) {
            $name = $child->getName();
            $freq[$name] = ($freq[$name] ?? 0) + 1;
        }
        if (!empty($freq)) {
            arsort($freq);
            $first = array_key_first($freq);
            $count = $freq[$first] ?? 0;
            if ($count > 1) return $count;
        }
        foreach ($rootChildren as $child) {
            $subChildren = $child->children();
            if (count($subChildren) > 1) {
                $subFreq = [];
                foreach ($subChildren as $sub) {
                    $nm = $sub->getName();
                    $subFreq[$nm] = ($subFreq[$nm] ?? 0) + 1;
                }
                if (!empty($subFreq)) {
                    arsort($subFreq);
                    $first = array_key_first($subFreq);
                    $count = $subFreq[$first] ?? 0;
                    if ($count > 1) return $count;
                }
            }
        }
        $all = $xml->xpath('//*');
        $total = is_array($all) ? max(0, count($all) - 1) : 0;
        return $total > 0 ? $total : 0;
    }

    private function checkEmbeddedUrls(\SimpleXMLElement $xml, ?string $formatType): array {
        $paths = [];
        if ($formatType === 'Sitemap URL Set') {
            $paths[] = "//*[local-name()='url']/*[local-name()='loc']";
        } elseif ($formatType === 'Sitemap Index') {
            $paths[] = "//*[local-name()='sitemap']/*[local-name()='loc']";
        } elseif ($formatType === 'RSS') {
            $paths[] = '//channel/item/link';
        } elseif ($formatType === 'Atom Feed') {
            $paths[] = "//*[local-name()='entry']/*[local-name()='link']/@href";
        } else {
            $paths[] = '//loc';
            $paths[] = '//link';
            $paths[] = "//*[@href]/@href";
        }
        $urls = [];
        foreach ($paths as $p) {
            $nodes = $xml->xpath($p);
            if (is_array($nodes)) {
                foreach ($nodes as $n) {
                    $urls[] = trim((string)$n);
                }
            }
        }
        $urls = array_values(array_filter(array_unique($urls)));
        $reachable = 0;
        foreach ($urls as $u) {
            $head = wp_remote_head($u, ['timeout' => 10, 'redirection' => 3]);
            if (is_wp_error($head)) continue;
            $code = (int) wp_remote_retrieve_response_code($head);
            if ($code >= 200 && $code < 400) $reachable++;
        }
        return ['reachable' => $reachable, 'total' => count($urls)];
    }

    public function extractUrlsForIndex(string $sourceUrl, int $page = 1, int $perPage = 100): array {
        $response = wp_remote_get($sourceUrl, [
            'timeout' => 60, // XMLファイル取得のタイムアウトを60秒に延長
            'sslverify' => false, // SSL証明書の検証を無効化（必要に応じて）
            'redirection' => 5, // リダイレクトを5回まで許可
            'headers' => [
                'Accept' => 'application/xml,text/xml,*/*;q=0.1',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Kashiwazaki-XML-VitalCheck/1.0'
            ],
        ]);
        if (is_wp_error($response)) {
            return ['total' => 0, 'items' => [], 'error' => $response->get_error_message()];
        }
        $body = wp_remote_retrieve_body($response);
        $trimmed = trim((string)$body);
        if ($trimmed === '') {
            return ['total' => 0, 'items' => [], 'error' => '空のコンテンツ'];
        }
        $xml = simplexml_load_string($trimmed, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($xml === false) {
            return ['total' => 0, 'items' => [], 'error' => 'XML解析に失敗しました'];
        }
        $formatType = $this->detectFormatType($xml);
        $paths = [];
        if ($formatType === 'Sitemap URL Set') {
            $paths[] = "//*[local-name()='url']/*[local-name()='loc']";
        } elseif ($formatType === 'Sitemap Index') {
            $paths[] = "//*[local-name()='sitemap']/*[local-name()='loc']";
        } elseif ($formatType === 'RSS') {
            $paths[] = '//channel/item/link';
        } elseif ($formatType === 'Atom Feed') {
            $paths[] = "//*[local-name()='entry']/*[local-name()='link']/@href";
        } else {
            $paths[] = '//loc';
            $paths[] = '//link';
            $paths[] = "//*[@href]/@href";
        }
        $urls = [];
        foreach ($paths as $p) {
            $nodes = $xml->xpath($p);
            if (is_array($nodes)) {
                foreach ($nodes as $n) {
                    $urls[] = $this->sanitizeUrlString((string)$n);
                }
            }
        }
        $urls = array_values(array_filter(array_unique($urls)));
        $total = count($urls);
        // 全件返却（制限なし）
        return ['total' => $total, 'items' => $urls, 'format_type' => $formatType];
    }

    private function sanitizeUrlString(string $url): string {
        $u = trim($url);
        if ($u === '') return '';
        $u = ltrim($u, '@');
        return $u;
    }
}
