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
 * profile :
 * 
 * {
 "userid": "2",
 "email": "toto",
 "groupname": "default",
 "username": "toto",
 "givenname": "toto",
 "lastname": "toto",
 "registrationdate": "2014-11-20T11:13:07Z",
 "activated": true,
 "lastsessionid": "vdqd28q0mv1pla7fdahkd40o07",
 "userhash": "f71dbe52628a3f83a77ab494817525c6"
 }
 */

/*
 * rights :
 * 
 *  {
 "status": "success",
 "message": "Rights for 2",
 "userid": "2",
 "groupname": "default",
 "rights": {
 "*": {
 "search": true,
 "visualize": false,
 "download": false,
 "post": false,
 "put": false,
 "delete": false
 },
 "Spirit": {
 "features": {
 "68468087-b3d4-505e-bcc0-805f275795e8": {
 "delete": null,
 "put": null,
 "post": null,
 "visualize": null,
 "download": null,
 "search": null
 }
 },
 "delete": true,
 "put": true,
 "post": null,
 "visualize": true,
 "download": null,
 "search": null
 },
 ...
 }
 }
 *  
 */

angular.module('administration').controller('UserController', ['$scope', '$location', '$routeParams', '_HISTORY', '_USER', '_RIGHTS', '_COLLECTIONS', 'initialization', 'CONFIG',
    function($scope, $location, $routeParams, _HISTORY, _USER, _RIGHTS, _COLLECTIONS, initialization, CONFIG) {

        if (initialization.ok) {
            $scope.templates =
                    {
                        'history': 'app/components/user/templates/history.html',
                        'profile': 'app/components/user/templates/profile.html',
                        'rightCreation': 'app/components/user/templates/rightCreation.html',
                        'signatures': 'app/components/user/templates/signatures.html'
                    };

            /*
             * Init the context
             */
            $scope.init = function() {
                $scope.showProfile = false;
                $scope.showHistory = false;
                $scope.showCreation = false;
                $scope.showAdvancedRights = false;
                $scope.showSignatures = false;

                $scope.history = [];
                $scope.feature = [];
                $scope.feature.collection = null;
                $scope.feature.search = false;
                $scope.feature.visualize = false;
                $scope.feature.download = false;
                $scope.feature.canput = false;
                $scope.feature.canpost = false;
                $scope.feature.candelete = false;
                $scope.feature.filters = null;

                $scope.ascOrDesc = 'DESC';
                $scope.orderBy = null;
                $scope.startIndex = 0;
                $scope.offset = CONFIG.offset;

                $scope.template = null;
            };

            /*
             * Set activation
             * 
             * If user isn't activated, this method activate user. Else, this method
             * deactivate user
             */
            $scope.activation = function() {
                if ($scope.selectedUser.activated === 1) {
                    _USER.deactivate($scope.selectedUser.userid, function() {
                        $scope.getUser();
                        $scope.getRights();
                    });
                } else if ($scope.selectedUser.activated === 0) {
                    _USER.activate($scope.selectedUser.userid, function() {
                        $scope.getUser();
                        $scope.getRights();
                    });
                } else {
                    $scope.alert('error : activation - selectedUser.activated is not set');
                }
            };

            /*
             * Switch between default and admin group
             */
            $scope.changeGroup = function() {
                if ($scope.selectedUser.groupname === 'default') {
                    $scope.setGroup('admin');
                } else if ($scope.selectedUser.groupname === 'admin') {
                    $scope.setGroup('default');
                }
            };


            /*
             * Set right
             * 
             * @param {String} collection
             * @param {String} right
             * @param {boolean} value
             * 
             */
            $scope.setRight = function(collection, right, value) {

                if (value === 1) {
                    value = 0;
                } else if (value === 0) {
                    value = 1;
                } else {
                    $scope.alert('error - setRight');
                    return;
                }

                var options = [];
                options['emailorgroup'] = $scope.selectedUser.email;
                options['userid'] = $scope.selectedUser.userid;
                options['collection'] = collection;
                options['field'] = right;
                options['value'] = value;

                _RIGHTS.set(options, function() {
                    $scope.getRights();
                });
            };

            /*
             * Set group
             * 
             * @param {String} groupname
             * 
             */
            $scope.setGroup = function(groupname) {

                var options = [];
                options['userid'] = $scope.selectedUser.userid;
                options['email'] = $scope.selectedUser.email;
                options['groupname'] = groupname;

                _USER.setGroup(options, function() {
                    $scope.getUser();
                    $scope.getRights();
                });
            };

            /*
             * Display history
             */
            $scope.displayHistory = function() {
                $scope.init();
                $scope.getHistory();
                $scope.template = $scope.templates.history;
                $scope.showHistory = true;
            };

            /*
             * Display profile
             */
            $scope.displayProfile = function() {
                $scope.init();
                $scope.getUser();
                $scope.getRights();
                $scope.template = $scope.templates.profile;
                $scope.showProfile = true;
            };

            /*
             * Display signatures
             */
            $scope.displaySignatures = function() {
                $scope.init();
                $scope.getSignatures();
                $scope.template = $scope.templates.signatures;
                $scope.showSignatures = true;
            };

            /*
             * Display advanced rights
             */
            $scope.displayAdvancedRights = function() {
                $scope.showAdvancedRights = !$scope.showAdvancedRights;
            };

            /*
             * go to history
             */
            $scope.goToHistory = function() {
                var path = '/users/' + $scope.selectedUser.userid + '/history';
                $location.path(path, false);
                $scope.displayHistory();
            };

            /*
             * go to profile
             */
            $scope.goToProfile = function() {
                var path = '/users/' + $scope.selectedUser.userid;
                $location.path(path, false);
                $scope.displayProfile();
            };

            /*
             * go to signatures
             */
            $scope.goToSignatures = function() {
                var path = '/users/' + $scope.selectedUser.userid + '/signatures';
                $location.path(path, false);
                $scope.displaySignatures();
            };

            /*
             * go to advanced rights
             */
            $scope.goToAdvancedRights = function() {
                var path = '/users/' + $scope.selectedUser.userid + '/rights';
                $location.path(path, false);
                $scope.displayCreateAdvancedRights();
            };

            /*
             * Set collection
             * 
             * @param {String} collection
             * 
             */
            $scope.setCollection = function(collection) {
                $scope.feature.collection = collection;
            };

            /*
             * Display create advanced rights
             */
            $scope.displayCreateAdvancedRights = function() {
                $scope.init();
                $scope.getCollections();
                $scope.template = $scope.templates.rightCreation;
                $scope.showCreation = true;
            };

            /*
             * Add advanced right
             */
            $scope.addAdvancedRight = function() {

                if (!$scope.feature.collection || !$scope.feature.id) {
                    $scope.alert('error - can t create rights - missing attributes');
                }

                var options = [];
                options['userid'] = $scope.selectedUser.userid;
                options['emailorgroup'] = $scope.selectedUser.email;
                options['collection'] = $scope.feature.collection.name;
                options['featureid'] = $scope.feature.id;
                options['search'] = Number($scope.feature.search);
                options['visualize'] = Number($scope.feature.visualize);
                options['download'] = Number($scope.feature.download);
                options['canput'] = Number($scope.feature.canput);
                options['canpost'] = Number($scope.feature.canpost);
                options['candelete'] = Number($scope.feature.candelete);
                options['filters'] = $scope.feature.filters;

                _RIGHTS.setAdvancedRight(options, function() {
                    $scope.displayProfile();
                    $scope.showAdvancedRights = true;
                }, function(data) {
                    $scope.alert('error - ' + data.ErrorMessage);
                });
            };

            /*
             * Delete advanced right
             * 
             * @param {String} collection
             * @param {String} featureid
             * 
             */
            $scope.deleteAdvancedRight = function(collection, featureid) {

                var x = confirm("Delete right ?");
                if (x)
                    $scope.deleteAdvancedRightConfirmed(collection, featureid);
            };

            /*
             * Delete advanced right
             * 
             * @param {String} collection
             * @param {String} featureid
             * 
             */
            $scope.deleteAdvancedRightConfirmed = function(collection, featureid) {

                var options = [];
                options['collection'] = collection;
                options['featureid'] = featureid;
                options['userid'] = $scope.selectedUser.userid;
                options['email'] = $scope.selectedUser.email;

                _RIGHTS.delete(options, function() {
                    $scope.getRights();
                });
            };

            /*
             * Delete rights
             * 
             * @param {String} collection
             * 
             */
            $scope.deleteRightsConfirmed = function(collection) {

                var options = [];
                options['collection'] = collection;
                options['userid'] = $scope.selectedUser.userid;
                options['email'] = $scope.selectedUser.email;

                _RIGHTS.delete(options, function() {
                    $scope.getRights();
                }, function(data) {
                    $scope.alert('error - ' + data);
                });
            };

            /*
             * Set right
             * 
             * @param {String} collection
             * @param {String} right
             * @param {boolean} value
             * 
             */
            $scope.deleteRights = function(collection) {

                var x = confirm("Reset rights ?");
                if (x)
                    $scope.deleteRightsConfirmed(collection);

            };

            /*
             * Get user
             */
            $scope.getUser = function() {
                _USER.get($routeParams.userid, function(data) {
                    $scope.selectedUser = data;
                });
            };

            /*
             * Get rights
             */
            $scope.getRights = function() {
                _RIGHTS.get($routeParams.userid, function(data) {
                    $scope.rights = data;
                });
            };

            /*
             * Get signatures
             */
            $scope.getSignatures = function() {
                _USER.getSignatures($routeParams.userid, function(data) {
                    $scope.signatures = data;
                });
            };

            /*
             * Set history 
             * 
             * @param {String} orderBy
             * 
             */
            $scope.setHistory = function(orderBy) {

                $scope.busy = true;
                $scope.startIndex = 0;
                $scope.offset = CONFIG.offset;

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
             * If concatData is true, the existing data is concataned with new data,
             * else, existing data is deleted.
             * 
             * @param {type} concatData
             * 
             */
            $scope.getHistory = function(concatData) {

                var options = [];
                options['startindex'] = $scope.startIndex;
                options['offset'] = $scope.offset;
                options['ascordesc'] = $scope.ascOrDesc;
                options['orderby'] = $scope.orderBy;
                options['userid'] = $routeParams.userid;

                _HISTORY.get(options, function(data) {
                    $scope.startIndex = $scope.startIndex + $scope.offset;
                    if (concatData === false) {
                        $scope.history = data;
                    } else {
                        $scope.history = $scope.history.concat(data);
                    }
                    /*
                     * At the end of data, stop infinitscrolling with busy attribute
                     */
                    if (!data[0]) {
                        $scope.busy = true;
                    }
                    $scope.busy = false;
                });
            };

            /*
             * Call by infinite scroll
             */
            $scope.loadMore = function() {
                if (!$scope.showHistory)
                    return;
                if ($scope.busy)
                    return;
                $scope.busy = true;
                $scope.getHistory();
            };

            /*
             * Get collections
             */
            $scope.getCollections = function() {
                _COLLECTIONS.get(function(data) {
                    $scope.collections = data;
                    $scope.busy = false;
                });
            };

            $scope.alert = function(message) {
                alert(message);
            };

            $scope.$emit('showUser');
            $scope.init();
            $scope.displayProfile();

            if ($routeParams.section === 'history') {
                $scope.displayHistory();
            } else if ($routeParams.section === 'signatures') {
                $scope.displaySignatures()
            } else if ($routeParams.section === 'rights') {
                $scope.displayCreateAdvancedRights();
            }

        }
    }]);

