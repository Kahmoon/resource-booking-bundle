/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */
"use strict";

class resourceBookingApp {
    constructor(vueElement, params) {
        new Vue({
            el: vueElement,
            data: {
                opt: [],
                isReady: false,
                filterBoard: null,
                userLoggedOut: false,
                isOnline: false,
                loggedInUser: [],
                requestToken: '',
                sessionId: '',
                weekdays: [],
                timeSlots: [],
                rows: [],
                activeResourceTypeId: '',
                activeResourceId: '',
                activeWeekTstamp: 0,
                activeResourceType: [],
                activeResource: [],
                activeWeek: [],
                bookingRepeatsSelection: [],
                bookingFormValidation: [],
                bookingModal: {},
                intervals: [],
                messages: null,
            },

            created: function created() {
                let self = this;

                // Detect unsupported browders
                var ua = window.navigator.userAgent;
                var msie = ua.indexOf('MSIE ');
                if (msie > 0) {
                    alert('This extension is not compatible with your browser. Please use a current browser (like Opera, Firefox, Safari or Google Chrome), that is not out of date.')
                }

                // Post requests require a request token
                self.requestToken = params.requestToken;

                // Session Id
                self.sessionId = params.sessionId;

                // Fetch data from server each 30s
                self.fetchDataRequest();
                self.intervals.fetchDataRequest = window.setInterval(function () {
                    self.fetchDataRequest();
                }, 30000);

                // Initialize idle detector
                window.setTimeout(function () {
                    self.initializeIdleDetector();
                }, 10000);
            },

            // Watchers
            watch: {
                // Watcher
                isOnline: function isOnline(val) {
                    let self = this;

                    if (val === false) {
                        // Clear interval
                        clearInterval(self.intervals.fetchDataRequest);

                        // Logout user after 7 min (420000 ms) of idle time
                        self.sendLogoutRequest();
                        window.setTimeout(function () {
                            // Close booking modal if it is still open
                            $(self.$el).find('.resource-booking-modal').first().modal('hide');
                            window.setTimeout(function () {
                                $(self.$el).find('.auto-logout-modal').first().on('hidden.bs.modal', function () {
                                    if (self.opt.autologout) {
                                        location.href = self.opt.autologoutRedirect;
                                    } else {
                                        location.href = '';
                                    }
                                });
                                $(self.$el).find('.auto-logout-modal').first().modal('show');
                            }, 100);
                        }, 400);
                    }
                },
                activeResourceTypeId: function activeResourceTypeId(newObj, oldObj) {
                    this.sendApplyFilterRequest();
                },
                activeResourceId: function activeResourceId(newObj, oldObj) {
                    this.sendApplyFilterRequest();
                },
                activeWeekTstamp: function activeWeekTstamp(newObj, oldObj) {
                    this.sendApplyFilterRequest();
                }
            },

            methods: {
                /**
                 * Fetch all the data from the server
                 */
                fetchDataRequest: function fetchDataRequest() {
                    let self = this;

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', self.requestToken);

                    // Fetch
                    let action = 'fetchDataRequest';
                    fetch('_resource_booking/ajax/' + action + '?sessionId=' + self.sessionId, {
                        method: "POST",
                        body: data,
                        headers: {
                            'x-requested-with': 'XMLHttpRequest'
                        },
                    })
                        .then(function (res) {
                            return res.json();
                        })
                        .then(function (response) {
                            if (response.status === 'success') {
                                for (let key in response['data']) {
                                    self[key] = response['data'][key];
                                }
                                self.isReady = true;
                            }

                            self.isOnline = true;
                        }).catch(function (error) {
                        self.isOnline = false;
                    });
                },

                /**
                 * Send booking request
                 */
                sendBookingRequest: function sendBookingRequest() {

                    let self = this;

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', self.requestToken);
                    data.append('resourceId', self.bookingModal.activeTimeSlot.resourceId);
                    data.append('description', $(self.$el).find('.resource-booking-modal [name="bookingDescription"]').first().val());
                    data.append('bookingRepeatStopWeekTstamp', $(self.$el).find('.booking-repeat-stop-week-tstamp').first().val());

                    let i;
                    for (i = 0; i < self.bookingModal.selectedTimeSlots.length; i++) {
                        data.append('bookingDateSelection[]', self.bookingModal.selectedTimeSlots[i]);
                    }

                    let action = 'sendBookingRequest';
                    fetch('_resource_booking/ajax/' + action + '?sessionId=' + self.sessionId,
                        {
                            method: "POST",
                            body: data,
                            headers: {
                                'x-requested-with': 'XMLHttpRequest'
                            },
                        })
                        .then(function (res) {
                            return res.json();
                        })
                        .then(function (response) {
                            if (response.status === 'success') {
                                self.bookingModal.alertSuccess = response.alertSuccess;
                                window.setTimeout(function () {
                                    $(self.$el).find('.resource-booking-modal').first().modal('hide');
                                }, 2500);
                            } else {
                                self.bookingModal.alertError = response.alertError;
                            }
                            self.isOnline = true;
                            // Always
                            self.bookingModal.showConfirmationMsg = true;
                            self.fetchDataRequest();
                        })
                        .catch(function (response) {
                            self.isOnline = false;
                            // Always
                            self.bookingModal.showConfirmationMsg = true;
                            self.fetchDataRequest();
                        });
                },

                /**
                 * Send resource availability request
                 */
                sendBookingFormValidationRequest: function sendBookingFormValidationRequest() {
                    let self = this;

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', self.requestToken);
                    data.append('resourceId', self.bookingModal.activeTimeSlot.resourceId);
                    data.append('bookingRepeatStopWeekTstamp', $(self.$el).find('.booking-repeat-stop-week-tstamp').first().val());

                    let i;
                    for (i = 0; i < self.bookingModal.selectedTimeSlots.length; i++) {
                        data.append('bookingDateSelection[]', self.bookingModal.selectedTimeSlots[i]);
                    }
                    let action = 'sendBookingFormValidationRequest';
                    fetch('_resource_booking/ajax/' + action + '?sessionId=' + self.sessionId,
                        {
                            method: "POST",
                            body: data,
                            headers: {
                                'x-requested-with': 'XMLHttpRequest'
                            },
                        })
                        .then(function (res) {
                            return res.json();
                        })
                        .then(function (response) {
                            if (response.status === 'success') {
                                self.bookingFormValidation = response.data;
                                self.isOnline = true;
                            } else {
                                self.isOnline = false;
                            }
                        }).catch(function (response) {
                        self.isOnline = false;
                    });

                },

                /**
                 * Send cancel booking request
                 */
                sendCancelBookingRequest: function sendCancelBookingRequest() {
                    let self = this;

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', self.requestToken);
                    data.append('bookingId', self.bookingModal.activeTimeSlot.bookingId);

                    let action = 'sendCancelBookingRequest';
                    fetch('_resource_booking/ajax/' + action + '?sessionId=' + self.sessionId, {
                        method: "POST",
                        body: data,
                        headers: {
                            'x-requested-with': 'XMLHttpRequest'
                        },
                    })
                        .then(function (res) {
                            return res.json();
                        })
                        .then(function (response) {
                            if (response.status === 'success') {
                                self.bookingModal.alertSuccess = response.alertSuccess;
                                window.setTimeout(function () {
                                    $(self.$el).find('.resource-booking-modal').first().modal('hide');
                                }, 2500);
                            } else {
                                self.bookingModal.alertError = response.alertError;
                            }
                            // Always
                            self.bookingModal.showConfirmationMsg = true;
                            self.fetchDataRequest();
                        })
                        .catch(function (response) {
                            self.isOnline = false;
                            // Always
                            self.bookingModal.showConfirmationMsg = true;
                            self.fetchDataRequest();
                        });
                },

                /**
                 * Send logout request
                 */
                sendLogoutRequest: function sendLogoutRequest() {

                    let self = this;

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', self.requestToken);

                    fetch('_resource_booking/ajax/logout?sessionId=' + self.sessionId,
                        {
                            method: "POST",
                            body: data,
                            headers: {
                                'x-requested-with': 'XMLHttpRequest'
                            },
                        })
                        .then(function (res) {
                            return res.json();
                        })
                        .then(function (response) {
                            // Always
                            self.isOnline = false;
                            self.userLoggedOut = true;
                        })
                        .catch(function (response) {
                            // Always
                            self.isOnline = false;
                            self.userLoggedOut = true;
                        });
                },

                /**
                 * Apply the filter changes
                 */
                sendApplyFilterRequest: function sendApplyFilterRequest() {

                    let self = this;
                    let data = new FormData();
                    data.append('REQUEST_TOKEN', self.requestToken);
                    data.append('resType', self.activeResourceTypeId);
                    data.append('res', self.activeResourceId);
                    data.append('date', self.activeWeekTstamp);

                    let action = 'sendApplyFilterRequest';
                    fetch('_resource_booking/ajax/' + action + '?sessionId=' + self.sessionId, {
                        method: "POST",
                        body: data,
                        headers: {
                            'x-requested-with': 'XMLHttpRequest'
                        },
                    })
                        .then(function (res) {
                            return res.json();
                        })
                        .then(function (response) {
                            if (response.status === 'success') {
                                for (let key in response.data) {
                                    self[key] = response.data[key];
                                }
                            }
                            self.isOnline = true;
                        })
                        .catch(function (response) {
                            self.isOnline = false;
                        });
                },

                /**
                 * Jump to next/previous week
                 * @param tstamp
                 * @param event
                 */
                sendJumpWeekRequest: function sendJumpWeekRequest(tstamp, event) {

                    let self = this;
                    event.preventDefault();
                    $('.modal-backdrop').remove();

                    let backdrop = '<div class="modal-backdrop show"></div>';
                    $("body").append(backdrop);

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', self.requestToken);
                    data.append('resType', self.activeResourceTypeId);
                    data.append('res', self.activeResourceId);
                    data.append('date', tstamp);


                    let action = 'sendJumpWeekRequest';
                    fetch('_resource_booking/ajax/' + action + '?sessionId=' + self.sessionId, {
                        method: "POST",
                        body: data,
                        headers: {
                            'x-requested-with': 'XMLHttpRequest'
                        },
                    })
                        .then(function (res) {
                            return res.json();
                        })
                        .then(function (response) {
                            if (response.status === 'success') {
                                for (let key in response.data) {
                                    self[key] = response.data[key];
                                }
                            }
                            self.isOnline = true;
                            // Always
                            window.setTimeout(function () {
                                $('.modal-backdrop').remove();
                            }, 200);
                        })
                        .catch(function (response) {
                            self.isOnline = false;
                            // Always
                            window.setTimeout(function () {
                                $('.modal-backdrop').remove();
                            }, 200);
                        });
                },

                /**
                 * Initialize idle detector
                 */
                initializeIdleDetector: function initializeIdleDetector() {
                    let self = this;
                    if (self.opt.autologout && parseInt(self.opt.autologoutDelay) > 0) {
                        $(document).idle({
                            onIdle: function onIdle() {
                                self.sendLogoutRequest();
                            },
                            idle: parseInt(self.opt.autologoutDelay) * 1000
                        });
                    }
                },

                /**
                 * Open booking modal window
                 * @param objActiveTimeSlot
                 * @param action
                 */
                openBookingModal: function openBookingModal(objActiveTimeSlot, action) {
                    let self = this;

                    self.bookingModal.selectedTimeSlots = [];
                    self.bookingModal.action = action;
                    self.bookingModal.showConfirmationMsg = false;
                    self.bookingModal.activeTimeSlot = objActiveTimeSlot;
                    self.bookingModal.alertSuccess = '';
                    self.bookingModal.alertError = '';
                    self.bookingModal.selectedTimeSlots.push(objActiveTimeSlot.bookingCheckboxValue);
                    self.bookingFormValidation = [];

                    // Hide booking preview
                    $(self.$el).find('.booking-preview').first().collapse('hide');
                    window.setTimeout(function () {
                        self.sendBookingFormValidationRequest();
                    }, 500);

                    $(self.$el).find('.resource-booking-modal').first().on('show.bs.modal', function () {
                        $(self.$el).find('.resource-booking-modal [name="bookingDescription"]').first().val('');
                        $(self.$el).find('.booking-repeat-stop-week-tstamp option').prop('selected', false);
                    });

                    $(self.$el).find('.resource-booking-modal').first().modal('show');
                }
            }
        });
    }
};



