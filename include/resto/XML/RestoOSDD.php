<?php
/*
 * Copyright 2014 Jérôme Gasperi
 *
 * Licensed under the Apache License, version 2.0 (the "License");
 * You may not use this file except in compliance with the License.
 * You may obtain a copy of the License at:
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

/**
 * resto OpenSearch Document Description class
 *    
 * <OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/" xmlns:geo="http://a9.com/-/opensearch/extensions/geo/1.0/" xmlns:time="http://a9.com/-/opensearch/extensions/time/1.0/">
 *      <ShortName>OpenSearch search</ShortName>
 *      <Description>My OpenSearch search interface</Description>
 *      <Tags>opensearch</Tags>
 *      <Contact>admin@myserver.org</Contact>
 *      <Url type="application/atom+xml" template="http://myserver.org/Controller_name/?q={searchTerms}&bbox={geo:box?}&format=atom&startDate={time:start?}&completionDate={time:end?}&modified={time:start?}&platform={take5:platform?}&instrument={take5:instrument?}&product={take5:product?}&maxRecords={count?}&index={startIndex?}"/>
 *      <LongName>My OpenSearch search interface</LongName>
 *      <Query role="example" searchTerms="observatory"/>
 *      <Attribution>mapshup.info</Attribution>
 *      <Language>fr</Language>
 * </OpenSearchDescription>
 *    
 */
class RestoOSDD extends RestoXML {
    
    /*
     * Reference to collection object
     */
    private $collection;
    
    /*
     * Client Id (CEOS Opensearch Best Practice document)
     */
    private $clientId;
    
    /*
     * Output contentTypes
     */
    private $contentTypes = array('atom', 'json');
    
    /*
     * Template extension parameters
     */
    private $extensionParams = array(
        'minimum',
        'maximum',
        'minExclusive',
        'maxExclusive',
        'minInclusive',
        'maxInclusive',
        'pattern',
        'title'
    );
    
    /**
     * Constructor
     * 
     * @param RestoCollection $collection
     */
    public function __construct($collection) {
        parent::__construct();
        $this->collection = $collection;
        $this->clientId = isset($this->collection->context->query['clientId']) ? 'clientId=' . rawurlencode($this->collection->context->query['clientId']) . '&' : '';
        $this->setOSDD();
    }
    
    /**
     * Set OpenSearch Description Document
     */
    private function setOSDD() {
         
        /*
         * Start OpsenSearchDescription
         */
        $this->startOSDD();
        
        /*
         * Start elements
         */
        $this->setStartingElements();
        
        /*
         * Generate <Url> elements
         */
        $this->setUrls();
        
        /*
         * Generate informations elements
         */
        $this->setEndingElements();
        
        /*
         * OpsenSearchDescription - end element
         */
        $this->endElement();
        
    }
    
    /**
     * Start XML OpenSearchDescription element
     */
    private function startOSDD() {
        $this->startElement('OpenSearchDescription');
        $this->writeAttributes(array(
            'xml:lang' => $this->collection->context->dictionary->language,
            'xmlns' => 'http://a9.com/-/spec/opensearch/1.1/',
            'xmlns:atom' => 'http://www.w3.org/2005/Atom',
            'xmlns:time' => 'http://a9.com/-/opensearch/extensions/time/1.0/',
            'xmlns:geo' => 'http://a9.com/-/opensearch/extensions/geo/1.0/',
            'xmlns:eo' => 'http://a9.com/-/opensearch/extensions/eo/1.0/',
            'xmlns:parameters' => 'http://a9.com/-/spec/opensearch/extensions/parameters/1.0/',
            'xmlns:dc' => 'http://purl.org/dc/elements/1.1/',
            'xmlns:rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'xmlns:resto' => 'http://mapshup.info/-/resto/2.0/'
        ));
    }
    
    /**
     * Set OSDD starting elements
     */
    private function setStartingElements() {
        $this->writeElements(array(
            'ShortName' => $this->collection->osDescription[$this->collection->context->dictionary->language]['ShortName'],
            'Description' => $this->collection->osDescription[$this->collection->context->dictionary->language]['Description'],
            'Tags' => $this->collection->osDescription[$this->collection->context->dictionary->language]['Tags'],
            'Contact' => $this->collection->osDescription[$this->collection->context->dictionary->language]['Contact']
        ));
    }
    
