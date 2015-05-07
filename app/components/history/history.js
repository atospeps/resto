'use strict';

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

/* Controller Users */

/*
 * 
 * history :
 * 
 * [
 {
 "gid": "3417",
 "userid": "2",
 "method": "GET",
 "service": "search",
 "collection": "SpotWorldHeritage",
 "resourceid": null,
 "query": "{\"lang\":\"en\",\"_view\":\"panel-list\",\"_\":\"1424249607459\"}",
 "querytime": "2015-02-18 09:53:31.010211",
 "url": "http:\/\/localhost\/PEPS\/api\/collections\/SpotWorldHeritage\/search.json?lang=en&_view=panel-list&_=1424249607459",
 "ip": "127.0.0.1"
 },
 ...
 ]
 */

angular.module('administration').controller('HistoryController', ['$scope', '_HISTORY', '_COLLECTIONS', 'initialization', 'CONFIG',
    function($scope, _HISTORY, _COLLECTIONS, initialization, CONFIG) {

        if (initialization.ok) {

            $scope.methods = ['POST', 'GET', 'PUT', 'DELETE'];
            $scope.services = ['search', 'visualize', 'create', 'insert', 'download'];
            $scope.collections = [];

            /**
             * Set history order by field "orderBy"
             * 
             * @param {String} orderBy
             */
            $scope.setHistory = function(orderBy) {

                $scope.busy = true;
                $scope.startIndex = 0;
                $scope.offset = CONFIG.offset;
                $scope.showHistory = false;

                if ($scope.ascOrDesc === 'DESC') {
                    $scope.ascOrDesc = 'ASC';
                } else {
                    $scope.ascOrDesc = 'DESC';
                }

                $scope.orderBy = orderBy;
                $scope.getHistory(false);
            };

            /**
             * Get history
             * 
             * If concatData is true, data is concataning with existing data. If not,
             * data is replacing existing data.
             * 
             * @param {boolean} concatData
             */
            $scope.getHistory = function(concatData) {

                var options = [];
                options['startindex'] = $scope.startIndex;
                options['offset'] = $scope.offset;
                options['ascordesc'] = $scope.ascOrDesc;
                options['orderby'] = $scope.orderBy;
                options['collection'] = $scope.collection;
                options['method'] = $scope.method;
                options['service'] = $scope.service;
                options['maxDate'] = $scope.maxDate;
                options['minDate'] = $scope.minDate;

                _HISTORY.get(options, function(data) {
                    $scope.startIndex = $scope.startIndex + $scope.offset;
                    if (concatData === false) {
                        $scope.history = data;
                    } else {
                        $scope.history = $scope.history.concat(data);
                    }
                    $scope.showHistory = true;
                    /*
                     * At the end of data, stop infinitscrolling with busy attribute
                     */
                    if (!data[0]) {
                        $scope.busy = true;
                        $scope.startIndex = $scope.startIndex - $scope.offset;
                    }
                    $scope.busy = false;
                });
            };

            /*
             * Call by infinite scroll
             */
            $scope.loadMore = function() {
                if ($scope.busy)
                    return;
                $scope.busy = true;
                $scope.getHistory();
            };

            $scope.getCollections = function() {
                _COLLECTIONS.get(function(data) {
                    for (var c in data) {
                        $scope.collections.push(c);
                    }
                }, function() {
                    alert('error');
                });
            };

            $scope.setParam = function(type, value) {
                $scope.init();

                if (type === 'method') {
                    $scope.method = value;
                } else if (type === 'service') {
                    $scope.service = value;
                } else if (type === 'collection') {
                    $scope.collection = value;
                }

                $scope.getHistory(false);
            };

            $scope.resetFilters = function() {
                $scope.init();
                $scope.initFilters();
                $scope.getHistory(false);
            };

            /*
             * Init the context
             */
            $scope.init = function() {
                $scope.ascOrDesc = 'DESC';
                $scope.orderBy = null;
                $scope.history = [];
                $scope.busy = true;
                $scope.startIndex = 0;
                $scope.offset = CONFIG.offset;
                $scope.showHistory = false;
            };

            $scope.initFilters = function() {
                $scope.selectedService = null;
                $scope.selectedCollection = null;
                $scope.selectedMethod = null;
                $scope.method = null;
                $scope.service = null;
                $scope.collection = null;
            };

            $scope.init();
            $scope.getHistory();
            $scope.getCollections();
            $scope.$emit('showHistory');
        }
    }]);
