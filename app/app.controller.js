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
/* 
    Created on : 4 mars 2015, 10:27:08
    Author     : remi.mourembles@capgemini.com
*/

angular.module('administration')
        .controller('main', ['$scope', 'profile', 'initialization',
            function($scope, profile, initialization) {

                $scope.$on('showUser', function() {
                    $scope.init();
                    $scope.showUser = true;
                });
                $scope.$on('showHistory', function() {
                    $scope.init();
                    $scope.showHistory = true;
                });
                $scope.$on('showUsers', function() {
                    $scope.init();
                    $scope.showUsers = true;
                });
                $scope.$on('showHome', function() {
                    $scope.init();
                    $scope.showHome = true;
                });
                $scope.$on('showCollections', function() {
                    $scope.init();
                    $scope.showCollections = true;
                });
                $scope.$on('showStats', function() {
                    $scope.init();
                    $scope.showStats = true;
                });

                $scope.init = function() {
                    $scope.showUsers = false;
                    $scope.showHistory = false;
                    $scope.showUser = false;
                    $scope.showHome = false;
                    $scope.showCollections = false;
                    $scope.showStats = false;
                    $scope.selectedUser = null;
                    $scope.showLeftMenu = false;
                };

                $scope.login = function(email, password) {
                    profile.login(email, password,
                            function(data) {
                                $scope.profile = data;
                                if ($scope.profile.groupname !== 'admin') {
                                    $scope.profile = null;
                                    alert('Sorry... You are not an administrator');
                                    profile.logout();
                                    return;
                                } else {
                                    initialization.isOK();
                                }
                                $scope.closeSignIn();
                            }, function(error) {
                        alert('error - login : ' + error);
                    });
                };

                $scope.checkCookies = function() {
                    profile.checkCookies(function(data) {
                        $scope.profile = data;
                        if ($scope.profile.groupname && $scope.profile.groupname !== 'admin') {
                            $scope.profile = null;
                            alert('Sorry... You are not an administrator.');
                            profile.logout();
                            return;
                        } else {
                            initialization.isOK();
                        }
                    });
                };

                $scope.logout = function() {
                    profile.logout();
                    $scope.profile = null;
                };

                $scope.startSignIn = function() {
                    $scope.displaySignIn = true;
                };

                $scope.closeSignIn = function() {
                    $scope.displaySignIn = false;
                };

                $scope.displayLeftMenu = function() {
                    $scope.showLeftMenu = !$scope.showLeftMenu;
                };

                $scope.init();
                $scope.checkCookies();

            }]);