    /**
     * Set OSDD ending elements
     */
    private function setEndingElements() {
        $this->writeElement('LongName', $this->collection->osDescription[$this->collection->context->dictionary->language]['LongName']);
        $this->startElement('Query');
        $this->writeAttributes(array(
            'role' => 'example',
            'searchTerms' => $this->collection->osDescription[$this->collection->context->dictionary->language]['Query']
        ));
        $this->endElement('Query');
        $this->writeElements(array(
            'Developper' => $this->collection->osDescription[$this->collection->context->dictionary->language]['Developper'],
            'Attribution' => $this->collection->osDescription[$this->collection->context->dictionary->language]['Attribution'],
            'SyndicationRight' => 'open',
            'AdultContent' => 'false'
        ));
        for ($i = 0, $l = count($this->collection->context->languages); $i < $l; $i++) {
            $this->writeElement('Language', $this->collection->context->languages[$i]);
        }
        $this->writeElements(array(
            'OutputEncoding' => 'UTF-8',
            'InputEncoding' => 'UTF-8'
        ));
    }
    
    /**
     * Generate OSDD <Url> elements
     */
    private function setUrls() {
        
        foreach (array_values($this->contentTypes) as $format) {
            
            /*
             * <Url> element
             */
            $this->startElement('Url');
            $this->writeAttributes(array(
                'type' => RestoUtil::$contentTypes[$format],
                'rel' => 'results',
                'template' => $this->getUrlTemplate($format)
            ));
            
            /*
             * Extension parameters
             */
            $this->setParameters();
            
            /*
             * End <Url> element
             */
            $this->endElement();
        }
        
    }
    
    /**
     * Return template url for format
     * 
     * @param string $format
     * @return string
     */
    private function getUrlTemplate($format) {
        $url = RestoUtil::restoUrl($this->collection->context->baseUrl, '/api/collections/' . $this->collection->name . '/search', $format) . '?' . $this->clientId;
        $count = 0;
        foreach ($this->collection->model->searchFilters as $filterName => $filter) {
            if (isset($filter)) {
                $optional = isset($filter['minimum']) && $filter['minimum'] === 1 ? '' : '?';
                $url .= ($count > 0 ? '&' : '') . $filter['osKey'] . '={' . $filterName . $optional . '}';
                $count++;
            }
        }
        return $url;
    }
    
    /**
     * Set <parameters:Parameter> elements
     */
    private function setParameters() {
       
        foreach ($this->collection->model->searchFilters as $filterName => $filter) {
            if (isset($filter)) {
                $this->startElement('parameters:Parameter');
                $this->writeAttributes(array(
                    'name' => $filter['osKey'],
                    'value' => '{' . $filterName . '}'
                ));
                for ($i = count($this->extensionParams); $i--;) {
                    if (isset($filter[$this->extensionParams[$i]])) {
                        $this->writeAttribute($this->extensionParams[$i], $filter[$this->extensionParams[$i]]);
                    }
                }

                /*
                 * Options - two cases
                 * 1. predefined value/label
                 * 2. retrieve from database
                 */
                if (isset($filter['options'])) {
                    if (is_array($filter['options'])) {
                        for ($i = count($filter['options']); $i--;) {
                            $this->startElement('parameters:Options');
                            $this->writeAttribute('value', $filter['options'][$i]['value']);
                            if (isset($filter['options'][$i]['label'])) {
                                $this->writeAttribute('label', $filter['options'][$i]['label']);
                            }
                            $this->endElement();
                        }
                    }
                    else if ($filter['options'] === 'auto') {
                        $statistics = $this->collection->getStatistics();
                        if (isset($filter['key']) && isset($statistics['facets'][$filter['key']])) {
                            foreach (array_keys($statistics['facets'][$filter['key']]) as $key) {
                                $this->startElement('parameters:Options');
                                $this->writeAttribute('value', $key);
                                $this->endElement();
                            }
                        }
                    }
                }
                $this->endElement(); // parameters:Parameter
            }
        }

        /*
         * Parameter extension for clientId
         */
        if ($this->clientId !== '') {
            $this->startElement('parameters:Parameter');
            $this->writeAttributes(array(
                'name' => 'clientId',
                'minimum', '1'
            ));
            $this->endElement(); // parameters:Parameter
        }

    }
}
