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

/* Services */

var services = angular.module('services', []);

services.factory('initialization',
        function() {
            var initialization = {};

            initialization.ok = false;
            
            initialization.setOk = function(isOk) {
                initialization.ok = isOk;
            };

            return initialization;
        });

services.factory('userActivation', ['$http', 'CONFIG',
    function($http, CONFIG) {
        return {
            get: function(callback, error) {
                $http({
                    method: 'POST',
                    url: CONFIG.activationURL,
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    transformRequest: function(obj) {
                        var str = [];
                        for (var p in obj)
                            str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
                        return str.join("&");
                    },
                    data: {
                        emailorgroup: 'default',
                        collection: collection,
                        field: right,
                        value: value
                    }
                }).success(function(data) {
                    if (data.ErrorMessage) {
                        error(data);
                    } else {
                        callback(data);
                    }
                }).error(function() {
                    alert('error - user activation');
                });
            }
        };
    }]);

services.factory('_HISTORY', ['$http', 'CONFIG',
    function($http, CONFIG) {
        return {
            get: function(options, callback, error) {

                var startindex = options['startindex'];
                var offset = options['offset'];
                var ascordesc = options['ascordesc'];
                var orderby = options['orderby'];
                var userid = options['userid'];
                var method = options['method'];
                var service = options['service'];
                var maxDate = options['maxDate'];
                var minDate = options['minDate'];
                var collection = options['collection'];

                var url = CONFIG.restoURL + CONFIG.administrationEndpoint + '/users/';
                if (userid) {
                    url = url + userid + '/';
                }


                url = url + 'history.json?startIndex=' + startindex + '&numberOfResults=' + offset;

                if (ascordesc) {
                    url = url + "&ascordesc=" + ascordesc;
                }
                if (orderby) {
                    url = url + "&orderBy=" + orderby;
                }
                if (method) {
                    url = url + "&method=" + method;
                }
                if (service) {
                    url = url + "&service=" + service;
                }
                if (maxDate) {
                    url = url + "&maxDate=" + maxDate;
                }
                if (minDate) {
                    url = url + "&minDate=" + minDate;
                }
                if (collection) {
                    url = url + "&collection=" + collection;
                }


                $http.get(url)
                        .success(function(data) {
                            if (data.ErrorMessage) {
                                error(data);
                            } else {
                                callback(data);
                            }
                        })
                        .error(function() {
                            alert('error - get history');
                        });



            }
        };
    }]);

services.factory('_COLLECTIONS', ['$http', 'CONFIG',
    function($http, CONFIG) {
        return {
            get: function(callback, error) {
                // /administration/collections.json
                $http.get(CONFIG.restoURL + CONFIG.administrationEndpoint + '/collections.json')
                        .success(
                                function(data) {
                                    if (data.ErrorMessage) {
                                        error(data);
                                    } else {
                                        callback(data);
                                    }
                                })
                        .error(function() {
                            alert('error - get collections');
                        });
            },
            setRight: function(options, callback, error) {

                var emailorgroup = options['emailorgroup'];
                var collection = options['collection'];
                var field = options['field'];
                var value = options['value'];

                $http({
                    method: 'POST',
                    // /administration/collections
                    url: CONFIG.restoURL + CONFIG.administrationEndpoint + '/collections',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    transformRequest: function(obj) {
                        var str = [];
                        for (var p in obj)
                            str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
                        return str.join("&");
                    },
                    data: {
                        emailorgroup: emailorgroup,
                        collection: collection,
                        field: field,
                        value: value
                    }
                }).success(function(data) {
                    if (data.ErrorMessage) {
                        error(data);
                    } else {
                        callback(data);
                    }
                }).error(function() {
                    alert('error - set right');
                });
            },
            stats: function(callback, error) {
                // /administration/stats/collections.json
                var url = CONFIG.restoURL + CONFIG.administrationEndpoint + '/stats/collections.json';

                $http.get(url)
                        .success(
                                function(data, status, headers, config) {
                                    if (data.ErrorMessage) {
                                        error(data);
                                    } else {
                                        callback(data);
                                    }
                                })
                        .error(function() {
                            alert('error - stats');
                        });
            }
        };
    }]);

