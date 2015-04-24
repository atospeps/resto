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

angular.module('administration', [
    'infinite-scroll',
    'ngRoute',
    'ngCookies',
    'services',
    'satellizer',
    'profile',
    'pascalprecht.translate'
]);

angular.module('administration')
        .config(['$authProvider', 'CONFIG',
            function($authProvider, CONFIG) {
                /*
                 * Authentication configuration
                 */
                // resto "Connect user" service
                $authProvider.loginUrl = CONFIG.restoURL + '/api/users/connect';

                // resto "Add user" service
                $authProvider.signupUrl = CONFIG.restoURL + '/users';

            }]);

angular.module('administration')
        .config(['$translateProvider', config]);

function config($translateProvider) {

    /*
     * Internationalization
     * (See app/i18n/{lang}.json)
     */
    $translateProvider.useStaticFilesLoader({
        prefix: 'app/i18n/',
        suffix: '.json'
    });

    $translateProvider.preferredLanguage('en');

}
;

angular.module('administration')
        .run(['$route', '$rootScope', '$location', function($route, $rootScope, $location) {
                var original = $location.path;
                $location.path = function(path, reload) {
                    if (reload === false) {
                        var lastRoute = $route.current;
                        var un = $rootScope.$on('$locationChangeSuccess', function() {
                            $route.current = lastRoute;
                            un();
                        });
                    }
                    return original.apply($location, [path]);
                };
            }]);