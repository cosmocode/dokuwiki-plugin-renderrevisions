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
class action_plugin_renderrevisions_save extends ActionPlugin
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

        // only process pages that match both the skip and match regex

        $page = $cacheObject->page;
        try {
            [$skipRE, $matchRE] = $this->getRegexps();
        } catch (\Exception $e) {
            msg(hsc($e->getMessage()), -1);
            return;
        }
        if (
            ($skipRE && preg_match($skipRE, ":$page")) ||
            ($matchRE && !preg_match($matchRE, ":$page"))
        ) {
            return;
        }

        // remember that this page was processed
        // This is a somewhat ugly workaround for when text snippets are rendered within the same page.
        // Those snippets will not have a page context set during cache use event and thus not be processed
        // later on in the RENDERER_CONTENT_POSTPROCESS event
        $this->pages[$page] = true;
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

        // all the above still does not ensure we skip sub renderings, so this is our last resort
        if (count(array_filter(debug_backtrace(), fn($t) => $t['function'] === 'p_render')) > 1) return;

        $md5cache = getCacheName($ID, '.renderrevision');

        // no or outdated MD5 cache, create new one
        // this means a new revision of the page has been created naturally
        // we store the new render result and are done
        if (!file_exists($md5cache) || filemtime(wikiFN($ID)) > filemtime($md5cache)) {
            file_put_contents($md5cache, md5($xhtml));

            if($this->getConf('store')) {
                /** @var helper_plugin_renderrevisions_storage $storage */
                $storage = plugin_load('helper', 'renderrevisions_storage');
                $storage->saveRevision($ID, filemtime(wikiFN($ID)), $xhtml);
                $storage->cleanUp($ID);
            }

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
        (new PageFile($ID))->saveWikiText(rawWiki($ID), $this->getLang('summary'));
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


    /**
     * Read the skip and match regex from the config
     *
     * Ensures the regular expressions are valid
     *
     * @return string[] [$skipRE, $matchRE]
     * @throws \Exception if the regular expressions are invalid
     */
    public function getRegexps()
    {
        $skip = $this->getConf('skipRegex');
        $skipRE = '';
        $match = $this->getConf('matchRegex');
        $matchRE = '';

        if ($skip) {
            $skipRE = '/' . $skip . '/';
            if (@preg_match($skipRE, '') === false) {
                throw new \Exception('Invalid regular expression in $conf[\'skipRegex\']. ' . preg_last_error_msg());
            }
        }

        if ($match) {
            $matchRE = '/' . $match . '/';
            if (@preg_match($matchRE, '') === false) {
                throw new \Exception('Invalid regular expression in $conf[\'matchRegex\']. ' . preg_last_error_msg());
            }
        }
        return [$skipRE, $matchRE];
    }
}
