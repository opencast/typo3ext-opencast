<?php
namespace Uos\Opencast\Helpers;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\AbstractOnlineMediaHelper;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class OpencastHelper extends AbstractOnlineMediaHelper
{
    protected $extension = 'opencast';
    protected string $host;
    protected int $version;

    private const MEDIA_ID_PATTERN = '([0-9a-f\-]+)';

    private const PATH_PATTERNS = [
        'paella\/ui\/watch\.html\?id=' . self::MEDIA_ID_PATTERN,
        'paella\/ui\/watch\.html\?cid=[0-9a-f]+&id=' . self::MEDIA_ID_PATTERN,
        'play\/' . self::MEDIA_ID_PATTERN,
    ];

    private static $cache = [];

    public function __construct($extension)
    {
        $this->extension = $extension;
        $this->host = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('opencast', 'host');
        $this->host = rtrim($this->host, '/') . '/';

        $this->version = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('opencast', 'version') ?? 16;
    }

    /**
     * Try to transform given URL to a File
     *
     * @param string $url
     * @param Folder $targetFolder
     * @return File|null
     */
    public function transformUrlToFile($url, Folder $targetFolder)
    {
        if ($this->host) {
            // Prepare host for regex
            $hostPattern = str_replace(['/', '.'], ['\/', '\.'], $this->host);

            foreach (self::PATH_PATTERNS as $pathPattern) {
                if (preg_match('/^' . $hostPattern . $pathPattern . '$/i', $url, $match)) {
                    $mediaId = $match[1];

                    $file = $this->findExistingFileByOnlineMediaId(
                        $mediaId,
                        $targetFolder,
                        $this->extension
                    );

                    // no existing file create new
                    if ($file === null) {
                        $filename = $this->getTitle($mediaId) . '.' . $this->extension;

                        $file = $this->createNewFile(
                            $targetFolder, // folder
                            $filename,     // filename
                            $mediaId       // content
                        );
                    }

                    return $file;
                }
            }
        } else {
            DebugUtility::debug(
                'Please make sure the \'host\' is defined within the extension configuration of EXT:opencast',
            );
            die();
        }
    }

    /**
     * Get public url
     *
     * Return NULL if you want to use core default behaviour
     *
     * @param File $file
     * @param bool $relativeToCurrentScript
     * @return string|null
     */
    public function getPublicUrl(File $file, $relativeToCurrentScript = false)
    {
        return null;
    }

    /**
     * Get local absolute file path to preview image
     *
     * Return an empty string when no preview image is available
     *
     * @param File $file
     * @return string
     */
    public function getPreviewImage(File $file)
    {
        $mediaId = $this->getOnlineMediaId($file);
        $temporaryFileName = $this->getTempFolderPath() . 'opencast_' . md5($mediaId) . '.png';

        if (!file_exists($temporaryFileName)) {
            $attachments = $this->getAttachments($mediaId);
            foreach ($attachments ?? [] as $attachment) {
                $previewImage = false;
                if ($attachment['type'] === 'presenter/player+preview') {
                    $previewImage = GeneralUtility::getUrl($attachment['url']);
                }
                if ($previewImage !== false) {
                    file_put_contents($temporaryFileName, $previewImage);
                    GeneralUtility::fixPermissions($temporaryFileName);
                    break;
                }
            }
        }

        return $temporaryFileName;
    }

    /**
     * Get meta data for OnlineMedia item
     *
     * See $GLOBALS[TCA][sys_file_metadata][columns] for possible fields to fill/use
     *
     * @param File $file
     * @return array with metadata
     */
    public function getMetaData(File $file)
    {
        $mediaId = $this->getOnlineMediaId($file);
        $metadata = $this->fetchMetaData($mediaId);

        return $metadata;
    }

    protected function getTitle($mediaId): string
    {
        return $this->fetchMetaData($mediaId)['title'];
    }

    protected function fetchMetaData($mediaId): array
    {
        if ($data = $this->fetchJson($mediaId)) {
            return $data['metadata'];
        } else {
            // Fallback: most basic information we've got!
            return ['title' => $mediaId];
        }
    }

    protected function getAttachments($mediaId): ?array
    {
        if ($data = $this->fetchJson($mediaId)) {
            return $data['attachments'];
        }

        return null;
    }

    /**
     * See docs for details on API endpoint:
     * https://stable.opencast.org/docs.html?path=/search#episodes-1
     *
     * @param  string $mediaId
     * @return array
     */
    protected function fetchJson($mediaId): ?array
    {
        if (preg_match('/' . self::MEDIA_ID_PATTERN . '/', $mediaId)) {
            if (empty(self::$cache[$mediaId])) {
                $url = $this->host . 'search/episode.json?id=' . $mediaId;
                if ($json = GeneralUtility::getUrl($url)) {
                    $json = json_decode($json, true);

                    $data = [
                        'metadata' => [],
                        'attachments' => [],
                    ];

                    if ($this->version < 16) {
                        // Opencast legacy (Solr Search)
                        if (isset($json['search-results']['result']) &&
                            is_array($json['search-results']['result'])) {
                            $legacyResult = $json['search-results']['result'];

                            $data['metadata']['title'] = $legacyResult['dcTitle'] ?? '';
                            $data['metadata']['creator'] = $legacyResult['dcCreator'] ?? '';
                            $data['metadata']['publisher'] = $legacyResult['dcPublisher'] ?? '';
                            $data['metadata']['content_creation_date'] = strtotime($legacyResult['dcCreated'] ?? '');
                            $data['metadata']['content_modification_date'] = strtotime($legacyResult['modified'] ?? '');
                            $data['metadata']['keywords'] = $legacyResult['keywords'] ?? '';
                            if ($legacyResult['mediapackage'] ?? false) {
                                $data['metadata']['duration'] = $legacyResult['mediapackage']['duration'] ?? 0;
                            }

                            if (isset($legacyResult['mediapackage']['attachments']['attachment']) &&
                                is_array($legacyResult['mediapackage']['attachments']['attachment'])) {
                                $data['attachments'] = $legacyResult['mediapackage']['attachments']['attachment'];
                            }
                        }
                    } else {
                        // Opencast 16+ (OpenSearch)
                        if (isset($json['result'][0]) &&
                            is_array($json['result'][0])) {
                            $result = $json['result'][0];

                            $data['metadata']['title'] = $result['dc']['title'][0] ?? '';
                            $data['metadata']['creator'] = $result['dc']['creator'][0] ?? '';
                            $data['metadata']['publisher'] = $result['dc']['publisher'][0] ?? '';
                            $data['metadata']['content_creation_date'] = strtotime($result['dc']['created'][0] ?? '');
                            $data['metadata']['content_modification_date'] = strtotime($result['modified'] ?? '');
                            $data['metadata']['keywords'] = $result['keywords'] ?? '';
                            if ($result['mediapackage'] ?? false) {
                                $data['metadata']['duration'] = ($result['mediapackage']['duration'] ?? 0) / 1000;
                            }

                            if (isset($result['mediapackage']['attachments']['attachment']) &&
                                is_array($result['mediapackage']['attachments']['attachment'])) {
                                $data['attachments'] = $result['mediapackage']['attachments']['attachment'];
                            }
                        }
                    }

                    self::$cache[$mediaId] = $data;
                }
            }

            return self::$cache[$mediaId] ?? null;
        }

        return null;
    }
}
