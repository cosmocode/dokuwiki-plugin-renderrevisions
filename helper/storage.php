<?php

use dokuwiki\Extension\Plugin;

/**
 * DokuWiki Plugin renderrevisions (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Andreas Gohr <dokuwiki@cosmocode.de>
 */
class helper_plugin_renderrevisions_storage extends Plugin
{

    /**
     * Get the filename for a rendered revision
     *
     * @param string $id
     * @param int $rev
     * @return string
     */
    public function getFilename($id, $rev)
    {
        global $conf;
        return $conf['olddir'] . '/' . utf8_encodeFN($id) . '.' . $rev . '.renderevision.xhtml';
    }

    /**
     * Save the rendered content of a revision
     *
     * @param string $id
     * @param int $rev
     * @param string $content
     * @return void
     */
    public function saveRevision($id, $rev, $content)
    {
        $file = $this->getFilename($id, $rev);
        file_put_contents($file, $content);
    }

    /**
     * Load the rendered content of a revision
     *
     * @param string $id
     * @param int $rev
     * @return false|string
     */
    public function getRevision($id, $rev)
    {
        if (!$this->hasRevision($id, $rev)) return false;
        $file = $this->getFilename($id, $rev);
        return file_get_contents($file);
    }

    /**
     * Does a rendered revision exist?
     *
     * @param string $id
     * @param int $rev
     * @return bool
     */
    public function hasRevision($id, $rev)
    {
        $file = $this->getFilename($id, $rev);
        if (!file_exists($file)) return false;
        return true;
    }

    /**
     * Delete rendered revisions that are older than $conf['recent_days']
     *
     * @param string $id
     * @return void
     */
    public function cleanUp($id)
    {
        global $conf;
        $files = glob($this->getFilename($id, '*'));
        foreach ($files as $file) {
            if (filemtime($file) < time() - $conf['recent_days'] * 24 * 60 * 60) {
                unlink($file);
            }
        }
    }
}
