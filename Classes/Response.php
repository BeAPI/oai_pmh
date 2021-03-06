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

class Response
{
    public $doc; // DOMDocument. Handle of current XML Document object

    public function __construct($uri, $verb, $request_args)
    {
        if (substr($uri, -1, 1) === '/') {
            $stylesheet = $uri . 'Resources/Stylesheet.xsl';
        } else {
            $stylesheet = Helper::getScheme();
            $stylesheet .= $_SERVER['HTTP_HOST'];
            $stylesheet .= pathinfo(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), PATHINFO_DIRNAME);
            $stylesheet .= '/Resources/Stylesheet.xsl';
        }

        $this->verb = $verb;
        $this->doc = new \DOMDocument('1.0', 'UTF-8');
        $this->doc->appendChild(
            $this->doc->createProcessingInstruction('xml-stylesheet', 'type="text/xsl" href="' . $stylesheet . '"')
        );

        $oai_node = $this->doc->createElement('OAI-PMH');
        $oai_node->setAttribute('xmlns', 'http://www.openarchives.org/OAI/2.0/');
        $oai_node->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $oai_node->setAttribute(
            'xsi:schemaLocation',
            'http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd'
        );
        $this->addChild($oai_node, 'responseDate', gmdate('Y-m-d\TH:i:s\Z'));
        $this->doc->appendChild($oai_node);

        $request = $this->addChild($this->doc->documentElement, 'request', $uri);
        if (!empty($this->verb)) {
            $request->setAttribute('verb', $this->verb);
        }

        foreach ($request_args as $key => $value) {
            // TODO, allow only RFC argument
            $request->setAttribute($key, $value);
        }
    }

    /**
     * Add a child node to a parent node on a XML Doc: a worker function.
     *
     * @param string $name The name of child node is being added.
     * @param string $value Text for the adding node if it is a text node.
     *
     * @param \DOMNode $mom_node The target node.
     * @return \DOMElement $added_node * The newly created node
     *
     */
    public function addChild($mom_node, $name, $value = ''): \DOMElement
    {
        $added_node = $this->doc->createElement($name, $value);
        $added_node = $mom_node->appendChild($added_node);

        return $added_node;
    }

    /**
     * Add direct child nodes to verb node (OAI-PMH), e.g. response to ListMetadataFormats.
     * Different verbs can have different required child nodes.
     *
     * @param string $nodeName The name of appending node.
     * @param string $value The content of appending node.
     *
     * @param bool $flag
     * @return \DOMElement
     * @see createHeader, importFragment
     */
    public function addToVerbNode($nodeName, $value = null, $flag = false): \DOMElement
    {
        if (!isset($this->verbNode) && !empty($this->verb)) {
            $this->verbNode = $this->addChild($this->doc->documentElement, $this->verb, $value);
            if (true === $flag) {
                return $this->verbNode;
            }
        }

        return $this->addChild($this->verbNode, $nodeName, $value);
    }

    /**
     * If there are too many records request could not finished a resumpToken is generated to let harvester know
     *
     * @param string $token A random number created somewhere?
     * @param string $expirationdatetime A string representing time.
     * @param integer $num_rows Number of records retrieved.
     * @param string $cursor Cursor can be used for database to retrieve next time.
     */
    public function createResumptionToken($token, $expirationdatetime, $num_rows, $cursor = null)
    {
        $resump_node = $this->addChild($this->verbNode, 'resumptionToken', $token);
        if (isset($expirationdatetime)) {
            $resump_node->setAttribute('expirationDate', $expirationdatetime);
        }
        $resump_node->setAttribute('completeListSize', $num_rows);
        $resump_node->setAttribute('cursor', $cursor);
    }

    /**
     * Imports a XML fragment into a parent node on a XML Doc: a worker function.
     *
     * @param \DOMDocument $fragment The XML fragment is being added.
     *
     * @param \DOMNode $mom_node The target node.
     * @return \DOMElement $added_node * The newly created node
     *
     */
    public function importFragment($mom_node, $fragment): \DOMElement
    {
        $added_node = $mom_node->appendChild($this->doc->importNode($fragment->documentElement, true));

        return $added_node;
    }

}
