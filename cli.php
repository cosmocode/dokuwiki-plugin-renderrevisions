<?php

use dokuwiki\Search\Indexer;
use splitbrain\phpcli\Options;

/**
 * DokuWiki Plugin renderrevisions (CLI Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <dokuwiki@cosmocode.de>
 */
class cli_plugin_renderrevisions extends \dokuwiki\Extension\CLIPlugin
{
    /** @inheritDoc */
    protected function setup(Options $options)
    {
        $options->setHelp(
            "Re-render pages if necessary\n" .
            "\n" .
            "This command will go through all pages in the wiki (adhering to the skipRegex and matchRegex settings) " .
            "and re-render them if necessary. This will trigger the renderrevisions plugin mechanism to create " .
            "a new revision of the page if the content changed."
        );
    }

    /** @inheritDoc */
    protected function main(Options $options)
    {
        $indexer = new Indexer();
        $pages = $indexer->getPages();

        $action = plugin_load('action', 'renderrevisions_save');
        [$skipRE, $matchRE] = $action->getRegexps();

        foreach ($pages as $page) {
            if (
                ($skipRE && preg_match($skipRE, ":$page")) ||
                ($matchRE && !preg_match($matchRE, ":$page"))
            ) {
                $this->info("Skipping $page");
                continue;
            }

            $this->notice("Processing $page");
            $file = wikiFN($page);
            try {
                p_cached_output($file, 'xhtml', $page);
            } catch (\Exception $e) {
                $this->error("Issues while rendering $page: " . $e->getMessage());
            }
        }
    }
}
