(function () {
    'use strict';

    function initThemeToggle() {
        var root = document.documentElement;
        var buttons = document.querySelectorAll('.theme-toggle');
        if (!buttons.length) {
            return;
        }

        function applyTheme(theme) {
            root.setAttribute('data-theme', theme);
            try {
                localStorage.setItem('theme', theme);
            } catch (err) {
                // Ignore storage restrictions.
            }
            buttons.forEach(function (button) {
                button.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
            });
        }

        var currentTheme = root.getAttribute('data-theme') || 'light';
        applyTheme(currentTheme);

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                currentTheme = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                applyTheme(currentTheme);
            });
        });
    }

    function initCountdownTimers() {
        var nodes = document.querySelectorAll('[data-countdown-target]');
        if (!nodes.length) {
            return;
        }

        function formatRemaining(msRemaining) {
            if (msRemaining <= 0) {
                return 'Starting soon';
            }

            var totalSeconds = Math.floor(msRemaining / 1000);
            var days = Math.floor(totalSeconds / 86400);
            var hours = Math.floor((totalSeconds % 86400) / 3600);
            var minutes = Math.floor((totalSeconds % 3600) / 60);
            var seconds = totalSeconds % 60;

            if (days > 0) {
                return days + 'd ' + hours + 'h';
            }
            if (hours > 0) {
                return hours + 'h ' + minutes + 'm';
            }
            return minutes + 'm ' + seconds + 's';
        }

        function tick() {
            var now = Date.now();
            nodes.forEach(function (node) {
                var raw = node.getAttribute('data-countdown-target');
                var eventTime = Date.parse(raw);
                if (Number.isNaN(eventTime)) {
                    node.textContent = 'Schedule unavailable';
                    return;
                }

                node.textContent = formatRemaining(eventTime - now);
            });
        }

        tick();
        window.setInterval(tick, 1000);
    }

    function initStreamPlayer() {
        var streamPlayerElement = document.getElementById('stream-player');
        if (!streamPlayerElement) {
            return;
        }

        var streamSrc = streamPlayerElement.getAttribute('data-manifest-url');
        var statusElement = document.querySelector('.stream-status');
        var reconnectDelayMs = 5000;
        var maxRecoverAttempts = 12;
        var recoverAttempts = 0;
        var recoverTimer = null;
        var hlsInstance = null;

        function setStatus(message, variant) {
            if (!statusElement) {
                return;
            }

            statusElement.classList.remove('alert-dark', 'alert-success', 'alert-warning', 'alert-danger');
            statusElement.classList.add('alert-' + (variant || 'dark'));
            statusElement.textContent = message;
        }

        function clearHlsInstance() {
            if (hlsInstance) {
                hlsInstance.destroy();
                hlsInstance = null;
            }
        }

        function clearRecoverTimer() {
            if (recoverTimer) {
                window.clearTimeout(recoverTimer);
                recoverTimer = null;
            }
        }

        function scheduleRecover() {
            if (!streamSrc || recoverAttempts >= maxRecoverAttempts) {
                if (recoverAttempts >= maxRecoverAttempts) {
                    setStatus('Stream is still unavailable. Please refresh in a few moments.', 'danger');
                }
                return;
            }

            clearRecoverTimer();
            recoverTimer = window.setTimeout(function () {
                recoverAttempts += 1;
                probeManifestAndAttach();
            }, reconnectDelayMs);
        }

        function attachSource() {
            if (!streamSrc) {
                setStatus('No stream URL is configured for this event.', 'danger');
                return;
            }

            clearHlsInstance();

            if (streamPlayerElement.canPlayType('application/vnd.apple.mpegurl')) {
                streamPlayerElement.src = streamSrc;
                streamPlayerElement.load();
                streamPlayerElement.play().catch(function () {
                    // Autoplay may be blocked by browser policy.
                });
                return;
            }

            if (typeof Hls !== 'undefined' && Hls.isSupported()) {
                hlsInstance = new Hls({
                    lowLatencyMode: true,
                    enableWorker: true,
                    liveSyncDurationCount: 3,
                    liveMaxLatencyDurationCount: 6,
                    maxLiveSyncPlaybackRate: 1.2,
                    fragLoadingRetryDelay: 900,
                    manifestLoadingRetryDelay: 900,
                    levelLoadingRetryDelay: 900
                });
                hlsInstance.attachMedia(streamPlayerElement);
                hlsInstance.on(Hls.Events.MEDIA_ATTACHED, function () {
                    hlsInstance.loadSource(streamSrc);
                });
                hlsInstance.on(Hls.Events.MANIFEST_PARSED, function () {
                    streamPlayerElement.play().catch(function () {
                        // User gesture may be required by browser.
                    });
                });
                hlsInstance.on(Hls.Events.ERROR, function (_, data) {
                    if (data && data.fatal) {
                        setStatus('Stream decoder error. Retrying...', 'danger');
                        scheduleRecover();
                    }
                });
                return;
            }

            setStatus('This browser cannot play HLS. Try modern Chrome, Edge, Safari, or Firefox.', 'danger');
        }

        function probeManifestAndAttach() {
            if (!streamSrc) {
                setStatus('No stream URL is configured for this event.', 'danger');
                return;
            }

            setStatus('Checking live stream availability...', 'dark');

            fetch(streamSrc, {
                cache: 'no-store',
                mode: 'cors'
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Manifest request failed');
                    }
                    return response.text();
                })
                .then(function (manifestText) {
                    if (manifestText.indexOf('#EXTM3U') === -1) {
                        throw new Error('Invalid manifest');
                    }
                    attachSource();
                })
                .catch(function () {
                    setStatus('Live stream is not ready yet. Reconnecting shortly...', 'warning');
                    scheduleRecover();
                });
        }

        streamPlayerElement.addEventListener('playing', function () {
            recoverAttempts = 0;
            clearRecoverTimer();
            setStatus('Live stream connected.', 'success');
        });

        streamPlayerElement.addEventListener('waiting', function () {
            setStatus('Buffering live stream...', 'warning');
            scheduleRecover();
        });

        streamPlayerElement.addEventListener('stalled', function () {
            setStatus('Network stalled. Reconnecting...', 'warning');
            scheduleRecover();
        });

        streamPlayerElement.addEventListener('error', function () {
            setStatus('Live stream is unavailable right now. Retrying...', 'danger');
            scheduleRecover();
        });

        probeManifestAndAttach();
    }

    document.addEventListener('DOMContentLoaded', function () {
        initThemeToggle();
        initCountdownTimers();
        initStreamPlayer();
    });
})();
