<?php
/**
 * File containing the View\Provider\Block class.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

namespace eZ\Publish\Core\MVC\Legacy\View\Provider;

use eZ\Publish\Core\MVC\Legacy\View\Provider;
use eZ\Publish\Core\MVC\Symfony\View\BlockValueView;
use eZ\Publish\Core\MVC\Symfony\View\View;
use eZ\Publish\Core\MVC\Symfony\View\ViewProvider;
use eZ\Publish\Core\MVC\Legacy\Templating\Adapter\BlockAdapter;
use eZ\Publish\Core\MVC\Symfony\View\ContentView;
use eZ\Publish\Core\FieldType\Page\PageService;
use eZTemplate;
use ezpEvent;

class Block extends Provider implements ViewProvider
{
    /**
     * @var \eZ\Publish\Core\FieldType\Page\PageService
     */
    protected $pageService;

    /**
     * @param \eZ\Publish\Core\FieldType\Page\PageService $pageService
     */
    public function setPageService(PageService $pageService)
    {
        $this->pageService = $pageService;
    }

    /**
     * Returns a ContentView object corresponding to block found within $view, or null if not applicable.
     *
     * @param \eZ\Publish\Core\MVC\Symfony\View\View $view
     *
     * @return \eZ\Publish\Core\MVC\Symfony\View\ContentView
     */
    public function getView(View $view)
    {
        if (!$view instanceof BlockValueView) {
            return null;
        }

        $block = $view->getBlock();

        $legacyKernel = $this->getLegacyKernel();
        $legacyBlockClosure = function (array $params) use ($block, $legacyKernel) {
            return $legacyKernel->runCallback(
                function () use ($block, $params) {
                    $tpl = eZTemplate::factory();
                    /**
                     * @var \eZObjectForwarder
                     */
                    $funcObject = $tpl->fetchFunctionObject('block_view_gui');
                    if (!$funcObject) {
                        return '';
                    }

                    $children = array();
                    $funcObject->process(
                        $tpl, $children, 'block_view_gui', false,
                        array(
                            'block' => array(
                                array(
                                    eZTemplate::TYPE_ARRAY,
                                    // eZTemplate::TYPE_OBJECT does not exist because
                                    // it's not possible to create "inline" objects in
                                    // legacy template engine (ie objects are always
                                    // stored in a tpl variable).
                                    // TYPE_ARRAY is used here to allow to directly
                                    // retrieve the object without creating a variable.
                                    // (TYPE_STRING, TYPE_BOOLEAN, ... have the same
                                    // behaviour, see eZTemplate::elementValue())
                                    new BlockAdapter($block),
                                ),
                            ),
                        ),
                        array(), '', ''
                    );
                    if (is_array($children) && isset($children[0])) {
                        return ezpEvent::getInstance()->filter('response/output', $children[0]);
                    }

                    return '';
                },
                false
            );
        };

        return new ContentView($legacyBlockClosure);
    }
}
