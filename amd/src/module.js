define(['jquery', 'core/log', 'core/ajax'], function ($, log, ajax) {
    return {
        init: function (elementId, url, cmid) {
            log.debug('MediaCMS: Initializing for ' + elementId);

            require(['media_videojs/video-lazy'], function (videojs) {
                var options = {
                    controls: true,
                    autoplay: false,
                    preload: 'auto',
                    fluid: true,
                    playbackRates: [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2],
                    sources: [{
                        src: url,
                        type: 'video/mp4' // Default assumption, or needs to be dynamic
                    }]
                };

                var player = videojs(elementId, options);

                player.ready(function () {
                    log.debug('MediaCMS: Player ready');

                    var lastUpdate = 0;

                    var viewedRanges = [];

                    player.on('timeupdate', function () {
                        var currentTime = player.currentTime();
                        var duration = player.duration();

                        if (duration > 0) {
                            // Add current time to tracked ranges (accounting for small buffer)
                            var rangeStart = Math.max(0, currentTime - 0.5);
                            var rangeEnd = currentTime;

                            // Merge with existing ranges
                            // Simple range merging logic
                            viewedRanges.push({ start: rangeStart, end: rangeEnd });
                            viewedRanges.sort(function (a, b) { return a.start - b.start; });

                            var consolidatedRanges = [];
                            if (viewedRanges.length > 0) {
                                var current = viewedRanges[0];
                                for (var i = 1; i < viewedRanges.length; i++) {
                                    if (viewedRanges[i].start <= current.end + 0.5) { // Allow 0.5s gap for smoothness
                                        current.end = Math.max(current.end, viewedRanges[i].end);
                                    } else {
                                        consolidatedRanges.push(current);
                                        current = viewedRanges[i];
                                    }
                                }
                                consolidatedRanges.push(current);
                            }
                            viewedRanges = consolidatedRanges;

                            // Calculate total watched duration
                            var totalWatched = 0;
                            viewedRanges.forEach(function (r) {
                                totalWatched += (r.end - r.start);
                            });

                            var percentage = Math.floor((totalWatched / duration) * 100);

                            // Cap at 100
                            if (percentage > 100) percentage = 100;

                            // Update progress overlay
                            var progressElement = document.getElementById('mediacms-progress-' + elementId);
                            if (progressElement) {
                                var valueSpan = progressElement.querySelector('.progress-value');
                                if (valueSpan) {
                                    valueSpan.textContent = percentage;
                                }
                            }

                            if (percentage > lastUpdate && (percentage % 5 === 0 || percentage >= 90)) {
                                lastUpdate = percentage;

                                ajax.call([{
                                    methodname: 'mod_mediacms_submit_progress',
                                    args: { cmid: cmid, progress: percentage }
                                }])[0].fail(function (ex) {
                                    log.error('MediaCMS: Failed to update progress: ' + ex);
                                });
                            }
                        }
                    });

                    player.on('ended', function () {
                        ajax.call([{
                            methodname: 'mod_mediacms_submit_progress',
                            args: { cmid: cmid, progress: 100 }
                        }]);
                        lastUpdate = 100;

                        // Update overlay to 100%
                        updateProgressUI(100);
                    });
                });
            }, function (err) {
                // Fallback if media_videojs is not found
                log.warn('MediaCMS: media_videojs not found, falling back to native controls');
                // The video tag already has 'controls' attribute, so native player will handle it.
            });
        }
    };
});
