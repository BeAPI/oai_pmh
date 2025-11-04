<?php
/**
 * Simple OAI-PMH 2.0 Data Provider
 * Copyright (C) 2005 Heinrich Stamerjohanns <stamer@uni-oldenburg.de>
 * Copyright (C) 2011 Jianfeng Li <jianfeng.li@adelaide.edu.au>
 * Copyright (C) 2013 Daniel Neis Araujo <danielneis@gmail.com>
 * Copyright (C) 2017 Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * Copyright (C) 2020 Amaury BALMER <amaury@beapi.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCC\OAI2;

class Data
{
    private static $instance;

    private $records = [];
    private $deleted = [];
    private $timestamps = [];
    private $sets = [];

    private $earliest;

    /**
     * @return Data
     */
    public static function getInstance(): Data
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->earliest = time();
    }

    private function __clone()
    {
    }

    public function getDirectoryByMeta($prefix)
    {
        $config = Config::getInstance();

        $directory = rtrim($config->getConfigValue('dataDirectory'), '/') . '/' . $prefix;

        // Prepend script's path if dataDir is not an absolute path
        if (strpos($directory, '/') !== 0) {
            $directory = ABSPATH . $directory;
        }

        return realpath($directory);
    }

    public function populateSets()
    {
        $config = Config::getInstance();

        foreach ($config->getConfigValue('metadataPrefix') as $metadataPrefix => $uris) {
            $directory = $this->getDirectoryByMeta($metadataPrefix);
            if (empty($directory)) {
                continue;
            }

            $all_files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
            $xml_files = new \RegexIterator(
                $all_files,
                '/' . preg_quote($config->getConfigValue('setDefinition'), '/') . '$/'
            );

            foreach ($xml_files as $fileInfo) {
                // Build set name
                $setName = str_replace($directory, '', $fileInfo->getPath());
                $setName = trim($setName, '/');
                $setName = str_replace('/', ':', $setName);

                $this->sets[] = $setName;
            }
        }
    }

    public function resetRecords()
    {
        $this->records = array();
        $this->deleted = array();
        $this->timestamps = array();
        $this->earliest = time();
    }

    public function populateRecords($set = '')
    {
        $this->resetRecords();

        $config = Config::getInstance();

        foreach ($config->getConfigValue('metadataPrefix') as $metadataPrefix => $uris) {
            $directory = $this->getDirectoryByMeta($metadataPrefix);
            if (empty($directory)) {
                continue;
            }

            $all_files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
            $xml_files = new \RegexIterator($all_files, '/\.xml$/');

            foreach ($xml_files as $fileInfo) {
                $filePath = $fileInfo->getPathname();
                $fileName = $fileInfo->getBasename();

                if (basename($filePath) === $config->getConfigValue('setDefinition')) {
                    continue;
                }

                // Translate path to setSpec URI identifier
                $setName = str_replace($directory, '', $fileInfo->getPath());
                $setName = trim($setName, '/');
                $setName = str_replace('/', ':', $setName);

                // Filter to a set
                if (!empty($set) && $setName !== $set) {
                    continue;
                }

                // If a record exist on multiple folder, because multisets, keep last not empty file in case or deletion
                if (isset($this->records[$metadataPrefix][$fileName]) && !filesize($filePath)) {
                    continue;
                }

                $this->records[$metadataPrefix][$fileName] = $filePath;
                $this->deleted[$metadataPrefix][$fileName] = !filesize($filePath);
                $this->timestamps[$metadataPrefix][$fileName] = $this->extractDatestampFromXml($filePath);

                if ($this->timestamps[$metadataPrefix][$fileName] < $this->earliest) {
                    $this->earliest = $this->timestamps[$metadataPrefix][$fileName];
                }
            }

            if (isset($this->records[$metadataPrefix])) {
                ksort($this->records[$metadataPrefix]);
                reset($this->records[$metadataPrefix]);
                asort($this->timestamps[$metadataPrefix]);
                reset($this->timestamps[$metadataPrefix]);
            }
        }
    }

    /**
     * @return array
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * @return array
     */
    public function getDeleted(): array
    {
        return $this->deleted;
    }

    /**
     * @return array
     */
    public function getTimestamps(): array
    {
        return $this->timestamps;
    }

    /**
     * @return int
     */
    public function getEarliest(): int
    {
        return $this->earliest;
    }

    /**
     * @return array
     */
    public function getSets(): array
    {
        return $this->sets;
    }

    public function getSetFile(string $set)
    {
        $config = Config::getInstance();

        $setDefinition = $config->getConfigValue('setDefinition');

        // Translate setSpec URL identifier to path
        $set = str_replace(':', '/', $set);

        foreach ($config->getConfigValue('metadataPrefix') as $prefix => $uris) {
            $directory = $this->getDirectoryByMeta($prefix);
            if (empty($directory)) {
                continue;
            }

            $file = $directory . "/$set/$setDefinition";
            if (is_file($file)) {
                return $file;
            }
        }

        return false;
    }

    /**
     * Extract datestamp from XML record file.
     * Falls back to file modification time if XML parsing fails or file is empty.
     *
     * @param string $filePath Path to the XML file
     * @return int Unix timestamp
     */
    private function extractDatestampFromXml($filePath)
    {
        // For empty files (deleted records), use file modification time
        if (filesize($filePath) === 0) {
            return filemtime($filePath);
        }

        // Try to load and parse the XML file
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $doc->load($filePath);
        libxml_clear_errors();

        if (!$loaded) {
            // If XML parsing fails, fall back to file modification time
            return filemtime($filePath);
        }

        // Extract datestamp from header
        // Note: We don't use namespace here as the XML files may not have namespace declarations
        $xpath = new \DOMXPath($doc);
        $datestampNodes = $xpath->query('/record/header/datestamp');
        
        if ($datestampNodes->length > 0) {
            $datestampString = trim($datestampNodes->item(0)->nodeValue);
            
            // Parse the datestamp (format: YYYY-MM-DDThh:mm:ssZ or YYYY-MM-DD)
            $datetime = \DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $datestampString, new \DateTimeZone('UTC'));
            if ($datetime === false) {
                // Try date-only format
                $datetime = \DateTime::createFromFormat('Y-m-d', $datestampString, new \DateTimeZone('UTC'));
            }
            
            if ($datetime !== false) {
                return $datetime->getTimestamp();
            }
        }

        // Fall back to file modification time if datestamp extraction fails
        return filemtime($filePath);
    }
}
