(function() {
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

    angular.module('profile', []);

    angular.module('profile')
            .factory('profile', ['$auth',
                function($auth) {
                    var profile = {};
                    
                    profile.checkCookies = function(callback){
                        if ($auth.isAuthenticated()){
                            var token = jwt_decode($auth.getToken());
                            callback(token.data);
                        }
                    };

                    profile.login = function(email, password, callback, error_callback) {
                        $auth.login({email: email, password: password})
                                .then(function(results) {
                                    /*
                                     * Decode token
                                     */
                                    var token = jwt_decode(results.data.token);
                                    callback(token.data);
                                })
                                ["catch"](function(response) {
                            error_callback(response.data);
                        });
                    };

                    profile.authenticate = function(provider, callback, error_callback) {
                        $auth.authenticate(provider)
                                .then(function(results) {
                                    /*
                                     * Decode token
                                     */
                                    var token = jwt_decode(results.data.token);
                                    callback(token.data);
                                })
                                ["catch"](function(response) {
                            error_callback(response.data);
                        });
                    };
                    
                    profile.logout = function(){
                        $auth.logout();
                    };

                    return profile;
                }]);


    angular.module('profile').controller('ProfileController', ['$scope', 
        function($scope) {
            $scope.password = null;
            $scope.email = null;
        }]);

})();