<?php

namespace Uos\Opencast\Rendering;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\OnlineMediaHelperInterface;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\OnlineMediaHelperRegistry;
use TYPO3\CMS\Core\Resource\Rendering\FileRendererInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

class OpencastRenderer implements FileRendererInterface
{
    protected ?OnlineMediaHelperInterface $onlineMediaHelper = null;

    /**
     * Returns the priority of the renderer
     * This way it is possible to define/overrule a renderer
     * for a specific file type/context.
     * For example create a video renderer for a certain storage/driver type.
     * Should be between 1 and 100, 100 is more important than 1
     *
     * @return int
     */
    public function getPriority()
    {
        return 1;
    }

    /**
     * Check if given File(Reference) can be rendered
     *
     * @param FileInterface $file File of FileReference to render
     * @return bool
     */
    public function canRender(FileInterface $file)
    {
        return ($file->getMimeType() === 'video/opencast' || $file->getExtension() === 'opencast') && $this->getOnlineMediaHelper($file) !== false;
    }

    /**
     * Get online media helper
     *
     * @param FileInterface $file
     * @return OnlineMediaHelperInterface|null
     */
    protected function getOnlineMediaHelper(FileInterface $file)
    {
        if ($this->onlineMediaHelper === null) {
            $orgFile = $file;
            if ($orgFile instanceof FileReference) {
                $orgFile = $orgFile->getOriginalFile();
            }
            if ($orgFile instanceof File) {
                $this->onlineMediaHelper = GeneralUtility::makeInstance(OnlineMediaHelperRegistry::class)->getOnlineMediaHelper($orgFile);
            }
        }
        return $this->onlineMediaHelper;
    }

    /**
     * Render for given File(Reference) html output
     *
     * @param FileInterface $file
     * @param int|string $width TYPO3 known format; examples: 220, 200m or 200c
     * @param int|string $height TYPO3 known format; examples: 220, 200m or 200c
     * @param array $options
     * @return string
     */
    public function render(FileInterface $file, $width, $height, array $options = [])
    {
        if ($host = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('opencast', 'host')) {
            $mediaId = $this->getMediaIdFromFile($file);

            try {
                $typoscript = $this->getTypoScript();
            } catch (\Exception $e) {
                return $e->getMessage();
            }

            $view = $this->getView($typoscript, 'Opencast/Iframe');

            $view->assignMultiple([
                'host' => $host,
                'file' => $file,
                'mediaId' => $mediaId,
            ]);

            return $view->render();
        }

        return 'Missing configuration: host!';
    }

    /**
     * @param FileInterface $file
     * @return string
     */
    protected function getMediaIdFromFile(FileInterface $file)
    {
        if ($file instanceof FileReference) {
            $orgFile = $file->getOriginalFile();
        } else {
            $orgFile = $file;
        }

        if ($orgFile instanceof File) {
            return $this->getOnlineMediaHelper($file)->getOnlineMediaId($orgFile);
        }

        return '';
    }

    protected function getTypoScript()
    {
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $fullTyposcript = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);

        $typoscript = $fullTyposcript['plugin.']['tx_opencast.'] ?? [];

        if (empty($typoscript)) {
            throw new \Exception('Can\'t find typoscript for EXT:opencast!');
        }

        return $typoscript;
    }

    protected function getView($settings, $template = 'Opencast/Iframe')
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setLayoutRootPaths($settings['view.']['layoutRootPaths.'] ?? []);
        $view->setPartialRootPaths($settings['view.']['partialRootPaths.'] ?? []);
        $view->setTemplateRootPaths($settings['view.']['templateRootPaths.'] ?? []);

        $view->setFormat('html');
        $view->setTemplate($template);

        return $view;
    }
}
