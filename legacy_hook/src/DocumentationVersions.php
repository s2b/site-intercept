<?php
declare(strict_types = 1);

namespace App;

/*
 * This file is part of the package t3g/intercept-legacy-hook.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Return a list of other versions of given documentation
 */
class DocumentationVersions
{
    protected $request;

    public function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * Output list of versions
     */
    public function getVersions(): Response
    {
        $url = $this->request->getQueryParams()['url'] ?? '';

        // /p/vendor/package/version/some/sub/page/Index.html/
        $urlPath = '/' . trim((parse_url($url)['path']) ?? '', '/') . '/';

        // Simple path traversal protection: remove '/../' and '/./'
        $urlPath = str_replace('/../', '', $urlPath);
        $urlPath = str_replace('/./', '', $urlPath);

        // Remove leading and trailing slashes again
        $urlPath = trim($urlPath, '/');
        $urlPath = explode('/', $urlPath);
        if (count($urlPath) < 4) {
            return new Response(200, [], '');
        }

        // first three segments are main root of that repo - eg. '[p, lolli42, enetcache]'
        $entryPoint = array_slice($urlPath, 0, 3);
        // 'current' called version, eg. 'master', or '9.5'
        $currentVersion = array_slice($urlPath, 3, 1)[0];
        // further path to currently viewed sub file, eg. '[subPage, Index.html]'
        $pathAfterEntryPoint = array_slice($urlPath, 4, 99);

        if (empty($currentVersion)) {
            return new Response(200, [], '');
        }

        // verify entry path exists and current version and full path actually exist
        // this additionally sanitizes the input url
        $documentRoot = $GLOBALS['_SERVER']['DOCUMENT_ROOT'];
        $filePathToDocsEntryPoint = $documentRoot . '/' . implode('/', $entryPoint);
        if (!is_dir($filePathToDocsEntryPoint)
            || !is_dir($filePathToDocsEntryPoint . '/' . $currentVersion)
            || !file_exists($filePathToDocsEntryPoint . '/' . $currentVersion . '/' . implode('/', $pathAfterEntryPoint))
        ) {
            return new Response(200, [], '');
        }

        // find versions of this project
        $versions = scandir($filePathToDocsEntryPoint);
        $validatedVersions = [];
        foreach ($versions as $version) {
            if ($version === '.' || $version === '..' || !is_dir($filePathToDocsEntryPoint . '/' . $version)) {
                continue;
            }
            $validatedVersions[] = $version;
        }

        // final version entries
        $entries = [];
        // One entry per version that is deployed
        foreach ($validatedVersions as $version) {
            $entry = $filePathToDocsEntryPoint . '/' . $version . '/';
            $checkSubPaths = $pathAfterEntryPoint;
            $subPathCount = count($checkSubPaths);
            for ($i = 0; $i < $subPathCount; $i++) {
                // Traverse sub path segments up until one has been found in filesystem, to find the
                // "nearest" matching version of currently viewed file
                $pathToCheck = $filePathToDocsEntryPoint . '/' . $version . '/' . implode('/', $checkSubPaths);
                if (is_file($pathToCheck) || is_dir($pathToCheck)) {
                    $entry = $pathToCheck;
                    break;
                }
                array_pop($checkSubPaths);
            }
            $entries[$version] = $entry;
        }

        $finalEntries = [];
        foreach ($entries as $version => $entry) {
            $finalEntries[] = '<dd><a href="https://stage.docs.typo3.com/' . str_replace($documentRoot, '', $entry) . '">' . $version . '</a></dd>';
        }

        return new Response(200, [], implode(chr(10), $finalEntries));
    }
}