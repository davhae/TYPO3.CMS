<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Adminpanel\Controller;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Adminpanel\Modules\AdminPanelModuleInterface;
use TYPO3\CMS\Adminpanel\Service\ModuleLoader;
use TYPO3\CMS\Adminpanel\View\AdminPanelView;
use TYPO3\CMS\Backend\FrontendBackendUserAuthentication;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Main controller for the admin panel
 *
 * @internal
 */
class MainController implements SingletonInterface
{
    /**
     * @var array<AdminPanelModuleInterface>
     */
    protected $modules = [];

    /**
     * Initializes settings for the admin panel.
     *
     * @param \TYPO3\CMS\Core\Http\ServerRequest $request
     */
    public function initialize(ServerRequest $request): void
    {
        $moduleLoader = GeneralUtility::makeInstance(ModuleLoader::class);
        $this->modules = $moduleLoader->getModulesFromConfiguration();
        $this->saveConfiguration();

        foreach ($this->modules as $module) {
            if ($module->isEnabled()) {
                $module->initializeModule($request);
            }
        }
    }

    /**
     * Renders the panel - Is currently called via RenderHook in postProcessOutput
     *
     * @todo Still uses the legacy AdminpanelView and should be rewritten to fluid
     *
     * @return string
     */
    public function render(): string
    {
        // handling via legacy functions
        $adminPanelView = GeneralUtility::makeInstance(AdminPanelView::class);
        $adminPanelView->setModules($this->modules);
        return $adminPanelView->display();
    }

    /**
     * Save admin panel configuration to backend user UC
     */
    protected function saveConfiguration(): void
    {
        $input = GeneralUtility::_GP('TSFE_ADMIN_PANEL');
        $beUser = $this->getBackendUser();
        if (is_array($input)) {
            // Setting
            $beUser->uc['TSFE_adminConfig'] = array_merge(
                !is_array($beUser->uc['TSFE_adminConfig']) ? [] : $beUser->uc['TSFE_adminConfig'],
                $input
            );
            unset($beUser->uc['TSFE_adminConfig']['action']);

            foreach ($this->modules as $module) {
                if ($module->isEnabled() && $module->isOpen()) {
                    $module->onSubmit($input);
                }
            }
            // Saving
            $beUser->writeUC();
            // Flush fluid template cache
            $cacheManager = new CacheManager();
            $cacheManager->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);
            $cacheManager->getCache('fluid_template')->flush();
        }
    }

    /**
     * Returns LanguageService
     *
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Returns the current BE user.
     *
     * @return FrontendBackendUserAuthentication
     */
    protected function getBackendUser(): FrontendBackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @return TypoScriptFrontendController
     */
    protected function getTypoScriptFrontendController(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }
}
