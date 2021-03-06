<?php
namespace Helhum\TerClient;

class ExtensionUploadPacker
{
    const KIND_DEPENDENCY = 'depends';
    const KIND_CONFLICT = 'conflicts';
    const KIND_SUGGEST = 'suggests';

    /**
     * @var array
     */
    protected $permittedDotFiles = array('.htaccess', '.htpasswd');

    /**
     * @param string $extensionKey
     * @param string $directory
     * @param string $comment
     * @throws \RuntimeException
     * @return array
     */
    public function pack($extensionKey, $directory, $comment = '')
    {
        $extensionConfiguration = $this->readExtensionConfigurationFile($directory, $extensionKey);
        $data = $this->createFileDataArray($directory);
        $data['EM_CONF'] = $extensionConfiguration;
        return $this->createSoapData($extensionKey, $data, $comment);
    }

    /**
     * @param string $directory
     * @param string $_EXTKEY
     * @throws \RuntimeException
     * @return array
     */
    private function readExtensionConfigurationFile($directory, $_EXTKEY)
    {
        $expectedFilename = $directory . '/ext_emconf.php';
        if (false === file_exists($expectedFilename)) {
            throw new \RuntimeException('Directory "' . $directory . "' does not contain an ext_emconf.php file");
        }
        $EM_CONF = array();
        include $expectedFilename;
        $this->validateVersionNumber($EM_CONF[$_EXTKEY]['version']);
        return $EM_CONF[$_EXTKEY];
    }

    /**
     * @param string $version
     * @throws \RuntimeException
     */
    protected function validateVersionNumber($version)
    {
        if (1 !== preg_match('/^[\\d]{1,2}\.[\\d]{1,2}\.[\\d]{1,2}$/i', $version)) {
            throw new \RuntimeException(
                'Invalid version number "' . $version . '" detected in ext_emconf.php, refusing to pack extension for upload',
                1426383996
            );
        }
    }

    /**
     * @param string $extensionData
     * @param string $key
     * @throws \RuntimeException
     * @return array
     */
    private function createDependenciesArray($extensionData, $key)
    {
        $dependenciesArr = array();
        if (false === isset($extensionData['EM_CONF']['constraints'][$key])) {
            return $dependenciesArr;
        }
        if (false === is_array($extensionData['EM_CONF']['constraints'][$key])) {
            return $dependenciesArr;
        }
        foreach ($extensionData['EM_CONF']['constraints'][$key] as $extKey => $version) {
            if (false === is_string($extKey)) {
                throw new \RuntimeException('Invalid dependency definition! Dependencies must be an array indexed by extension key');
            }
            $dependenciesArr[] = array(
                'kind' => $key,
                'extensionKey' => $extKey,
                'versionRange' => $version,
            );
        }
        return $dependenciesArr;
    }

