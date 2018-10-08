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
    
    /*
     * Titles for options values (uppercase keys)
     */
    private $titles = array(
        /* processingLevel */
        'LEVEL1'      => 'Use of algorithms and calibration data to create a basic product from which higher level products are derived',
        'LEVEL1C'     => 'Radiometric and geometric corrections, including ortho-rectification and spatial registration on a global reference system with sub-pixel accuracy',
        'LEVEL2'      => 'Geo-localized Geophysical Products Derived From Level 1 Products',
        'LEVEL2A'     => 'Ortho-corrected products providing ground-corrected reflectances corrected for atmospheric effects, and basic pixel classification (including different classes for cloud types)',
        /* productType */
        'GRD'         => 'Radar data projected to the ground via an ellipsoid terrain model',
        'OCN'         => 'Geo-referenced radar data via orbit and satellite altitude',
        'SLC'         => 'Single Look Complex data: complex imaging with amplitude and phase',
        'S2MSI1C'     => 'Reflectance at the top of the ortho-rectified atmosphere, with a sub-pixel recalibration between spectral bands. Cloud, land, and water masks included in the product',
        'OL_1_EFR___' => 'S3 Ocean and Land Color Instrument: Reflectance at the top of the atmosphere at full resolution',
        'OL_1_ERR___' => 'S3 Ocean and Land Color Instrument: Reflectance at the top of the atmosphere at reduced resolution',
        'SL_1_RBT___' => 'S3 Radiometer at surface temperature on Earth and Ocean: brightness and radiance temperatures',
        'SR_2_LAN___' => 'S3 Radar altimeter with synthetic aperture: waveform and parameters LRM / SAR in Ku and C bands at 1Hz and 20Hz. On Land, coastal areas, ice and inland waters',
        /* instrument */
        'SAR-C SAR'   => 'Synthetic aperture radar operating in C-band',
        'MSI'         => 'Multi-Spectral Instrument',
        'SLSTR'       => 'Sea and Land Surface Temperature Radiometer',
        'OLCI'        => 'Ocean and Land Colour Instrument',
        'SRAL'        => 'Synthetic Aperture Radar Altimeter',
        /* sensorMode */
        'EW'          => 'Extra Wide swath',
        'IW'          => 'Interferometric Wide swath',
        'SM'          => 'Stripmap',
        'WV'          => 'Wave mode',
        'INS-NOBS'    => 'Nominal Observation',
        'INS-RAW'     => 'Raw Measurement',
        'INS-VIC'     => 'Vicarious Calibration',
        'EARTH OBSERVATION' => 'sea surface topography, sea and land surface temperature, and ocean and land surface colour with high accuracy and reliability',
        /* Realtime */
        'NOMINAL'     => 'Product S2 available between 3h and 24h after its acquisition',
        'NRT-10M'     => 'Product S1 available 10 minutes after acquisition',
        'NRT-1H'      => 'Product S1 available 1h after its acquisition',
        'NRT-3H'      => 'Product S1 available 3h after its acquisition',
        'FAST-24H'    => 'Product S1 available 24 hours after its acquisition',
        'OFF-LINE'    => 'S1 gross final product',
        'REPROCESSING'=> 'Final product S1 processed',
        'NRT'         => 'Product S2 available between 100 minutes and 3 hours after its acquisition or Product S3 available less than 3h after its acquisition, mainly used for marine meteorology and the study of the transfer of gas between the ocean and the atmosphere',
        'RT'          => 'Product S2 available less than 100 minutes after acquisition',
        'STC'         => 'S3 product available less than 48 hours after its acquisition, mainly used for geophysical and oceanographic studies',
        'NTC'         => 'Product S3 available less than 1 month after its acquisition, mainly used for geophysical and oceanographic studies'
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
                            $this->startElement('parameters:Option');
                            $this->writeAttribute('value', $filter['options'][$i]['value']);
                            if (isset($filter['options'][$i]['label'])) {
                                $this->writeAttribute('label', $filter['options'][$i]['label']);
                            }
                            if (isset($this->titles[strtoupper($filter['options'][$i]['value'])])) {
                                $this->writeAttribute('title', $this->titles[$filter['options'][$i]['value']]);
                            }
                            $this->endElement();
                        }
                    }
                    else if ($filter['options'] === 'auto') {
                        $statistics = $this->collection->getStatistics();
                        if (isset($filter['key']) && isset($statistics['facets'][$filter['key']])) {
                            foreach (array_keys($statistics['facets'][$filter['key']]) as $key) {
                                $this->startElement('parameters:Option');
                                $this->writeAttribute('value', $key);
                                if (isset($this->titles[strtoupper($key)])) {
                                    $this->writeAttribute('title', $this->titles[strtoupper($key)]);
                                }
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
