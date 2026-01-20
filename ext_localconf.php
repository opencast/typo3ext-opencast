<?php

defined('TYPO3') || die('Access denied.');

// Register opencast as media type
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['onlineMediaHelpers']['opencast'] = \Uos\Opencast\Helpers\OpencastHelper::class;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['FileInfo']['fileExtensionToMimeType']['opencast'] = 'video/opencast';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['mediafile_ext'] .= ',opencast';

$rendererRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Rendering\RendererRegistry::class);
$rendererRegistry->registerRendererClass(
    \Uos\Opencast\Rendering\OpencastRenderer::class
);