    /**
     * @param array $extensionData
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function valueOrDefault($extensionData, $key, $default = null)
    {
        return true === isset($extensionData['EM_CONF'][$key]) ? $extensionData['EM_CONF'][$key] : $default;
    }

    /**
     * @param string $extensionKey
     * @param array $extensionData
     * @param string $comment
     * @return array
     */
    protected function createSoapData($extensionKey, $extensionData, $comment = '')
    {
        // Create dependency / conflict information:
        $dependenciesArr = $this->createDependenciesArray($extensionData, self::KIND_DEPENDENCY);
        $dependenciesArr = array_merge($dependenciesArr, $this->createDependenciesArray($extensionData, self::KIND_CONFLICT));
        $dependenciesArr = array_merge($dependenciesArr, $this->createDependenciesArray($extensionData, self::KIND_SUGGEST));

        // Compile data for SOAP call:
        $extension = array(
            'extensionKey' => $extensionKey,
            'version' => $this->valueOrDefault($extensionData, 'version'),
            'metaData' => array(
                'title' => $this->valueOrDefault($extensionData, 'title'),
                'description' => $this->valueOrDefault($extensionData, 'description'),
                'category' => $this->valueOrDefault($extensionData, 'category'),
                'state' => $this->valueOrDefault($extensionData, 'state'),
                'authorName' => $this->valueOrDefault($extensionData, 'author'),
                'authorEmail' => $this->valueOrDefault($extensionData, 'author_email'),
                'authorCompany' => $this->valueOrDefault($extensionData, 'author_company'),
            ),
            'technicalData' => array(
                'dependencies' => $dependenciesArr,
                'loadOrder' => $this->valueOrDefault($extensionData, 'loadOrder'),
                'uploadFolder' => (bool) $this->valueOrDefault($extensionData, 'uploadFolder'),
                'createDirs' => $this->valueOrDefault($extensionData, 'createDirs'),
                'shy' => $this->valueOrDefault($extensionData, 'shy', false),
                'modules' => $this->valueOrDefault($extensionData, 'module'),
                'modifyTables' => $this->valueOrDefault($extensionData, 'modify_tables'),
                'priority' => $this->valueOrDefault($extensionData, 'priority'),
                'clearCacheOnLoad' => (bool) $this->valueOrDefault($extensionData, 'clearCacheOnLoad'),
                'lockType' => $this->valueOrDefault($extensionData, 'lockType'),
                'doNotLoadInFEe' => $this->valueOrDefault($extensionData, 'doNotLoadInFE'),
                'docPath' => $this->valueOrDefault($extensionData, 'docPath'),
            ),
            'infoData' => array(
                'codeLines' => intval($extensionData['misc']['codelines']),
                'codeBytes' => intval($extensionData['misc']['codebytes']),
                'codingGuidelinesCompliance' => $this->valueOrDefault($extensionData, 'CGLcompliance'),
                'codingGuidelinesComplianceNotes' => $this->valueOrDefault($extensionData, 'CGLcompliance_note'),
                'uploadComment' => $comment,
                'techInfo' => $extensionData['techInfo'],
            ),
        );

        $files = array();
        foreach ($extensionData['FILES'] as $filename => $infoArr) {
            $files[] = array(
                'name' => $infoArr['name'],
                'size' => intval($infoArr['size']),
                'modificationTime' => intval($infoArr['mtime']),
                'isExecutable' => intval($infoArr['is_executable']),
                'content' => $infoArr['content'],
                'contentMD5' => $infoArr['content_md5'],
            );
        }
        return array(
            'extensionData' => $extension,
            'filesData' => $files,
        );
    }

    /**
     * @param string $directory
     * @return array
     */
    protected function createFileDataArray($directory)
    {
        // Initialize output array:
        $uploadArray = array();
        $uploadArray['extKey'] = rtrim(pathinfo($directory, PATHINFO_FILENAME), '/');
        $uploadArray['misc']['codelines'] = 0;
        $uploadArray['misc']['codebytes'] = 0;

        $uploadArray['techInfo'] = 'All good, baby';

        $uploadArray['FILES'] = array();
        $directoryLength = strlen(rtrim($directory, '/')) + 1;
        $directoryIterator = new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($directoryIterator);
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($this->isFilePermitted($file, $directory)) {
                $filename = $file->getPathname();
                $relativeFilename = substr($filename, $directoryLength);
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                $uploadArray['FILES'][$relativeFilename] = array(
                    'name' => $relativeFilename,
                    'size' => filesize($file),
                    'mtime' => filemtime($file),
                    'is_executable' => is_executable($file),
                    'content' => file_get_contents($file),
                );
                if (in_array($extension, array('php', 'inc'), true)) {
                    $uploadArray['FILES'][$relativeFilename]['codelines'] = count(explode(PHP_EOL, $uploadArray['FILES'][$relativeFilename]['content']));
                    $uploadArray['misc']['codelines'] += $uploadArray['FILES'][$relativeFilename]['codelines'];
                    $uploadArray['misc']['codebytes'] += $uploadArray['FILES'][$relativeFilename]['size'];
                }
                $uploadArray['FILES'][$relativeFilename]['content_md5'] = md5($uploadArray['FILES'][$relativeFilename]['content']);
            }
        }

        return $uploadArray;
    }

    /**
     * @param \SplFileInfo $file
     * @param string $inPath
     * @return bool
     */
    private function isFilePermitted(\SplFileInfo $file, $inPath)
    {
        $name = $file->getFilename();
        if (true === $this->isDotFileAndNotPermitted($name)) {
            return false;
        }
        $consideredPathLength = strlen($inPath);
        foreach (explode('/', trim(substr($file->getPathname(), $consideredPathLength), '/')) as $segment) {
            if (true === $this->isDotFileAndNotPermitted($segment)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $filename
     * @return bool
     */
    private function isDotFileAndNotPermitted($filename)
    {
        return !empty($filename) && '.' === $filename[0]
        && !in_array($filename, $this->permittedDotFiles, true);
    }
}
