<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;

class LyricsScraper {

    public function getLyrics(string $title, ?string $artist, string $provider, int $resultIndex): array {
        if ($artist == null)
            $artist = "";
        switch (strtolower($provider)) {
            case 'jlyric':
                return $this->scrapeJLyric($title, $artist, $resultIndex);
                break;
            case 'utaten':
                return $this->scrapeUtaten($title, $artist, $resultIndex);
                break;
            case 'musixmatch':
                return $this->scrapeMusixmatch($title, $artist, $resultIndex);
                break;
            default:
                return array('lyrics'=>'', 'results'=>0, 'message'=>'Unsupported provider.');
        }
    }
    
    private function scrapeJLyric(string $title, string $artist, int $resultIndex): array {
        $htmlContent = '';
        $message = 'unspecified error. something might be wrong with the code so please let me know.';

        libxml_use_internal_errors(true);

        $dom = new DomDocument;
        $dom->loadHtmlFile('http://search.j-lyric.net/index.php?ex=on&ct=2&ca=2&cl=2&kt='.rawurlencode($title).'&ka='.rawurlencode($artist));
        $xpath = new DomXPath($dom);
        $nodes = $xpath->query('//div[@id="mnb"]/div[@class="bdy"]/p[1]/a');

        $resultCount = $nodes->length;
        if (!$resultCount) {
            $nodes = $xpath->query('//div[@id="mnb"]/div[@class="bdyc"]');
            if ($nodes->length) {
                $message = 'No results.';
            }
        } 
        else if ($resultIndex < $resultCount) {
            $link = $nodes[$resultIndex]->getAttribute("href");
            $dom->loadHtmlFile($link);
            $xpath = new DomXPath($dom);
            $nodes = $xpath->query('//p[@id="Lyric"]');
            if ($nodes->length) {
                $htmlContent = $nodes[0]->ownerDocument->saveHTML($nodes[0]);
                //$htmlContent = str_replace(array("\r", "\n"), '<br>', $htmlContent);
                $message = 'Showing result '.($resultIndex + 1).' out of '.$resultCount.'.';
            }
        }

        $result = [
            'lyrics' => strip_tags($htmlContent, '<br>'),
            'results' => $resultCount,
            'message' => $message
        ];

        return $result;
    }

    private function scrapeUtaten(string $title, string $artist, int $resultIndex): array {
        $root = "https://utaten.com";
        $htmlContent = '';
        $message = 'unspecified error. something might be wrong with the code so please let me know.';

        $context = stream_context_create(array(
            "ssl"=>array(
                'verify_peer' => false,
                'verify_peer_name' => false
            )
        ));
        
        libxml_set_streams_context($context);
        libxml_use_internal_errors(true);

        $dom = new DomDocument;
        $dom->loadHtmlFile('https://utaten.com/search/=/sort=popular_sort%3Aasc/artist_name='.rawurlencode($artist).'/title='.rawurlencode($title));
        $xpath = new DomXPath($dom);
        $nodes = $xpath->query('//div[@class="contentBox__body"]/table[1]//tr/td[1]/p[@class="searchResult__title"]/a');

        $resultCount = $nodes->length;

        if (!$resultCount) {
            $nodes = $xpath->query('//div[@class="contentBox__body"]/p[@class="noItem"]');
            if ($nodes->length) {
                $message = 'No results.';
            }
        }
        else if ($resultIndex < $resultCount) {
            $link = $nodes[$resultIndex]->getAttribute("href");
            $dom->loadHtmlFile($root.$link);
            $xpath = new DomXPath($dom);
            $nodes = $xpath->query('//div[@class="hiragana"]');
            if ($nodes->length) {
                $result = new DOMDocument();
                $cloned = $nodes[0]->cloneNode(TRUE);
                $result->appendChild($result->importNode($cloned, True));
                $xpath = new DomXPath($result);
                foreach($xpath->query('//span/span[@class="rt"]') as $e ) {
                    $e->parentNode->removeChild($e);
                }
                $htmlContent = $result->saveHTML($result);
                $htmlContent = str_replace(array("\r", "\n"), '', $htmlContent);
                $message = 'Showing result '.($resultIndex + 1).' out of '.$resultCount.'.';
            }
        }

        $result = [
            'lyrics' => strip_tags($htmlContent, '<br>'),
            'results' => $resultCount,
            'message' => $message
        ];

        return $result;
    }

    private function scrapeMusixmatch(string $title, string $artist, int $resultIndex): array {
        $root = "https://www.musixmatch.com";
        $htmlContent = '';
        $message = 'unspecified error. something might be wrong with the code so please let me know.';

        $context = stream_context_create(array(
            "ssl"=>array(
                'verify_peer' => false,
                'verify_peer_name' => false
            )
        ));
        
        libxml_set_streams_context($context);
        libxml_use_internal_errors(true);

        $dom = new DomDocument;
        $url = 'https://www.musixmatch.com/search/'.rawurlencode($title.' '.$artist).'/tracks';
        $dom->loadHtmlFile('https://www.musixmatch.com/search/'.rawurlencode($title.' '.$artist).'/tracks');
        $xpath = new DomXPath($dom);
        $nodes = $xpath->query('//div[@id="search-tracks"]//ul[contains(@class, "tracks")]//div[contains(@class, "track-card")]//meta[1]');
        
        $resultCount = $nodes->length;

        if (!$resultCount) {
            $nodes = $xpath->query('//div[@id="search-tracks"]//div[@class="empty"]');
            if ($nodes->length) {
                $message = 'No results.';
            }
        }
        else if ($nodes->length) {
            $link = $nodes[0]->getAttribute("content");
            $dom->loadHtmlFile($root.$link);
            $xpath = new DomXPath($dom);
            $nodes = $xpath->query('//div[@class="mxm-lyrics"]//p[contains(@class, "mxm-lyrics__content")]/span');

            if ($nodes->length) {
                foreach($nodes as $node){
                    $htmlContent = $htmlContent.$node->ownerDocument->saveHTML($node);
                }
                $htmlContent = str_replace(array("\r", "\n"), '<br>', $htmlContent);
                $message = 'Showing result '.($resultIndex + 1).' out of '.$resultCount.'.';
            }
        }

        $result = [
            'lyrics' => strip_tags($htmlContent, '<br>'),
            'results' => $resultCount,
            'message' => $message
        ];

        return $result;
    }
}