<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Form\HTMLElement;
use Caxy\HtmlDiff\HtmlDiff;

/**
 * DokuWiki Plugin renderrevisions (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Andreas Gohr <dokuwiki@cosmocode.de>
 */
class action_plugin_renderrevisions_revisions extends ActionPlugin
{
    /** @inheritDoc */
    public function register(EventHandler $controller)
    {
        if($this->getConf('store')) {
            $controller->register_hook('FORM_REVISIONS_OUTPUT', 'BEFORE', $this, 'handleRevisions');
            $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleActPreprocess');
            $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE', $this, 'handleActUnknown');
        }
    }

    /**
     * Event handler for FORM_REVISIONS_OUTPUT
     *
     * Link revisions to their rendered output when available
     *
     * @see https://www.dokuwiki.org/devel:events:FORM_REVISIONS_OUTPUT
     * @param Event $event Event object
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handleRevisions(Event $event, $param)
    {
        global $INFO;

        /** @var dokuwiki\Form\Form $form */
        $form = $event->data;

        /** @var helper_plugin_renderrevisions_storage $storage */
        $storage = plugin_load('helper', 'renderrevisions_storage');

        $id = $INFO['id'];
        $elementCount = $form->elementCount();

        for ($i = 0; $i < $elementCount; $i++) {
            $element = $form->getElementAt($i);
            if (!$element instanceof HTMLElement) continue;
            if (!preg_match('/\?rev=(\d+)/', $element->val(), $match)) continue;
            $rev = (int)$match[1];
            if (!$rev) continue;
            if (!$storage->hasRevision($id, $rev)) continue;

            $html = $element->val();
            $html = preg_replace('/(\?rev=\d+)/', '\\1&amp;do=renderrevisions', $html);
            $html = preg_replace('/class="wikilink1"/', 'class="wikilink1 renderrevisions"', $html);
            $element->val($html);
        }
    }

    /**
     * Event handler for ACTION_ACT_PREPROCESS
     *
     * @see https://www.dokuwiki.org/devel:event:ACTION_ACT_PREPROCESS
     * @param Event $event Event object
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handleActPreprocess(Event $event, $param)
    {
        global $REV;
        global $INFO;

        // not our circus?
        if ($event->data !== 'renderrevisions') return;

        // no revision? show the page. id should always be set, but just in case
        if (!$REV || !$INFO['id']) {
            $event->data = 'show';
            return;
        }

        // check permissions
        if(auth_quickaclcheck($INFO['id']) < AUTH_READ) {
            $event->data = 'denied';
            return;
        }

        // no stored revision? show the page
        /** @var helper_plugin_renderrevisions_storage $storage */
        $storage = plugin_load('helper', 'renderrevisions_storage');
        if (!$storage->hasRevision($INFO['id'], (int)$REV)) {
            $event->data = 'show';
            return;
        }

        // we can handle it!
        $event->preventDefault();
        $event->stopPropagation();
    }


    /**
     * Event handler for TPL_ACT_UNKNOWN
     *
     * @see https://www.dokuwiki.org/devel:event:TPL_ACT_UNKNOWN
     * @param Event $event Event object
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handleActUnknown(Event $event, $param)
    {
        global $REV;
        global $INFO;
        global $ACT;

        if ($event->data !== 'renderrevisions') return;
        $event->preventDefault();
        $event->stopPropagation();

        /** @var helper_plugin_renderrevisions_storage $storage */
        $storage = plugin_load('helper', 'renderrevisions_storage');
        $content = $storage->getRevision($INFO['id'], (int)$REV);
        $intro = sprintf(
            $this->getLang('intro'),
            dformat($REV),
            '<a href="' . wl($INFO['id'], ['rev' => $REV]) . '">' . $this->getLang('rerender') . '</a>'
        );

        echo '<div class="renderrevisions">';
        echo $intro;
        echo '</div>';
        //echo $content;

        $current = p_cached_output(wikiFN($INFO['id']), 'xhtml', $INFO['id']);

        $htmlDiff = new HtmlDiff($content, $current);
        echo $htmlDiff->build();
    }
}