services.factory('_USERS', ['$http', 'CONFIG',
    function($http, CONFIG) {
        return {
            get: function(options, callback, error) {

                var startIndex = options['startIndex'];
                var offset = options['offset'];
                var keyword = options['keyword'];

                var url = CONFIG.restoURL + CONFIG.administrationEndpoint + '/users?min=' + startIndex + '&number=' + offset;
                if (keyword) {
                    url = url + '&keyword=' + keyword;
                }
                
                $http.get(url)
                        .success(
                                function(data, status, headers, config) {
                                    if (data.ErrorMessage) {
                                        error(data);
                                    } else {
                                        callback(data);
                                    }
                                })
                        .error(function() {
                            alert('error - get users');
                        });


            },
            stats: function(callback, error) {
                // administration/stats/users.json
                var url = CONFIG.restoURL + CONFIG.administrationEndpoint + '/stats/users.json';
                ;

                $http.get(url)
                        .success(
                                function(data, status, headers, config) {
                                    if (data.ErrorMessage) {
                                        error(data);
                                    } else {
                                        callback(data);
                                    }
                                })
                        .error(function() {
                            alert('error - get stats');
                        });
            },
            add: function(options, callback, error) {
                $http({
                    method: 'POST',
                    url: CONFIG.restoURL + CONFIG.administrationEndpoint + '/users',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    transformRequest: function(obj) {
                        var str = [];
                        for (var p in obj)
                            str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
                        return str.join("&");
                    },
                    data: {
                        email: options['email'],
                        password: options['password'],
                        username: options['username'],
                        givename: options['givename'],
                        lastname: options['lastname']
                    }
                }).success(function(data) {
                    if (data.ErrorMessage) {
                        error(data);
                    } else {
                        callback(data);
                    }
                }).error(function(e) {
                    callback(e);
                });
            }
        };
    }]);

services.factory('_USER', ['$http', 'CONFIG',
    function($http, CONFIG) {
        return {
            get: function(userid, callback, error) {
                $http.get(CONFIG.restoURL + '/users/' + userid + '.json')
                        .success(
                                function(data, status, headers, config) {
                                    if (data.ErrorMessage) {
                                        error(data);
                                    } else {
                                        callback(data.profile);
                                    }
                                })
                        .error(function() {
                            alert('error - get user');
                        });
            },
            activate: function(userid, callback, error) {
                $http({
                    method: 'POST',
                    url: CONFIG.restoURL + CONFIG.administrationEndpoint + "users/" + userid + "/activate"
                }).success(function(data) {
                    if (data.ErrorMessage) {
                        error(data);
                    } else {
                        callback(data);
                    }
                }).error(function() {
                    alert('error - activate user');
                });
            },
            deactivate: function(userid, callback, error) {
                $http({
                    method: 'POST',
                    url: CONFIG.restoURL + CONFIG.administrationEndpoint + "users/" + userid + "/deactivate"
                }).success(function(data) {
                    if (data.ErrorMessage) {
                        error(data);
                    } else {
                        callback(data);
                    }
                }).error(function() {
                    alert('error - deactivate user');
                });
            },
            setGroup: function(options, callback, error) {

                var userid = options['userid'];
                var email = options['email'];
                var groupname = options['groupname'];

                $http({
                    method: 'POST',
                    url: CONFIG.restoURL + CONFIG.administrationEndpoint + 'users/' + userid,
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    transformRequest: function(obj) {
                        var str = [];
                        for (var p in obj)
                            str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
                        return str.join("&");
                    },
                    data: {
                        email: email,
                        groupname: groupname
                    }
                }).success(function(data) {
                    if (data.ErrorMessage) {
                        error(data);
                    } else {
                        callback(data);
                    }
                }).error(function() {
                    alert('error - set group');
                });
            },
            getSignatures: function(userid, callback, error) {
                $http.get(CONFIG.restoURL + '/users/' + userid + '/signatures.json')
                        .success(
                                function(data, status, headers, config) {
                                    if (data.ErrorMessage) {
                                        error(data);
                                    } else {
                                        callback(data.signatures);
                                    }
                                })
                        .error(function() {
                            alert('error - get signatures');
                        });
            },
            updateProfile: function(userid, data, callback, error) {
                $http.put(CONFIG.restoURL + '/users/' + userid, data)
                .success(function(data, status, headers, config) {
                		callback();
                    }).error(function(data) {
                		error(data);
                });
    }
        };
    }]);

