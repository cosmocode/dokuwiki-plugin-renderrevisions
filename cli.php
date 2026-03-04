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
        $options->registerArgument("page_id", "The page id that will be processed. " .
        "Leave empty for process all pages.", false);
    }

    /** @inheritDoc */
    protected function main(Options $options)
    {
        global $INFO;
        global $ID;
        global $ACT;

        auth_setup(); // make sure ACLs are initialized

        $args = $options->getArgs();

        if (isset($args[0])) { // process the single page
            $page = $args[0];

            $action = plugin_load('action', 'renderrevisions_save');
            [$skipRE, $matchRE] = $action->getRegexps();
            if (
                ($skipRE && preg_match($skipRE, ":$page")) ||
                ($matchRE && !preg_match($matchRE, ":$page"))
            ) {
                $this->info("Skipping $page");
            } else {
                $this->notice("Processing $page");
                $file = wikiFN($page);
                try {
                    $ID = $page;
                    $INFO = pageinfo();
                    $ACT = 'show';

                    p_cached_output($file, 'xhtml', $page);
                } catch (\Exception $e) {
                    $this->error("Issues while rendering $page: " . $e->getMessage());
                }
            }
        } else { // run for all pages
            $indexer = new Indexer();
            $pages = $indexer->getPages();
            foreach ($pages as $page) {
                $cmd = PHP_BINARY . ' ' . $_SERVER['SCRIPT_FILENAME'] . ' renderrevisions ' . $page;
                $output = shell_exec($cmd);
                $this->notice($output);
            }
        }
    }
}
