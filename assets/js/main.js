// Main JS functionality

$(document).ready(function () {
    if ($('#stream-player').length === 0 || typeof videojs === 'undefined') {
        return;
    }

    const streamSrc = $('#stream-player').data('manifest-url') || $('#stream-player source').attr('src');
    const statusElement = $('.stream-status');
    const reconnectDelayMs = 5000;
    const maxRecoverAttempts = 8;
    let recoverAttempts = 0;
    let recoverTimer = null;
    let player = null;

    function setStatus(message, variant) {
        if (!statusElement.length) {
            return;
        }

        statusElement
            .removeClass('alert-dark alert-success alert-warning alert-danger')
            .addClass('alert-' + (variant || 'dark'))
            .text(message);
    }

    function createPlayer() {
        if (player) {
            return player;
        }

        player = videojs('stream-player', {
            liveui: true,
            autoplay: false,
            controls: true,
            muted: false,
            responsive: true,
            fluid: true,
            preload: 'metadata',
            html5: {
                vhs: {
                    enableLowInitialPlaylist: true,
                    smoothQualityChange: true,
                    experimentalBufferBasedABR: true,
                    limitRenditionByPlayerDimensions: false,
                    useDevicePixelRatio: true,
                    withCredentials: false
                },
                nativeAudioTracks: false,
                nativeVideoTracks: false
            }
        });

        player.on('playing', function () {
            recoverAttempts = 0;
            clearRecoverTimer();
            setStatus('Live stream connected.', 'success');
        });

        player.on('waiting', function () {
            setStatus('Buffering live stream...', 'warning');
            scheduleRecover();
        });

        player.on('stalled', function () {
            setStatus('Network stalled. Reconnecting...', 'warning');
            scheduleRecover();
        });

        player.on('error', function () {
            setStatus('Live stream is unavailable right now. Retrying...', 'danger');
            scheduleRecover();
        });

        return player;
    }

    function clearRecoverTimer() {
        if (recoverTimer) {
            clearTimeout(recoverTimer);
            recoverTimer = null;
        }
    }

    function scheduleRecover() {
        if (!streamSrc || recoverAttempts >= maxRecoverAttempts) {
            return;
        }

        clearRecoverTimer();
        recoverTimer = setTimeout(function () {
            recoverAttempts += 1;
            probeManifestAndAttach();
        }, reconnectDelayMs);
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

                const currentPlayer = createPlayer();
                currentPlayer.src({ src: streamSrc, type: 'application/x-mpegURL' });
                currentPlayer.load();
                currentPlayer.play().catch(function () {
                    // Browser policy may require an explicit click.
                });
            })
            .catch(function () {
                setStatus('Live stream is not ready yet. Reconnecting shortly...', 'warning');
                scheduleRecover();
            });
    }

    createPlayer();
    probeManifestAndAttach();
});
