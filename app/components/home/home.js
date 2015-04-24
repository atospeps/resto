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

angular.module('administration').controller('HomeController', ['$scope', '_USERS', 'initialization',
    function($scope, _USERS, initialization) {

        if (initialization.ok) {
            /*
             * Init the context
             */
            $scope.init = function() {
                _USERS.stats(function(data) {
                    $scope.nb_users = data.users.count;
                    $scope.nb_downloads = data.download.count;
                    $scope.nb_search = data.search.count;
                }, function(data) {
                    alert('error - ' + data.ErrorMessage);
                });

                $scope.$emit('showHome');
            };

            $scope.init();
        }
    }]);