services.factory('_RIGHTS', ['$http', 'CONFIG',
    function($http, CONFIG) {
        return {
            get: function(userid, callback, error) {
                $http.get(CONFIG.restoURL + CONFIG.administrationEndpoint + '/users/' + userid + '/rights.json')
                        .success(
                                function(data, status, headers, config) {
                                    if (data.ErrorMessage) {
                                        error(data);
                                    } else {
                                        callback(data.rights);
                                    }
                                })
                        .error(function() {
                            alert('error - get rights');
                        });
            },
            delete: function(options, callback, error) {


                var params = [];

                params['collection'] = options['collection'];
                if (options['featureid']) {
                    params['featureid'] = options['featureid'];
                }
                params['userid'] = options['userid'];
                params['emailorgroup'] = options['email'];

                $http({
                    method: 'POST',
                    url: CONFIG.restoURL + CONFIG.administrationEndpoint + '/users/' + params['userid'] + "/rights/delete",
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    transformRequest: function(obj) {
                        var str = [];
                        for (var p in obj)
                            str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
                        return str.join("&");
                    },
                    data: params
                }).success(function(data) {
                    if (data.ErrorMessage) {
                        error(data.ErrorMessage);
                    } else {
                        callback(data);
                    }
                }).error(function() {
                    alert('error - delete right');
                });
            },
            set: function(options, callback, error) {

                var userid = options['userid'];
                var email = options['emailorgroup'];
                var collection = options['collection'];
                var field = options['field'];
                var value = options['value'];

                $http({
                    method: 'POST',
                    url: CONFIG.restoURL + CONFIG.administrationEndpoint + '/users/' + userid + '/rights/update',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    transformRequest: function(obj) {
                        var str = [];
                        for (var p in obj)
                            str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
                        return str.join("&");
                    },
                    data: {
                        emailorgroup: email,
                        collection: collection,
                        field: field,
                        value: value
                    }
                }).success(function(data) {
                    if (data.ErrorMessage) {
                        error(data);
                    } else {
                        callback(data);
                    }
                }).error(function() {
                    alert('error - set right');
                });
            },
            setAdvancedRight: function(options, callback, error) {

                var userid = options['userid'];
                var emailorgroup = options['emailorgroup'];
                var collection = options['collection'];
                var featureid = options['featureid'];
                var search = options['search'];
                var visualize = options['visualize'];
                var download = options['download'];
                var canput = options['canput'];
                var canpost = options['canpost'];
                var candelete = options['candelete'];
                var filters = options['filters'];

                $http({
                    method: 'POST',
                    url: CONFIG.restoURL + CONFIG.administrationEndpoint + '/users/' + userid + '/rights',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    transformRequest: function(obj) {
                        var str = [];
                        for (var p in obj)
                            str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
                        return str.join("&");
                    },
                    data: {
                        emailorgroup: emailorgroup,
                        collection: collection,
                        featureid: featureid,
                        search: search,
                        visualize: visualize,
                        download: download,
                        canput: canput,
                        canpost: canpost,
                        candelete: candelete,
                        filters: filters
                    }
                }).success(function(data) {
                    if (data.ErrorMessage) {
                        error(data);
                    } else {
                        callback(data);
                    }
                }).error(function() {
                    alert('error - set advanced right');
                });
            }
        };
    }]);