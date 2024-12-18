<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;
use dokuwiki\File\PageFile;

/**
 * DokuWiki Plugin renderrevisions (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Andreas Gohr <dokuwiki@cosmocode.de>
 */
class action_plugin_renderrevisions extends ActionPlugin
{
    /** @var array list of pages that are processed by the plugin */
    protected $pages = [];

    /** @var string|null  the current page being saved, used to overwrite the contentchanged check */
    protected $current = null;

    /** @inheritDoc */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('PARSER_CACHE_USE', 'AFTER', $this, 'handleParserCacheUse');

        $controller->register_hook(
            'RENDERER_CONTENT_POSTPROCESS',
            'AFTER',
            $this,
            'handleRenderContent',
            null,
            PHP_INT_MAX // other plugins might want to change the content before we see it
        );

        $controller->register_hook('COMMON_WIKIPAGE_SAVE', 'BEFORE', $this, 'handleCommonWikipageSave');
    }

    /**
     * Event handler for PARSER_CACHE_USE
     *
     * @see https://www.dokuwiki.org/devel:event:PARSER_CACHE_USE
     * @param Event $event Event object
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handleParserCacheUse(Event $event, $param)
    {
        $cacheObject = $event->data;

        if (!$cacheObject->page) return;
        if ($cacheObject->mode !== 'xhtml') return;

        // remember that this page was processed
        // This is a somewhat ugly workaround for when text snippets are rendered within the same page.
        // Those snippets will not have a page context set during cache use event and thus not be processed
        // later on in the RENDERER_CONTENT_POSTPROCESS event
        $this->pages[$cacheObject->page] = true;

        msg("This is before rendering {$cacheObject->page}");
    }


    /**
     * Event handler for RENDERER_CONTENT_POSTPROCESS
     *
     * @see https://www.dokuwiki.org/devel:event:RENDERER_CONTENT_POSTPROCESS
     * @param Event $event Event object
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handleRenderContent(Event $event, $param)
    {
        [$format, $xhtml] = $event->data;
        if ($format !== 'xhtml') return;

        // thanks to the $this->pages property we might be able to skip some of those checks, but they don't hurt
        global $ACT;
        global $REV;
        global $DATE_AT;
        global $ID;
        global $INFO;
        if ($ACT !== 'show') return;
        if ($REV) return;
        if ($DATE_AT) return;
        if (!$INFO['exists']) return;
        if (!$ID) return;
        if (!isset($this->pages[$ID])) return;

        $md5cache = getCacheName($ID, '.renderrevision');

        // no or outdated MD5 cache, create new one
        // this means a new revision of the page has been created naturally
        // we store the new render result and are done
        if (!file_exists($md5cache) || filemtime(wikiFN($ID)) > filemtime($md5cache)) {
            file_put_contents($md5cache, md5($xhtml));
            return;
        }

        // only act on pages that have not been changed very recently
        if (time() - filemtime(wikiFN($ID)) < $this->getConf('maxfrequency')) {
            return;
        }

        // get the render result as it were when the page was last changed
        $oldMd5 = file_get_contents($md5cache);

        // did the rendered content change?
        if ($oldMd5 === md5($xhtml)) {
            return;
        }

        // time to create a new revision
        $this->current = $ID;
        (new PageFile($ID))->saveWikiText(rawWiki($ID), 'Automatic revision due to content change');
        $this->current = null;
    }

    /**
     * Event handler for COMMON_WIKIPAGE_SAVE
     *
     * Overwrite the contentChanged flag to force a new revision even though the content did not change
     *
     * @see https://www.dokuwiki.org/devel:event:COMMON_WIKIPAGE_SAVE
     * @param Event $event Event object
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handleCommonWikipageSave(Event $event, $param)
    {
        if ($this->current !== $event->data['id']) return;
        $event->data['contentChanged'] = true;
    }
}
