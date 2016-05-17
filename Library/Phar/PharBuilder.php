<?php

namespace sweikenb\Library\Phar;

/**
 * Class PharBuilder
 *
 * @package Sweikenb\Library\Phar
 */
class PharBuilder
{
    /**
     * @var string
     */
    protected $filename;

    /**
     * @var string
     */
    protected $sourceDir;

    /**
     * @var string
     */
    protected $targetName;

    /**
     * @var \Phar
     */
    protected $phar;

    /**
     * @var bool
     */
    protected $debugging;

    /**
     * @param string $filename
     * @param string $sourceDir
     * @param string $targetDir
     * @param bool   $debugging
     */
    public function __construct($filename, $sourceDir = '.', $targetDir = '.', $debugging = false)
    {
        $this->filename = $filename;
        $this->sourceDir = \realpath($sourceDir);
        $this->targetName = \sprintf('%s%s%s', \realpath($targetDir), \DIRECTORY_SEPARATOR, $this->filename);
        $this->debugging = (true == $debugging);
    }

    /**
     * @return $this
     */
    public function removeOldBuild()
    {
        // check fir existing target
        if (\file_exists($this->targetName)) {
            \unlink($this->targetName);
        }

        // return fluid interface
        return $this;
    }

    /**
     * @param string $cli
     * @param string $web
     * @param array  $ignored
     * @param bool   $ignoreScm
     *
     * @return $this
     */
    public function build($cli = null, $web = null, array $ignored = null, $ignoreScm = true)
    {
        // debug
        $this->debug("Creating phar-file ...");

        // create phar
        $pharAlias = basename($this->filename);
        $this->phar = new \Phar($this->targetName, 0, $pharAlias);
        $this->phar->setSignatureAlgorithm(\Phar::SHA1);

        // start buffering
        $this->phar->startBuffering();

        // normalize ignored-array if present
        if (null !== $ignored) {
            foreach ($ignored as &$path) {
                $path = \str_replace($this->sourceDir, '', $path);
                if (\DIRECTORY_SEPARATOR !== \substr($path, 0, 1)) {
                    $path = \DIRECTORY_SEPARATOR . $path;
                }
            }
        }

        // indexing source
        $this->indexSourceDir($this->sourceDir, $ignored, $ignoreScm);

        // debug
        $this->debug(\str_repeat('-', 76));
        $this->debug("--> Indexing done.");

        // normalize entry files
        if ($cli) {
            $cli = \str_replace($this->sourceDir, '', $cli);
            $this->debug(\sprintf("--> Setting entry-point for CLI-execution to '%s'", $cli));
        }
        if ($web) {
            $web = \str_replace($this->sourceDir, '', $web);
            $this->debug(\sprintf("--> Setting entry-point for WEB-execution to '%s'", $web));
        }

        // set entry-point
        $this->phar->setStub($this->phar->createDefaultStub($cli, $web));

        // stop the buffering
        $this->phar->stopBuffering();

        // return fluid interface
        return $this;
    }

    /**
     * @return $this
     */
    public function makeExecutable()
    {
        // phar already available?
        if ($this->phar) {
            // debug
            $this->debug("\nTrying to make target executable ...");
            $result = \trim(\shell_exec(\sprintf('chmod +x %s', \escapeshellarg($this->targetName))));
            if (!empty($result)) {
                $this->debug($result);
            } else {
                $this->debug("- OK\n");
            }
        } else {
            // debug
            $this->debug("\nCan't set as executable. Target has not been built yet!");
        }

        // return fluid interface
        return $this;
    }

    /**
     * @param string $msg
     */
    protected function debug($msg)
    {
        if (true === $this->debugging) {
            echo "$msg\n";
        }
    }

    /**
     * @param string $dir
     * @param array  $ignored
     * @param bool   $ignoreScm
     */
    protected function indexSourceDir($dir, array $ignored = null, $ignoreScm = true)
    {
        // define the dirs to skipp
        $skippDirs = array('.', '..');
        if ($ignoreScm) {
            $skippDirs[] = '.git';
            // TODO: Add more scm pattern here
        }

        $h = \opendir($dir);
        while ($row = \readdir($h)) {

            // ignore hidden files and directories
            if (in_array($row, $skippDirs)) {
                continue;
            }

            // define current path
            $path = \sprintf("%s%s%s", $dir, \DIRECTORY_SEPARATOR, $row);
            $pathRel = \str_replace($this->sourceDir, '', $path);

            // switch path-type
            if (\is_dir($path)) {
                // need to ignore file?
                if (null !== $ignored && \in_array($pathRel, $ignored)) {
                    $this->debug("- IGNORING directory: $pathRel");
                    continue;
                }

                // indexing
                $this->indexSourceDir($path, $ignored);
            } else {
                // need to ignore file?
                if (null !== $ignored && \in_array($pathRel, $ignored)) {
                    $this->debug("- IGNORING file: $pathRel");
                } else {
                    // register
                    $this->debug("- setting index: $pathRel");
                    $this->phar[$pathRel] = @\file_get_contents($path);
                }
            }
        }
        \closedir($h);
    }
}